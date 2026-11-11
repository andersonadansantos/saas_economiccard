<?php
// ============================================================================
// PLANO ÚNICO MENSAL — ADESÃO + MENSALIDADES + ATIVAÇÃO POR 36 MESES
// ============================================================================
// Regras do modelo:
//  - Existe apenas UM plano ativo ("Mensal"), configurado em Admin > Planos.
//  - Cada contratação gera uma cobrança de ADESÃO seguida de N mensalidades
//    configuráveis (padrão: 2). Com o padrão, o ciclo tem 3 cobranças:
//      1) Adesão: cobrada no momento da contratação e ATIVA o cartão assim que
//         o pagamento é confirmado;
//      2) 1ª mensalidade: fica disponível imediatamente após a ativação;
//      3) 2ª mensalidade: fica disponível 30 dias após o pagamento da 1ª.
//  - As cobranças são únicas e consecutivas. A próxima só é liberada depois que
//    a anterior for efetivamente PAGA.
//  - A adesão ativa o cartão, mas NÃO quita o plano. A quitação ocorre somente
//    após o pagamento da ÚLTIMA mensalidade; nesse momento a validade do cartão
//    é renovada para 36 meses contados da data desse pagamento. Não existe
//    cobrança seguinte nem renovação automática durante a validade.
//  - Os valores contratados (adesão, mensalidade e quantidade) são gravados no
//    contrato do usuário no momento da contratação e não mudam se o admin
//    alterar o plano.
//  - A validade respeita o calendário: ao somar meses, o dia é limitado ao
//    último dia do mês de destino (ex.: 31/01 + 1 mês = 28/02 ou 29/02).
//
// A fonte da verdade financeira são os registros reais de pagamento
// (pagamentos_pix.status = 'approved'). O contrato/cobranças apenas refletem
// essa sequência, de forma idempotente, para impedir cobranças e ativações
// duplicadas.

if (!defined('PLANO_QTD_MENSALIDADES')) define('PLANO_QTD_MENSALIDADES', 2);
if (!defined('PLANO_TOTAL_COBRANCAS')) define('PLANO_TOTAL_COBRANCAS', PLANO_QTD_MENSALIDADES + 1);
if (!defined('PLANO_VALIDADE_MESES')) define('PLANO_VALIDADE_MESES', 36);
if (!defined('PLANO_INTERVALO_MENSALIDADE_DIAS')) define('PLANO_INTERVALO_MENSALIDADE_DIAS', 30);

// Soma dias a uma data Y-m-d (usado no intervalo de 30 dias entre mensalidades).
function plano_add_dias($data, $dias) {
    $data = trim((string)$data);
    if ($data === '' || substr($data, 0, 10) === '0000-00-00') {
        $data = date('Y-m-d');
    }
    $dt = DateTime::createFromFormat('!Y-m-d', substr($data, 0, 10));
    if (!$dt) {
        try { $dt = new DateTime($data); } catch (Exception $e) { $dt = new DateTime(date('Y-m-d')); }
    }
    $dt->modify(($dias >= 0 ? '+' : '') . (int)$dias . ' days');
    return $dt->format('Y-m-d');
}

// Vencimento da PRÓXIMA cobrança:
//  - depois da adesão, a 1ª mensalidade fica disponível imediatamente;
//  - depois de uma mensalidade, a seguinte só é liberada após 30 dias.
function plano_vencimento_apos($tipoAnterior, $dataAnterior) {
    $base = substr((string)$dataAnterior, 0, 10);
    if ($base === '' || $base === '0000-00-00') $base = date('Y-m-d');
    if ((string)$tipoAnterior === 'adesao') return $base;
    return plano_add_dias($base, PLANO_INTERVALO_MENSALIDADE_DIAS);
}

// Soma meses a uma data Y-m-d respeitando o calendário (sem "vazar" para o mês
// seguinte quando o dia não existe no mês de destino).
function plano_add_meses($data, $meses) {
    $data = trim((string)$data);
    if ($data === '' || substr($data, 0, 10) === '0000-00-00') {
        $data = date('Y-m-d');
    }
    $dt = DateTime::createFromFormat('!Y-m-d', substr($data, 0, 10));
    if (!$dt) {
        try { $dt = new DateTime($data); } catch (Exception $e) { $dt = new DateTime(date('Y-m-d')); }
    }
    $dia = (int)$dt->format('d');
    $dt->setDate((int)$dt->format('Y'), (int)$dt->format('m'), 1);
    $meses = (int)$meses;
    $dt->modify(($meses >= 0 ? '+' : '') . $meses . ' months');
    $ultimoDia = (int)$dt->format('t');
    $dt->setDate((int)$dt->format('Y'), (int)$dt->format('m'), min($dia, $ultimoDia));
    return $dt->format('Y-m-d');
}

