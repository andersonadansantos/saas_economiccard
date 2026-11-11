<?php
// ============================================================================
// MIGRAÇÃO ÚNICA — ATIVAÇÃO DO CARTÃO PELA ADESÃO
// ============================================================================
// Regra nova: o pagamento da ADESÃO ativa o cartão imediatamente, com validade
// provisória de 36 meses. A quitação (última mensalidade) renova a validade.
//
// Esta migração corrige contratos EXISTENTES que já tiveram a adesão paga, mas
// ficaram sem a data de ativação (modelo anterior ativava somente na última
// cobrança). Também cria o contrato de usuários legados que pagaram a adesão
// antes da existência das tabelas de contrato, aproveitando o histórico real de
// pagamentos aprovados e ativando o cartão com validade provisória.
// Ela NÃO gera cobranças novas, NÃO altera valores e NÃO mexe em usuários sem
// adesão paga.
//
// Uso:
//   php migracao_ativacao_adesao.php            (somente simulação)
//   php migracao_ativacao_adesao.php --apply    (aplica as alterações)
//   ou acesse pelo navegador com ?apply=1 uma única vez.
//
// É idempotente: contratos já corrigidos (ativado_em preenchido) são ignorados.
require_once __DIR__ . '/app/config.php';

$aplicar = (PHP_SAPI === 'cli')
    ? in_array('--apply', $argv ?? [], true)
    : (isset($_GET['apply']) && (string)$_GET['apply'] === '1');

// Pelo navegador, somente o administrador logado pode executar a migração.
if (PHP_SAPI !== 'cli' && !($_SESSION['admin_logado'] ?? false)) {
    http_response_code(403);
    exit('Acesso restrito ao administrador.');
}

echo "Migracao: ativacao do cartao pela adesao\n";
echo $aplicar ? "MODO: APLICAR ALTERACOES\n" : "MODO: SIMULACAO (use --apply para gravar)\n";
echo str_repeat('-', 64) . "\n";

$sql = "SELECT c.*, u.nome, u.email, u.cartao_ativo, u.cartao_validade, u.adesao_paga
        FROM planos_contratos c
        INNER JOIN usuarios u ON u.id = c.usuario_id
        WHERE c.status IN ('em_andamento','concluido')
          AND c.ativado_em IS NULL
        ORDER BY c.id ASC";
$res = $conn->query($sql);
if (!$res) {
    echo "[erro] consulta de contratos: " . $conn->error . "\n";
    exit(1);
}

$total = 0;
$corrigidos = 0;
$ignorados = 0;

