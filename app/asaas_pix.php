<?php
// Integração Asaas (API v3) — PIX com Split de Pagamentos nativo.
// A cobrança é emitida pela conta da EMPRESA e o parceiro recebe automaticamente
// o VALOR FIXO configurado (api_pagamento.valor_fixo_parceiro, em R$) via walletId.
// O split é definido na própria cobrança: não há fluxo OAuth nem transferência manual.
// Docs: https://docs.asaas.com/docs/split-de-pagamentos

// Retorna a configuração Asaas ou null se a chave não estiver preenchida.
function asaas_config($conn) {
    $r = @$conn->query("SELECT asaas_api_key, asaas_ambiente, asaas_wallet_parceiro, valor_fixo_parceiro FROM api_pagamento WHERE id = 1");
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

// Monta o array de split (valor fixo em R$ para a carteira do PARCEIRO) ou null se não configurado.
// Campo obrigatório do Asaas é fixedValue (docs: docs.asaas.com/docs/split-de-pagamentos).
function asaas_split_payload(array $cfg, $valor) {
    $wallet = trim((string)($cfg['asaas_wallet_parceiro'] ?? ''));
    $valorFixo = round((float)($cfg['valor_fixo_parceiro'] ?? 0), 2);
    if ($wallet === '' || $valorFixo <= 0 || $valorFixo >= (float)$valor) {
        return null;
    }
    return [['walletId' => $wallet, 'fixedValue' => $valorFixo]];
}

// Cria a cobrança PIX (billingType=PIX) já com o split para o parceiro, quando configurado.
function asaas_criar_cobranca_pix(array $cfg, $customerId, $valor, $descricao, $uid) {
    $payload = [
        'customer' => (string)$customerId,
        'billingType' => 'PIX',
        'value' => round((float)$valor, 2),
        'dueDate' => date('Y-m-d'),
        'description' => (string)$descricao
    ];
    $split = asaas_split_payload($cfg, $valor);
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

// Cria a cobrança de CARTÃO DE CRÉDITO (billingType=CREDIT_CARD) com split quando configurado.
function asaas_criar_cobranca_cartao(array $cfg, $customerId, $valor, $descricao, $uid, array $cartao) {
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
    $split = asaas_split_payload($cfg, $valor);
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
