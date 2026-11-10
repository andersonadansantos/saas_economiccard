<?php
// ====== REMUNERAÇÃO DO PARCEIRO DA EMPRESA: VALOR FIXO POR TRANSAÇÃO PAGA ======
// O parceiro (único, exclusivo da empresa) recebe um valor fixo — configurado
// em api_pagamento.valor_fixo_parceiro (Admin > API Pagamento > aba Split) —
// por CADA pagamento efetivamente confirmado no sistema, independente:
//  - do usuário que pagou (com ou sem afiliado, cadastrado direto, qualquer origem);
//  - do tipo de cobrança (adesão, mensalidade, renovação, taxa do cartão físico);
//  - do método (PIX ou cartão).
//
// O parceiro NÃO é afiliado e NÃO usa a lógica de remuneração dos afiliados:
// não há link de indicação, código ou vínculo com usuários. As duas regras
// convivem: na mesma mensalidade paga o parceiro recebe o valor fixo dele e o
// afiliado (se houver e for elegível) recebe o valor fixo dele — sem qualquer
// interferência entre os modelos.
//
// Regras:
//  - Somente pagamentos com status='approved' geram crédito (pendentes,
//    vencidos, recusados e cancelados não geram).
//  - Nunca há crédito duplicado para a mesma transação: a tabela
//    creditos_parceiro tem UNIQUE KEY (pagamento_id) e o INSERT é IGNORE
//    (notificações repetidas dos gateways são idempotentes).
//  - Reembolso/estorno do pagamento (status='refunded') desfaz o crédito
//    correspondente (status='estornado') para ajuste financeiro correto.
//  - O repasse físico do dinheiro ocorre pelo Split nativo do Asaas no
//    momento da criação da cobrança (asaas_pix.php); este livro-caixa registra
//    a remuneração na confirmação efetiva (webhook/polling/cartão).

// Valor fixo do parceiro (R$ por transação confirmada). Zero desativa a
// remuneração (nenhum crédito é gerado).
function parceiro_valor_fixo($conn) {
    $r = @$conn->query("SELECT valor_fixo_parceiro FROM api_pagamento WHERE id = 1");
    $row = ($r && ($x = $r->fetch_assoc())) ? $x : null;
    return round((float)($row['valor_fixo_parceiro'] ?? 0), 2);
}

// Registra, de forma idempotente, o crédito do parceiro referente a um
// pagamento confirmado. Deve ser chamado sempre que um pagamento transiciona
// para 'approved' (webhook do Asaas, verificação por polling, aprovação
// imediata de cartão e taxa do cartão físico). Vale para TODO pagamento,
// sem distinção de tipo, método, usuário ou origem. Retornos:
//   true  -> crédito criado agora
//   false -> não se aplica (pagamento inexistente, não confirmado ou valor
//            fixo zerado) ou já existia (sem duplicar)
function parceiro_registrar_credito_pagamento($conn, $pagamentoId) {
    $pagamentoId = (int)$pagamentoId;
    if ($pagamentoId <= 0) return false;

    // Somente transação efetivamente confirmada gera crédito.
    $stmt = $conn->prepare("SELECT id, usuario_id, status FROM pagamentos_pix WHERE id = ?");
    $stmt->bind_param('i', $pagamentoId);
    $stmt->execute();
    $pag = $stmt->get_result()->fetch_assoc();
    if (!$pag || ($pag['status'] ?? '') !== 'approved') {
        return false;
    }

    // Valor fixo do parceiro (o mesmo para qualquer pagamento).
    $valor = parceiro_valor_fixo($conn);
    if ($valor <= 0) return false;

    // Idempotente: UNIQUE KEY (pagamento_id) + INSERT IGNORE impedem duplicar.
    $usuarioId = (int)$pag['usuario_id'];
    $ins = $conn->prepare("INSERT IGNORE INTO creditos_parceiro (pagamento_id, usuario_id, valor) VALUES (?, ?, ?)");
    $ins->bind_param('iid', $pagamentoId, $usuarioId, $valor);
    $ins->execute();
    return $ins->affected_rows > 0;
}

// Estorna o crédito do parceiro de uma transação reembolsada (ajuste
// financeiro em reembolsos/estornos). Idempotente: só altera créditos ativos.
function parceiro_estornar_credito_pagamento($conn, $pagamentoId) {
    $pagamentoId = (int)$pagamentoId;
    if ($pagamentoId <= 0) return false;
    $stmt = $conn->prepare("UPDATE creditos_parceiro SET status = 'estornado', estornado_em = NOW() WHERE pagamento_id = ? AND status = 'ativo'");
    $stmt->bind_param('i', $pagamentoId);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

// Totais do parceiro para o painel administrativo:
//   saldo       -> remuneração ativa (R$)
//   estornado   -> valor estornado por reembolso (R$)
//   transacoes  -> quantidade de transações pagas que geraram crédito
function parceiro_totais_creditos($conn) {
    $r = @$conn->query("SELECT
            COALESCE(SUM(CASE WHEN status = 'ativo' THEN valor ELSE 0 END), 0) AS saldo,
            COALESCE(SUM(CASE WHEN status = 'estornado' THEN valor ELSE 0 END), 0) AS estornado,
            COUNT(CASE WHEN status = 'ativo' THEN 1 END) AS transacoes
        FROM creditos_parceiro");
    $row = ($r && ($x = $r->fetch_assoc())) ? $x : null;
    return [
        'saldo'      => (float)($row['saldo'] ?? 0),
        'estornado'  => (float)($row['estornado'] ?? 0),
        'transacoes' => (int)($row['transacoes'] ?? 0),
    ];
}
