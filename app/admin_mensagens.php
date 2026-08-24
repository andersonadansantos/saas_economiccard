<?php
require_once 'config.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

$erro = '';
$sucesso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'enviar') {
    $destino = trim($_POST['destino'] ?? 'todos');
    $titulo = trim($_POST['titulo'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    if ($titulo === '' || $mensagem === '') {
        $erro = 'Preencha título e mensagem.';
    } else {
        if ($destino === 'todos') {
            $stmt = $conn->prepare("INSERT INTO mensagens (usuario_id, titulo, mensagem) VALUES (NULL, ?, ?)");
            $stmt->bind_param('ss', $titulo, $mensagem);
            $stmt->execute();
        } else {
            $uid = (int)$destino;
            $stmt = $conn->prepare("INSERT INTO mensagens (usuario_id, titulo, mensagem) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $uid, $titulo, $mensagem);
            $stmt->execute();
        }
        $sucesso = 'Mensagem enviada com sucesso!';
    }
}

$usuarios = $conn->query("SELECT id, nome FROM usuarios ORDER BY nome");
$mensagens = $conn->query("SELECT m.*, u.nome AS usuario_nome FROM mensagens m LEFT JOIN usuarios u ON u.id = m.usuario_id ORDER BY m.id DESC");
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
<p class="text-sm text-gray-500">Envie mensagens aos usuários (aparecem no sino do app)</p>
</div>
<a href="logout.php?admin=1" class="bg-[#51036d] hover:bg-[#3a024d] text-white rounded-lg px-4 py-2 text-sm font-semibold transition">Sair</a>
</div>
</header>
<div class="p-6">
<?php if ($sucesso): ?>
<div class="mb-6 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm"><?php echo htmlspecialchars($sucesso); ?></div>
<?php endif; ?>
<?php if ($erro): ?>
<div class="mb-6 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

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
<div>
<button class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition" type="submit">ENVIAR MENSAGEM</button>
</div>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
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
</div>
</main>
</body>
</html>