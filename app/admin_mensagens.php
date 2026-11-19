<?php
require_once 'config.php';
require_once 'fcm.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

$erro = '';
$sucesso = '';
$pushResumo = '';

$fcm = new FcmSender();
$fcmOk = $fcm->configurado();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'enviar') {
    $destino    = trim($_POST['destino'] ?? 'todos');
    $titulo     = trim($_POST['titulo'] ?? '');
    $mensagem   = trim($_POST['mensagem'] ?? '');
    $enviarPush = !empty($_POST['enviar_push']);
    $linkBruto  = trim((string)($_POST['link'] ?? ''));
    $link       = push_link_seguro($linkBruto);

    if ($titulo === '' || $mensagem === '') {
        $erro = 'Preencha título e mensagem.';
    } elseif ($destino !== 'todos' && (int)$destino <= 0) {
        $erro = 'Destinatário inválido.';
    } elseif ($enviarPush && $linkBruto !== '' && $link === '') {
        $erro = 'Link inválido. Use um caminho interno (ex.: /dashboard.php) ou uma URL do próprio site.';
    } else {
        if ($destino === 'todos') {
            $stmt = $conn->prepare("INSERT INTO mensagens (usuario_id, titulo, mensagem) VALUES (NULL, ?, ?)");
            $stmt->bind_param('ss', $titulo, $mensagem);
            $stmt->execute();
            $mensagemId = (int)$conn->insert_id;
            $stmt->close();
        } else {
            $uid = (int)$destino;
            $stmt = $conn->prepare("INSERT INTO mensagens (usuario_id, titulo, mensagem) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $uid, $titulo, $mensagem);
            $stmt->execute();
            $mensagemId = (int)$conn->insert_id;
            $stmt->close();
        }
        $sucesso = 'Mensagem enviada com sucesso!';

        if ($enviarPush && $fcmOk) {
            $uidDestino = $destino === 'todos' ? null : (int)$destino;

            if ($uidDestino !== null) {
                $stmt = $conn->prepare("SELECT token FROM push_devices WHERE status = 1 AND user_id = ?");
                $stmt->bind_param('i', $uidDestino);
            } else {
                $stmt = $conn->prepare("SELECT token FROM push_devices WHERE status = 1");
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $tokens = [];
            while ($row = $res->fetch_assoc()) {
                if (!empty($row['token'])) $tokens[] = $row['token'];
            }
            $stmt->close();

            $resultado = ['ok' => 0, 'falhas' => 0, 'invalidos' => [], 'erros' => []];

            if (!$fcm->configurado()) {
                $resultado['falhas'] = count($tokens);
                $resultado['erros'][] = $fcm->erroConfig();
            } elseif ($tokens) {
                $resultado = $fcm->enviarMuitos($tokens, $titulo, $mensagem, $link, [
                    'mensagem_id' => (string)$mensagemId,
                ]);
            }

            // Tokens recusados pelo Firebase (app desinstalado, token expirado
            // etc.) são desativados — nunca apagados, para manter o histórico.
            $removidos = 0;
            if (!empty($resultado['invalidos'])) {
                $up = $conn->prepare("UPDATE push_devices SET status = 0 WHERE token = ?");
                foreach ($resultado['invalidos'] as $tokenInvalido) {
                    $up->bind_param('s', $tokenInvalido);
                    if ($up->execute()) $removidos++;
                }
                $up->close();
            }

            $detalhe = implode("\n", array_slice($resultado['erros'], 0, 10));
            $totalTokens = count($tokens);
            $enviados = (int)$resultado['ok'];
            $falhas = (int)$resultado['falhas'];

            $log = $conn->prepare("INSERT INTO push_envios
                    (mensagem_id, titulo, corpo, link, destino, usuario_id, total_tokens, enviados, falhas, removidos, detalhe)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $log->bind_param(
                'issssiiiiis',
                $mensagemId,
                $titulo,
                $mensagem,
                $link,
                $destino,
                $uidDestino,
                $totalTokens,
                $enviados,
                $falhas,
                $removidos,
                $detalhe
            );
            $log->execute();
            $log->close();

            if ($totalTokens === 0) {
                $pushResumo = 'Nenhum dispositivo com push ativo para este destino.';
            } else {
                $pushResumo = 'Push: ' . $enviados . ' enviado(s), ' . $falhas . ' falha(s)'
                    . ($removidos > 0 ? ', ' . $removidos . ' token(s) inválido(s) removido(s)' : '') . '.';
            }
        }
    }
}

$usuarios = $conn->query("SELECT id, nome FROM usuarios ORDER BY nome");
$mensagens = $conn->query("SELECT m.*, u.nome AS usuario_nome FROM mensagens m LEFT JOIN usuarios u ON u.id = m.usuario_id ORDER BY m.id DESC");

$totalDispositivos = 0;
if ($rDisp = $conn->query("SELECT COUNT(*) AS n FROM push_devices WHERE status = 1")) {
    $totalDispositivos = (int)$rDisp->fetch_assoc()['n'];
}
$enviosPush = $conn->query("SELECT * FROM push_envios ORDER BY id DESC LIMIT 15");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mensagens - Admin Economic Card</title>
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
<h1 class="text-xl font-extrabold text-gray-800">Mensagens</h1>
<p class="text-sm text-gray-500">Envie mensagens no sino do app e notificações push para os celulares</p>
</div>
<a href="logout.php?admin=1" class="bg-[#51036d] hover:bg-[#3a024d] text-white rounded-lg px-4 py-2 text-sm font-semibold transition">Sair</a>
</div>
</header>
<div class="p-6">
<?php if ($sucesso): ?>
<div class="mb-6 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm">
<?php echo htmlspecialchars($sucesso); ?>
<?php if ($pushResumo): ?><br/><span class="font-semibold"><?php echo htmlspecialchars($pushResumo); ?></span><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($erro): ?>
<div class="mb-6 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
<div class="bg-white rounded-xl shadow-sm p-5 flex items-center gap-4">
<span class="material-symbols-outlined text-[28px] text-[#51036d]">smartphone</span>
<div>
<p class="text-xs font-bold text-gray-500 uppercase">Dispositivos com push ativo</p>
<p class="text-2xl font-extrabold text-gray-800"><?php echo $totalDispositivos; ?></p>
</div>
</div>
<div class="bg-white rounded-xl shadow-sm p-5 flex items-center gap-4">
<span class="material-symbols-outlined text-[28px] <?php echo $fcmOk ? 'text-green-600' : 'text-amber-500'; ?>"><?php echo $fcmOk ? 'notifications_active' : 'notifications_off'; ?></span>
<div>
<p class="text-xs font-bold text-gray-500 uppercase">Firebase (envio)</p>
<p class="text-sm font-extrabold <?php echo $fcmOk ? 'text-green-700' : 'text-amber-600'; ?>"><?php echo $fcmOk ? 'Configurado' : 'Não configurado no servidor'; ?></p>
<?php if (!$fcmOk): ?>
<p class="text-[11px] text-gray-500 mt-0.5"><?php echo htmlspecialchars($fcm->erroConfig()); ?></p>
<?php endif; ?>
</div>
</div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-4">Enviar nova mensagem</h2>
<form method="POST" action="admin_mensagens.php" class="grid grid-cols-1 gap-4">
<input type="hidden" name="acao" value="enviar"/>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Destinatário</label>
<select name="destino" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<option value="todos">Todos os usuários</option>
<?php while ($us = $usuarios->fetch_assoc()): ?>
<option value="<?php echo $us['id']; ?>"><?php echo htmlspecialchars($us['nome']); ?></option>
<?php endwhile; ?>
</select>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Título *</label>
<input name="titulo" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Mensagem *</label>
<textarea name="mensagem" rows="4" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]"></textarea>
</div>

<div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
<label class="flex items-start gap-3 <?php echo $fcmOk ? 'cursor-pointer' : 'cursor-not-allowed opacity-70'; ?>">
<input type="checkbox" name="enviar_push" value="1" <?php echo $fcmOk ? 'checked' : 'disabled'; ?> class="mt-1 w-4 h-4 accent-[#51036d]"/>
<span>
<span class="block text-sm font-bold text-gray-800">Enviar também como notificação push</span>
<span class="block text-xs text-gray-500">Chega no celular mesmo com o app fechado ou a tela bloqueada.</span>
<?php if (!$fcmOk): ?>
<span class="block text-xs text-amber-600 mt-1">Indisponível: a credencial do Firebase ainda não foi configurada no servidor.</span>
<?php endif; ?>
</span>
</label>
<div class="mt-3">
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Abrir esta página ao tocar na notificação</label>
<input name="link" placeholder="/dashboard.php" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]"/>
<p class="text-[11px] text-gray-500 mt-1">Use um caminho do sistema (ex.: <code>/dashboard.php</code>, <code>/perfil.php</code>, <code>/ativar.php</code>). Em branco abre a tela inicial do app.</p>
</div>
</div>

<div>
<button class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition" type="submit">ENVIAR MENSAGEM</button>
</div>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
<h2 class="px-6 py-4 text-lg font-extrabold text-gray-800 border-b border-gray-100">Mensagens enviadas</h2>
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead>
<tr class="bg-gray-50 text-gray-600 text-left uppercase text-xs tracking-wider">
<th class="px-4 py-3">#</th>
<th class="px-4 py-3">Destinatário</th>
<th class="px-4 py-3">Título</th>
<th class="px-4 py-3">Mensagem</th>
<th class="px-4 py-3">Enviada em</th>
</tr>
</thead>
<tbody>
<?php if ($mensagens->num_rows === 0): ?>
<tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">Nenhuma mensagem enviada.</td></tr>
<?php endif; ?>
<?php while ($m = $mensagens->fetch_assoc()): ?>
<tr class="border-t border-gray-100 hover:bg-gray-50">
<td class="px-4 py-3 text-gray-500"><?php echo $m['id']; ?></td>
<td class="px-4 py-3 font-semibold text-gray-800"><?php echo $m['usuario_id'] ? htmlspecialchars($m['usuario_nome'] ?? 'Usuário ' . $m['usuario_id']) : 'Todos os usuários'; ?></td>
<td class="px-4 py-3"><?php echo htmlspecialchars($m['titulo']); ?></td>
<td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars(mb_strimwidth($m['mensagem'], 0, 60, '...')); ?></td>
<td class="px-4 py-3 text-gray-500"><?php echo date('d/m/Y H:i', strtotime($m['criado_em'])); ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
<h2 class="px-6 py-4 text-lg font-extrabold text-gray-800 border-b border-gray-100">Envios push recentes</h2>
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead>
<tr class="bg-gray-50 text-gray-600 text-left uppercase text-xs tracking-wider">
<th class="px-4 py-3">#</th>
<th class="px-4 py-3">Quando</th>
<th class="px-4 py-3">Título</th>
<th class="px-4 py-3">Link</th>
<th class="px-4 py-3">Tokens</th>
<th class="px-4 py-3">Enviados</th>
<th class="px-4 py-3">Falhas</th>
<th class="px-4 py-3">Inválidos</th>
</tr>
</thead>
<tbody>
<?php if (!$enviosPush || $enviosPush->num_rows === 0): ?>
<tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">Nenhum envio push registrado.</td></tr>
<?php endif; ?>
<?php while ($p = $enviosPush->fetch_assoc()): ?>
<tr class="border-t border-gray-100 hover:bg-gray-50">
<td class="px-4 py-3 text-gray-500"><?php echo (int)$p['id']; ?></td>
<td class="px-4 py-3 text-gray-500"><?php echo date('d/m/Y H:i', strtotime($p['criado_em'])); ?></td>
<td class="px-4 py-3 font-semibold text-gray-800"><?php echo htmlspecialchars($p['titulo']); ?></td>
<td class="px-4 py-3 text-gray-600"><?php echo $p['link'] !== '' ? htmlspecialchars($p['link']) : '—'; ?></td>
<td class="px-4 py-3 text-gray-600"><?php echo (int)$p['total_tokens']; ?></td>
<td class="px-4 py-3 text-green-700 font-semibold"><?php echo (int)$p['enviados']; ?></td>
<td class="px-4 py-3 <?php echo (int)$p['falhas'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>"><?php echo (int)$p['falhas']; ?></td>
<td class="px-4 py-3 text-gray-600"><?php echo (int)$p['removidos']; ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</div>
</main>
</body>
</html>
