<?php
session_start();

// Politica zero cache: garante que nenhum navegador armazene o sistema do cartao,
// mesmo que mod_headers nao esteja disponivel no servidor.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

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

// URL absoluta para assets em app/uploads (funciona mesmo quando a página é
// acessada via URL reescrita, ex.: /card/admin_banners.php em vez de /card/app/...)
function asset_url($src) {
    if (!$src) return '';
    if (preg_match('#^https?://#i', $src) || strpos($src, 'data:') === 0 || strpos($src, '//') === 0) {
        return $src;
    }
    if (strpos($src, '/') === 0) return $src;
    static $appBase = null;
    if ($appBase === null) {
        $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $appAbs = str_replace('\\', '/', __DIR__);
        $appBase = $docRoot && strpos($appAbs, $docRoot) === 0
            ? '/' . ltrim(substr($appAbs, strlen($docRoot)), '/')
            : dirname($_SERVER['SCRIPT_NAME'] ?? '');
    }
    return $appBase . '/' . ltrim($src, '/');
}

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
    $stmt = $conn->prepare("SELECT cartao_ativo, status FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r && ($r['status'] ?? 'ativo') === 'desativado') {
        session_unset();
        session_destroy();
        header('Location: login.php?conta_encerrada=1');
        exit;
    }
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
