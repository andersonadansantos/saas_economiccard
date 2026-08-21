<?php
session_start();

// Segredos locais (fora do git) — veja secrets_exemplo.php.
// Em produção/Docker use variáveis de ambiente; localmente o XAMPP usa secrets_local.php.
if (file_exists(__DIR__ . '/secrets_local.php')) {
    require_once __DIR__ . '/secrets_local.php';
}

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db   = getenv('DB_NAME') ?: 'economicacard';

try {
    $conn = new mysqli($host, $user, $pass, $db);
} catch (mysqli_sql_exception $e) {
    http_response_code(503);
    die('Falha na conexao com o banco de dados. Verifique se o MySQL esta em execucao no XAMPP.');
}
$conn->set_charset('utf8mb4');

// Cloudflare Turnstile (proteção das páginas de login) — helpers centralizados em turnstile.php
require_once __DIR__ . '/turnstile.php';

// Contratos: código único + certificação de aceite
require_once __DIR__ . '/contrato_aceite.php';

// Monta o Split de Pagamentos do Mercado Pago (modelo oficial 1:1 / marketplace).
// O pagamento é criado usando o access_token OAuth do PARCEIRO (coletor) e a parte da
// empresa vai no campo application_fee (em R$). O token do parceiro fica salvo na própria
// tabela api_pagamento (preenchido automaticamente pelo oauth_callback.php com state=parceiro).
// Afiliados NÃO participam do split — somente o parceiro configurado.
// Retorna null se não estiver configurado; nesse caso o pagamento segue 100% para a empresa.
function montar_split_pagamento($conn, $valor) {
    $cfg = $conn->query("SELECT marketplace_user_id, parceiro_user_id, porcentagem_parceiro, parceiro_access_token FROM api_pagamento WHERE id = 1")->fetch_assoc();
    if (!$cfg) return null;
    $marketplace = trim((string)($cfg['marketplace_user_id'] ?? ''));
    $pct = (float)($cfg['porcentagem_parceiro'] ?? 0);
    $tokenParceiro = trim((string)($cfg['parceiro_access_token'] ?? ''));
    if ($marketplace === '' || $tokenParceiro === '' || $pct <= 0 || $pct >= 100) return null;
    return [
        'mp_access_token'     => $tokenParceiro,
        'collector_id'        => trim((string)($cfg['parceiro_user_id'] ?? '')),
        'marketplace_user_id' => $marketplace,
        'application_fee'     => round(((100 - $pct) / 100) * (float)$valor, 2),
    ];
}

// Desativa cartões com validade expirada (60 dias)
$conn->query("UPDATE usuarios SET cartao_ativo = 0 WHERE cartao_ativo = 1 AND cartao_validade IS NOT NULL AND cartao_validade < CURDATE()");

// Bloqueia o acesso enquanto o usuário não ativar o cartão.
// Redireciona para ativar.php (usado na versão app / raiz).
function exigirCartaoAtivo() {
    if (!isset($_SESSION['usuario_id'])) return;
    global $conn;
    $uid = (int)$_SESSION['usuario_id'];
    $stmt = $conn->prepare("SELECT cartao_ativo FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r && !$r['cartao_ativo']) {
        header('Location: ativar.php?bloqueado=1');
        exit;
    }
}

// Valida CPF brasileiro (algoritmo oficial dos dígitos verificadores).
function cpf_valido($cpf) {
    $cpf = preg_replace('/\D/', '', (string)$cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ((int)$cpf[$c] !== $d) {
            return false;
        }
    }
    return true;
}
