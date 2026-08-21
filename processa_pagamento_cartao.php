<?php
require_once 'config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['usuario_id'])) {
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

if ($u['cartao_ativo']) {
    echo json_encode(['status' => 'approved', 'message' => 'Cartão já ativo']);
    exit;
}

$token = '';
$tr = $conn->query("SELECT access_token FROM api_pagamento WHERE id = 1");
if ($tr && ($row = $tr->fetch_assoc())) { $token = $row['access_token'] ?? ''; }
if (!$token) {
    echo json_encode(['status' => 'error', 'message' => 'API não configurada']);
    exit;
}

$paymentMethodId = trim($_POST['payment_method_id'] ?? '');
$cardToken = trim($_POST['token'] ?? '');
$installments = max(1, (int)($_POST['installments'] ?? 1));
$payerEmail = trim($_POST['payer_email'] ?? 'usuario' . $uid . '@economiccard.com.br');
$payerType = trim($_POST['payer_identification_type'] ?? 'CPF');
if ($payerType !== 'CPF' && $payerType !== 'CNPJ') { $payerType = 'CPF'; }
$payerNumber = preg_replace('/\D/', '', $_POST['payer_identification_number'] ?? '');
if ($payerNumber === '') { $payerNumber = preg_replace('/\D/', '', $u['cpf']); }
if ($payerNumber === '') { $payerNumber = '00000000000'; }

$planoId = (int)($_POST['plano_id'] ?? 0);
$plano = $conn->query("SELECT * FROM planos WHERE id = $planoId AND ativo = 1")->fetch_assoc();
if (!$plano) {
    echo json_encode(['status' => 'error', 'message' => 'Plano inválido']);
    exit;
}
$valor = (float)$plano['valor'];
$dias = (int)$plano['dias'];
$nomePlano = $plano['nome'];
$descricao = "Ativação Economic Card - $nomePlano - $dias dias";

if (!$cardToken || !$paymentMethodId) {
    echo json_encode(['status' => 'error', 'message' => 'Dados do cartão inválidos']);
    exit;
}

$payload = json_encode([
    'transaction_amount' => $valor,
    'description' => $descricao,
    'payment_method_id' => $paymentMethodId,
    'token' => $cardToken,
    'installments' => $installments,
    'payer' => [
        'email' => $payerEmail,
        'first_name' => $u['nome'],
        'identification' => ['type' => $payerType, 'number' => $payerNumber]
    ]
], JSON_UNESCAPED_UNICODE);

$split = montar_split_pagamento($conn, $valor);
$authToken = $token;
if ($split) {
    $arr = json_decode($payload, true);
    $arr['application_fee'] = $split['application_fee'];
    $payload = json_encode($arr, JSON_UNESCAPED_UNICODE);
    $authToken = $split['mp_access_token'];
}

$ch = curl_init('https://api.mercadopago.com/v1/payments');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $authToken,
        'Content-Type: application/json',
        'X-Idempotency-Key: ' . uniqid('card', true)
    ],
    CURLOPT_TIMEOUT => 30
]);
$res = curl_exec($ch);
curl_close($ch);
$dados = json_decode($res, true);

$status = strtolower($dados['status'] ?? 'rejected');
$mpId = $dados['id'] ?? null;
$statusDetail = $dados['status_detail'] ?? '';

if ($status === 'approved') {
    $validade = date('Y-m-d', strtotime('+' . $dias . ' days'));
    $stmt = $conn->prepare("UPDATE usuarios SET cartao_ativo = 1, cartao_validade = ? WHERE id = ?");
    $stmt->bind_param('si', $validade, $uid);
    $stmt->execute();
    require_once 'email_sender.php';
    if (!empty($u['email'])) {
        enviar_template_geral('cartao_ativado', ['nome' => $u['nome'], 'email' => $u['email']]);
    }
}

$statusDb = 'rejected';
if ($status === 'approved') { $statusDb = 'approved'; }
elseif ($status === 'pending' || $status === 'in_process') { $statusDb = 'pending'; }

$stmt = $conn->prepare("INSERT INTO pagamentos_pix (usuario_id, plano_id, mp_payment_id, valor, descricao, status, pix_validade) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
$stmt->bind_param('iiidss', $uid, $planoId, $mpId, $valor, $descricao, $statusDb);
$stmt->execute();

if ($status === 'approved') {
    echo json_encode(['status' => 'approved', 'mp_payment_id' => $mpId, 'split_aplicado' => (bool)$split]);
} elseif ($status === 'pending' || $status === 'in_process') {
    echo json_encode(['status' => 'pending', 'mp_payment_id' => $mpId, 'split_aplicado' => (bool)$split]);
} else {
    $msg = $dados['message'] ?? 'Pagamento recusado';
    if (!empty($dados['error'])) { $msg = $dados['error']; }
    echo json_encode(['status' => 'rejected', 'message' => $msg, 'detail' => $statusDetail]);
}