while ($c = $res->fetch_assoc()) {
    $total++;
    $contratoId = (int)$c['id'];

    // Data real do pagamento da adesão deste contrato.
    $stmt = $conn->prepare("SELECT pago_em, vencimento, criado_em
        FROM planos_cobrancas
        WHERE contrato_id = ? AND tipo = 'adesao' AND status = 'pago'
        ORDER BY sequencia ASC, id ASC LIMIT 1");
    $stmt->bind_param('i', $contratoId);
    $stmt->execute();
    $adesao = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$adesao) {
        $ignorados++;
        continue; // adesão ainda não paga: nada a ativar
    }

    $dataAdesao = substr((string)($adesao['pago_em'] ?? ''), 0, 10);
    if ($dataAdesao === '' || $dataAdesao === '0000-00-00') {
        $dataAdesao = substr((string)($adesao['vencimento'] ?? ''), 0, 10);
    }
    if ($dataAdesao === '' || $dataAdesao === '0000-00-00') {
        $dataAdesao = substr((string)($adesao['criado_em'] ?? ''), 0, 10);
    }
    if ($dataAdesao === '' || $dataAdesao === '0000-00-00') {
        echo "[ignorado] contrato {$contratoId}: data da adesao desconhecida\n";
        $ignorados++;
        continue;
    }

    $validadeMeses = max(1, (int)($c['validade_meses'] ?? PLANO_VALIDADE_MESES));
    $validadeProvisoria = plano_add_meses($dataAdesao, $validadeMeses);

    echo "Contrato {$contratoId} | usuario " . (int)$c['usuario_id'] . " | " . $c['nome'] . "\n";
    echo "  adesao paga em {$dataAdesao}, validade provisoria {$validadeProvisoria}\n";

    if (!$aplicar) {
        $corrigidos++;
        continue;
    }

    $conn->begin_transaction();
    try {
        // Grava a data de ativação no contrato (a quitação continua usando a
        // validade_ate própria).
        $stmt = $conn->prepare("UPDATE planos_contratos SET ativado_em = ? WHERE id = ?");
        $stmt->bind_param('si', $dataAdesao, $contratoId);
        $stmt->execute();
        $stmt->close();

        if ($c['status'] === 'em_andamento') {
            // Só ativa quem ainda está em andamento. Contratos concluídos
            // seguem com a validade definitiva já gravada.
            $stmt = $conn->prepare("UPDATE usuarios
                SET cartao_ativo = 1, cartao_validade = ?, adesao_paga = 1
                WHERE id = ?");
            $userId = (int)$c['usuario_id'];
            $stmt->bind_param('si', $validadeProvisoria, $userId);
            $stmt->execute();
            $stmt->close();
            echo "  cartao ativado com validade provisoria\n";
        }

        $conn->commit();
        $corrigidos++;
    } catch (Throwable $e) {
        $conn->rollback();
        echo "  [erro] " . $e->getMessage() . "\n";
    }
}

// ---------------------------------------------------------------------------
// Segunda etapa: usuários legados que já pagaram a adesão, mas ainda não têm
// contrato no novo modelo (base anterior à criação de planos_contratos).
// Cria o contrato a partir do histórico aprovado e ativa o cartão com a
// validade provisória contada da adesão. Nenhuma cobrança nova é gerada.
// ---------------------------------------------------------------------------
$sqlLegado = "SELECT u.id, u.nome, u.email, u.cartao_ativo, u.cartao_validade, u.adesao_paga
    FROM usuarios u
    LEFT JOIN planos_contratos c ON c.usuario_id = u.id
    WHERE u.adesao_paga = 1 AND c.id IS NULL
    ORDER BY u.id ASC";
$resLegado = $conn->query($sqlLegado);
if (!$resLegado) {
    echo "[erro] consulta de usuarios legados: " . $conn->error . "\n";
    exit(1);
}

$legados = 0;
$legadosCorrigidos = 0;
$legadosIgnorados = 0;

while ($u = $resLegado->fetch_assoc()) {
    $legados++;
    $userId = (int)$u['id'];

    // Histórico real: a primeira cobrança de plano aprovada equivale à adesão
    // do modelo anterior. Sem pagamento aprovado não há o que ativar.
    $stmt = $conn->prepare("SELECT id, valor, criado_em FROM pagamentos_pix
        WHERE usuario_id = ? AND tipo = 'plano' AND status = 'approved'
        ORDER BY criado_em ASC, id ASC");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rPag = $stmt->get_result();
    $pagamentos = [];
    while ($p = $rPag->fetch_assoc()) $pagamentos[] = $p;
    $stmt->close();

    if (!$pagamentos) {
        echo "[ignorado] usuario {$userId} ({$u['nome']}): adesao marcada, sem pagamento aprovado\n";
        $legadosIgnorados++;
        continue;
    }

    $dataAdesao = substr((string)($pagamentos[0]['criado_em'] ?? ''), 0, 10);
    if ($dataAdesao === '' || $dataAdesao === '0000-00-00') {
        echo "[ignorado] usuario {$userId}: data da adesao desconhecida\n";
        $legadosIgnorados++;
        continue;
    }
    $validadeProvisoria = plano_add_meses($dataAdesao, PLANO_VALIDADE_MESES);

    echo "Usuario legado {$userId} | {$u['nome']}\n";
    echo "  adesao paga em {$dataAdesao} ({$pagamentos[0]['valor']}), validade provisoria {$validadeProvisoria}\n";

    if (!$aplicar) {
        $legadosCorrigidos++;
        continue;
    }

    $conn->begin_transaction();
    try {
        $contrato = plano_garantir_contrato($conn, $userId, $u);
        if (!$contrato) {
            $conn->rollback();
            echo "  [ignorado] plano indisponivel para novas contratacoes\n";
            $legadosIgnorados++;
            continue;
        }

        // Só ativa se a adesão do contrato realmente estiver paga.
        $stmt = $conn->prepare("SELECT status, pago_em FROM planos_cobrancas
            WHERE contrato_id = ? AND tipo = 'adesao' ORDER BY sequencia ASC, id ASC LIMIT 1");
        $contratoId = (int)$contrato['id'];
        $stmt->bind_param('i', $contratoId);
        $stmt->execute();
        $cobAdesao = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cobAdesao || (string)$cobAdesao['status'] !== 'pago') {
            $conn->rollback();
            echo "  [ignorado] contrato {$contratoId} sem adesao paga (ciclo novo)\n";
            $legadosIgnorados++;
            continue;
        }

        $dataReal = substr((string)($cobAdesao['pago_em'] ?? ''), 0, 10);
        if ($dataReal === '' || $dataReal === '0000-00-00') $dataReal = $dataAdesao;
        $validade = plano_add_meses($dataReal, max(1, (int)($contrato['validade_meses'] ?? PLANO_VALIDADE_MESES)));

        if (empty($contrato['ativado_em'])) {
            $stmt = $conn->prepare("UPDATE planos_contratos SET ativado_em = ? WHERE id = ?");
            $stmt->bind_param('si', $dataReal, $contratoId);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare("UPDATE usuarios
            SET cartao_ativo = 1, cartao_validade = ?, adesao_paga = 1
            WHERE id = ?");
        $stmt->bind_param('si', $validade, $userId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo "  contrato {$contratoId} criado e cartao ativado (validade {$validade})\n";
        $legadosCorrigidos++;
    } catch (Throwable $e) {
        $conn->rollback();
        echo "  [erro] " . $e->getMessage() . "\n";
        $legadosIgnorados++;
    }
}

echo str_repeat('-', 64) . "\n";
echo "Contratos analisados: {$total} | corrigidos: {$corrigidos} | ignorados: {$ignorados}\n";
echo "Usuarios legados: {$legados} | ativados: {$legadosCorrigidos} | ignorados: {$legadosIgnorados}\n";
if (!$aplicar) {
    echo "Nenhuma alteracao foi gravada. Execute com --apply para aplicar.\n";
}
