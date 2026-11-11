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

$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if (!$u) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário inválido']);
    exit;
}

$hoje = date('Y-m-d');

$cfg = asaas_config($conn);
if (!$cfg) {
    echo json_encode(['status' => 'error', 'message' => 'API de pagamento não configurada']);
    exit;
}

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
    echo json_encode(['status' => 'waiting', 'message' => $disp['motivo'] ?: 'A próxima cobrança ainda não está disponível.']);
    exit;
}
$valor = round((float)$cobranca['valor'], 2);
$sequencia = (int)$cobranca['sequencia'];
$cobrancaId = (int)$cobranca['id'];
$planoId = (int)$contrato['plano_id'];
$nomePlano = 'Mensal';
$rotulo = plano_rotulo_cobranca($sequencia, (string)$cobranca['tipo']);
$descricao = "Economic Card - Plano $nomePlano - $rotulo";

if ($valor < ASAAS_VALOR_MINIMO) {
    echo json_encode(['status' => 'error', 'message' => 'O valor desta cobrança (R$ ' . number_format($valor, 2, ',', '.') . ') está abaixo do mínimo aceito pelo gateway (R$ 5,00). Ajuste o valor no painel administrativo.']);
    exit;
}

// Regra de split: o parceiro (único, exclusivo da empresa) recebe o valor fixo
// por transação em TODA cobrança; o afiliado que indicou o usuário recebe a
// comissão da parcela correspondente do contrato (1ª = 1ª cobrança).
$afiliadoSplit = asaas_afiliado_split_usuario($conn, $u, $sequencia);

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
], $afiliadoSplit['wallet'], $afiliadoSplit['valor_fixo']);
if (!$cob['ok']) {
    echo json_encode(['status' => 'error', 'message' => $cob['message']]);
    exit;
}

$payId = (string)$cob['payment']['id'];
$statusDb = asaas_status_local((string)($cob['payment']['status'] ?? ''));

$stmt = $conn->prepare("INSERT INTO pagamentos_pix (usuario_id, plano_id, cobranca_id, mp_payment_id, valor, descricao, status, pix_validade, criado_em, provedor, asaas_payment_id) VALUES (?, ?, ?, 0, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW(), 'asaas', ?)");
$stmt->bind_param('iiidsss', $uid, $planoId, $cobrancaId, $valor, $descricao, $statusDb, $payId);
$stmt->execute();
$pagamentoId = (int)$conn->insert_id;

$resultado = null;
if ($statusDb === 'approved') {
    // Confirmação centralizada: avança o contrato, libera a próxima cobrança
    // e ativa o cartão somente após a última mensalidade paga (idempotente).
    $resultado = plano_marcar_pagamento_aprovado($conn, $pagamentoId);
}

echo json_encode([
    'status' => $statusDb,
    'gateway_payment_id' => $payId,
    'split_aplicado' => (bool)$cob['split_aplicado'],
    'sequencia' => $sequencia,
    'rotulo' => $rotulo,
    'ativou' => (bool)($resultado['ativou'] ?? false),
    'concluiu' => (bool)($resultado['concluiu'] ?? false),
    'validade' => $resultado['validade'] ?? null,
]);
