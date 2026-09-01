<?php
// Integração Asaas (API v3) — PIX com Split de Pagamentos nativo.
// A cobrança é emitida pela conta da EMPRESA e o parceiro recebe automaticamente
// o VALOR FIXO configurado (api_pagamento.valor_fixo_parceiro, em R$) via walletId.
// O split é definido na própria cobrança: não há fluxo OAuth nem transferência manual.
// Docs: https://docs.asaas.com/docs/split-de-pagamentos

// Valor mínimo por cobrança aceito pelo Asaas.
define('ASAAS_VALOR_MINIMO', 5.00);

// Retorna a configuração Asaas ou null se a chave não estiver preenchida.
// Inclui os valores da regra de Split (Admin > API Pagamento > aba Split).
function asaas_config($conn) {
    $r = @$conn->query("SELECT * FROM api_pagamento WHERE id = 1");
    if (!$r) return null;
    $cfg = $r->fetch_assoc();
    if (!$cfg || trim((string)($cfg['asaas_api_key'] ?? '')) === '') return null;
    return $cfg;
}

function asaas_base_url(array $cfg) {
    return ($cfg['asaas_ambiente'] ?? 'producao') === 'sandbox'
        ? 'https://api-sandbox.asaas.com/v3'
        : 'https://api.asaas.com/v3';
}

function asaas_request(array $cfg, $metodo, $caminho, $body = null) {
    $ch = curl_init(asaas_base_url($cfg) . $caminho);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($metodo),
        CURLOPT_HTTPHEADER => [
            'access_token: ' . trim((string)$cfg['asaas_api_key']),
            'Content-Type: application/json'
        ],
        CURLOPT_USERAGENT => 'EconomicCard/1.0 (+https://economiccard.com.br)',
        CURLOPT_TIMEOUT => 30
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    $res = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    $dados = json_decode((string)$res, true);
    return [
        'http' => $http,
        'dados' => is_array($dados) ? $dados : [],
        'erro_curl' => $erro
    ];
}

function asaas_primeiro_erro(array $resp, $prefixo) {
    if (!empty($resp['dados']['errors'][0]['description'])) {
        return $prefixo . ': ' . $resp['dados']['errors'][0]['description'];
    }
    return $prefixo . ': HTTP ' . $resp['http'] . (!empty($resp['erro_curl']) ? ' (' . $resp['erro_curl'] . ')' : '');
}

// Registra payload e resposta em app/logs/asaas.log para diagnóstico de falhas.
function asaas_log_erro(array $cfg, $caminho, $body, array $resp) {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $linha = date('c') . ' ' . asaas_base_url($cfg) . $caminho
        . "\nPAYLOAD: " . json_encode($body)
        . "\nRESPOSTA: HTTP " . $resp['http'] . ' ' . json_encode($resp['dados'])
        . (!empty($resp['erro_curl']) ? ' CURL: ' . $resp['erro_curl'] : '')
        . "\n------\n";
    if (@file_put_contents($dir . '/asaas.log', $linha, FILE_APPEND) === false) {
        error_log('asaas-erro ' . str_replace("\n", ' | ', $linha));
    }
}

// Log enxuto do fluxo de geracao de PIX (app/logs/pix_flow.log).
function asaas_fluxo_log($etapa, $detalhe = '') {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $linha = date('c') . " [$etapa] $detalhe\n";
    if (@file_put_contents($dir . '/pix_flow.log', $linha, FILE_APPEND) === false) {
        error_log("asaas-fluxo [$etapa] $detalhe");
    }
}

// Busca o customer pelo CPF/CNPJ e cria se ainda não existir.
function asaas_obter_customer(array $cfg, array $u) {
    $cpfCnpj = preg_replace('/\D/', '', (string)($u['cpf'] ?? ''));
    if ($cpfCnpj !== '') {
        $busca = asaas_request($cfg, 'GET', '/customers?cpfCnpj=' . $cpfCnpj . '&limit=1');
        if (!empty($busca['dados']['data'][0]['id'])) {
            return ['ok' => true, 'customer_id' => (string)$busca['dados']['data'][0]['id']];
        }
    }
    $criar = asaas_request($cfg, 'POST', '/customers', [
        'name' => (string)($u['nome'] ?? ''),
        'cpfCnpj' => $cpfCnpj,
        'email' => !empty($u['email']) ? (string)$u['email'] : 'usuario' . (int)$u['id'] . '@economiccard.com.br'
    ]);
    if (!empty($criar['dados']['id'])) {
        return ['ok' => true, 'customer_id' => (string)$criar['dados']['id']];
    }
    asaas_log_erro($cfg, '/customers', ['name' => $u['nome'] ?? '', 'cpfCnpj' => $cpfCnpj], $criar);
    return ['ok' => false, 'message' => asaas_primeiro_erro($criar, 'Asaas (customer)')];
}

