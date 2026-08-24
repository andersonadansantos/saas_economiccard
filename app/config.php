<?php
session_start();

// Segredos locais (fora do git) — veja secrets_exemplo.php.
// Em produção/Docker use variáveis de ambiente; localmente o XAMPP usa secrets_local.php.
if (file_exists(__DIR__ . '/secrets_local.php')) {
    require_once __DIR__ . '/secrets_local.php';
}

$host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
$port = getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : 3306);
$user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (defined('DB_PASS') ? DB_PASS : '');
$db   = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'economicacard');

try {
    $conn = new mysqli($host, $user, $pass, $db, (int)$port);
} catch (mysqli_sql_exception $e) {
    http_response_code(503);
    die('Falha na conexao com o banco de dados. Verifique se o MySQL esta em execucao no XAMPP.');
}
$conn->set_charset('utf8mb4');

// Cloudflare Turnstile (proteção das páginas de login) — helpers centralizados em turnstile.php
require_once __DIR__ . '/turnstile.php';

// Contratos: código único + certificação de aceite
require_once __DIR__ . '/contrato_aceite.php';

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
