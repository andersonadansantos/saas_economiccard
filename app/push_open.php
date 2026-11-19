<?php
require_once __DIR__ . '/config.php';

$track = preg_replace('/[^a-f0-9]/i', '', (string)($_GET['t'] ?? ''));
$token = trim((string)($_GET['token'] ?? ($_GET['tk'] ?? '')));
$destino = '/app/dashboard.php';

if ($track !== '') {
    $stmt = $conn->prepare("SELECT id, link FROM push_envios WHERE track_key = ? LIMIT 1");
    $stmt->bind_param('s', $track);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $envioId = (int)$row['id'];
        $link = push_link_seguro((string)($row['link'] ?? ''));
        if ($link !== '') {
            $destino = $link;
        }
        $userId = null;
        if ($token !== '' && strlen($token) >= 20) {
            $d = $conn->prepare("SELECT user_id FROM push_devices WHERE token = ? LIMIT 1");
            $d->bind_param('s', $token);
            $d->execute();
            $dr = $d->get_result()->fetch_assoc();
            $d->close();
            if ($dr && !empty($dr['user_id'])) $userId = (int)$dr['user_id'];
        }
        $tokenHash = $token !== '' ? hash('sha256', $token) : hash('sha256', 'anon|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $origem = 'link';
        if ($userId === null) {
            $ins = $conn->prepare("INSERT IGNORE INTO push_aberturas (envio_id, user_id, token_hash, origem, aberto_em) VALUES (?, NULL, ?, ?, NOW())");
            $ins->bind_param('iss', $envioId, $tokenHash, $origem);
        } else {
            $ins = $conn->prepare("INSERT IGNORE INTO push_aberturas (envio_id, user_id, token_hash, origem, aberto_em) VALUES (?, ?, ?, ?, NOW())");
            $ins->bind_param('iiss', $envioId, $userId, $tokenHash, $origem);
        }
        $ins->execute();
        $afetou = $ins->affected_rows;
        $ins->close();
        if ($afetou > 0) {
            @$conn->query("UPDATE push_envios SET aberturas = aberturas + 1 WHERE id = " . $envioId);
        }
    }
} else {
    $l = push_link_seguro((string)($_GET['link'] ?? ''));
    if ($l !== '') $destino = $l;
}

header('Location: ' . $destino);
exit;