// Classifica o tipo de cobrança do usuário para aplicar a regra de split:
//  - 'adesao'        => 1º pagamento do usuário (taxa de adesão do plano Mensal)
//  - '1a_mensalidade'=> 2º pagamento (1ª mensalidade do plano escolhido)
//  - 'recorrencia'   => 3º pagamento em diante (mensalidades seguintes)
// A partir do 2º pagamento o afiliado não entra mais no split (só parceiro + empresa).
function asaas_tipo_cobranca($conn, array $u) {
    $uid = (int)$u['id'];
    $r = $conn->query("SELECT COUNT(*) AS n FROM pagamentos_pix WHERE usuario_id = $uid AND tipo = 'plano' AND status = 'approved'");
    $n = (int)(($r && ($row = $r->fetch_assoc())) ? $row['n'] : 0);
    if ($n <= 0) return 'adesao';
    if ($n === 1) return '1a_mensalidade';
    return 'recorrencia';
}

// Retorna o wallet ID do afiliado que indicou o usuário (se houver e se ele tiver
// cadastrado a carteira). Usado no split apenas nos pagamentos de adesão e 1ª mensalidade.
function asaas_wallet_afiliado_usuario($conn, array $u) {
    if (!empty($u['afiliado_token'])) {
        $stmt = $conn->prepare("SELECT wallet_afiliado FROM afiliados WHERE token = ? AND ativo = 1");
        $stmt->bind_param('s', $u['afiliado_token']);
        $stmt->execute();
        $af = $stmt->get_result()->fetch_assoc();
        if ($af) return trim((string)($af['wallet_afiliado'] ?? ''));
    }
    return '';
}

// Monta o array de split conforme a regra de distribuição configurada na aba Split:
//  - Parceiro: recebe "Adesão" no 1º pagamento e "Recorrência" a partir da 1ª
//    mensalidade (enquanto o usuário estiver pagando, em todos os pagamentos).
//  - Afiliado : recebe "Adesão (do usuário indicado por ele)" no 1º pagamento e
//    "1ª mensalidade" apenas no 2º pagamento; a partir do 3º não recebe mais.
// O restante do valor fica automaticamente com a empresa (conta que emite a cobrança).
// Retorna null quando não houver split configurado/válido (100% fica com a empresa).
function asaas_split_payload(array $cfg, $valor, $tipo = 'adesao', $walletAfiliado = '') {
    $soma = 0.0;
    $splits = [];

    // Parceiro
    $walletParceiro = trim((string)($cfg['asaas_wallet_parceiro'] ?? ''));
    $valorParceiro = $tipo === 'adesao'
        ? (float)($cfg['split_parceiro_adesao'] ?? 0)
        : (float)($cfg['split_parceiro_recorrencia'] ?? 0);
    if ($walletParceiro !== '' && $valorParceiro > 0 && $valorParceiro < (float)$valor) {
        $splits[] = ['walletId' => $walletParceiro, 'fixedValue' => round($valorParceiro, 2)];
        $soma += round($valorParceiro, 2);
    }

    // Afiliado (somente nos 2 primeiros pagamentos)
    $valorAfiliado = 0.0;
    if ($walletAfiliado !== '') {
        if ($tipo === 'adesao') {
            $valorAfiliado = (float)($cfg['split_afiliado_adesao'] ?? 0);
        } elseif ($tipo === '1a_mensalidade') {
            $valorAfiliado = (float)($cfg['split_afiliado_1a_mensal'] ?? 0);
        }
    }
    if ($valorAfiliado > 0 && $valorAfiliado < (float)$valor) {
        $splits[] = ['walletId' => $walletAfiliado, 'fixedValue' => round($valorAfiliado, 2)];
        $soma += round($valorAfiliado, 2);
    }

    // Soma dos splits não pode atingir o valor líquido da cobrança (precisa sobrar
    // algo para a empresa). Se não sobrar, desabilita o split (tudo fica com a empresa).
    if (!$splits || $soma >= (float)$valor) {
        return null;
    }
    return $splits;
}

// Cria a cobrança PIX (billingType=PIX) já com o split conforme a regra da aba Split.
// $tipo: 'adesao' | '1a_mensalidade' | 'recorrencia' (controle do afiliado no split).
// $walletAfiliado: carteira do afiliado que indicou o usuário ('' se não houver).
// $aplicarSplit = false emite a cobrança 100% para a empresa (ex.: taxa do Cartão Físico).
function asaas_criar_cobranca_pix(array $cfg, $customerId, $valor, $descricao, $uid, $aplicarSplit = true, $tipo = 'adesao', $walletAfiliado = '') {
    $payload = [
        'customer' => (string)$customerId,
        'billingType' => 'PIX',
        'value' => round((float)$valor, 2),
        'dueDate' => date('Y-m-d'),
        'description' => (string)$descricao
    ];
    $split = $aplicarSplit ? asaas_split_payload($cfg, $valor, $tipo, $walletAfiliado) : null;
    $splitAplicado = false;
    if ($split) {
        $payload['splits'] = $split;
        $splitAplicado = true;
    }
    // Parâmetro uid garante idempotência (evita cobrança duplicada em retry).
    $resp = asaas_request($cfg, 'POST', '/payments?uid=' . rawurlencode('ec-' . (int)$uid . '-' . uniqid()), $payload);
    if (!empty($resp['dados']['id'])) {
        return ['ok' => true, 'payment' => $resp['dados'], 'split_aplicado' => $splitAplicado];
    }
    asaas_log_erro($cfg, '/payments (pix)', $payload, $resp);
    return ['ok' => false, 'message' => asaas_primeiro_erro($resp, 'Asaas (cobrança)')];
}

