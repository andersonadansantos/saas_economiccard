<?php
/**
 * Registro/atualização do token FCM do aparelho.
 *
 * Chamado de dentro da WebView pelo bridge app/push_client.js
 * (window.EconomicCardPush.register(token)) com a sessão do usuário e o token
 * CSRF da página. Um usuário pode registrar vários dispositivos; o token FCM é
 * único por aparelho — ao trocar de usuário no mesmo celular a linha é
 * reatribuída (upsert).
 *
 * Requisitos: HTTPS (produção), CSRF, JSON, prepared statements.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function push_resposta($dados, $status = 200) {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function push_token_masc($token) {
    $token = (string)$token;
    if (strlen($token) <= 12) return $token;
    return substr($token, 0, 6) . '...' . substr($token, -4);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    app_log('push', 'push_register método inválido: ' . ($_SERVER['REQUEST_METHOD'] ?? '-'));
    push_resposta(['ok' => false, 'erro' => 'Método não permitido.'], 405);
}

// HTTPS obrigatório (localhost/XAMPP liberado apenas para testes).
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$hostSemPorta = preg_replace('/:\d+$/', '', $host);
$local = in_array($hostSemPorta, ['localhost', '127.0.0.1', '::1'], true);
if (!request_is_https() && !$local) {
    app_log('push', 'push_register bloqueado sem HTTPS host=' . $host);
    push_resposta(['ok' => false, 'erro' => 'Conexão segura (HTTPS) obrigatória.'], 403);
}

$csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? ($_GET['csrf'] ?? '')));
if (!csrf_valido($csrf)) {
    app_log('push', 'push_register CSRF inválido host=' . $host . ' ua=' . substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120));
    push_resposta(['ok' => false, 'erro' => 'Sessão expirada. Abra o app novamente.'], 403);
}

$corpo = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($corpo)) {
    $corpo = $_POST;
}

$acao  = strtolower(trim((string)($corpo['acao'] ?? 'registrar')));
$token = trim((string)($corpo['token'] ?? ''));

if ($token === '' || strlen($token) < 20 || strlen($token) > 4096
    || !preg_match('/^[A-Za-z0-9_\-:.]+$/', $token)) {
    app_log('push', 'push_register token inválido acao=' . $acao . ' len=' . strlen($token));
    push_resposta(['ok' => false, 'erro' => 'Token FCM inválido.'], 422);
}

$usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
if ($usuarioId !== null && $usuarioId <= 0) $usuarioId = null;

if ($acao === 'remover') {
    $stmt = $conn->prepare("UPDATE push_devices SET status = 0 WHERE token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $af = $stmt ? $stmt->affected_rows : 0;
    $stmt->close();
    app_log('push', 'push_register remover token=' . push_token_masc($token) . ' afetados=' . $af);
    push_resposta(['ok' => true, 'removido' => true]);
}

$plataforma = strtolower(trim((string)($corpo['plataforma'] ?? 'android')));
if (!in_array($plataforma, ['android', 'ios'], true)) $plataforma = 'android';

$stmt = $conn->prepare("INSERT INTO push_devices
        (user_id, token, plataforma, status)
    VALUES (?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
        user_id = VALUES(user_id),
        plataforma = VALUES(plataforma),
        status = 1,
        updated_at = NOW()");
$stmt->bind_param('iss', $usuarioId, $token, $plataforma);

if (!$stmt->execute()) {
    app_log('push', 'Falha ao registrar token: ' . $stmt->error . ' token=' . push_token_masc($token));
    $stmt->close();
    push_resposta(['ok' => false, 'erro' => 'Não foi possível registrar o dispositivo.'], 500);
}
$stmt->close();

app_log('push', 'push_register ok usuario=' . ($usuarioId !== null ? $usuarioId : 'null') . ' token=' . push_token_masc($token) . ' plataforma=' . $plataforma);

push_resposta([
    'ok'      => true,
    'usuario' => $usuarioId !== null,
]);