// Retorna o plano único (preferência pelo nome "Mensal") mesmo se inativo.
function plano_mensal($conn) {
    $r = @$conn->query("SELECT * FROM planos
        ORDER BY (LOWER(TRIM(nome)) = 'mensal') DESC, ativo DESC, ordem ASC, id ASC
        LIMIT 1");
    return ($r && ($x = $r->fetch_assoc())) ? $x : null;
}

// Valores atuais do plano (adesão, mensalidade, quantidade) e total previsto
// do ciclo: adesão + (mensalidade × quantidade de mensalidades).
function plano_valores_atuais($conn) {
    $pl = plano_mensal($conn);
    $adesao = (float)($pl['valor_adesao'] ?? 0);
    $mensal = (float)($pl['valor_mensal'] ?? 0);
    if ($mensal <= 0) $mensal = (float)($pl['valor'] ?? 0);
    $qtd = (int)($pl['qtd_mensalidades'] ?? PLANO_QTD_MENSALIDADES);
    if ($qtd < 1) $qtd = PLANO_QTD_MENSALIDADES;
    if ($pl === null) {
        $pers = @$conn->query("SELECT valor_adesao FROM personalizacao WHERE id = 1")->fetch_assoc();
        if ($pers) $adesao = (float)($pers['valor_adesao'] ?? 0);
    }
    $adesao = round($adesao, 2);
    $mensal = round($mensal, 2);
    return [
        'plano'   => $pl,
        'ativo'   => (int)($pl['ativo'] ?? 0) === 1,
        'adesao'  => $adesao,
        'mensal'  => $mensal,
        'qtd'     => $qtd,
        'total'   => round($adesao + ($mensal * $qtd), 2),
        'total_cobrancas' => ($adesao > 0 ? 1 : 0) + $qtd,
    ];
}

// Rótulo amigável de uma cobrança do contrato.
function plano_rotulo_cobranca($sequencia, $tipo = '') {
    $sequencia = (int)$sequencia;
    if ($tipo === 'adesao') return 'Adesão';
    // Contratos antigos (modelo sem adesão) começam na 1ª mensalidade.
    if ($sequencia === 1) return '1ª mensalidade';
    return max(1, $sequencia - 1) . 'ª mensalidade';
}

function plano_rotulo_status_cobranca($status) {
    switch ((string)$status) {
        case 'pago':       return 'Paga';
        case 'pendente':   return 'Pendente';
        case 'aguardando': return 'Aguardando pagamento';
        case 'atrasada':   return 'Atrasada';
        case 'cancelado':  return 'Cancelada';
        default:           return ucfirst((string)$status);
    }
}

// Status exibido ao usuário. O banco mantém apenas pendente/pago/aguardando;
// "atrasada" é derivado quando a cobrança pendente já passou do vencimento.
function plano_status_visual_cobranca($cob, $hoje = null) {
    $status = is_array($cob) ? (string)($cob['status'] ?? '') : (string)$cob;
    $venc = is_array($cob) ? substr((string)($cob['vencimento'] ?? ''), 0, 10) : '';
    if ($hoje === null) $hoje = date('Y-m-d');
    if ($status === 'pendente' && $venc !== '' && $venc < $hoje) return 'atrasada';
    return $status;
}

// Cobrança já liberada para pagamento? As futuras aguardam o vencimento.
function plano_cobranca_liberada($cob, $hoje = null) {
    if (!$cob || (string)($cob['status'] ?? '') !== 'pendente') return false;
    if ($hoje === null) $hoje = date('Y-m-d');
    $venc = substr((string)($cob['vencimento'] ?? ''), 0, 10);
    return $venc === '' || $venc <= $hoje;
}

// Contrato mais recente do usuário (o vigente tem prioridade).
function plano_contrato_atual($conn, $uid) {
    $uid = (int)$uid;
    if ($uid <= 0) return null;
    $stmt = $conn->prepare("SELECT * FROM planos_contratos WHERE usuario_id = ? ORDER BY (vigente = 1) DESC, id DESC LIMIT 1");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

// Todas as cobranças de um contrato, indexadas pelo número da sequência.
function plano_cobrancas_contrato($conn, $contratoId) {
    $contratoId = (int)$contratoId;
    $out = [];
    if ($contratoId <= 0) return $out;
    $stmt = $conn->prepare("SELECT * FROM planos_cobrancas WHERE contrato_id = ? ORDER BY sequencia ASC");
    $stmt->bind_param('i', $contratoId);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) {
        $out[(int)$row['sequencia']] = $row;
    }
    return $out;
}

// Cria as cobranças de um contrato: adesão (seq 1) + N mensalidades.
// $opts['pagas'] indica quantas cobranças já foram pagas (usado no bootstrap de
// usuários legados). $opts['pagamentos'] é a lista de pagamentos aprovados, na
// ordem, para vincular às cobranças pagas.
// $opts['total_cobrancas'] permite preservar contratos legados com mais
// cobranças do que o ciclo padrão atual (ex.: adesão + 3 mensalidades).
function plano_contrato_criar($conn, $uid, array $opts = []) {
    $uid = (int)$uid;
    $valores = $opts['valores'] ?? plano_valores_atuais($conn);
    $planoId = (int)($valores['plano']['id'] ?? 0);
    $adesao  = round((float)($valores['adesao'] ?? 0), 2);
    $mensal  = round((float)($valores['mensal'] ?? 0), 2);
    $qtd     = max(1, (int)($valores['qtd'] ?? PLANO_QTD_MENSALIDADES));
    $temAdesao = $adesao > 0;
    $totalCobrancas = (int)($opts['total_cobrancas'] ?? (($temAdesao ? 1 : 0) + $qtd));
    $totalCobrancas = max(1, $totalCobrancas);
    $pagas   = max(0, min($totalCobrancas, (int)($opts['pagas'] ?? 0)));
    $pagamentos = is_array($opts['pagamentos'] ?? null) ? $opts['pagamentos'] : [];
    $status  = (string)($opts['status'] ?? ($pagas >= $totalCobrancas ? 'concluido' : 'em_andamento'));
    $ativadoEm = $opts['ativado_em'] ?? null;
    $validadeAte = $opts['validade_ate'] ?? null;
    $validadeMeses = max(1, (int)($opts['validade_meses'] ?? PLANO_VALIDADE_MESES));
    $vigente = ($status === 'em_andamento') ? 1 : null;
    $hoje = date('Y-m-d');

    $stmt = $conn->prepare("INSERT INTO planos_contratos
        (usuario_id, plano_id, valor_adesao, valor_mensal, qtd_mensalidades, validade_meses,
         status, vigente, ativado_em, validade_ate, criado_em, atualizado_em)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param('iiddiisiss', $uid, $planoId, $adesao, $mensal, $qtd, $validadeMeses,
        $status, $vigente, $ativadoEm, $validadeAte);
    $stmt->execute();
    $contratoId = (int)$conn->insert_id;

    for ($seq = 1; $seq <= $totalCobrancas; $seq++) {
        $tipo  = ($temAdesao && $seq === 1) ? 'adesao' : 'mensalidade';
        $valor = ($tipo === 'adesao') ? $adesao : $mensal;
        $pagamentoId = null;
        $pagoEm = null;
        $vencimento = null;

        if ($seq <= $pagas) {
            $pag = $pagamentos[$seq - 1] ?? null;
            // Preserva o valor realmente pago no histórico (usuários legados
            // podem ter pago valores diferentes do plano atual).
            if ($pag && isset($pag['valor'])) $valor = round((float)$pag['valor'], 2);
            $pagoEm = $pag ? (string)($pag['criado_em'] ?? '') : $hoje;
            if ($pagoEm === '' || substr($pagoEm, 0, 10) === '0000-00-00') $pagoEm = $hoje;
            $pagamentoId = $pag ? (int)($pag['id'] ?? 0) : null;
            if ($pagamentoId === 0) $pagamentoId = null;
            $vencimento = substr($pagoEm, 0, 10);
            $statusCob = 'pago';
        } elseif ($seq === $pagas + 1) {
            $statusCob = 'pendente';
            if ($pagas > 0) {
                $pagAnterior = $pagamentos[$pagas - 1] ?? null;
                $base = $pagAnterior['criado_em'] ?? $hoje;
                $tipoAnterior = ($temAdesao && $pagas === 1) ? 'adesao' : 'mensalidade';
                $vencimento = plano_vencimento_apos($tipoAnterior, $base);
            } else {
                $vencimento = $hoje;
            }
        } else {
            $statusCob = 'aguardando';
        }

        $stmtC = $conn->prepare("INSERT INTO planos_cobrancas
            (contrato_id, usuario_id, sequencia, tipo, valor, status, vencimento, pagamento_id, pago_em, criado_em, atualizado_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmtC->bind_param('iiisdssis', $contratoId, $uid, $seq, $tipo, $valor, $statusCob,
            $vencimento, $pagamentoId, $pagoEm);
        $stmtC->execute();
        $cobrancaId = (int)$conn->insert_id;

        // Vincula o pagamento real à cobrança correspondente (histórico legado).
        if ($statusCob === 'pago' && $pagamentoId) {
            $stmtP = $conn->prepare("UPDATE pagamentos_pix SET cobranca_id = ? WHERE id = ?");
            $stmtP->bind_param('ii', $cobrancaId, $pagamentoId);
            $stmtP->execute();
        }
    }

    return $contratoId;
}

// Garante que o usuário tenha um contrato para o ciclo atual.
// - Contrato vigente em andamento: usa.
// - Contrato concluído ainda dentro da validade: não gera novas cobranças.
// - Contrato concluído/vencido ou inexistente: cria o novo ciclo.
// - Usuários legados: aproveita os pagamentos de plano já aprovados para
//   continuar a sequência de mensalidades, sem recriar cobranças já pagas.
function plano_garantir_contrato($conn, $uid, array &$u = null) {
    $uid = (int)$uid;
    if ($u === null) {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc() ?: [];
    }
    $hoje = date('Y-m-d');
    $valores = plano_valores_atuais($conn);
    $qtdPlano = max(1, (int)($valores['qtd'] ?? PLANO_QTD_MENSALIDADES));
    $baseCobrancas = (int)($valores['total_cobrancas'] ?? (($valores['adesao'] > 0 ? 1 : 0) + $qtdPlano));
    $contrato = plano_contrato_atual($conn, $uid);
    $pagas = 0;
    $pagamentos = [];
    $limparCadastro = false;

    if ($contrato) {
        if ($contrato['status'] === 'em_andamento') {
            return $contrato;
        }
        if ($contrato['status'] === 'concluido') {
            $validadeContrato = (string)($contrato['validade_ate'] ?? '');
            $cardAtivo = ((int)($u['cartao_ativo'] ?? 0) === 1);
            $cardValidade = (string)($u['cartao_validade'] ?? '');
            $aindaValido = ($validadeContrato !== '' && $validadeContrato >= $hoje)
                || ($cardAtivo && $cardValidade !== '' && $cardValidade >= $hoje);
            if ($aindaValido) {
                return $contrato; // ciclo concluído e ainda dentro da validade
            }
        }
        // Concluído/vencido ou cancelado: novo ciclo a partir da adesão.
        $limparCadastro = true;
    }

    if (!$contrato) {
        // Bootstrap legado: sequência real dos pagamentos de plano já aprovados.
        $stmt = $conn->prepare("SELECT id, valor, criado_em FROM pagamentos_pix
            WHERE usuario_id = ? AND tipo = 'plano' AND status = 'approved'
            ORDER BY criado_em ASC, id ASC");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $pagamentos[] = $row;
        }
        // Contratos antigos podiam ter mais cobranças (adesão + 3 mensalidades).
        // Preserva a quantidade real já paga para não apagar histórico.
        $totalCobrancasLegado = max($baseCobrancas, count($pagamentos));
        $pagas = min($totalCobrancasLegado, count($pagamentos));
        $qtdPlanoLegado = max(1, $totalCobrancasLegado - ($valores['adesao'] > 0 ? 1 : 0));

        if ($pagas >= $totalCobrancasLegado) {
            $cardAtivo = ((int)($u['cartao_ativo'] ?? 0) === 1);
            $cardValidade = (string)($u['cartao_validade'] ?? '');
            if ($cardAtivo && $cardValidade !== '' && $cardValidade >= $hoje) {
                // Usuário legado com todas as cobranças pagas e cartão válido:
                // registra o contrato concluído apenas para histórico.
                plano_contrato_criar($conn, $uid, [
                    'valores' => array_merge($valores, ['qtd' => $qtdPlanoLegado]),
                    'total_cobrancas' => $totalCobrancasLegado,
                    'pagas' => $pagas,
                    'pagamentos' => $pagamentos,
                    'status' => 'concluido',
                    'ativado_em' => ($pagamentos[0]['criado_em'] ?? $cardValidade),
                    'validade_ate' => $cardValidade,
                ]);
                return plano_contrato_atual($conn, $uid);
            }
            // Já quitou o ciclo em modelo anterior e o cartão venceu:
            // inicia um NOVO ciclo de cobranças.
            $pagas = 0;
            $pagamentos = [];
            $limparCadastro = true;
        }
    }

    if ($limparCadastro) {
        // Novo ciclo: remove a validade do ciclo anterior do cadastro para não
        // exibir datas antigas de ativação/vencimento nas novas cobranças.
        $stmtU = $conn->prepare("UPDATE usuarios SET cartao_ativo = 0, cartao_validade = NULL, adesao_paga = 0 WHERE id = ?");
        $stmtU->bind_param('i', $uid);
        $stmtU->execute();
        if (is_array($u)) {
            $u['cartao_ativo'] = 0;
            $u['cartao_validade'] = null;
            $u['adesao_paga'] = 0;
        }
    }

    // O status do plano controla apenas NOVAS contratações: contratos já em
    // andamento continuam pagáveis (retornam acima).
    if (empty($valores['ativo'])) {
        return null;
    }

    plano_contrato_criar($conn, $uid, ['pagas' => $pagas, 'pagamentos' => $pagamentos, 'valores' => $valores]);
    return plano_contrato_atual($conn, $uid);
}

// Cobrança atual (primeira não paga) do contrato + se já está liberada para
// pagamento (respeita o intervalo mensal entre as cobranças).
function plano_cobranca_disponivel($conn, $contratoId) {
    $cobs = plano_cobrancas_contrato($conn, $contratoId);
    $hoje = date('Y-m-d');
    foreach ($cobs as $seq => $c) {
        if ($c['status'] === 'pago') continue;
        $disponivel = false;
        $motivo = '';
        if ($c['status'] === 'pendente') {
            if (plano_cobranca_liberada($c, $hoje)) {
                $disponivel = true;
            } else {
                $motivo = 'Próxima cobrança disponível a partir de ' . date('d/m/Y', strtotime($c['vencimento'])) . '.';
            }
        } else {
            $motivo = 'Aguardando a confirmação da cobrança anterior.';
        }
        return ['cobranca' => $c, 'disponivel' => $disponivel, 'motivo' => $motivo, 'cobrancas' => $cobs];
    }
    return ['cobranca' => null, 'disponivel' => false, 'motivo' => 'Todas as cobranças já foram pagas.', 'cobrancas' => $cobs];
}

// Resumo completo para o admin e para a área do cliente.
// Não cria contratos/cobranças: é somente leitura.
function plano_resumo_usuario($conn, $uid) {
    $uid = (int)$uid;
    $hoje = date('Y-m-d');
    $u = [];
    $stmt = $conn->prepare("SELECT id, cartao_ativo, cartao_validade, adesao_paga, criado_em FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc() ?: [];

    $contrato = plano_contrato_atual($conn, $uid);
    $valores = plano_valores_atuais($conn);
    $qtdPlano = max(1, (int)($valores['qtd'] ?? PLANO_QTD_MENSALIDADES));
    $baseCobrancas = (int)($valores['total_cobrancas'] ?? (($valores['adesao'] > 0 ? 1 : 0) + $qtdPlano));
    $adesaoPlano = (float)$valores['adesao'];
    $resumo = [
        'origem' => $contrato ? 'contrato' : 'legado',
        'contrato' => $contrato,
        'contrato_id' => $contrato ? (int)$contrato['id'] : 0,
        'status_contrato' => $contrato['status'] ?? 'sem_contrato',
        'valor_adesao' => $contrato ? (float)$contrato['valor_adesao'] : $adesaoPlano,
        'valor_mensal' => $contrato ? (float)$contrato['valor_mensal'] : (float)$valores['mensal'],
        'qtd_mensalidades' => $contrato ? max(1, (int)$contrato['qtd_mensalidades']) : $qtdPlano,
        'total_cobrancas' => $contrato ? 0 : $baseCobrancas,
        'valor_total' => $contrato ? 0.0 : (float)$valores['total'],
        'validade_meses' => $contrato ? max(1, (int)$contrato['validade_meses']) : PLANO_VALIDADE_MESES,
        'cobrancas' => [],
        'pagas' => 0,
        'pendentes' => 0,
        'aguardando' => 0,
        'cobranca_atual' => null,
        'cobranca_disponivel' => false,
        'motivo_disponibilidade' => '',
        'ativado_em' => $contrato ? ($contrato['ativado_em'] ?? null) : null,
        'validade_ate' => $contrato
            ? (($contrato['validade_ate'] ?? null) ?: ($u['cartao_validade'] ?? null))
            : ($u['cartao_validade'] ?? null),
        'cartao_ativo' => (int)($u['cartao_ativo'] ?? 0) === 1,
        'cartao_vigente' => false,
        'quitado' => false,
        'validade_provisoria' => false,
        'dias_restantes' => null,
        'situacao' => 'sem_contrato',
    ];

    if ($contrato) {
        $cobs = plano_cobrancas_contrato($conn, $contrato['id']);
        $resumo['cobrancas'] = [];
        $resumo['total_cobrancas'] = max(1, count($cobs));
        $resumo['valor_total'] = 0.0;
        foreach ($cobs as $seq => $c) {
            $c['status_visual'] = plano_status_visual_cobranca($c, $hoje);
            $c['liberada'] = plano_cobranca_liberada($c, $hoje);
            $resumo['cobrancas'][$seq] = $c;
            $resumo['valor_total'] += (float)$c['valor'];
            if ($c['status'] === 'pago') $resumo['pagas']++;
            elseif ($c['status'] === 'pendente') $resumo['pendentes']++;
            elseif ($c['status'] === 'aguardando') $resumo['aguardando']++;
        }
        $resumo['valor_total'] = round($resumo['valor_total'], 2);
        $disp = plano_cobranca_disponivel($conn, $contrato['id']);
        $resumo['cobranca_atual'] = $disp['cobranca'];
        $resumo['cobranca_disponivel'] = $disp['disponivel'];
        $resumo['motivo_disponibilidade'] = $disp['motivo'];
        if ($contrato['status'] === 'concluido') {
            $resumo['situacao'] = ($resumo['validade_ate'] && $resumo['validade_ate'] >= $hoje) ? 'concluido' : 'expirado';
            $resumo['quitado'] = true;
        } elseif ($contrato['status'] === 'em_andamento') {
            $resumo['quitado'] = ($resumo['pagas'] >= $resumo['total_cobrancas']);
            // O cartão pode estar ativo (adesão paga) mesmo com mensalidades em
            // aberto. "Ativo" e "Quitado" são situações distintas.
            $resumo['situacao'] = $resumo['cartao_ativo'] ? 'ativo' : 'em_andamento';
        } else {
            $resumo['situacao'] = 'cancelado';
        }
    } else {
        // Sem contrato: resume o histórico legado real (pagamentos aprovados).
        $stmt = $conn->prepare("SELECT id, valor, status, criado_em, cobranca_id FROM pagamentos_pix
            WHERE usuario_id = ? AND tipo = 'plano' AND status = 'approved'
            ORDER BY criado_em ASC, id ASC");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $r = $stmt->get_result();
        $pagamentos = [];
        while ($row = $r->fetch_assoc()) $pagamentos[] = $row;
        // Preserva contratos antigos com mais cobranças (adesão + 3 mensalidades).
        $totalCobrancas = max($baseCobrancas, count($pagamentos));
        $temAdesao = $adesaoPlano > 0;
        $pagas = min($totalCobrancas, count($pagamentos));
        $resumo['pagas'] = $pagas;
        $resumo['total_cobrancas'] = $totalCobrancas;
        $resumo['aguardando'] = max(0, $totalCobrancas - $pagas - 1);
        $resumo['pendentes'] = $pagas < $totalCobrancas ? 1 : 0;
        $resumo['qtd_mensalidades'] = max(1, $totalCobrancas - ($temAdesao ? 1 : 0));
        $resumo['valor_total'] = 0.0;
        foreach ($pagamentos as $i => $p) {
            if ($i >= $totalCobrancas) break;
            $seq = $i + 1;
            $tipo = ($temAdesao && $seq === 1) ? 'adesao' : 'mensalidade';
            $resumo['cobrancas'][$seq] = [
                'sequencia' => $seq,
                'tipo' => $tipo,
                'valor' => (float)$p['valor'],
                'status' => 'pago',
                'status_visual' => 'pago',
                'liberada' => false,
                'vencimento' => substr((string)$p['criado_em'], 0, 10),
                'pago_em' => (string)$p['criado_em'],
                'pagamento_id' => (int)$p['id'],
                'cobranca_id' => (int)($p['cobranca_id'] ?? 0),
            ];
            $resumo['valor_total'] += (float)$p['valor'];
        }
        for ($seq = $pagas + 1; $seq <= $totalCobrancas; $seq++) {
            $tipo = ($temAdesao && $seq === 1) ? 'adesao' : 'mensalidade';
            $valor = ($tipo === 'adesao') ? $adesaoPlano : (float)$resumo['valor_mensal'];
            $statusCob = $seq === $pagas + 1 ? 'pendente' : 'aguardando';
            $resumo['cobrancas'][$seq] = [
                'sequencia' => $seq,
                'tipo' => $tipo,
                'valor' => $valor,
                'status' => $statusCob,
                'status_visual' => $statusCob,
                'liberada' => $statusCob === 'pendente',
                'vencimento' => $seq === $pagas + 1 ? $hoje : null,
                'pago_em' => null,
                'pagamento_id' => null,
                'cobranca_id' => 0,
            ];
            $resumo['valor_total'] += $valor;
        }
        $resumo['valor_total'] = round($resumo['valor_total'], 2);
        $resumo['cobranca_atual'] = $resumo['cobrancas'][$pagas + 1] ?? null;
        $resumo['cobranca_disponivel'] = $resumo['cobranca_atual'] !== null;
        $resumo['quitado'] = ($pagas >= $totalCobrancas);
        if ($resumo['cartao_ativo'] && !empty($pagamentos)) {
            $resumo['ativado_em'] = (string)$pagamentos[0]['criado_em'];
        }
        $resumo['situacao'] = $resumo['cartao_ativo'] ? 'legado_ativo' : ($pagas >= $totalCobrancas ? 'expirado' : 'em_andamento');
    }

    $resumo['cartao_vigente'] = !empty($resumo['cartao_ativo'])
        && !empty($resumo['validade_ate'])
        && substr((string)$resumo['validade_ate'], 0, 10) >= $hoje;
    $resumo['validade_provisoria'] = !empty($resumo['cartao_vigente'])
        && ($resumo['status_contrato'] ?? '') === 'em_andamento'
        && empty($contrato['validade_ate']);

    if ($resumo['validade_ate']) {
        $d1 = new DateTime($hoje);
        $d2 = new DateTime(substr((string)$resumo['validade_ate'], 0, 10));
        $dias = (int)$d1->diff($d2)->days;
        $resumo['dias_restantes'] = ($d2 < $d1) ? 0 : $dias;
    }

    return $resumo;
}

// Registra a confirmação de um pagamento de plano e avança o ciclo.
// IDEMPOTENTE: notificações repetidas do gateway não duplicam cobrança,
// comissão nem ativação. Retorna um array com o resultado.
// $dataPagamento é opcional (usado em testes/importações); o fluxo real usa NOW().
function plano_marcar_pagamento_aprovado($conn, $pagamentoId, $dataPagamento = null) {
    require_once __DIR__ . '/afiliado_creditos.php';
    require_once __DIR__ . '/parceiro_creditos.php';

    $pagamentoId = (int)$pagamentoId;
    if ($pagamentoId <= 0) return ['ok' => false, 'motivo' => 'Pagamento inválido'];

    $stmt = $conn->prepare("SELECT * FROM pagamentos_pix WHERE id = ?");
    $stmt->bind_param('i', $pagamentoId);
    $stmt->execute();
    $pag = $stmt->get_result()->fetch_assoc();
    if (!$pag) return ['ok' => false, 'motivo' => 'Pagamento não encontrado'];
    if (($pag['tipo'] ?? 'plano') !== 'plano') return ['ok' => false, 'motivo' => 'Não é cobrança de plano'];

    $uid = (int)$pag['usuario_id'];
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc() ?: [];
    if (!$u) return ['ok' => false, 'motivo' => 'Usuário não encontrado'];

    $data = trim((string)($dataPagamento ?? ''));
    if ($data === '' || substr($data, 0, 10) === '0000-00-00') $data = date('Y-m-d H:i:s');
    $dataDia = substr($data, 0, 10);

    $cob = null;
    $contrato = null;
    if (!empty($pag['cobranca_id'])) {
        $stmt = $conn->prepare("SELECT * FROM planos_cobrancas WHERE id = ?");
        $stmt->bind_param('i', $pag['cobranca_id']);
        $stmt->execute();
        $cob = $stmt->get_result()->fetch_assoc() ?: null;
        if ($cob) {
            $stmt = $conn->prepare("SELECT * FROM planos_contratos WHERE id = ?");
            $stmt->bind_param('i', $cob['contrato_id']);
            $stmt->execute();
            $contrato = $stmt->get_result()->fetch_assoc() ?: null;
        }
    }

    if (!$cob) {
        // Pagamento criado antes do novo modelo (ou sem vínculo): localiza a
        // cobrança correspondente no contrato do usuário.
        if (((int)($u['cartao_ativo'] ?? 0) === 1) && !plano_contrato_atual($conn, $uid)) {
            // Usuário já ativo no modelo antigo: apenas confirma o pagamento e
            // registra os créditos, sem abrir um novo ciclo de cobranças.
            $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'approved' WHERE id = ?");
            $stmt->bind_param('i', $pagamentoId);
            $stmt->execute();
            afiliado_registrar_credito_pagamento($conn, $pagamentoId);
            parceiro_registrar_credito_pagamento($conn, $pagamentoId);
            return ['ok' => true, 'ja_processado' => true, 'motivo' => 'Usuário já ativo (legado)'];
        }
        $contrato = plano_garantir_contrato($conn, $uid, $u);
        if (!$contrato) {
            // Plano inativo e sem contrato em andamento: confirma o pagamento
            // real e registra os créditos, sem abrir novas cobranças.
            $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'approved' WHERE id = ?");
            $stmt->bind_param('i', $pagamentoId);
            $stmt->execute();
            afiliado_registrar_credito_pagamento($conn, $pagamentoId);
            parceiro_registrar_credito_pagamento($conn, $pagamentoId);
            return ['ok' => true, 'ja_processado' => true, 'motivo' => 'Plano inativo, sem novo ciclo'];
        }
        $cobs = plano_cobrancas_contrato($conn, (int)$contrato['id']);
        foreach ($cobs as $c) {
            if ($c['status'] !== 'pago') { $cob = $c; break; }
        }
        if (!$cob) {
            $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'approved' WHERE id = ?");
            $stmt->bind_param('i', $pagamentoId);
            $stmt->execute();
            afiliado_registrar_credito_pagamento($conn, $pagamentoId);
            parceiro_registrar_credito_pagamento($conn, $pagamentoId);
            return ['ok' => true, 'ja_processado' => true, 'motivo' => 'Todas as cobranças já pagas'];
        }
        $stmt = $conn->prepare("UPDATE pagamentos_pix SET cobranca_id = ?, plano_id = ? WHERE id = ?");
        $planoId = (int)($contrato['plano_id'] ?? 0);
        $stmt->bind_param('iii', $cob['id'], $planoId, $pagamentoId);
        $stmt->execute();
        $pag['cobranca_id'] = (int)$cob['id'];
    }

    if (!$contrato) {
        $stmt = $conn->prepare("SELECT * FROM planos_contratos WHERE id = ?");
        $stmt->bind_param('i', $cob['contrato_id']);
        $stmt->execute();
        $contrato = $stmt->get_result()->fetch_assoc() ?: [];
    }

    $sequencia = (int)$cob['sequencia'];
    // Total de cobranças deste contrato (3 no modelo padrão: adesão + 2
    // mensalidades). Contratos antigos podem ter outra quantidade e continuam
    // funcionando com a própria quantidade gravada.
    $cobsContrato = plano_cobrancas_contrato($conn, (int)$cob['contrato_id']);
    $totalCobrancas = max(1, count($cobsContrato));

    if ($cob['status'] === 'pago') {
        // Já processada por outra notificação/aba: não duplica nada.
        $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'approved' WHERE id = ?");
        $stmt->bind_param('i', $pagamentoId);
        $stmt->execute();
        return ['ok' => true, 'ja_processado' => true, 'sequencia' => $sequencia, 'contrato' => $contrato];
    }

    // Marca a cobrança como paga de forma atômica (só um processo consegue).
    $stmt = $conn->prepare("UPDATE planos_cobrancas
        SET status = 'pago', pagamento_id = ?, pago_em = ?, vencimento = COALESCE(vencimento, ?)
        WHERE id = ? AND status <> 'pago'");
    $stmt->bind_param('issi', $pagamentoId, $data, $dataDia, $cob['id']);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        return ['ok' => true, 'ja_processado' => true, 'sequencia' => $sequencia, 'contrato' => $contrato];
    }

    // Confirma o pagamento real.
    $stmt = $conn->prepare("UPDATE pagamentos_pix SET status = 'approved' WHERE id = ?");
    $stmt->bind_param('i', $pagamentoId);
    $stmt->execute();

    // Comissões (idempotentes por pagamento).
    afiliado_registrar_credito_pagamento($conn, $pagamentoId);
    parceiro_registrar_credito_pagamento($conn, $pagamentoId);

    $ativou = false;
    $concluiu = false;
    $validade = null;
    $validadeMeses = max(1, (int)($contrato['validade_meses'] ?? PLANO_VALIDADE_MESES));

    if (($cob['tipo'] ?? '') === 'adesao') {
        // A ADESÃO ativa o cartão imediatamente, com validade provisória de 36
        // meses contados do pagamento. A quitação (última mensalidade) renova
        // essa validade; ativação e quitação são situações diferentes.
        $validade = plano_add_meses($dataDia, $validadeMeses);
        $jaAtivo = ((int)($u['cartao_ativo'] ?? 0) === 1);

        if (empty($contrato['ativado_em'])) {
            $stmt = $conn->prepare("UPDATE planos_contratos SET ativado_em = ? WHERE id = ?");
            $stmt->bind_param('si', $dataDia, $cob['contrato_id']);
            $stmt->execute();
        }

        $stmt = $conn->prepare("UPDATE usuarios SET cartao_ativo = 1, cartao_validade = ?, adesao_paga = 1 WHERE id = ?");
        $stmt->bind_param('si', $validade, $uid);
        $stmt->execute();
        $ativou = !$jaAtivo;

        // Comunicação de ativação usando o template já existente no sistema.
        if ($ativou && !empty($u['email'])) {
            require_once __DIR__ . '/email_sender.php';
            enviar_template_geral('cartao_ativado', ['nome' => $u['nome'], 'email' => $u['email']]);
        }
    }

    if ($sequencia < $totalCobrancas) {
        // Libera a próxima cobrança. Depois da adesão a 1ª mensalidade fica
        // disponível imediatamente; depois de uma mensalidade, a seguinte
        // respeita o intervalo de 30 dias.
        $proxSeq = $sequencia + 1;
        $vencProx = plano_vencimento_apos((string)$cob['tipo'], $dataDia);
        $stmt = $conn->prepare("UPDATE planos_cobrancas
            SET status = 'pendente', vencimento = ?
            WHERE contrato_id = ? AND sequencia = ? AND status <> 'pago'");
        $contratoId = (int)$cob['contrato_id'];
        $stmt->bind_param('sii', $vencProx, $contratoId, $proxSeq);
        $stmt->execute();
    } else {
        // Última mensalidade: quita o plano e renova a validade do cartão por
        // 36 meses contados deste pagamento. O cartão já está ativo desde a
        // adesão, mas a validade definitiva só é gravada agora.
        $validade = plano_add_meses($dataDia, $validadeMeses);

        // Data de ativação original: adesão deste contrato (fallback seguro).
        $ativadoEm = (string)($contrato['ativado_em'] ?? '');
        if ($ativadoEm === '' || substr($ativadoEm, 0, 10) === '0000-00-00') {
            foreach ($cobsContrato as $cc) {
                if (($cc['tipo'] ?? '') === 'adesao' && !empty($cc['pago_em'])) {
                    $ativadoEm = substr((string)$cc['pago_em'], 0, 10);
                    break;
                }
            }
        }
        if ($ativadoEm === '' || substr($ativadoEm, 0, 10) === '0000-00-00') $ativadoEm = $dataDia;

        $stmt = $conn->prepare("UPDATE planos_contratos
            SET status = 'concluido', vigente = NULL, ativado_em = ?, validade_ate = ?
            WHERE id = ?");
        $stmt->bind_param('ssi', $ativadoEm, $validade, $cob['contrato_id']);
        $stmt->execute();

        $jaAtivo = ((int)($u['cartao_ativo'] ?? 0) === 1);
        $stmt = $conn->prepare("UPDATE usuarios SET cartao_ativo = 1, cartao_validade = ?, adesao_paga = 1 WHERE id = ?");
        $stmt->bind_param('si', $validade, $uid);
        $stmt->execute();
        $concluiu = true;
        $ativou = !$jaAtivo;

        if ($ativou && !empty($u['email'])) {
            require_once __DIR__ . '/email_sender.php';
            enviar_template_geral('cartao_ativado', ['nome' => $u['nome'], 'email' => $u['email']]);
        }
    }

    return [
        'ok' => true,
        'ja_processado' => false,
        'sequencia' => $sequencia,
        'tipo' => $cob['tipo'],
        'ativou' => $ativou,
        'concluiu' => $concluiu,
        'validade' => $validade,
        'contrato' => $contrato,
    ];
}

// Reverte uma cobrança paga quando o pagamento é estornado/reembolsado.
// A cobrança volta a ficar pendente (o pagamento original permanece no
// histórico como 'refunded' e as comissões são estornadas pelo webhook/polling).
function plano_registrar_estorno($conn, $pagamentoId) {
    $pagamentoId = (int)$pagamentoId;
    if ($pagamentoId <= 0) return false;

    $stmt = $conn->prepare("SELECT * FROM pagamentos_pix WHERE id = ?");
    $stmt->bind_param('i', $pagamentoId);
    $stmt->execute();
    $pag = $stmt->get_result()->fetch_assoc();
    if (!$pag) return false;

    $cob = null;
    if (!empty($pag['cobranca_id'])) {
        $stmt = $conn->prepare("SELECT * FROM planos_cobrancas WHERE id = ?");
        $stmt->bind_param('i', $pag['cobranca_id']);
        $stmt->execute();
        $cob = $stmt->get_result()->fetch_assoc() ?: null;
    }
    if (!$cob) {
        $stmt = $conn->prepare("SELECT * FROM planos_cobrancas WHERE pagamento_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('i', $pagamentoId);
        $stmt->execute();
        $cob = $stmt->get_result()->fetch_assoc() ?: null;
    }
    if (!$cob || $cob['status'] !== 'pago') return false;

    $contratoId = (int)$cob['contrato_id'];
    $sequencia = (int)$cob['sequencia'];
    $hoje = date('Y-m-d');
    $cobsContrato = plano_cobrancas_contrato($conn, $contratoId);
    $totalCobrancas = max(1, count($cobsContrato));

    // Reabre a mesma cobrança (não cria uma cobrança extra).
    $stmt = $conn->prepare("UPDATE planos_cobrancas
        SET status = 'pendente', pago_em = NULL, vencimento = ?, pagamento_id = NULL
        WHERE id = ?");
    $stmt->bind_param('si', $hoje, $cob['id']);
    $stmt->execute();

    // As cobranças seguintes que ainda não foram pagas voltam a "aguardando",
    // pois dependem do pagamento desta (evita duas cobranças pendentes juntas).
    $stmt = $conn->prepare("UPDATE planos_cobrancas
        SET status = 'aguardando', vencimento = NULL
        WHERE contrato_id = ? AND sequencia > ? AND status <> 'pago'");
    $stmt->bind_param('ii', $contratoId, $sequencia);
    $stmt->execute();

    $uid = (int)$pag['usuario_id'];
    $stmt = $conn->prepare("SELECT * FROM planos_contratos WHERE id = ?");
    $stmt->bind_param('i', $contratoId);
    $stmt->execute();
    $contrato = $stmt->get_result()->fetch_assoc() ?: [];
    $validadeMeses = max(1, (int)($contrato['validade_meses'] ?? PLANO_VALIDADE_MESES));

    if (($cob['tipo'] ?? '') === 'adesao') {
        // Estorno da ADESÃO: desfaz a ativação do cartão e reinicia o contrato.
        $stmt = $conn->prepare("UPDATE usuarios SET adesao_paga = 0, cartao_ativo = 0, cartao_validade = NULL WHERE id = ?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE planos_contratos
            SET status = 'em_andamento', vigente = 1, ativado_em = NULL, validade_ate = NULL
            WHERE id = ?");
        $stmt->bind_param('i', $contratoId);
        $stmt->execute();
    } elseif ($sequencia >= $totalCobrancas) {
        // Estorno da ÚLTIMA mensalidade: o plano deixa de estar quitado, mas o
        // cartão permanece ativo com a validade provisória contada da adesão.
        $ativadoEm = (string)($contrato['ativado_em'] ?? '');
        if ($ativadoEm === '' || substr($ativadoEm, 0, 10) === '0000-00-00') {
            foreach ($cobsContrato as $cc) {
                if (($cc['tipo'] ?? '') === 'adesao' && !empty($cc['pago_em'])) {
                    $ativadoEm = substr((string)$cc['pago_em'], 0, 10);
                    break;
                }
            }
        }
        $validadeProvisoria = null;
        if ($ativadoEm !== '' && substr($ativadoEm, 0, 10) !== '0000-00-00') {
            $validadeProvisoria = plano_add_meses($ativadoEm, $validadeMeses);
        }

        $stmt = $conn->prepare("UPDATE planos_contratos
            SET status = 'em_andamento', vigente = 1, validade_ate = NULL
            WHERE id = ?");
        $stmt->bind_param('i', $contratoId);
        $stmt->execute();

        $cartaoAtivo = ($validadeProvisoria !== null && $validadeProvisoria >= $hoje) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE usuarios SET cartao_ativo = ?, cartao_validade = ? WHERE id = ?");
        $stmt->bind_param('isi', $cartaoAtivo, $validadeProvisoria, $uid);
        $stmt->execute();
    }
    // Estorno de mensalidade INTERMEDIÁRIA: o cartão permanece ativo e o
    // contrato continua em andamento; apenas a cobrança é reaberta acima.

    return true;
}
