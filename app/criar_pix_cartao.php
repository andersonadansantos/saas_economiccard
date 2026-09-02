<?php
require_once 'config.php';
require_once 'asaas_pix.php';
require_once 'cartao_fisico_pix.php';
header('Content-Type: application/json');
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sessão inválida']);
    exit;
}
$uid = (int)$_SESSION['usuario_id'];

$checa = $conn->prepare("SELECT id FROM pedidos_cartao WHERE usuario_id = ?");
$checa->bind_param('i', $uid);
$checa->execute();
if ($checa->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Você já solicitou o cartão físico.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if (!$u) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário inválido']);
    exit;
}

$cfgAsaas = asaas_config($conn);
if (!$cfgAsaas) {
    echo json_encode(['status' => 'error', 'message' => 'API de pagamento não configurada']);
    exit;
}

// Cancela PIX pendentes anteriores do pedido de cartão físico.
$stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'cancelled' WHERE usuario_id = ? AND tipo = 'cartao_fisico' AND status = 'pending'");
$stmt->bind_param('i', $uid);
$stmt->execute();

$valor = PEDIDO_CARTAO_TAXA;
$descricao = 'Taxa Cartão Físico - Economic Card';

$cust = asaas_obter_customer($cfgAsaas, $u);
if (!$cust['ok']) {
    echo json_encode(['status' => 'error', 'message' => $cust['message']]);
    exit;
}
$cob = asaas_criar_cobranca_pix($cfgAsaas, $cust['customer_id'], $valor, $descricao, $uid, false);
if (!$cob['ok']) {
    echo json_encode(['status' => 'error', 'message' => $cob['message']]);
    exit;
}
$payId = (string)$cob['payment']['id'];
$qr = asaas_qrcode_pix($cfgAsaas, $payId, $valor);
if (!$qr['ok']) {
    echo json_encode(['status' => 'error', 'message' => $qr['message']]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO pagamentos_pix (usuario_id, plano_id, mp_payment_id, provedor, tipo, asaas_payment_id, valor, descricao, qr_code_base64, qr_code_copia_cola, status, pix_validade, criado_em) VALUES (?, NULL, 0, 'asaas', 'cartao_fisico', ?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao registrar o pagamento']);
    exit;
}
$stmt->bind_param('isdsss', $uid, $payId, $valor, $descricao, $qr['encoded_image'], $qr['payload']);
if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao registrar o pagamento']);
    exit;
}
$pix = $conn->query("SELECT * FROM pagamentos_pix WHERE id = " . $conn->insert_id)->fetch_assoc();

echo json_encode([
    'status' => 'pending',
    'provedor' => 'asaas',
    'pix' => [
        'id' => (int)$pix['id'],
        'gateway_payment_id' => $payId,
        'qr_code_base64' => $pix['qr_code_base64'],
        'qr_code_copia_cola' => $pix['qr_code_copia_cola'],
        'valor' => $valor
    ]
]);