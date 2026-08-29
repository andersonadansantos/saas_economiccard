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
$idParam = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$idParam) {
    echo json_encode(['status' => 'error', 'message' => 'Pagamento inválido']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM pagamentos_pix WHERE id = ? AND usuario_id = ? AND tipo = 'cartao_fisico'");
$stmt->bind_param('ii', $idParam, $uid);
$stmt->execute();
$pix = $stmt->get_result()->fetch_assoc();

if (!$pix || ($pix['provedor'] ?? '') !== 'asaas' || empty($pix['asaas_payment_id'])) {
    echo json_encode(['status' => 'cancelled', 'message' => 'Pagamento expirado. Gere um novo PIX.']);
    exit;
}

$cfg = asaas_config($conn);
if (!$cfg) {
    echo json_encode(['status' => 'pending']);
    exit;
}
$statusAsaas = asaas_status_pagamento($cfg, $pix['asaas_payment_id']);
$status = $statusAsaas !== '' ? asaas_status_local($statusAsaas) : 'pending';

if ($status === 'approved' && $pix['status'] !== 'approved') {
    // Primeira confirmação: registra o pedido + notifica o admin (na hora).
    $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'approved' WHERE id = ? AND status <> 'approved'");
    $stmt->bind_param('i', $pix['id']);
    $stmt->execute();
    if ($conn->affected_rows > 0) {
        registrar_pedido_cartao($conn, $uid);
        notificar_admin_pedido_cartao($conn, $uid);
    }
}

echo json_encode(['status' => $status]);