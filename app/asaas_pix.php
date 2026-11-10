<?php
// Integração Asaas (API v3) — PIX com Split de Pagamentos nativo.
// A cobrança é emitida pela conta da EMPRESA e o parceiro recebe automaticamente
// o VALOR FIXO configurado (api_pagamento.valor_fixo_parceiro, em R$) via walletId.
// O afiliado que indicou o usuário recebe o VALOR FIXO POR MENSALIDADE dele
// (afiliados.valor_fixo_mensalidade, configurado no Admin > Afiliados) em cada
// mensalidade paga. O split é definido na própria cobrança: não há fluxo OAuth
// nem transferência manual. O crédito no extrato do afiliado é registrado em
// app/afiliado_creditos.php quando o pagamento é confirmado.
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
// O afiliado recebe seu VALOR FIXO POR MENSALIDADE em todos esses pagamentos.
function asaas_tipo_cobranca($conn, array $u) {
    $uid = (int)$u['id'];
    $r = $conn->query("SELECT COUNT(*) AS n FROM pagamentos_pix WHERE usuario_id = $uid AND tipo = 'plano' AND status = 'approved'");
    $n = (int)(($r && ($row = $r->fetch_assoc())) ? $row['n'] : 0);
    if ($n <= 0) return 'adesao';
    if ($n === 1) return '1a_mensalidade';
    return 'recorrencia';
}

// Dados de split do afiliado que indicou o usuário (se houver): wallet da
// carteira Asaas de recebimento + VALOR FIXO POR MENSALIDADE configurado para
// ele no Admin > Afiliados. O repasse automático via split ocorre quando o
// afiliado está ativo e tem Wallet ID cadastrado; o crédito no extrato é
// registrado independentemente (app/afiliado_creditos.php).
function asaas_afiliado_split_usuario($conn, array $u) {
    $r = ['wallet' => '', 'valor_fixo' => 0.0];
    if (!empty($u['afiliado_token'])) {
        $stmt = $conn->prepare("SELECT wallet_afiliado, valor_fixo_mensalidade FROM afiliados WHERE token = ? AND ativo = 1");
        $stmt->bind_param('s', $u['afiliado_token']);
        $stmt->execute();
        $af = $stmt->get_result()->fetch_assoc();
        if ($af) {
            $r['wallet'] = trim((string)($af['wallet_afiliado'] ?? ''));
            $r['valor_fixo'] = round((float)($af['valor_fixo_mensalidade'] ?? 0), 2);
        }
    }
    return $r;
}

// Monta o array de split conforme a regra de distribuição configurada:
//  - Parceiro: recebe "Adesão" no 1º pagamento e "Recorrência" a partir da 1ª
//    mensalidade (enquanto o usuário estiver pagando, em todos os pagamentos).
//  - Afiliado : recebe o VALOR FIXO POR MENSALIDADE configurado no cadastro
//    dele (Admin > Afiliados) em TODOS os pagamentos de plano do usuário que
//    ele indicou (adesão, 1ª mensalidade e recorrências), quando tem Wallet ID.
// O restante do valor fica automaticamente com a empresa (conta que emite a cobrança).
// Retorna null quando não houver split configurado/válido (100% fica com a empresa).
function asaas_split_payload(array $cfg, $valor, $tipo = 'adesao', $walletAfiliado = '', $valorFixoAfiliado = 0.0) {
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

    // Afiliado: valor fixo por mensalidade paga (configurado por afiliado),
    // independente do preço da mensalidade e do tipo de cobrança.
    $valorAfiliado = round((float)$valorFixoAfiliado, 2);
    if ($walletAfiliado !== '' && $valorAfiliado > 0 && $valorAfiliado < (float)$valor) {
        $splits[] = ['walletId' => $walletAfiliado, 'fixedValue' => $valorAfiliado];
        $soma += $valorAfiliado;
    }

    // Soma dos splits não pode atingir o valor líquido da cobrança (precisa sobrar
    // algo para a empresa). Se não sobrar, desabilita o split (tudo fica com a empresa).
    if (!$splits || $soma >= (float)$valor) {
        return null;
    }
    return $splits;
}

