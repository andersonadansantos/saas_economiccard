<?php
header('Content-Type: application/json');
http_response_code(200);

require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/asaas_pix.php';
require_once __DIR__ . '/app/cartao_fisico_pix.php';
require_once __DIR__ . '/app/afiliado_creditos.php';
require_once __DIR__ . '/app/parceiro_creditos.php';

function responder($dados) {
    echo json_encode($dados);
    exit;
}

// Confirma o pagamento do plano pelo motor central: avança o contrato,
// libera a próxima cobrança e ativa o cartão apenas após a última mensalidade.
// Toda a operação é idempotente (notificações repetidas não duplicam nada).
function aprovar_pagamento_local($conn, $pix) {
    return plano_marcar_pagamento_aprovado($conn, (int)$pix['id']);
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

    if ($status === 'approved') {
        if (($pix['tipo'] ?? 'plano') === 'cartao_fisico') {
            // Taxa do cartão físico: registra o pedido + notifica o admin (na hora).
            $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'approved' WHERE id = ? AND status <> 'approved'");
            $stmt->bind_param('i', $pix['id']);
            $stmt->execute();
            if ($conn->affected_rows > 0) {
                registrar_pedido_cartao($conn, (int)$pix['usuario_id']);
                notificar_admin_pedido_cartao($conn, (int)$pix['usuario_id']);
                // Transação paga (taxa do cartão físico): o parceiro recebe seu
                // valor fixo por toda transação confirmada, mesmo não sendo mensalidade.
                parceiro_registrar_credito_pagamento($conn, (int)$pix['id']);
            }
        } else {
            aprovar_pagamento_local($conn, $pix);
        }
    } elseif ($status === 'refunded' && in_array($pix['status'], ['pending', 'approved'], true)) {
        // Reembolso/estorno: marca o pagamento como reembolsado e desfaz o
        // crédito do afiliado e do parceiro (ajuste financeiro correto). Se o
        // pagamento era de uma cobrança do plano, reabre a MESMA cobrança
        // (não cria uma cobrança extra) e desfaz a ativação quando for a última.
        $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'refunded' WHERE id = ?");
        $stmt->bind_param('i', $pix['id']);
        $stmt->execute();
        if (($pix['tipo'] ?? 'plano') === 'plano') {
            plano_registrar_estorno($conn, (int)$pix['id']);
        }
        afiliado_estornar_credito_pagamento($conn, (int)$pix['id']);
        parceiro_estornar_credito_pagamento($conn, (int)$pix['id']);
    } elseif ($status === 'cancelled' && $pix['status'] === 'pending') {
        $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'cancelled' WHERE id = ?");
        $stmt->bind_param('i', $pix['id']);
        $stmt->execute();
    }

    responder(['recebido' => true, 'provedor' => 'asaas', 'payment_id' => $payId, 'status' => $status]);
}

// Evento não reconhecido.
responder(['recebido' => true]);