// Cria a cobrança de CARTÃO DE CRÉDITO (billingType=CREDIT_CARD) com split conforme a regra.
function asaas_criar_cobranca_cartao(array $cfg, $customerId, $valor, $descricao, $uid, array $cartao, $tipo = 'adesao', $walletAfiliado = '') {
    $payload = [
        'customer' => (string)$customerId,
        'billingType' => 'CREDIT_CARD',
        'value' => round((float)$valor, 2),
        'dueDate' => date('Y-m-d'),
        'description' => (string)$descricao,
        'creditCard' => [
            'holderName' => (string)$cartao['holderName'],
            'number' => preg_replace('/\D/', '', (string)$cartao['number']),
            'expiryMonth' => (string)$cartao['expiryMonth'],
            'expiryYear' => (string)$cartao['expiryYear'],
            'ccc' => preg_replace('/\D/', '', (string)$cartao['ccc'])
        ],
        'creditCardHolderInfo' => [
            'name' => (string)$cartao['holderName'],
            'email' => (string)$cartao['email'],
            'cpfCnpj' => preg_replace('/\D/', '', (string)$cartao['cpfCnpj']),
            'postalCode' => preg_replace('/\D/', '', (string)$cartao['postalCode']),
            'addressNumber' => (string)$cartao['addressNumber'],
            'phone' => preg_replace('/\D/', '', (string)($cartao['phone'] ?? ''))
        ]
    ];
    $split = asaas_split_payload($cfg, $valor, $tipo, $walletAfiliado);
    $splitAplicado = false;
    if ($split) {
        $payload['splits'] = $split;
        $splitAplicado = true;
    }
    $resp = asaas_request($cfg, 'POST', '/payments?uid=' . rawurlencode('ec-' . (int)$uid . '-' . uniqid()), $payload);
    if (!empty($resp['dados']['id'])) {
        return ['ok' => true, 'payment' => $resp['dados'], 'split_aplicado' => $splitAplicado];
    }
    asaas_log_erro($cfg, '/payments (cartao)', $payload, $resp);
    return ['ok' => false, 'message' => asaas_primeiro_erro($resp, 'Asaas (cartão)')];
}

// Retorna o QR Code PIX (copia e cola + imagem base64) de uma cobrança.
// O Asaas pode levar alguns instantes para gerar o QR; tenta até 3 vezes.
function asaas_qrcode_pix(array $cfg, $paymentId) {
    $resp = [];
    for ($i = 0; $i < 3; $i++) {
        $resp = asaas_request($cfg, 'GET', '/payments/' . rawurlencode((string)$paymentId) . '/pixQrCode');
        $payload = (string)($resp['dados']['payload'] ?? '');
        $imagem = (string)($resp['dados']['encodedImage'] ?? '');
        if ($payload !== '' && $imagem !== '') {
            return ['ok' => true, 'payload' => $payload, 'encoded_image' => $imagem];
        }
        if ($i < 2) { sleep(1); }
    }
    asaas_log_erro($cfg, '/payments/{id}/pixQrCode', ['paymentId' => $paymentId], $resp);
    return ['ok' => false, 'message' => asaas_primeiro_erro($resp, 'Asaas (QR Code)')];
}

// Consulta o status atual da cobrança na API (fonte oficial da verdade).
function asaas_status_pagamento(array $cfg, $paymentId) {
    $resp = asaas_request($cfg, 'GET', '/payments/' . rawurlencode((string)$paymentId));
    return (string)($resp['dados']['status'] ?? '');
}

// Traduz o status da Asaas para os status internos (approved/pending/cancelled/refunded).
function asaas_status_local($statusAsaas) {
    switch (strtoupper((string)$statusAsaas)) {
        case 'RECEIVED':
        case 'CONFIRMED':
            return 'approved';
        case 'CANCELLED':
            return 'cancelled';
        case 'REFUNDED':
        case 'REFUND_IN_PROGRESS':
            return 'refunded';
        default:
            return 'pending';
    }
}

// Teste de conexão usado pelo painel admin: consulta o saldo da conta.
function asaas_testar_conexao(array $cfg) {
    $resp = asaas_request($cfg, 'GET', '/finance/balance');
    if ($resp['http'] >= 200 && $resp['http'] < 300 && isset($resp['dados']['balance'])) {
        return ['ok' => true, 'message' => 'Conexão OK. Saldo da conta: R$ ' . number_format((float)$resp['dados']['balance'], 2, ',', '.')];
    }
    return ['ok' => false, 'message' => asaas_primeiro_erro($resp, 'Falha na conexão')];
}
