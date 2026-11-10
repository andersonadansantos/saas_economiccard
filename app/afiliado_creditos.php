<?php
// ====== REMUNERAÇÃO DE AFILIADOS (VENDEDORES): VALOR FIXO POR PARCELA PAGA ======
// Modelo: o afiliado que indicou o usuário (cadastro pelo link exclusivo dele)
// recebe comissão pelas parcelas efetivamente pagas daquele usuário:
//   1ª parcela (adesão) ............ valor de adesão   (padrão R$ 20,00)
//   2ª, 3ª e 4ª parcelas (mensalidade) valor de parcela (padrão R$ 5,00 cada)
//   5ª parcela em diante .......... sem comissão
// Total: até R$ 35,00 por usuário indicado, conforme as 4 parcelas sejam pagas.
//
// Configuração (Admin > Afiliados > Remuneração):
//  - afiliado_config (id=1): valores PADRÃO GLOBAIS + chave geral
//    remuneracao_ativa (ativar/desativar a remuneração automática).
//  - afiliados.valor_adesao / valor_parcela2 / valor_parcela3 / valor_parcela4:
//    valores INDIVIDUALIZADOS por vendedor; NULL = usar o padrão global.
//    (A coluna valor_fixo_mensalidade é legada, preservada por histórico.)
//
// Regras:
//  - Somente pagamentos tipo='plano' com status='approved' geram crédito
//    (parcelas pendentes, vencidas, recusadas ou canceladas não geram).
//  - A parcela é identificada pela posição da COBRANÇA na sequência real das
//    cobranças de plano pagas do usuário (ordem de criação: criado_em, id) —
//    não por contadores ou flags; cobranças não pagas não avançam a sequência.
//  - Nunca há crédito duplicado para o mesmo pagamento: creditos_afiliado tem
//    UNIQUE KEY (pagamento_id) e o INSERT é IGNORE (notificações repetidas do
//    gateway são idempotentes).
//  - Reembolso/estorno do pagamento (status='refunded') desfaz o crédito
//    correspondente (status='estornado' + motivo + data), sem apagar o
//    histórico original — um mesmo pagamento nunca gera duas comissões.
//  - O admin pode registrar valores JÁ PAGOS ao vendedor (status='pago' +
//    pago_em) e estornar manualmente um crédito informando o motivo.
//  - O saldo disponível do afiliado é a soma dos créditos ativos (ainda não
//    pagos ao vendedor); o total acumulado soma ativos + já pagos.

// Configuração global de remuneração dos afiliados (afiliado_config id=1).
// Sempre retorna o array completo — se a linha ainda não existir, usa os
// padrões do novo modelo (20 / 5 / 5 / 5, remuneração ativa).
function afiliado_config_global($conn) {
    $r = @$conn->query("SELECT valor_adesao, valor_parcela2, valor_parcela3, valor_parcela4, remuneracao_ativa FROM afiliado_config WHERE id = 1");
    $row = ($r && ($x = $r->fetch_assoc())) ? $x : null;
    if (!$row) {
        return [
            'valor_adesao'   => 20.00,
            'valor_parcela2' => 5.00,
            'valor_parcela3' => 5.00,
            'valor_parcela4' => 5.00,
            'remuneracao_ativa' => 1,
        ];
    }
    return [
        'valor_adesao'   => round((float)$row['valor_adesao'], 2),
        'valor_parcela2' => round((float)$row['valor_parcela2'], 2),
        'valor_parcela3' => round((float)$row['valor_parcela3'], 2),
        'valor_parcela4' => round((float)$row['valor_parcela4'], 2),
        'remuneracao_ativa' => ((int)$row['remuneracao_ativa'] === 1) ? 1 : 0,
    ];
}

// Valor da comissão de uma parcela para um dado afiliado: usa o valor
// INDIVIDUAL do afiliado quando definido e o PADRÃO GLOBAL quando NULL.
// Retorna 0 quando a parcela estiver fora das 4 remuneradas (5ª em diante)
// ou quando o valor configurado for zerado.
function afiliado_valor_parcela(array $afiliado, $parcela, array $cfgGlobal) {
    $parcela = (int)$parcela;
    if ($parcela < 1 || $parcela > 4) return 0.0;
    $campo = $parcela === 1 ? 'valor_adesao' : ('valor_parcela' . $parcela);
    $override = array_key_exists($campo, $afiliado) ? $afiliado[$campo] : null;
    if ($override !== null && $override !== '') {
        return round((float)$override, 2);
    }
    return round((float)(isset($cfgGlobal[$campo]) ? $cfgGlobal[$campo] : 0), 2);
}

