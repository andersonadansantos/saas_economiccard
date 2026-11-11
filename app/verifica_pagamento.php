<?php
require_once 'config.php';
require_once 'asaas_pix.php';
require_once 'afiliado_creditos.php';
require_once 'parceiro_creditos.php';
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

// O ID recebido é o id LOCAL da tabela pagamentos_pix (provedor Asaas).
$stmt = $conn->prepare("SELECT * FROM pagamentos_pix WHERE id = ? AND usuario_id = ?");
$stmt->bind_param('ii', $idParam, $uid);
$stmt->execute();
$pixRow = $stmt->get_result()->fetch_assoc();

if (!$pixRow || ($pixRow['provedor'] ?? '') !== 'asaas' || empty($pixRow['asaas_payment_id'])) {
    // Pagamento legado/inesistente: não há mais consulta ao Mercado Pago.
    echo json_encode(['status' => 'cancelled', 'message' => 'Pagamento expirado. Gere um novo PIX.']);
    exit;
}

$cfg = asaas_config($conn);
if (!$cfg) {
    echo json_encode(['status' => 'pending']);
    exit;
}
$statusAsaas = asaas_status_pagamento($cfg, $pixRow['asaas_payment_id']);
$status = $statusAsaas !== '' ? asaas_status_local($statusAsaas) : 'pending';

// Pagamento da taxa do cartão físico tem fluxo próprio (verifica_pagamento_cartao.php).
if (($pixRow['tipo'] ?? 'plano') === 'cartao_fisico') {
    echo json_encode(['status' => $status]);
    exit;
}

$extra = [];
if ($status === 'approved') {
    // Confirmação centralizada e idempotente: avança o contrato, libera a
    // próxima cobrança e ativa o cartão somente após a última mensalidade paga.
    $r = plano_marcar_pagamento_aprovado($conn, (int)$pixRow['id']);
    $extra = [
        'sequencia' => $r['sequencia'] ?? null,
        'ativou' => (bool)($r['ativou'] ?? false),
        'concluiu' => (bool)($r['concluiu'] ?? false),
        'validade' => $r['validade'] ?? null,
    ];
} elseif ($status === 'refunded' && in_array($pixRow['status'], ['pending', 'approved'], true)) {
    // Reembolso/estorno: marca o pagamento como reembolsado, reabre a mesma
    // cobrança do contrato e desfaz os créditos de afiliado e parceiro.
    $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'refunded' WHERE id = ?");
    $stmt->bind_param('i', $pixRow['id']);
    $stmt->execute();
    plano_registrar_estorno($conn, (int)$pixRow['id']);
    afiliado_estornar_credito_pagamento($conn, (int)$pixRow['id']);
    parceiro_estornar_credito_pagamento($conn, (int)$pixRow['id']);
}

echo json_encode(array_merge(['status' => $status], $extra));
