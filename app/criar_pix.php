<?php
require_once 'config.php';
require_once 'asaas_pix.php';
header('Content-Type: application/json');
if (!isset($_SESSION['usuario_id'])) {
    asaas_fluxo_log('erro', 'sessao invalida: ' . json_encode($_POST));
    echo json_encode(['status' => 'error', 'message' => 'Sessão inválida']);
    exit;
}
$uid = (int)$_SESSION['usuario_id'];

$planoId = (int)($_POST['plano_id'] ?? 0);
asaas_fluxo_log('inicio', "uid=$uid plano_id=$planoId");
$plano = $conn->query("SELECT * FROM planos WHERE id = $planoId AND ativo = 1")->fetch_assoc();
if (!$plano) {
    asaas_fluxo_log('erro', "plano invalido uid=$uid plano_id=$planoId");
    echo json_encode(['status' => 'error', 'message' => 'Plano inválido']);
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

if ($u['cartao_ativo']) {
    echo json_encode(['status' => 'approved', 'message' => 'Cartão já ativo']);
    exit;
}

// ---- Asaas (Split de Pagamentos nativo) ----
// A cobrança é emitida pela conta da empresa com split automático para o parceiro.
$cfgAsaas = asaas_config($conn);
if (!$cfgAsaas) {
    asaas_fluxo_log('erro', "asaas nao configurado uid=$uid");
    echo json_encode(['status' => 'error', 'message' => 'API de pagamento não configurada']);
    exit;
}

$stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'cancelled' WHERE usuario_id = ? AND status = 'pending'");
$stmt->bind_param('i', $uid);
$stmt->execute();

$valor = (float)$plano['valor'];
$dias = (int)$plano['dias'];
$nomePlano = $plano['nome'];
$descricao = "Ativação Economic Card - $nomePlano - $dias dias";

if ($valor < ASAAS_VALOR_MINIMO) {
    asaas_fluxo_log('erro', "valor abaixo do minimo uid=$uid plano=$nomePlano valor=$valor");
    echo json_encode(['status' => 'error', 'message' => 'O valor deste plano (R$ ' . number_format($valor, 2, ',', '.') . ') está abaixo do mínimo aceito pelo gateway (R$ 5,00). Ajuste o preço do plano no painel administrativo.']);
    exit;
}

$cust = asaas_obter_customer($cfgAsaas, $u);
if (!$cust['ok']) {
        asaas_fluxo_log('erro', "customer falhou uid=$uid: " . $cust['message']);
        echo json_encode(['status' => 'error', 'message' => $cust['message']]);
        exit;
    }
    asaas_fluxo_log('customer_ok', "uid=$uid customer={$cust['customer_id']}");
    $cob = asaas_criar_cobranca_pix($cfgAsaas, $cust['customer_id'], $valor, $descricao, $uid);
    if (!$cob['ok']) {
        asaas_fluxo_log('erro', "cobranca falhou uid=$uid valor=$valor: " . $cob['message']);
        echo json_encode(['status' => 'error', 'message' => $cob['message']]);
        exit;
    }
asaas_fluxo_log('cobranca_ok', "uid=$uid payment={$cob['payment']['id']} split=" . ($cob['split_aplicado'] ? 'sim' : 'nao'));
$payId = (string)$cob['payment']['id'];
$qr = asaas_qrcode_pix($cfgAsaas, $payId);
if (!$qr['ok']) {
    asaas_fluxo_log('erro', "qrcode falhou uid=$uid payment=$payId: " . $qr['message']);
    echo json_encode(['status' => 'error', 'message' => $qr['message']]);
    exit;
}
asaas_fluxo_log('qrcode_ok', "uid=$uid payment=$payId");
$stmt = $conn->prepare("INSERT INTO pagamentos_pix (usuario_id, plano_id, mp_payment_id, valor, descricao, qr_code_base64, qr_code_copia_cola, status, pix_validade, criado_em, provedor, asaas_payment_id) VALUES (?, ?, 0, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW(), 'asaas', ?)");
if (!$stmt) {
    asaas_fluxo_log('erro', "prepare insert falhou uid=$uid payment=$payId: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao registrar o pagamento']);
    exit;
}
$stmt->bind_param('iidssss', $uid, $planoId, $valor, $descricao, $qr['encoded_image'], $qr['payload'], $payId);
if (!$stmt->execute()) {
    asaas_fluxo_log('erro', "insert pix falhou uid=$uid payment=$payId: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao registrar o pagamento']);
    exit;
}
$pix = $conn->query("SELECT * FROM pagamentos_pix WHERE id = " . $conn->insert_id)->fetch_assoc();
asaas_fluxo_log('pix_criado', "uid=$uid pix_id={$pix['id']} payment=$payId");
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
        'plano' => $nomePlano,
        'dias' => $dias
    ]
]);
