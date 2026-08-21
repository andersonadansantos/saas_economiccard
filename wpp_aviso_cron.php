<?php
require_once 'config.php';

function limparNumeroWhatsappCron($numero) {
    $n = preg_replace('/\D/', '', $numero ?? '');
    if (strlen($n) < 10 || strlen($n) > 13) { return ''; }
    if (strlen($n) === 13) { return $n; }
    return '55' . $n;
}

$cfg = $conn->query("SELECT * FROM config_whatsapp WHERE id = 1")->fetch_assoc();

// Quando acessado via HTTP (cron-job.org), exige o token de segurança
$viaHttp = (php_sapi_name() !== 'cli');
if ($viaHttp) {
    $tokenEnviado = $_GET['token'] ?? '';
    $tokenOk = !empty($cfg['cron_token']) && hash_equals($cfg['cron_token'], $tokenEnviado);
    if (!$tokenOk) {
        http_response_code(403);
        echo "Acesso negado. Token inválido.\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

if (!$cfg || empty($cfg['ativo']) || empty($cfg['url_base']) || empty($cfg['instancia']) || empty($cfg['api_key'])) {
    echo "API WhatsApp desativada ou não configurada.\n";
    exit;
}

$q = $conn->query("SELECT id, nome, whatsapp, cartao_validade FROM usuarios WHERE cartao_ativo = 1 AND cartao_validade IS NOT NULL AND DATEDIFF(cartao_validade, CURDATE()) BETWEEN 1 AND 5 ORDER BY cartao_validade ASC");

$enviados = 0;
$falhas = 0;
while ($u = $q->fetch_assoc()) {
    $numero = limparNumeroWhatsappCron($u['whatsapp']);
    if ($numero === '') { continue; }
    $jaEnviado = $conn->query("SELECT id FROM envios_whatsapp WHERE usuario_id = " . (int)$u['id'] . " AND tipo = 'expira_5dias' AND criado_em > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->num_rows;
    if ($jaEnviado > 0) { continue; }

    $diasRestantes = max(1, (int)((strtotime($u['cartao_validade']) - strtotime(date('Y-m-d'))) / 86400));
    $msg = $cfg['template'] ?? '';
    $msg = str_replace('{nome}', $u['nome'], $msg);
    $msg = str_replace('{dias}', $diasRestantes, $msg);
    $msg = str_replace('{validade}', date('d/m/Y', strtotime($u['cartao_validade'])), $msg);

    $url = rtrim($cfg['url_base'], '/') . '/message/sendText/' . rawurlencode($cfg['instancia']);
    $payload = json_encode(['number' => $numero, 'text' => $msg], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'apikey: ' . $cfg['api_key']],
        CURLOPT_TIMEOUT => 30
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $ok = ($err === '' && $httpCode >= 200 && $httpCode < 300);
    $statusDb = $ok ? 'enviado' : 'erro';
    $resposta = $err !== '' ? 'Curl: ' . $err : 'HTTP ' . $httpCode . ': ' . substr($res, 0, 300);
    $stmt = $conn->prepare("INSERT INTO envios_whatsapp (usuario_id, tipo, numero, status, resposta) VALUES (?, 'expira_5dias', ?, ?, ?)");
    $stmt->bind_param('isss', $u['id'], $numero, $statusDb, $resposta);
    $stmt->execute();

    if ($ok) { $enviados++; } else { $falhas++; }
    sleep(1);
}

echo "Cron concluído: $enviados enviados, $falhas falhas.\n";
