<?php
require_once 'config.php';
require_once 'asaas_pix.php';
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

$cfg = asaas_config($conn);
if (!$cfg) {
    echo json_encode(['status' => 'error', 'message' => 'API de pagamento não configurada']);
    exit;
}

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

if ($valor < ASAAS_VALOR_MINIMO) {
    echo json_encode(['status' => 'error', 'message' => 'O valor deste plano (R$ ' . number_format($valor, 2, ',', '.') . ') está abaixo do mínimo aceito pelo gateway (R$ 5,00). Ajuste o preço do plano no painel administrativo.']);
    exit;
}

$holderName = trim($_POST['holder_name'] ?? '');
$numero = preg_replace('/\D/', '', $_POST['number'] ?? '');
$ccc = preg_replace('/\D/', '', $_POST['ccc'] ?? '');
$vencimento = trim($_POST['expiry'] ?? '');
if (!preg_match('/^(\d{1,2})\s*\/?\s*(\d{2}|\d{4})$/', $vencimento, $m)) {
    echo json_encode(['status' => 'error', 'message' => 'Validade do cartão inválida (use MM/AA)']);
    exit;
}
$mes = str_pad($m[1], 2, '0', STR_PAD_LEFT);
if ((int)$mes < 1 || (int)$mes > 12) {
    echo json_encode(['status' => 'error', 'message' => 'Mês de validade inválido']);
    exit;
}
$ano = strlen($m[2]) === 2 ? ('20' . $m[2]) : $m[2];

$cpfCnpj = preg_replace('/\D/', '', $_POST['cpf_cnpj'] ?? '');
if ($cpfCnpj === '') {
    $cpfCnpj = preg_replace('/\D/', '', (string)$u['cpf']);
}

$postalCode = preg_replace('/\D/', '', $_POST['postal_code'] ?? '');
if ($postalCode === '') {
    $postalCode = preg_replace('/\D/', '', (string)$u['cep']);
}

$addressNumber = trim($_POST['address_number'] ?? '');
if ($addressNumber === '' && !empty($u['endereco'])) {
    if (preg_match('/(\d+)/', (string)$u['endereco'], $mm)) {
        $addressNumber = $mm[1];
    }
}
if ($addressNumber === '') {
    $addressNumber = 'S/N';
}

$email = trim((string)$u['email']);
if ($email === '') {
    $email = 'usuario' . $uid . '@economiccard.com.br';
}

if ($holderName === '' || strlen($numero) < 13 || strlen($ccc) < 3 || $cpfCnpj === '' || $postalCode === '') {
    echo json_encode(['status' => 'error', 'message' => 'Dados do cartão/endereço incompletos']);
    exit;
}

$cust = asaas_obter_customer($cfg, $u);
if (!$cust['ok']) {
    echo json_encode(['status' => 'error', 'message' => $cust['message']]);
    exit;
}

$cob = asaas_criar_cobranca_cartao($cfg, $cust['customer_id'], $valor, $descricao, $uid, [
    'holderName' => $holderName,
    'number' => $numero,
    'expiryMonth' => $mes,
    'expiryYear' => $ano,
    'ccc' => $ccc,
    'cpfCnpj' => $cpfCnpj,
    'postalCode' => $postalCode,
    'addressNumber' => $addressNumber,
    'phone' => $u['whatsapp'] ?? ''
]);
if (!$cob['ok']) {
    echo json_encode(['status' => 'error', 'message' => $cob['message']]);
    exit;
}

$payId = (string)$cob['payment']['id'];
$statusDb = asaas_status_local((string)($cob['payment']['status'] ?? ''));

if ($statusDb === 'approved') {
    $validade = date('Y-m-d', strtotime('+' . $dias . ' days'));
    $stmt = $conn->prepare("UPDATE usuarios SET cartao_ativo = 1, cartao_validade = ? WHERE id = ?");
    $stmt->bind_param('si', $validade, $uid);
    $stmt->execute();
    require_once 'email_sender.php';
    if (!empty($u['email'])) {
        enviar_template_geral('cartao_ativado', ['nome' => $u['nome'], 'email' => $u['email']]);
    }
}

$stmt = $conn->prepare("INSERT INTO pagamentos_pix (usuario_id, plano_id, mp_payment_id, valor, descricao, status, pix_validade, criado_em, provedor, asaas_payment_id) VALUES (?, ?, 0, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW(), 'asaas', ?)");
$stmt->bind_param('iidsss', $uid, $planoId, $valor, $descricao, $statusDb, $payId);
$stmt->execute();

echo json_encode([
    'status' => $statusDb,
    'gateway_payment_id' => $payId,
    'split_aplicado' => (bool)$cob['split_aplicado']
]);
