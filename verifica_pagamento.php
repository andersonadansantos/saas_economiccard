<?php
require_once 'config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sessão inválida']);
    exit;
}
$uid = (int)$_SESSION['usuario_id'];
$mpPaymentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$mpPaymentId) {
    echo json_encode(['status' => 'error', 'message' => 'Pagamento inválido']);
    exit;
}

$tr = $conn->query("SELECT access_token FROM api_pagamento WHERE id = 1");
$token = '';
if ($tr && ($row = $tr->fetch_assoc())) { $token = $row['access_token'] ?? ''; }
if (!$token) {
    echo json_encode(['status' => 'error', 'message' => 'API não configurada']);
    exit;
}

// Com split ativo, o pagamento foi criado com o token OAuth do parceiro.
// Consulta primeiro com o token da empresa e, se não encontrar, repete com o do parceiro.
$candidatos = [$token];
$split = montar_split_pagamento($conn, 0);
if ($split) {
    $candidatos[] = $split['mp_access_token'];
}

$dados = [];
foreach ($candidatos as $tk) {
    $ch = curl_init('https://api.mercadopago.com/v1/payments/' . $mpPaymentId);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tk],
        CURLOPT_TIMEOUT => 30
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $dados = json_decode($res, true) ?: [];
    if (!empty($dados['status'])) {
        break;
    }
}

$status = strtolower($dados['status'] ?? 'pending');

if ($status === 'approved') {
    require_once 'email_sender.php';
    $dias = 60;
    $stmt = $conn->prepare("SELECT plano_id FROM pagamentos_pix WHERE mp_payment_id = ? AND usuario_id = ?");
    $stmt->bind_param('ii', $mpPaymentId, $uid);
    $stmt->execute();
    $pp = $stmt->get_result()->fetch_assoc();
    if ($pp && !empty($pp['plano_id'])) {
        $pl = $conn->query("SELECT dias FROM planos WHERE id = " . (int)$pp['plano_id'] . " AND ativo = 1")->fetch_assoc();
        if ($pl) { $dias = (int)$pl['dias']; }
    }
    $validade = date('Y-m-d', strtotime('+' . $dias . ' days'));
    $stmt = $conn->prepare("UPDATE usuarios SET cartao_ativo = 1, cartao_validade = ? WHERE id = ?");
    $stmt->bind_param('si', $validade, $uid);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'approved' WHERE mp_payment_id = ? AND usuario_id = ?");
    $stmt->bind_param('ii', $mpPaymentId, $uid);
    $stmt->execute();

    if ($conn->affected_rows > 0) {
        $u = $conn->query("SELECT nome, email FROM usuarios WHERE id = $uid")->fetch_assoc();
        if ($u && !empty($u['email'])) {
            enviar_template_geral('cartao_ativado', ['nome' => $u['nome'], 'email' => $u['email']]);
        }
    }
}

echo json_encode(['status' => $status]);
