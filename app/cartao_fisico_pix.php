<?php
// Fluxo PIX do pedido de cartão físico (taxa de emissão/envio).
// Compartilhado entre o endpoint de pagamento e o webhook.

define('PEDIDO_CARTAO_TAXA', 35.00);

// Registra o pedido de cartão físico (uma vez por usuário).
// Retorna true se o pedido foi criado agora, false se já existia.
function registrar_pedido_cartao($conn, $uid) {
    $check = $conn->prepare("SELECT id FROM pedidos_cartao WHERE usuario_id = ?");
    $check->bind_param('i', $uid);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        return false;
    }
    $stmt = $conn->prepare("INSERT INTO pedidos_cartao (usuario_id) VALUES (?)");
    $stmt->bind_param('i', $uid);
    return $stmt->execute();
}

// Notifica o admin (WhatsApp + e-mail, melhor esforço) sobre o novo pedido.
function notificar_admin_pedido_cartao($conn, $uid) {
    $stmt = $conn->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    if (!$u) {
        return;
    }

    $atend = $conn->query("SELECT * FROM config_atendimento WHERE id = 1")->fetch_assoc() ?: [];
    $nomeUsuario = $u['nome'] ?? '';
    $emailUsuario = $u['email'] ?? '';

    // WhatsApp (Evolution API), se configurado.
    if (!empty($atend['whatsapp'])) {
        $wpp = $conn->query("SELECT * FROM config_whatsapp WHERE id = 1")->fetch_assoc() ?: [];
        if (!empty($wpp['ativo']) && !empty($wpp['url_base']) && !empty($wpp['instancia']) && !empty($wpp['api_key'])) {
            $numero = preg_replace('/\D/', '', (string)$atend['whatsapp']);
            if (strlen($numero) === 10 || strlen($numero) === 11) { $numero = '55' . $numero; }
            if (strlen($numero) >= 12 && strlen($numero) <= 13) {
                $msg = "Novo pedido de cartão físico (taxa paga)!\n"
                    . "Cliente: $nomeUsuario\n"
                    . "E-mail: $emailUsuario\n"
                    . "Confira no painel admin em Pedidos / Cartão.";
                enviar_whatsapp_evolution($wpp['url_base'], $wpp['instancia'], $wpp['api_key'], $numero, $msg);
            }
        }
    }

    // E-mail (SMTP), se configurado.
    if (!empty($atend['email'])) {
        require_once __DIR__ . '/email_sender.php';
        $corpo = templateShell('<p>Um novo pedido de <strong>cartão físico</strong> foi registrado após a confirmação do pagamento da taxa de R$ ' . number_format(PEDIDO_CARTAO_TAXA, 2, ',', '.') . '.</p>' .
            '<p><b>Cliente:</b> ' . htmlspecialchars($nomeUsuario) . '<br><b>E-mail:</b> ' . htmlspecialchars($emailUsuario) . '</p>' .
            '<p>Consulte o painel administrativo no menu <b>Pedidos / Cartão</b>.</p>');
        enviar_email_smtp($atend['email'], 'Novo pedido de cartão físico', $corpo);
    }
}

// Envio via Evolution API (duplicado local para não depender do painel admin).
if (!function_exists('enviar_whatsapp_evolution')) {
    function enviar_whatsapp_evolution($url_base, $instancia, $api_key, $numero, $texto) {
        $url = rtrim($url_base, '/') . '/message/sendText/' . rawurlencode($instancia);
        $payload = json_encode(['number' => $numero, 'text' => $texto], JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'apikey: ' . $api_key
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err !== '') { return ['ok' => false, 'msg' => 'Erro de conexão: ' . $err]; }
        if ($httpCode >= 200 && $httpCode < 300) { return ['ok' => true, 'msg' => 'Enviado (HTTP ' . $httpCode . ')']; }
        return ['ok' => false, 'msg' => 'HTTP ' . $httpCode . ': ' . substr($res, 0, 300)];
    }
}