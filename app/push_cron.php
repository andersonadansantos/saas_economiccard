<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/fcm.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $tokenReq = (string)($_GET['token'] ?? '');
    $cfg = $conn->query("SELECT cron_token FROM push_config WHERE id = 1")->fetch_assoc();
    $tokenOk = $cfg && !empty($cfg['cron_token']) && hash_equals((string)$cfg['cron_token'], $tokenReq);
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $local = in_array($ip, ['127.0.0.1', '::1'], true);
    if (!$tokenOk && !$local) {
        http_response_code(403);
        echo "Acesso negado.\n";
        exit;
    }
}

// Garante colunas/tabelas em ambientes que ainda nao passaram pelo bootstrap completo.
@$conn->query("CREATE TABLE IF NOT EXISTS push_config (id INT PRIMARY KEY, cron_token VARCHAR(64) NOT NULL DEFAULT '', atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
@$conn->query("INSERT IGNORE INTO push_config (id, cron_token) VALUES (1, '')");

function push_cron_tokens(mysqli $conn, array $envio): array {
    $alvo = (string)($envio['alvo_tipo'] ?? ($envio['destino'] ?? 'todos'));
    $valor = (string)($envio['alvo_valor'] ?? ($envio['usuario_id'] ?? ''));
    $sql = "SELECT DISTINCT pd.token, pd.user_id FROM push_devices pd";
    $where = " WHERE pd.status = 1";
    $types = '';
    $params = [];

    if ($alvo === 'usuario' && ctype_digit($valor) && (int)$valor > 0) {
        $where .= " AND pd.user_id = ?";
        $types .= 'i'; $params[] = (int)$valor;
    } elseif ($alvo === 'grupo' && in_array($valor, ['ativos','inativos','pendentes'], true)) {
        $sql .= " INNER JOIN usuarios u ON u.id = pd.user_id";
        if ($valor === 'ativos') {
            $where .= " AND COALESCE(u.cartao_ativo,0) = 1 AND COALESCE(u.status,'ativo') <> 'desativado'";
        } elseif ($valor === 'inativos') {
            $where .= " AND (COALESCE(u.cartao_ativo,0) = 0 OR COALESCE(u.status,'ativo') = 'desativado')";
        } elseif ($valor === 'pendentes') {
            $where .= " AND COALESCE(u.cartao_ativo,0) = 0 AND COALESCE(u.status,'ativo') <> 'desativado'";
        }
    } elseif ($alvo === 'segmento' && ctype_digit($valor) && (int)$valor > 0) {
        $sql .= " INNER JOIN planos_contratos pc ON pc.usuario_id = pd.user_id AND pc.vigente = 1";
        $where .= " AND pc.plano_id = ?";
        $types .= 'i'; $params[] = (int)$valor;
    } elseif ($alvo === 'teste') {
        $ids = array_values(array_filter(array_map('intval', explode(',', $valor))));
        if (!$ids) return [];
        $place = implode(',', array_fill(0, count($ids), '?'));
        $where .= " AND pd.user_id IN ($place)";
        $types .= str_repeat('i', count($ids));
        foreach ($ids as $id) $params[] = $id;
    }
    // alvo todos (ou vazio): sem filtro extra

    $stmt = $conn->prepare($sql . $where);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = ['token' => $r['token'], 'user_id' => $r['user_id']];
    }
    $stmt->close();
    return $rows;
}

function push_cron_disparar(mysqli $conn, array $envio): array {
    $fcm = new FcmSender();
    $rows = push_cron_tokens($conn, $envio);
    $tokens = [];
    foreach ($rows as $r) { if (!empty($r['token'])) $tokens[] = $r['token']; }

    $resultado = ['ok' => 0, 'falhas' => 0, 'invalidos' => [], 'erros' => []];
    if (!$fcm->configurado()) {
        $resultado['falhas'] = count($tokens);
        $resultado['erros'][] = $fcm->erroConfig();
    } elseif ($tokens) {
        $linkFinal = (string)($envio['link'] ?? '');
        $trackKey = (string)($envio['track_key'] ?? '');
        if ($trackKey !== '') {
            $base = (request_is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
            $linkFinal = $base . '/push_open.php?t=' . rawurlencode($trackKey);
        }
        $imagem = (string)($envio['imagem'] ?? '');
        $dados = ['mensagem_id' => (string)($envio['mensagem_id'] ?? '')];
        if ($imagem !== '') $dados['imagem'] = $imagem;
        if (!empty($envio['id'])) $dados['envio_id'] = (string)$envio['id'];
        $resultado = $fcm->enviarMuitos($tokens, (string)$envio['titulo'], (string)$envio['corpo'], $linkFinal, $dados);
    }

    $removidos = 0;
    if (!empty($resultado['invalidos'])) {
        $up = $conn->prepare("UPDATE push_devices SET status = 0 WHERE token = ?");
        foreach ($resultado['invalidos'] as $tk) {
            $up->bind_param('s', $tk);
            if ($up->execute()) $removidos++;
        }
        $up->close();
    }

    $detalhe = implode("\n", array_slice($resultado['erros'], 0, 10));
    $total = count($tokens);
    $enviados = (int)$resultado['ok'];
    $falhas = (int)$resultado['falhas'];
    $agora = date('Y-m-d H:i:s');

    if (!empty($envio['id'])) {
        $id = (int)$envio['id'];
        $status = ($total > 0 && $enviados === 0 && $falhas > 0) ? 'falhou' : 'enviado';
        $stmt = $conn->prepare("UPDATE push_envios SET total_tokens=?, enviados=?, falhas=?, removidos=?, detalhe=?, status_envio=?, disparo_em=? WHERE id=?");
        $stmt->bind_param('iiiisssi', $total, $enviados, $falhas, $removidos, $detalhe, $status, $agora, $id);
        $stmt->execute();
        $stmt->close();
    }

    return ['total' => $total, 'enviados' => $enviados, 'falhas' => $falhas, 'removidos' => $removidos];
}

$agoraSql = date('Y-m-d H:i:s');
$pend = $conn->query("SELECT * FROM push_envios WHERE status_envio = 'agendado' AND agendado_para IS NOT NULL AND agendado_para <= '$agoraSql' ORDER BY agendado_para ASC LIMIT 20");
$processados = 0;
while ($pend && ($envio = $pend->fetch_assoc())) {
    $r = push_cron_disparar($conn, $envio);
    echo "Envio #{$envio['id']}: {$r['enviados']} ok, {$r['falhas']} falhas, {$r['removidos']} invalidos\n";
    $processados++;
}
if ($processados === 0) {
    echo "Nenhum envio agendado pendente.\n";
}
