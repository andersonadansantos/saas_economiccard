<?php
require_once 'config.php';
require_once 'fcm.php';

if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

// Garante tabelas/colunas do módulo
@$conn->query("CREATE TABLE IF NOT EXISTS push_config (id INT PRIMARY KEY, cron_token VARCHAR(64) NOT NULL DEFAULT '', atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
@$conn->query("INSERT IGNORE INTO push_config (id, cron_token) VALUES (1, '')");
@$conn->query("CREATE TABLE IF NOT EXISTS push_aberturas (id INT AUTO_INCREMENT PRIMARY KEY, envio_id INT NOT NULL, user_id INT NULL DEFAULT NULL, token_hash CHAR(64) NOT NULL DEFAULT '', origem VARCHAR(20) NOT NULL DEFAULT 'link', aberto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_envio_hash (envio_id, token_hash), KEY idx_envio (envio_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$peCols = [];
if ($rpc = @$conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'push_envios'")) {
    while ($c = $rpc->fetch_assoc()) { $peCols[$c['COLUMN_NAME']] = true; }
}
$peAdd = [
    'imagem' => "ALTER TABLE push_envios ADD COLUMN imagem VARCHAR(500) NOT NULL DEFAULT '' AFTER link",
    'segmento' => "ALTER TABLE push_envios ADD COLUMN segmento VARCHAR(100) NOT NULL DEFAULT '' AFTER imagem",
    'alvo_tipo' => "ALTER TABLE push_envios ADD COLUMN alvo_tipo VARCHAR(30) NOT NULL DEFAULT '' AFTER segmento",
    'alvo_valor' => "ALTER TABLE push_envios ADD COLUMN alvo_valor VARCHAR(255) NOT NULL DEFAULT '' AFTER alvo_tipo",
    'agendado_para' => "ALTER TABLE push_envios ADD COLUMN agendado_para DATETIME NULL DEFAULT NULL AFTER alvo_valor",
    'disparo_em' => "ALTER TABLE push_envios ADD COLUMN disparo_em DATETIME NULL DEFAULT NULL AFTER agendado_para",
    'status_envio' => "ALTER TABLE push_envios ADD COLUMN status_envio VARCHAR(20) NOT NULL DEFAULT 'enviado' AFTER disparo_em",
    'tipo_envio' => "ALTER TABLE push_envios ADD COLUMN tipo_envio VARCHAR(20) NOT NULL DEFAULT 'imediato' AFTER status_envio",
    'track_key' => "ALTER TABLE push_envios ADD COLUMN track_key VARCHAR(64) NOT NULL DEFAULT '' AFTER tipo_envio",
    'aberturas' => "ALTER TABLE push_envios ADD COLUMN aberturas INT NOT NULL DEFAULT 0 AFTER track_key",
];
foreach ($peAdd as $col => $sql) {
    if (!isset($peCols[$col])) {
        try { @$conn->query($sql); } catch (Throwable $e) { /* ignora: coluna pode já existir */ }
    }
}

function push_abs_url($path) {
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    $scheme = request_is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . $host . $dir . '/' . ltrim($path, '/');
}

function push_processar_imagem($prefixo = 'push') {
    $imagem = trim((string)($_POST['imagem_url'] ?? ''));
    if (isset($_FILES['imagem_file']) && is_array($_FILES['imagem_file']) && ($_FILES['imagem_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $tmp = $_FILES['imagem_file']['tmp_name'];
        $info = @getimagesize($tmp);
        $exts = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if ($info !== false && isset($exts[$info['mime']])) {
            $dirUpload = __DIR__ . '/uploads';
            if (!is_dir($dirUpload)) { @mkdir($dirUpload, 0777, true); }
            $nomeArq = $prefixo . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $exts[$info['mime']];
            if (@move_uploaded_file($tmp, $dirUpload . '/' . $nomeArq)) {
                $imagem = 'uploads/' . $nomeArq;
            }
        }
    }
    if ($imagem !== '' && !preg_match('#^https?://#i', $imagem)) {
        $imagem = ltrim(str_replace('\\', '/', $imagem), '/');
    }
    return $imagem;
}

function push_buscar_tokens(mysqli $conn, string $alvoTipo, string $alvoValor): array {
    $sql = "SELECT DISTINCT pd.token, pd.user_id FROM push_devices pd";
    $where = " WHERE pd.status = 1";
    $types = '';
    $params = [];

    if ($alvoTipo === 'usuario' && ctype_digit($alvoValor) && (int)$alvoValor > 0) {
        $where .= " AND pd.user_id = ?";
        $types .= 'i'; $params[] = (int)$alvoValor;
    } elseif ($alvoTipo === 'grupo' && in_array($alvoValor, ['ativos','inativos','pendentes'], true)) {
        $sql .= " INNER JOIN usuarios u ON u.id = pd.user_id";
        if ($alvoValor === 'ativos') {
            $where .= " AND COALESCE(u.cartao_ativo,0) = 1 AND COALESCE(u.status,'ativo') <> 'desativado'";
        } elseif ($alvoValor === 'inativos') {
            $where .= " AND (COALESCE(u.cartao_ativo,0) = 0 OR COALESCE(u.status,'ativo') = 'desativado')";
        } else {
            $where .= " AND COALESCE(u.cartao_ativo,0) = 0 AND COALESCE(u.status,'ativo') <> 'desativado'";
        }
    } elseif ($alvoTipo === 'segmento' && ctype_digit($alvoValor) && (int)$alvoValor > 0) {
        $sql .= " INNER JOIN planos_contratos pc ON pc.usuario_id = pd.user_id AND pc.vigente = 1";
        $where .= " AND pc.plano_id = ?";
        $types .= 'i'; $params[] = (int)$alvoValor;
    } elseif ($alvoTipo === 'teste') {
        $ids = array_values(array_filter(array_map('intval', explode(',', $alvoValor))));
        if (!$ids) return [];
        $place = implode(',', array_fill(0, count($ids), '?'));
        $where .= " AND pd.user_id IN ($place)";
        $types .= str_repeat('i', count($ids));
        foreach ($ids as $id) $params[] = $id;
    }

    $stmt = $conn->prepare($sql . $where);
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
    return $rows;
}

function push_disparar_envio(mysqli $conn, array $envio): array {
    $fcm = new FcmSender();
    $rows = push_buscar_tokens($conn, (string)$envio['alvo_tipo'], (string)$envio['alvo_valor']);
    $tokens = [];
    foreach ($rows as $r) { if (!empty($r['token'])) $tokens[] = $r['token']; }

    $resultado = ['ok' => 0, 'falhas' => 0, 'invalidos' => [], 'erros' => []];
    if (!$fcm->configurado()) {
        $resultado['falhas'] = count($tokens);
        $resultado['erros'][] = $fcm->erroConfig();
    } elseif ($tokens) {
        $trackKey = (string)($envio['track_key'] ?? '');
        $linkFinal = (string)($envio['link'] ?? '');
        if ($trackKey !== '') {
            $base = push_abs_url('push_open.php');
            $linkFinal = $base . '?t=' . rawurlencode($trackKey);
        }
        $dados = ['mensagem_id' => (string)($envio['mensagem_id'] ?? ''), 'envio_id' => (string)($envio['id'] ?? '')];
        $imagemAbs = '';
        if (!empty($envio['imagem'])) { $imagemAbs = push_abs_url($envio['imagem']); $dados['imagem'] = $imagemAbs; }
        $resultado = $fcm->enviarMuitos($tokens, (string)$envio['titulo'], (string)$envio['corpo'], $linkFinal, $dados);
    }

    $removidos = 0;
    if (!empty($resultado['invalidos'])) {
        $up = $conn->prepare("UPDATE push_devices SET status = 0 WHERE token = ?");
        foreach ($resultado['invalidos'] as $tk) { $up->bind_param('s', $tk); if ($up->execute()) $removidos++; }
        $up->close();
    }

    $detalhe = implode("\n", array_slice($resultado['erros'], 0, 10));
    $total = count($tokens);
    $enviados = (int)$resultado['ok'];
    $falhas = (int)$resultado['falhas'];
    $agora = date('Y-m-d H:i:s');
    $id = (int)$envio['id'];
    $status = ($total > 0 && $enviados === 0 && $falhas > 0) ? 'falhou' : 'enviado';
    $stmt = $conn->prepare("UPDATE push_envios SET total_tokens=?, enviados=?, falhas=?, removidos=?, detalhe=?, status_envio=?, disparo_em=COALESCE(disparo_em,?) WHERE id=?");
    $stmt->bind_param('iiiisssi', $total, $enviados, $falhas, $removidos, $detalhe, $status, $agora, $id);
    $stmt->execute();
    $stmt->close();

    return ['total' => $total, 'enviados' => $enviados, 'falhas' => $falhas, 'removidos' => $removidos, 'status' => $status];
}

$fcm = new FcmSender();
$fcmOk = $fcm->configurado();
$erro = '';
$sucesso = '';
$resumo = '';

// Disparar agendado pendente manualmente
if (isset($_GET['disparar_agora'])) {
    $id = (int)$_GET['disparar_agora'];
    $e = $conn->query("SELECT * FROM push_envios WHERE id = $id AND status_envio = 'agendado'")->fetch_assoc();
    if ($e) {
        $r = push_disparar_envio($conn, $e);
        $sucesso = "Envio #$id disparado.";
        $resumo = "Enviados: {$r['enviados']}, falhas: {$r['falhas']}, dispositivos: {$r['total']}, inválidos: {$r['removidos']}.";
    } else {
        $erro = 'Envio agendado não encontrado ou já processado.';
    }
}

// Cancelar agendado
if (isset($_GET['cancelar'])) {
    $id = (int)$_GET['cancelar'];
    @$conn->query("UPDATE push_envios SET status_envio='cancelado' WHERE id=$id AND status_envio='agendado'");
    $sucesso = "Agendamento #$id cancelado.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'enviar_push') {
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $corpo  = trim((string)($_POST['mensagem'] ?? ''));
    $destino = (string)($_POST['destino'] ?? 'todos');
    $usuarioId = (int)($_POST['usuario_id'] ?? 0);
    $grupo = (string)($_POST['grupo'] ?? '');
    $segmentoPlano = (int)($_POST['segmento_plano'] ?? 0);
    $linkBruto = trim((string)($_POST['link'] ?? ''));
    $link = push_link_seguro($linkBruto);
    $agendar = trim((string)($_POST['agendado_para'] ?? ''));
    $modoTeste = !empty($_POST['modo_teste']);
    $testeUsuario = (int)($_POST['teste_usuario_id'] ?? 0);

    $alvoTipo = 'todos'; $alvoValor = '';
    if ($modoTeste) {
        $alvoTipo = 'teste';
        $alvoValor = $testeUsuario > 0 ? (string)$testeUsuario : '';
    } elseif ($destino === 'usuario' && $usuarioId > 0) {
        $alvoTipo = 'usuario'; $alvoValor = (string)$usuarioId;
    } elseif ($destino === 'segmento' && $segmentoPlano > 0) {
        $alvoTipo = 'segmento'; $alvoValor = (string)$segmentoPlano;
    } elseif ($destino === 'grupo' && in_array($grupo, ['ativos','inativos','pendentes'], true)) {
        $alvoTipo = 'grupo'; $alvoValor = $grupo;
    }

    if ($titulo === '' || $corpo === '') {
        $erro = 'Preencha título e mensagem.';
    } elseif ($linkBruto !== '' && $link === '') {
        $erro = 'Link inválido. Use um caminho interno (ex.: /app/dashboard.php) ou URL do próprio site.';
    } elseif ($alvoTipo === 'teste' && $alvoValor === '') {
        $erro = 'Selecione um usuário para o teste.';
    } elseif ($alvoTipo === 'usuario' && $alvoValor === '') {
        $erro = 'Selecione um usuário.';
    } elseif ($alvoTipo === 'segmento' && $alvoValor === '') {
        $erro = 'Selecione um plano/grupo.';
    } elseif ($alvoTipo === 'grupo' && $alvoValor === '') {
        $erro = 'Selecione um grupo.';
    } else {
        $imagem = push_processar_imagem('push');
        $trackKey = bin2hex(random_bytes(16));
        $destinoDb = $alvoTipo === 'teste' ? 'teste' : $alvoTipo;
        $usuarioDb = ($alvoTipo === 'usuario' || $alvoTipo === 'teste') ? (int)$alvoValor : null;
        $statusEnvio = ($agendar !== '' && !$modoTeste) ? 'agendado' : 'enviado';
        $tipoEnvio = $modoTeste ? 'teste' : ($agendar !== '' ? 'agendado' : 'imediato');
        $agendadoPara = null;
        if ($agendar !== '' && !$modoTeste) {
            $ts = strtotime($agendar);
            if ($ts && $ts > time()) { $agendadoPara = date('Y-m-d H:i:s', $ts); }
            else { $erro = 'Data/hora de agendamento inválida ou no passado.'; }
        }

        if ($erro === '') {
            // Mensagem interna (sino) para todos/individual quando não é teste
            $mensagemId = null;
            if (!$modoTeste) {
                if ($alvoTipo === 'todos') {
                    $stmt = $conn->prepare("INSERT INTO mensagens (usuario_id, titulo, mensagem) VALUES (NULL, ?, ?)");
                    $stmt->bind_param('ss', $titulo, $corpo);
                    $stmt->execute();
                    $mensagemId = (int)$conn->insert_id;
                    $stmt->close();
                } elseif ($alvoTipo === 'usuario') {
                    $uidM = (int)$alvoValor;
                    $stmt = $conn->prepare("INSERT INTO mensagens (usuario_id, titulo, mensagem) VALUES (?, ?, ?)");
                    $stmt->bind_param('iss', $uidM, $titulo, $corpo);
                    $stmt->execute();
                    $mensagemId = (int)$conn->insert_id;
                    $stmt->close();
                }
            }

            $segmentoLabel = $alvoTipo === 'grupo' ? $alvoValor : ($alvoTipo === 'segmento' ? ('plano:' . $alvoValor) : $alvoTipo);
            $sqlIns = "INSERT INTO push_envios (mensagem_id, titulo, corpo, link, imagem, segmento, alvo_tipo, alvo_valor, agendado_para, status_envio, tipo_envio, track_key, destino, usuario_id, total_tokens, enviados, falhas, removidos, detalhe, criado_em)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, '', NOW())";
            $stmt = $conn->prepare($sqlIns);
            $usuarioDbBind = $usuarioDb === null ? 0 : (int)$usuarioDb;
            // usuario_id: 0 representa envio sem usuário específico (NULL lógico); manter compatível com bind integer
            $stmt->bind_param('issssssssssssi', $mensagemId, $titulo, $corpo, $link, $imagem, $segmentoLabel, $alvoTipo, $alvoValor, $agendadoPara, $statusEnvio, $tipoEnvio, $trackKey, $destinoDb, $usuarioDbBind);
            $stmt->execute();
            $envioId = (int)$conn->insert_id;
            if ($usuarioDb === null) {
                @$conn->query("UPDATE push_envios SET usuario_id = NULL WHERE id = " . $envioId . " AND usuario_id = 0");
            }
            $stmt->close();

            $envio = [
                'id' => $envioId, 'mensagem_id' => $mensagemId, 'titulo' => $titulo, 'corpo' => $corpo,
                'link' => $link, 'imagem' => $imagem, 'alvo_tipo' => $alvoTipo, 'alvo_valor' => $alvoValor, 'track_key' => $trackKey,
            ];

            if ($statusEnvio === 'agendado') {
                $sucesso = 'Push agendado para ' . date('d/m/Y H:i', strtotime($agendadoPara)) . '.';
            } else {
                $r = push_disparar_envio($conn, $envio);
                $sucesso = $modoTeste ? 'Teste de push enviado.' : 'Push disparado com sucesso.';
                $resumo = "Dispositivos: {$r['total']} · Enviados: {$r['enviados']} · Falhas: {$r['falhas']} · Inválidos removidos: {$r['removidos']}.";
            }
        }
    }
}

// Dados para filtros/form
$usuarios = $conn->query("SELECT id, nome, email, cartao_ativo, status FROM usuarios ORDER BY nome ASC");
$usuariosArr = [];
if ($usuarios) { while ($u = $usuarios->fetch_assoc()) { $usuariosArr[] = $u; } }

$planos = @$conn->query("SELECT DISTINCT p.id, p.nome FROM planos p INNER JOIN planos_contratos pc ON pc.plano_id = p.id WHERE pc.vigente = 1 ORDER BY p.nome");
if (!$planos || $planos->num_rows === 0) { $planos = @$conn->query("SELECT id, nome FROM planos ORDER BY nome"); }
$planosArr = [];
if ($planos) { while ($p = $planos->fetch_assoc()) { $planosArr[] = $p; } }

$totalDispositivos = 0;
if ($r = @$conn->query("SELECT COUNT(*) AS n FROM push_devices WHERE status = 1")) { $totalDispositivos = (int)$r->fetch_assoc()['n']; }
$totalUsuariosComPush = 0;
if ($r = @$conn->query("SELECT COUNT(DISTINCT user_id) AS n FROM push_devices WHERE status = 1 AND user_id IS NOT NULL")) { $totalUsuariosComPush = (int)$r->fetch_assoc()['n']; }

$cronCfg = $conn->query("SELECT cron_token FROM push_config WHERE id = 1")->fetch_assoc();
$cronToken = $cronCfg['cron_token'] ?? '';
if ($cronToken === '') {
    $cronToken = bin2hex(random_bytes(16));
    $st = $conn->prepare("UPDATE push_config SET cron_token=? WHERE id=1");
    $st->bind_param('s', $cronToken);
    $st->execute();
    $st->close();
}
$cronUrl = push_abs_url('push_cron.php') . '?token=' . $cronToken;

$envios = @$conn->query("SELECT e.*, u.nome AS usuario_nome FROM push_envios e LEFT JOIN usuarios u ON u.id = e.usuario_id ORDER BY e.id DESC LIMIT 30");

$totalAberturas = 0; $totalEnviadosAll = 0; $totalFalhasAll = 0;
if ($r = @$conn->query("SELECT COALESCE(SUM(aberturas),0) AS ab, COALESCE(SUM(enviados),0) AS env, COALESCE(SUM(falhas),0) AS fal FROM push_envios")) {
    $row = $r->fetch_assoc();
    $totalAberturas = (int)$row['ab']; $totalEnviadosAll = (int)$row['env']; $totalFalhasAll = (int)$row['fal'];
}
$taxaAbertura = $totalEnviadosAll > 0 ? round(($totalAberturas / $totalEnviadosAll) * 100, 1) : 0.0;

$abaAtual = 'admin_push.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Push - Admin Economic Card</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Manrope', sans-serif; }
  .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
</style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php require 'admin_menu.php'; ?>
<main class="md:ml-60 min-h-screen">
<header class="bg-white shadow-sm sticky top-0 z-30">
  <div class="px-6 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-extrabold text-gray-800">Notificações Push</h1>
      <p class="text-sm text-gray-500">Envio, segmentação, agendamento e relatórios</p>
    </div>
    <a href="logout.php?admin=1" class="bg-[#51036d] hover:bg-[#3a024d] text-white rounded-lg px-4 py-2 text-sm font-semibold transition">Sair</a>
  </div>
</header>
<div class="p-6 space-y-6">

<?php if ($sucesso): ?>
<div class="p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm">
  <?php echo htmlspecialchars($sucesso); ?>
  <?php if ($resumo): ?><br><span class="font-semibold"><?php echo htmlspecialchars($resumo); ?></span><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($erro): ?>
<div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
  <div class="bg-white rounded-xl shadow-sm p-5">
    <p class="text-xs font-bold text-gray-500 uppercase">Dispositivos ativos</p>
    <p class="text-2xl font-extrabold text-gray-800 mt-1"><?php echo $totalDispositivos; ?></p>
    <p class="text-xs text-gray-500 mt-1"><?php echo $totalUsuariosComPush; ?> usuários com push</p>
  </div>
  <div class="bg-white rounded-xl shadow-sm p-5">
    <p class="text-xs font-bold text-gray-500 uppercase">Firebase</p>
    <p class="text-sm font-extrabold mt-1 <?php echo $fcmOk ? 'text-green-700' : 'text-amber-600'; ?>"><?php echo $fcmOk ? 'Configurado' : 'Não configurado'; ?></p>
    <?php if (!$fcmOk): ?><p class="text-[11px] text-gray-500 mt-1"><?php echo htmlspecialchars($fcm->erroConfig()); ?></p><?php endif; ?>
  </div>
  <div class="bg-white rounded-xl shadow-sm p-5">
    <p class="text-xs font-bold text-gray-500 uppercase">Total enviados</p>
    <p class="text-2xl font-extrabold text-gray-800 mt-1"><?php echo $totalEnviadosAll; ?></p>
    <p class="text-xs text-red-600 mt-1">Falhas: <?php echo $totalFalhasAll; ?></p>
  </div>
  <div class="bg-white rounded-xl shadow-sm p-5">
    <p class="text-xs font-bold text-gray-500 uppercase">Aberturas rastreadas</p>
    <p class="text-2xl font-extrabold text-gray-800 mt-1"><?php echo $totalAberturas; ?></p>
    <p class="text-xs text-gray-500 mt-1">Taxa: <?php echo $taxaAbertura; ?>%</p>
  </div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-extrabold text-gray-800">Novo envio</h2>
    <span class="text-xs text-gray-500">Push chega mesmo com app fechado/tela bloqueada</span>
  </div>
  <form method="POST" action="admin_push.php" enctype="multipart/form-data" class="grid grid-cols-1 gap-4" id="formPush">
    <input type="hidden" name="acao" value="enviar_push"/>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Título *</label>
        <input name="titulo" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Ex.: Promoção exclusiva">
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Destino</label>
        <select name="destino" id="destino" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
          <option value="todos">Enviar para todos (dispositivos ativos)</option>
          <option value="usuario">Usuário individual</option>
          <option value="segmento">Segmento (plano/vigentes)</option>
          <option value="grupo">Grupo (status do cartão)</option>
        </select>
      </div>
    </div>

    <div id="wrapUsuario" class="hidden">
      <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Usuário</label>
      <select name="usuario_id" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
        <option value="">Selecione...</option>
        <?php foreach ($usuariosArr as $u): ?>
        <option value="<?php echo (int)$u['id']; ?>"><?php echo htmlspecialchars($u['nome']); ?><?php echo $u['email'] ? ' (' . htmlspecialchars($u['email']) . ')' : ''; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div id="wrapSegmento" class="hidden">
      <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Plano (contrato vigente)</label>
      <select name="segmento_plano" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
        <option value="">Selecione...</option>
        <?php foreach ($planosArr as $p): ?>
        <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
        <?php endforeach; ?>
      </select>
      <p class="text-[11px] text-gray-500 mt-1">Envia para usuários com contrato vigente nesse plano.</p>
    </div>
    <div id="wrapGrupo" class="hidden">
      <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Grupo</label>
      <select name="grupo" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
        <option value="ativos">Usuários com cartão ativo</option>
        <option value="inativos">Usuários inativos/desativados</option>
        <option value="pendentes">Usuários pendentes (sem cartão ativo)</option>
      </select>
    </div>

    <div>
      <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Mensagem *</label>
      <textarea name="mensagem" rows="4" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Texto da notificação"></textarea>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Link de destino (abre página do app/site)</label>
        <input name="link" placeholder="/app/dashboard.php" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
        <p class="text-[11px] text-gray-500 mt-1">Caminho interno ou URL do próprio site. Vazio = tela inicial.</p>
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Imagem da notificação (opcional)</label>
        <input type="file" name="imagem_file" accept="image/png,image/jpeg,image/webp" class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#51036d] file:text-white file:font-semibold file:cursor-pointer">
        <input name="imagem_url" placeholder="ou URL da imagem (https://...)" class="mt-2 w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
        <p class="text-[11px] text-gray-500 mt-1">Imagens grandes podem ser ignoradas por alguns aparelhos/canais.</p>
      </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Agendar (opcional)</label>
        <input type="datetime-local" name="agendado_para" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
        <p class="text-[11px] text-gray-500 mt-1">Se preenchido, o envio fica agendado e será disparado pelo cron no horário.</p>
      </div>
      <div>
        <label class="flex items-center gap-2 text-sm font-semibold text-gray-800 mt-6">
          <input type="checkbox" name="modo_teste" id="modoTeste" value="1" class="w-4 h-4 accent-[#51036d]">
          Modo teste (enviar só para um usuário, sem agendar)
        </label>
        <select name="teste_usuario_id" id="testeUsuario" class="mt-2 w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d] hidden">
          <option value="">Selecione o usuário do teste...</option>
          <?php foreach ($usuariosArr as $u): ?>
          <option value="<?php echo (int)$u['id']; ?>"><?php echo htmlspecialchars($u['nome']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="flex flex-wrap gap-3">
      <button type="submit" <?php echo $fcmOk ? '' : 'disabled'; ?> class="bg-[#51036d] hover:bg-[#3a024d] disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold px-6 py-3 rounded-lg transition inline-flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">send</span> Enviar / Agendar
      </button>
      <?php if (!$fcmOk): ?><span class="text-sm text-amber-600 self-center">Configure as credenciais do Firebase no servidor para habilitar o envio.</span><?php endif; ?>
    </div>
  </form>
</div>

<div class="bg-white rounded-xl shadow-sm p-6">
  <h2 class="text-lg font-extrabold text-gray-800 mb-2">Agendamento automático (cron)</h2>
  <p class="text-sm text-gray-600 mb-3">Para disparar envios agendados, configure um cron (a cada 1–5 min) chamando a URL abaixo. Também pode disparar manualmente na tabela de histórico.</p>
  <div class="flex flex-col sm:flex-row gap-2">
    <input id="cronUrl" readonly value="<?php echo htmlspecialchars($cronUrl); ?>" class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 bg-gray-50 text-sm">
    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('cronUrl').value);this.textContent='Copiado!';setTimeout(()=>this.textContent='Copiar URL',1500);" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold px-4 py-2.5 rounded-lg transition">Copiar URL</button>
  </div>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
  <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
    <h2 class="text-lg font-extrabold text-gray-800">Histórico e relatórios</h2>
    <span class="text-xs text-gray-500">Aberturas contam cliques rastreados no link de destino</span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-gray-50 text-gray-600 text-left uppercase text-xs tracking-wider">
          <th class="px-4 py-3">#</th>
          <th class="px-4 py-3">Quando</th>
          <th class="px-4 py-3">Título</th>
          <th class="px-4 py-3">Destino</th>
          <th class="px-4 py-3">Tipo</th>
          <th class="px-4 py-3">Tokens</th>
          <th class="px-4 py-3">Enviados</th>
          <th class="px-4 py-3">Falhas</th>
          <th class="px-4 py-3">Aberturas</th>
          <th class="px-4 py-3">Status</th>
          <th class="px-4 py-3">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$envios || $envios->num_rows === 0): ?>
        <tr><td colspan="11" class="px-4 py-10 text-center text-gray-500">Nenhum envio registrado.</td></tr>
        <?php endif; ?>
        <?php while ($envios && ($e = $envios->fetch_assoc())):
          $destLabel = $e['alvo_tipo'] ?? $e['destino'] ?? '';
          if ($destLabel === 'usuario') $destLabel = 'Usuário: ' . ($e['usuario_nome'] ?? $e['alvo_valor'] ?? $e['usuario_id'] ?? '');
          elseif ($destLabel === 'grupo') $destLabel = 'Grupo: ' . ($e['alvo_valor'] ?? '');
          elseif ($destLabel === 'segmento') $destLabel = 'Plano: ' . ($e['alvo_valor'] ?? '');
          elseif ($destLabel === 'todos' || $destLabel === '') $destLabel = 'Todos';
          elseif ($destLabel === 'teste') $destLabel = 'Teste: ' . ($e['usuario_nome'] ?? $e['alvo_valor'] ?? '');
          $st = $e['status_envio'] ?? 'enviado';
          $stCls = $st === 'enviado' ? 'text-green-700 bg-green-50' : ($st === 'agendado' ? 'text-amber-700 bg-amber-50' : ($st === 'cancelado' ? 'text-gray-600 bg-gray-100' : 'text-red-700 bg-red-50'));
        ?>
        <tr class="border-t border-gray-100 hover:bg-gray-50 align-top">
          <td class="px-4 py-3 text-gray-500"><?php echo (int)$e['id']; ?></td>
          <td class="px-4 py-3 text-gray-500 whitespace-nowrap"><?php echo date('d/m/Y H:i', strtotime($e['criado_em'])); ?><?php if (!empty($e['agendado_para'])): ?><div class="text-[11px] text-amber-700">Agendado: <?php echo date('d/m/Y H:i', strtotime($e['agendado_para'])); ?></div><?php endif; ?></td>
          <td class="px-4 py-3 font-semibold text-gray-800 max-w-[220px]">
            <div><?php echo htmlspecialchars($e['titulo']); ?></div>
            <div class="text-xs text-gray-500 font-normal mt-0.5"><?php echo htmlspecialchars(mb_strimwidth((string)($e['corpo'] ?? ''), 0, 70, '...')); ?></div>
            <?php if (!empty($e['imagem'])): ?><div class="text-[11px] text-[#51036d] mt-1 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">image</span> com imagem</div><?php endif; ?>
          </td>
          <td class="px-4 py-3 text-gray-700"><?php echo htmlspecialchars($destLabel); ?></td>
          <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($e['tipo_envio'] ?? 'imediato'); ?></td>
          <td class="px-4 py-3 text-gray-600"><?php echo (int)$e['total_tokens']; ?></td>
          <td class="px-4 py-3 text-green-700 font-semibold"><?php echo (int)$e['enviados']; ?></td>
          <td class="px-4 py-3 <?php echo (int)$e['falhas'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>"><?php echo (int)$e['falhas']; ?></td>
          <td class="px-4 py-3 text-gray-800 font-semibold"><?php echo (int)($e['aberturas'] ?? 0); ?></td>
          <td class="px-4 py-3"><span class="px-2 py-1 rounded-md text-xs font-bold <?php echo $stCls; ?>"><?php echo htmlspecialchars($st); ?></span></td>
          <td class="px-4 py-3 whitespace-nowrap">
            <?php if ($st === 'agendado'): ?>
              <a href="admin_push.php?disparar_agora=<?php echo (int)$e['id']; ?>" class="text-[#3e6a00] font-semibold hover:underline mr-2">Disparar</a>
              <a href="admin_push.php?cancelar=<?php echo (int)$e['id']; ?>" class="text-red-600 font-semibold hover:underline" onclick="return confirm('Cancelar este agendamento?');">Cancelar</a>
            <?php else: ?>
              <span class="text-gray-400">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

</div>
</main>
<script>
(function(){
  var destino = document.getElementById('destino');
  var wrapUsuario = document.getElementById('wrapUsuario');
  var wrapSegmento = document.getElementById('wrapSegmento');
  var wrapGrupo = document.getElementById('wrapGrupo');
  var modoTeste = document.getElementById('modoTeste');
  var testeUsuario = document.getElementById('testeUsuario');
  function syncDestino(){
    var v = destino.value;
    var teste = modoTeste.checked;
    wrapUsuario.classList.toggle('hidden', !(v === 'usuario' && !teste));
    wrapSegmento.classList.toggle('hidden', !(v === 'segmento' && !teste));
    wrapGrupo.classList.toggle('hidden', !(v === 'grupo' && !teste));
    testeUsuario.classList.toggle('hidden', !teste);
  }
  destino.addEventListener('change', syncDestino);
  modoTeste.addEventListener('change', syncDestino);
  syncDestino();
})();
</script>
</body>
</html>
