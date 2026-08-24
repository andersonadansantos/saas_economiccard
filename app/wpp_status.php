<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!($_SESSION['admin_logado'] ?? false)) {
    echo json_encode(['ok' => false, 'msg' => 'Não autorizado']);
    exit;
}

$cfg = $conn->query("SELECT * FROM config_whatsapp WHERE id = 1")->fetch_assoc();
if (!$cfg || empty($cfg['url_base']) || empty($cfg['instancia']) || empty($cfg['api_key'])) {
    echo json_encode(['ok' => false, 'msg' => 'Credenciais não configuradas']);
    exit;
}

$action = $_GET['action'] ?? 'status';
$base = rtrim($cfg['url_base'], '/');
$instancia = rawurlencode($cfg['instancia']);
$apiKey = $cfg['api_key'];

function wpp_curl($url, $method = 'GET', $post = null, $apiKey = '') {
    $ch = curl_init($url);
    $headers = ['apikey: ' . $apiKey];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($post !== null) {
            $opts[CURLOPT_POSTFIELDS] = $post;
            $headers[] = 'Content-Type: application/json';
        }
    }
    if ($method === 'DELETE') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['res' => $res, 'http' => $httpCode, 'err' => $err];
}

if ($action === 'status') {
    $r = wpp_curl($base . '/instance/connectionState/' . $instancia, 'GET', null, $apiKey);
    if ($r['err'] !== '') {
        echo json_encode(['ok' => false, 'msg' => 'Erro de conexão: ' . $r['err']]);
        exit;
    }
    $d = json_decode($r['res'], true);
    $state = $d['instance']['state'] ?? ($d['state'] ?? 'close');
    $nome = $d['instance']['instanceName'] ?? $instancia;
    echo json_encode([
        'ok' => true,
        'state' => strtolower($state),
        'instance' => $nome,
        'http' => $r['http']
    ]);
    exit;
}

if ($action === 'qrcode') {
    $r = wpp_curl($base . '/instance/connect/' . $instancia, 'GET', null, $apiKey);
    if ($r['err'] !== '') {
        echo json_encode(['ok' => false, 'msg' => 'Erro de conexão: ' . $r['err']]);
        exit;
    }
    $d = json_decode($r['res'], true);
    if (isset($d['base64'])) {
        $img = $d['base64'];
        if (strpos($img, 'data:') !== 0) { $img = 'data:image/png;base64,' . $img; }
        echo json_encode(['ok' => true, 'state' => 'connecting', 'base64' => $img, 'pairing' => $d['pairingCode'] ?? null]);
        exit;
    }
    $state = strtolower($d['instance']['state'] ?? 'close');
    if ($state === 'open') {
        echo json_encode(['ok' => true, 'state' => 'open']);
    } else {
        echo json_encode(['ok' => false, 'msg' => $d['error'] ?? $d['message'] ?? 'Falha ao obter QR Code (HTTP ' . $r['http'] . ')', 'http' => $r['http']]);
    }
    exit;
}

if ($action === 'desconectar') {
    $r = wpp_curl($base . '/instance/logout/' . $instancia, 'DELETE', null, $apiKey);
    echo json_encode(['ok' => $r['http'] < 400, 'msg' => 'Desconectado (HTTP ' . $r['http'] . ')', 'res' => substr($r['res'], 0, 200)]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Ação inválida']);