// Rótulo descritivo da parcela de um crédito (novo modelo por parcela e
// também os créditos antigos do modelo anterior, sem nº de parcela).
function afiliado_rotulo_parcela($parcela, $tipoCobranca = '') {
    $parcela = (int)$parcela;
    if ($parcela >= 1 && $parcela <= 4) {
        return $parcela === 1 ? 'Adesão (1ª parcela)' : ($parcela . 'ª parcela — mensalidade');
    }
    if ($tipoCobranca === 'adesao') return 'Adesão (1º pagamento)';
    if ($tipoCobranca === '1a_mensalidade') return '1ª mensalidade';
    if ($tipoCobranca === 'recorrencia') return 'Recorrência';
    return 'Mensalidade';
}

// Número da parcela de uma cobrança de plano paga: posição dela na sequência
// real das cobranças do usuário (ordem de criação: criado_em, id), contando
// as cobranças efetivamente PAGAS (status='approved'). A própria cobrança,
// já aprovada, entra na contagem. Cobranças pendentes, vencidas, canceladas
// ou recusadas não avançam a sequência — uma parcela só existe quando paga.
function afiliado_parcela_da_cobranca($conn, array $pag) {
    $uid = (int)$pag['usuario_id'];
    $criadoEm = (string)$pag['criado_em'];
    $id = (int)$pag['id'];
    $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM pagamentos_pix
        WHERE usuario_id = ? AND tipo = 'plano' AND status = 'approved'
          AND (criado_em < ? OR (criado_em = ? AND id <= ?))");
    $stmt->bind_param('issi', $uid, $criadoEm, $criadoEm, $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return (int)(($r && isset($r['n'])) ? $r['n'] : 0);
}

// Registra, de forma idempotente, o crédito do afiliado referente a uma
// parcela paga. Deve ser chamado sempre que um pagamento transiciona para
// 'approved' (webhook do Asaas, verificação por polling e aprovação
// imediata de cartão). Retornos:
//   true  -> crédito criado agora
//   false -> não se aplica (não é plano, não está pago, remuneração
//            desativada, usuário sem afiliado, afiliado inativo, 5ª parcela
//            em diante, valor zerado) ou já existia (sem duplicar)
function afiliado_registrar_credito_pagamento($conn, $pagamentoId) {
    $pagamentoId = (int)$pagamentoId;
    if ($pagamentoId <= 0) return false;

    // Somente mensalidade (pagamento de plano) efetivamente paga gera crédito.
    $stmt = $conn->prepare("SELECT id, usuario_id, status, tipo, criado_em FROM pagamentos_pix WHERE id = ?");
    $stmt->bind_param('i', $pagamentoId);
    $stmt->execute();
    $pag = $stmt->get_result()->fetch_assoc();
    if (!$pag || ($pag['tipo'] ?? 'plano') !== 'plano' || ($pag['status'] ?? '') !== 'approved') {
        return false;
    }

    // Remuneração automática desligada no painel administrativo.
    $cfg = afiliado_config_global($conn);
    if ((int)$cfg['remuneracao_ativa'] !== 1) return false;

    // Afiliado ativo que indicou o usuário (vínculo permanente registrado no
    // cadastro pelo link exclusivo: usuarios.afiliado_token -> afiliados.token).
    $uid = (int)$pag['usuario_id'];
    $stmt = $conn->prepare("SELECT a.id AS afiliado_id, a.valor_adesao, a.valor_parcela2, a.valor_parcela3, a.valor_parcela4
                            FROM usuarios u
                            INNER JOIN afiliados a ON a.token = u.afiliado_token
                            WHERE u.id = ? AND a.ativo = 1 LIMIT 1");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $af = $stmt->get_result()->fetch_assoc();
    if (!$af) return false;

    // Parcela pela sequência real das cobranças pagas do usuário.
    $parcela = afiliado_parcela_da_cobranca($conn, $pag);

    // Valor da comissão desta parcela (individual do afiliado ou padrão global).
    $valor = afiliado_valor_parcela($af, $parcela, $cfg);

    // 5ª parcela em diante ou valor zerado: sem comissão.
    if ($parcela < 1 || $parcela > 4 || $valor <= 0) return false;

    // Idempotente: UNIQUE KEY (pagamento_id) + INSERT IGNORE impedem duplicar.
    $tipoCobranca = 'parcela_' . $parcela;
    $ins = $conn->prepare("INSERT IGNORE INTO creditos_afiliado (afiliado_id, usuario_id, pagamento_id, valor, tipo_cobranca, parcela) VALUES (?, ?, ?, ?, ?, ?)");
    $ins->bind_param('iiidsi', $af['afiliado_id'], $uid, $pagamentoId, $valor, $tipoCobranca, $parcela);
    $ins->execute();
    return $ins->affected_rows > 0;
}

// Estorna o crédito de uma parcela reembolsada (ajuste financeiro correto,
// sem apagar o histórico original). Registra o MOTIVO e a DATA do ajuste.
// Idempotente: só altera créditos ativos ou já pagos ao vendedor.
function afiliado_estornar_credito_pagamento($conn, $pagamentoId, $motivo = '') {
    $pagamentoId = (int)$pagamentoId;
    if ($pagamentoId <= 0) return false;
    $motivo = trim((string)$motivo);
    if ($motivo === '') $motivo = 'Reembolso/estorno da parcela confirmado pelo gateway';
    $stmt = $conn->prepare("UPDATE creditos_afiliado SET status = 'estornado', estornado_em = NOW(), motivo_estorno = ? WHERE pagamento_id = ? AND status IN ('ativo','pago')");
    $stmt->bind_param('si', $motivo, $pagamentoId);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

// Marca TODOS os créditos ativos do afiliado como PAGOS ao vendedor —
// registra os "valores já pagos" no extrato dele. Idempotente: só altera
// créditos com status='ativo'. Retorna a quantidade de créditos marcados.
function afiliado_marcar_creditos_pagos($conn, $afiliadoId) {
    $afiliadoId = (int)$afiliadoId;
    if ($afiliadoId <= 0) return 0;
    $stmt = $conn->prepare("UPDATE creditos_afiliado SET status = 'pago', pago_em = NOW() WHERE afiliado_id = ? AND status = 'ativo'");
    $stmt->bind_param('i', $afiliadoId);
    $stmt->execute();
    return (int)$stmt->affected_rows;
}

// Estorno MANUAL de um crédito individual, feito pelo administrador com
// motivo obrigatório. Registra motivo e data; não apaga o histórico.
function afiliado_estornar_credito_manual($conn, $creditoId, $motivo) {
    $creditoId = (int)$creditoId;
    $motivo = trim((string)$motivo);
    if ($creditoId <= 0 || $motivo === '') return false;
    $stmt = $conn->prepare("UPDATE creditos_afiliado SET status = 'estornado', estornado_em = NOW(), motivo_estorno = ? WHERE id = ? AND status IN ('ativo','pago')");
    $stmt->bind_param('si', $motivo, $creditoId);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

// Saldo disponível do afiliado: soma dos créditos ativos (parcelas pagas
// menos estornos, ainda não pagas ao vendedor).
function afiliado_saldo_disponivel($conn, $afiliadoId) {
    $afiliadoId = (int)$afiliadoId;
    $r = $conn->query("SELECT COALESCE(SUM(valor), 0) AS saldo FROM creditos_afiliado WHERE afiliado_id = $afiliadoId AND status = 'ativo'");
    return (float)(($r && ($row = $r->fetch_assoc())) ? $row['saldo'] : 0);
}

// Totais do afiliado para painéis e relatórios:
//   saldo        -> créditos ativos ainda não pagos ao vendedor (R$)
//   pago         -> valores já pagos ao vendedor (R$)
//   estornado    -> valor estornado por reembolso/ajuste manual (R$)
//   total_gerado -> comissões geradas líquidas (ativo + pago) (R$)
//   adesoes      -> adesões (1ª parcela) pagas que geraram comissão
//   parcelas     -> parcelas seguintes (2ª/3ª/4ª) pagas que geraram comissão
//   creditos     -> quantidade de créditos válidos (ativo + pago)
// Créditos antigos do modelo anterior (parcela=0) entram pela descrição
// antiga: 'adesao' conta como adesão; '1a_mensalidade'/'recorrencia' como
// parcelas seguintes.
function afiliado_totais_creditos($conn, $afiliadoId) {
    $afiliadoId = (int)$afiliadoId;
    $r = $conn->query("SELECT
            COALESCE(SUM(CASE WHEN status = 'ativo' THEN valor ELSE 0 END), 0) AS saldo,
            COALESCE(SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END), 0) AS pago,
            COALESCE(SUM(CASE WHEN status = 'estornado' THEN valor ELSE 0 END), 0) AS estornado,
            COALESCE(SUM(CASE WHEN status IN ('ativo','pago') THEN valor ELSE 0 END), 0) AS total_gerado,
            COUNT(CASE WHEN status IN ('ativo','pago') AND (parcela = 1 OR (parcela = 0 AND tipo_cobranca = 'adesao')) THEN 1 END) AS adesoes,
            COUNT(CASE WHEN status IN ('ativo','pago') AND ((parcela BETWEEN 2 AND 4) OR (parcela = 0 AND tipo_cobranca IN ('1a_mensalidade','recorrencia'))) THEN 1 END) AS parcelas,
            COUNT(CASE WHEN status IN ('ativo','pago') THEN 1 END) AS creditos
        FROM creditos_afiliado WHERE afiliado_id = $afiliadoId");
    $row = ($r && ($x = $r->fetch_assoc())) ? $x : null;
    return [
        'saldo'        => (float)($row['saldo'] ?? 0),
        'pago'         => (float)($row['pago'] ?? 0),
        'estornado'    => (float)($row['estornado'] ?? 0),
        'total_gerado' => (float)($row['total_gerado'] ?? 0),
        'adesoes'      => (int)($row['adesoes'] ?? 0),
        'parcelas'     => (int)($row['parcelas'] ?? 0),
        'creditos'     => (int)($row['creditos'] ?? 0),
    ];
}

// Registra créditos RETROATIVOS: as parcelas (pagamentos tipo='plano' com
// status='approved') já pagas pelo usuário indicado geram crédito para o
// afiliado dele, conforme o modelo por parcela (1ª=valor de adesão,
// 2ª/3ª/4ª=valor de parcela, nada além da 4ª). Usado quando o admin vincula
// manualmente um usuário já cadastrado a um afiliado (Admin > Afiliados >
// Usuários indicados > Vincular usuário), pois o usuário era indicado
// daquele afiliado antes de o vínculo existir no sistema.
//  - Idempotente: UNIQUE KEY (pagamento_id) + INSERT IGNORE nunca duplicam.
//  - A parcela de cada pagamento é a posição dele na sequência real das
//    cobranças pagas do usuário (igual ao crédito em tempo real).
//  - Remuneração desativada ou afiliado inativo não gera crédito.
// Retorna quantos créditos foram criados agora.
function afiliado_registrar_creditos_retroativos($conn, $usuarioId) {
    $usuarioId = (int)$usuarioId;
    if ($usuarioId <= 0) return 0;

    $cfg = afiliado_config_global($conn);
    if ((int)$cfg['remuneracao_ativa'] !== 1) return 0;

    // Afiliado ativo que indicou o usuário + valores individuais dele.
    $stmt = $conn->prepare("SELECT a.id AS afiliado_id, a.valor_adesao, a.valor_parcela2, a.valor_parcela3, a.valor_parcela4
                            FROM usuarios u
                            INNER JOIN afiliados a ON a.token = u.afiliado_token
                            WHERE u.id = ? AND a.ativo = 1 LIMIT 1");
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $af = $stmt->get_result()->fetch_assoc();
    if (!$af) return 0;

    // Parcelas efetivamente pagas (aprovadas), em ordem cronológica de
    // criação — a posição de cada uma na sequência define a parcela.
    $stmt = $conn->prepare("SELECT id FROM pagamentos_pix
                            WHERE usuario_id = ? AND tipo = 'plano' AND status = 'approved'
                            ORDER BY criado_em ASC, id ASC");
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $res = $stmt->get_result();

    $criados = 0;
    $parcela = 0;
    while ($pag = $res->fetch_assoc()) {
        $parcela++;
        if ($parcela > 4) break; // a partir da 5ª parcela não há comissão
        $valor = afiliado_valor_parcela($af, $parcela, $cfg);
        if ($valor <= 0) continue;
        $pagamentoIdAtual = (int)$pag['id'];
        $tipoCobranca = 'parcela_' . $parcela;
        $ins = $conn->prepare("INSERT IGNORE INTO creditos_afiliado (afiliado_id, usuario_id, pagamento_id, valor, tipo_cobranca, parcela) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->bind_param('iiidsi', $af['afiliado_id'], $usuarioId, $pagamentoIdAtual, $valor, $tipoCobranca, $parcela);
        $ins->execute();
        if ($ins->affected_rows > 0) { $criados++; }
    }
    return $criados;
}
