<?php
// Geração do PIX da COBRANÇA ATUAL do contrato do plano único mensal.
// A cobrança (mensalidade do ciclo) já existe em planos_cobrancas
// com o valor congelado no contrato; aqui apenas emitimos o PIX no gateway.
require_once 'config.php';
require_once 'asaas_pix.php';
header('Content-Type: application/json');
if (!isset($_SESSION['usuario_id'])) {
    asaas_fluxo_log('erro', 'sessao invalida: ' . json_encode($_POST));
    echo json_encode(['status' => 'error', 'message' => 'Sessão inválida']);
    exit;
}
$uid = (int)$_SESSION['usuario_id'];

$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if (!$u) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário inválido']);
    exit;
}

$hoje = date('Y-m-d');

$contrato = plano_garantir_contrato($conn, $uid, $u);
if (!$contrato) {
    $vals = plano_valores_atuais($conn);
    $msg = empty($vals['ativo'])
        ? 'Plano temporariamente indisponível para novas contratações. Fale com o suporte.'
        : 'Não foi possível localizar seu contrato. Fale com o suporte.';
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}
$disp = plano_cobranca_disponivel($conn, (int)$contrato['id']);
$cobranca = $disp['cobranca'] ?? null;
if (!$cobranca) {
    echo json_encode(['status' => 'approved', 'message' => 'Todas as cobranças do seu plano já foram pagas.']);
    exit;
}
if (!$disp['disponivel']) {
    echo json_encode([
        'status' => 'waiting',
        'message' => $disp['motivo'] ?: 'A próxima cobrança ainda não está disponível.',
    ]);
    exit;
}

$valor = round((float)$cobranca['valor'], 2);
$sequencia = (int)$cobranca['sequencia'];
$rotulo = plano_rotulo_cobranca($sequencia, (string)$cobranca['tipo']);
$descricao = 'Economic Card - Plano Mensal - ' . $rotulo;
$planoId = (int)$contrato['plano_id'];

// ---- Asaas (Split de Pagamentos nativo) ----
$cfgAsaas = asaas_config($conn);
if (!$cfgAsaas) {
    asaas_fluxo_log('erro', "asaas nao configurado uid=$uid");
    echo json_encode(['status' => 'error', 'message' => 'API de pagamento não configurada']);
    exit;
}

if ($valor < ASAAS_VALOR_MINIMO) {
    asaas_fluxo_log('erro', "valor abaixo do minimo uid=$uid cobranca={$cobranca['id']} valor=$valor");
    echo json_encode(['status' => 'error', 'message' => 'O valor desta cobrança (R$ ' . number_format($valor, 2, ',', '.') . ') está abaixo do mínimo aceito pelo gateway (R$ 5,00). Ajuste o valor no painel administrativo.']);
    exit;
}

// Evita múltiplos PIX pendentes da mesma cobrança/plano.
$stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'cancelled' WHERE usuario_id = ? AND tipo = 'plano' AND status = 'pending'");
$stmt->bind_param('i', $uid);
$stmt->execute();

// Split: o parceiro recebe o valor fixo por transação; o afiliado que indicou
// o usuário recebe a comissão da parcela correspondente (1ª = 1ª cobrança).
$afiliadoSplit = asaas_afiliado_split_usuario($conn, $u, $sequencia);
asaas_fluxo_log('split_regra', "uid=$uid cobranca={$cobranca['id']} seq=$sequencia valor=$valor afiliado_wallet=" . ($afiliadoSplit['wallet'] !== '' ? 'sim' : 'nao') . " parcela=" . (int)$afiliadoSplit['parcela'] . " comissao_afiliado=" . number_format($afiliadoSplit['valor_fixo'], 2, '.', ''));

$cust = asaas_obter_customer($cfgAsaas, $u);
if (!$cust['ok']) {
    asaas_fluxo_log('erro', "customer falhou uid=$uid: " . $cust['message']);
    echo json_encode(['status' => 'error', 'message' => $cust['message']]);
    exit;
}
asaas_fluxo_log('customer_ok', "uid=$uid customer={$cust['customer_id']}");

$cob = asaas_criar_cobranca_pix($cfgAsaas, $cust['customer_id'], $valor, $descricao, $uid, true, $afiliadoSplit['wallet'], $afiliadoSplit['valor_fixo']);
if (!$cob['ok']) {
    asaas_fluxo_log('erro', "cobranca falhou uid=$uid valor=$valor: " . $cob['message']);
    echo json_encode(['status' => 'error', 'message' => $cob['message']]);
    exit;
}
asaas_fluxo_log('cobranca_ok', "uid=$uid payment={$cob['payment']['id']} split=" . ($cob['split_aplicado'] ? 'sim' : 'nao'));

$payId = (string)$cob['payment']['id'];
$qr = asaas_qrcode_pix($cfgAsaas, $payId, $valor);
if (!$qr['ok']) {
    asaas_fluxo_log('erro', "qrcode falhou uid=$uid payment=$payId: " . $qr['message']);
    echo json_encode(['status' => 'error', 'message' => $qr['message']]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO pagamentos_pix (usuario_id, plano_id, cobranca_id, mp_payment_id, valor, descricao, qr_code_base64, qr_code_copia_cola, status, pix_validade, criado_em, provedor, asaas_payment_id)
    VALUES (?, ?, ?, 0, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW(), 'asaas', ?)");
if (!$stmt) {
    asaas_fluxo_log('erro', "prepare insert falhou uid=$uid payment=$payId: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao registrar o pagamento']);
    exit;
}
$cobrancaId = (int)$cobranca['id'];
$stmt->bind_param('iiidssss', $uid, $planoId, $cobrancaId, $valor, $descricao, $qr['encoded_image'], $qr['payload'], $payId);
if (!$stmt->execute()) {
    asaas_fluxo_log('erro', "insert pix falhou uid=$uid payment=$payId: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao registrar o pagamento']);
    exit;
}
$pix = $conn->query("SELECT * FROM pagamentos_pix WHERE id = " . $conn->insert_id)->fetch_assoc();
asaas_fluxo_log('pix_criado', "uid=$uid pix_id={$pix['id']} cobranca=$cobrancaId payment=$payId");

echo json_encode([
    'status' => 'pending',
    'provedor' => 'asaas',
    'split_aplicado' => $cob['split_aplicado'],
    'pix' => [
        'id' => (int)$pix['id'],
        'mp_payment_id' => 0,
        'gateway_payment_id' => $payId,
        'qr_code_base64' => $pix['qr_code_base64'],
        'qr_code_copia_cola' => $pix['qr_code_copia_cola'],
        'valor' => $valor,
        'plano' => 'Mensal',
        'sequencia' => $sequencia,
        'rotulo' => $rotulo,
    ],
]);
