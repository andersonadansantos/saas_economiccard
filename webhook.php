<?php
header('Content-Type: application/json');
http_response_code(200);

require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/asaas_pix.php';
require_once __DIR__ . '/app/cartao_fisico_pix.php';

function responder($dados) {
    echo json_encode($dados);
    exit;
}

// Ativa o cartão do usuário e marca o pagamento como aprovado (compartilhado entre provedores).
function aprovar_pagamento_local($conn, $pix) {
    $uid = (int)$pix['usuario_id'];
    require_once __DIR__ . '/app/email_sender.php';
    $dias = 60;
    if (!empty($pix['plano_id'])) {
        $pl = $conn->query("SELECT dias FROM planos WHERE id = " . (int)$pix['plano_id'] . " AND ativo = 1")->fetch_assoc();
        if ($pl) { $dias = (int)$pl['dias']; }
    }
    $validade = date('Y-m-d', strtotime('+' . $dias . ' days'));
    $stmt = $conn->prepare("UPDATE usuarios SET cartao_ativo = 1, cartao_validade = ?, adesao_paga = 1 WHERE id = ?");
    $stmt->bind_param('si', $validade, $uid);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'approved' WHERE id = ?");
    $stmt->bind_param('i', $pix['id']);
    $stmt->execute();

    if ($conn->affected_rows > 0) {
        $u = $conn->query("SELECT nome, email FROM usuarios WHERE id = $uid")->fetch_assoc();
        if ($u && !empty($u['email'])) {
            enviar_template_geral('cartao_ativado', ['nome' => $u['nome'], 'email' => $u['email']]);
        }
    }
}

$corpo = json_decode(file_get_contents('php://input'), true) ?: [];

// ---- Eventos Asaas (ex.: PAYMENT_CREATED, PAYMENT_RECEIVED, PAYMENT_CONFIRMED, PAYMENT_REFUNDED) ----
$eventoAsaas = (string)($corpo['event'] ?? '');
if ($eventoAsaas !== '' && stripos($eventoAsaas, 'PAYMENT_') === 0 && !empty($corpo['payment']['id'])) {
    $cfg = asaas_config($conn);
    if (!$cfg) {
        error_log('webhook: evento Asaas recebido mas a API nao esta configurada');
        responder(['recebido' => true]);
    }
    // Verificação opcional de token (Asaas envia no header asaas.accessToken ou ?token=)
    $tokenRecebido = $_SERVER['HTTP_ASAAS_ACCESSTOKEN'] ?? ($_GET['token'] ?? '');
    if ($tokenRecebido !== '' && !hash_equals(trim((string)$cfg['asaas_api_key']), (string)$tokenRecebido)) {
        http_response_code(401);
        responder(['erro' => 'token invalido']);
    }

    $payId = (string)$corpo['payment']['id'];
    // Reconsulta a API oficial para não confiar cegamente no payload recebido.
    $statusAsaas = asaas_status_pagamento($cfg, $payId);
    if ($statusAsaas === '') {
        $statusAsaas = (string)($corpo['payment']['status'] ?? '');
    }
    $status = asaas_status_local($statusAsaas);

    $stmt = $conn->prepare("SELECT id, usuario_id, plano_id, status, tipo FROM pagamentos_pix WHERE asaas_payment_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('s', $payId);
    $stmt->execute();
    $pix = $stmt->get_result()->fetch_assoc();

    if (!$pix) {
        error_log('webhook: cobranca Asaas ' . $payId . ' nao encontrada localmente');
        responder(['recebido' => true]);
    }

    if ($status === 'approved' && $pix['status'] !== 'approved') {
        if (($pix['tipo'] ?? 'plano') === 'cartao_fisico') {
            // Taxa do cartão físico: registra o pedido + notifica o admin (na hora).
            $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'approved' WHERE id = ? AND status <> 'approved'");
            $stmt->bind_param('i', $pix['id']);
            $stmt->execute();
            if ($conn->affected_rows > 0) {
                registrar_pedido_cartao($conn, (int)$pix['usuario_id']);
                notificar_admin_pedido_cartao($conn, (int)$pix['usuario_id']);
            }
        } else {
            aprovar_pagamento_local($conn, $pix);
        }
    } elseif ($status !== 'approved' && $pix['status'] === 'pending' && in_array($status, ['cancelled', 'refunded'], true)) {
        $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $pix['id']);
        $stmt->execute();
    }

    responder(['recebido' => true, 'provedor' => 'asaas', 'payment_id' => $payId, 'status' => $status]);
}

// Evento não reconhecido.
responder(['recebido' => true]);