// Cria a cobrança PIX (billingType=PIX) já com o split conforme a regra da aba Split.
// $tipo: 'adesao' | '1a_mensalidade' | 'recorrencia' (contexto da cobrança).
// $walletAfiliado / $valorFixoAfiliado: carteira e valor fixo por mensalidade do
// afiliado que indicou o usuário ('' / 0 se não houver).
// $aplicarSplit = false emite a cobrança 100% para a empresa (ex.: taxa do Cartão Físico).
function asaas_criar_cobranca_pix(array $cfg, $customerId, $valor, $descricao, $uid, $aplicarSplit = true, $tipo = 'adesao', $walletAfiliado = '', $valorFixoAfiliado = 0.0) {
    $payload = [
        'customer' => (string)$customerId,
        'billingType' => 'PIX',
        'value' => round((float)$valor, 2),
        'dueDate' => date('Y-m-d'),
        'description' => (string)$descricao
    ];
    $split = $aplicarSplit ? asaas_split_payload($cfg, $valor, $tipo, $walletAfiliado, $valorFixoAfiliado) : null;
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
function asaas_criar_cobranca_cartao(array $cfg, $customerId, $valor, $descricao, $uid, array $cartao, $tipo = 'adesao', $walletAfiliado = '', $valorFixoAfiliado = 0.0) {
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
    $split = asaas_split_payload($cfg, $valor, $tipo, $walletAfiliado, $valorFixoAfiliado);
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

// Valida um BR Code PIX (copia e cola / payload EMV) conforme o padrão do Bacen:
//  - deve começar com "0002" (Payload Format Indicator "000201")
//  - campo 63 (CRC-16/CCITT-FALSE) deve conferir com o conteúdo anterior
//  - tamanho mínimo razoável e sem quebras de linha espaços indevidos.
function asaas_validar_brcode($payload) {
    $payload = trim((string)$payload);
    if ($payload === '') return ['ok' => false, 'motivo' => 'vazio'];
    if (strlen($payload) < 60 || strlen($payload) > 400) {
        return ['ok' => false, 'motivo' => 'tamanho ' . strlen($payload)];
    }
    if (substr($payload, 0, 4) !== '0002') {
        return ['ok' => false, 'motivo' => 'prefixo ausente'];
    }
    if ((substr($payload, -8, 2) ?? '') !== '63') {
        return ['ok' => false, 'motivo' => 'sem campo CRC'];
    }
    $crcInformado = strtoupper(substr($payload, -4));
    $corpo = substr($payload, 0, -4);
    $calc = crc16_ccitt_false($corpo);
    if ($crcInformado !== $calc) {
        return ['ok' => false, 'motivo' => 'CRC ' . $crcInformado . ' != ' . $calc];
    }
    return ['ok' => true];
}

// CRC-16/CCITT-FALSE (polinômio 0x1021, inicial 0xFFFF, sem reflexão) exigido pelo BR Code.
function crc16_ccitt_false($str) {
    $crc = 0xFFFF;
    $len = strlen($str);
    for ($i = 0; $i < $len; $i++) {
        $crc ^= (ord($str[$i]) & 0xFF) << 8;
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
            else $crc = ($crc << 1) & 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

// Insere o campo 54 (Transaction Amount) no BR Code e recalcula o CRC.
// O Asaas emite o QR sem embutir o valor; muitos apps de banco recusam o
// "copia e cola" quando o valor nao esta no payload (mostram "código inválido").
// Como o campo 26 continua apontando para a MESMA cobrança Asaas, o pagamento
// continua sendo conciliado normalmente. Retorna false se o payload for inesperado.
function asaas_brcode_com_valor($payload, $valor) {
    $payload = trim((string)$payload);
    if ($payload === '' || strpos($payload, '5303986') === false) return false;
    $valor = round((float)$valor, 2);
    if ($valor <= 0) return false;
    $amt = number_format($valor, 2, '.', '');                    // "60.00"
    $f54 = '54' . str_pad((string)strlen($amt), 2, '0', STR_PAD_LEFT) . $amt; // 540560.00
    $pos53 = strpos($payload, '5303986');
    $crcPos = strrpos($payload, '6304');
    if ($crcPos === false) return false;
    $header = substr($payload, 0, $pos53 + 7);                   // ate o fim do campo 53
    $tail = substr($payload, $pos53 + 7, $crcPos - ($pos53 + 7));// campos 58..62 (sem 63)
    $novo = $header . $f54 . $tail;                              // agora com 54 antes de 58
    return $novo . '6304' . crc16_ccitt_false($novo);
}

// Retorna o QR Code PIX (copia e cola + imagem base64) de uma cobrança.
// O Asaas pode levar alguns instantes para gerar o QR; valida o BR Code (CRC) e
// só considera válido quando o payload está íntegro. Em seguida embute o VALOR
// (campo 54) no "copia e cola" para que os apps de banco aceitem a colagem.
function asaas_qrcode_pix(array $cfg, $paymentId, $valor = null) {
    $resp = [];
    for ($i = 0; $i < 3; $i++) {
        $resp = asaas_request($cfg, 'GET', '/payments/' . rawurlencode((string)$paymentId) . '/pixQrCode');
        $payload = (string)($resp['dados']['payload'] ?? '');
        $imagem = (string)($resp['dados']['encodedImage'] ?? '');
        if ($payload !== '' && $imagem !== '') {
            $val = asaas_validar_brcode($payload);
            if ($val['ok']) {
                $payloadFinal = ($valor !== null) ? asaas_brcode_com_valor($payload, $valor) : $payload;
                if ($payloadFinal === false) {
                    asaas_fluxo_log('qrcode_valor', "payment=$paymentId erro ao embutir valor");
                    $payloadFinal = $payload;
                }
                return ['ok' => true, 'payload' => $payloadFinal, 'encoded_image' => $imagem];
            }
            asaas_fluxo_log('qrcode_invalido', "payment=$paymentId motivo={$val['motivo']}");
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
