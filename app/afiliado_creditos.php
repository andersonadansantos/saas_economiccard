<?php
// ====== REMUNERAÇÃO DE AFILIADOS: VALOR FIXO POR MENSALIDADE PAGA ======
// Cada mensalidade (pagamento de plano) efetivamente PAGA por um usuário
// indicado gera um crédito de valor fixo para o afiliado que o indicou.
// O valor é configurado individualmente por afiliado no painel administrativo
// (afiliados.valor_fixo_mensalidade) e NÃO depende do preço da mensalidade.
//
// Regras:
//  - Somente pagamentos tipo='plano' com status='approved' geram crédito
//    (mensalidades pendentes, vencidas, canceladas ou rejeitadas não geram).
//  - Nunca há crédito duplicado para a mesma mensalidade: a tabela
//    creditos_afiliado tem UNIQUE KEY (pagamento_id) e o INSERT é IGNORE.
//  - Reembolso/estorno do pagamento (status='refunded') desfaz o crédito
//    correspondente (status='estornado') para evitar remuneração indevida.
//  - O saldo disponível do afiliado é a soma dos créditos ativos.

// Registra, de forma idempotente, o crédito do afiliado referente a um
// pagamento de plano. Deve ser chamado sempre que um pagamento transiciona
// para 'approved' (webhook do Asaas, verificação por polling e aprovação
// imediata de cartão). Retornos:
//   true  -> crédito criado agora
//   false -> não se aplica (não é plano, não está pago, usuário sem afiliado,
//            afiliado inativo, valor fixo zerado) ou já existia (sem duplicar)
function afiliado_registrar_credito_pagamento($conn, $pagamentoId) {
    $pagamentoId = (int)$pagamentoId;
    if ($pagamentoId <= 0) return false;

    // Somente mensalidade (pagamento de plano) efetivamente paga gera crédito.
    $stmt = $conn->prepare("SELECT id, usuario_id, status, tipo FROM pagamentos_pix WHERE id = ?");
    $stmt->bind_param('i', $pagamentoId);
    $stmt->execute();
    $pag = $stmt->get_result()->fetch_assoc();
    if (!$pag || ($pag['tipo'] ?? '') !== 'plano' || ($pag['status'] ?? '') !== 'approved') {
        return false;
    }

    // Afiliado que indicou o usuário (usuarios.afiliado_token -> afiliados.token).
    $uid = (int)$pag['usuario_id'];
    $stmt = $conn->prepare("SELECT a.id AS afiliado_id, a.valor_fixo_mensalidade, a.ativo
                            FROM usuarios u
                            INNER JOIN afiliados a ON a.token = u.afiliado_token
                            WHERE u.id = ? LIMIT 1");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $af = $stmt->get_result()->fetch_assoc();
    if (!$af || !(int)$af['ativo']) return false;

    // Valor fixo daquele afiliado (independe do preço da mensalidade paga).
    $valor = round((float)$af['valor_fixo_mensalidade'], 2);
    if ($valor <= 0) return false;

    // Contexto do crédito: qual mensalidade foi paga (1º pagamento/adesão,
    // 1ª mensalidade ou recorrência). O pagamento já está aprovado, então
    // a contagem abaixo o inclui.
    $r = $conn->query("SELECT COUNT(*) AS n FROM pagamentos_pix WHERE usuario_id = $uid AND tipo = 'plano' AND status = 'approved'");
    $n = (int)(($r && ($row = $r->fetch_assoc())) ? $row['n'] : 0);
    $tipoCobranca = $n <= 1 ? 'adesao' : ($n === 2 ? '1a_mensalidade' : 'recorrencia');

    // Idempotente: UNIQUE KEY (pagamento_id) + INSERT IGNORE impedem duplicar.
    $ins = $conn->prepare("INSERT IGNORE INTO creditos_afiliado (afiliado_id, usuario_id, pagamento_id, valor, tipo_cobranca) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param('iiids', $af['afiliado_id'], $uid, $pagamentoId, $valor, $tipoCobranca);
    $ins->execute();
    return $ins->affected_rows > 0;
}

// Estorna o crédito de uma mensalidade reembolsada (evita remuneração indevida).
// Idempotente: só altera créditos com status='ativo'.
function afiliado_estornar_credito_pagamento($conn, $pagamentoId) {
    $pagamentoId = (int)$pagamentoId;
    if ($pagamentoId <= 0) return false;
    $stmt = $conn->prepare("UPDATE creditos_afiliado SET status = 'estornado', estornado_em = NOW() WHERE pagamento_id = ? AND status = 'ativo'");
    $stmt->bind_param('i', $pagamentoId);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

// Saldo disponível do afiliado: soma dos créditos ativos (mensalidades pagas
// menos estornos).
function afiliado_saldo_disponivel($conn, $afiliadoId) {
    $afiliadoId = (int)$afiliadoId;
    $r = $conn->query("SELECT COALESCE(SUM(valor), 0) AS saldo FROM creditos_afiliado WHERE afiliado_id = $afiliadoId AND status = 'ativo'");
    return (float)(($r && ($row = $r->fetch_assoc())) ? $row['saldo'] : 0);
}

// Totais do afiliado para painéis e relatórios:
//   saldo       -> ganhos ativos (R$)
//   estornado   -> valor estornado por reembolso (R$)
//   mensalidades -> quantidade de mensalidades pagas que geraram crédito
function afiliado_totais_creditos($conn, $afiliadoId) {
    $afiliadoId = (int)$afiliadoId;
    $r = $conn->query("SELECT
            COALESCE(SUM(CASE WHEN status = 'ativo' THEN valor ELSE 0 END), 0) AS saldo,
            COALESCE(SUM(CASE WHEN status = 'estornado' THEN valor ELSE 0 END), 0) AS estornado,
            COUNT(CASE WHEN status = 'ativo' THEN 1 END) AS mensalidades
        FROM creditos_afiliado WHERE afiliado_id = $afiliadoId");
    $row = ($r && ($x = $r->fetch_assoc())) ? $x : null;
    return [
        'saldo'        => (float)($row['saldo'] ?? 0),
        'estornado'    => (float)($row['estornado'] ?? 0),
        'mensalidades' => (int)($row['mensalidades'] ?? 0),
    ];
}
