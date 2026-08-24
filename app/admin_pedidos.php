<?php
require_once 'config.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM pedidos_cartao WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: admin_pedidos.php?excluido=1');
    exit;
}

$excluido = isset($_GET['excluido']);

$pedidos = $conn->query("SELECT p.id AS pedido_id, p.criado_em AS pedido_em, u.* FROM pedidos_cartao p JOIN usuarios u ON u.id = p.usuario_id ORDER BY p.criado_em DESC");
$totalPedidos = $pedidos->num_rows;

$dados = [];
$r = $conn->query("SELECT * FROM pedidos_cartao");
while ($linha = $r->fetch_assoc()) { $dados[] = $linha; }
$usuarios = $conn->query("SELECT * FROM usuarios");
$mapa = [];
while ($u = $usuarios->fetch_assoc()) { $mapa[$u['id']] = $u; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pedidos de Cartão - Economic Card</title>
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
<h1 class="text-xl font-extrabold text-gray-800">Pedidos / Cartão</h1>
<p class="text-sm text-gray-500">Pedidos de cartão físico</p>
</div>
<div class="flex items-center gap-4">
<span class="text-sm text-gray-600 hidden sm:block">Olá, <?php echo htmlspecialchars($_SESSION['admin_usuario'] ?? 'admin'); ?></span>
<a href="logout.php?admin=1" class="bg-[#51036d] hover:bg-[#3a024d] text-white rounded-lg px-4 py-2 text-sm font-semibold transition">Sair</a>
</div>
</div>
</header>
<div class="p-6">
<?php if ($excluido): ?>
<div class="mb-6 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">Pedido excluído com sucesso!</div>
<?php endif; ?>
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead>
<tr class="bg-gray-50 text-gray-600 text-left uppercase text-xs tracking-wider">
<th class="px-4 py-3">#</th>
<th class="px-4 py-3">Nome do usuário</th>
<th class="px-4 py-3">E-mail</th>
<th class="px-4 py-3">CPF</th>
<th class="px-4 py-3">Pedido em</th>
<th class="px-4 py-3 text-right">Ações</th>
</tr>
</thead>
<tbody>
<?php if ($totalPedidos === 0): ?>
<tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">Nenhum pedido de cartão físico encontrado.</td></tr>
<?php endif; ?>
<?php while ($p = $pedidos->fetch_assoc()): ?>
<tr class="border-t border-gray-100 hover:bg-gray-50">
<td class="px-4 py-3 text-gray-500"><?php echo (int)$p['pedido_id']; ?></td>
<td class="px-4 py-3">
<button type="button" class="text-[#51036d] font-bold hover:underline text-left" onclick="abrirModal(<?php echo (int)$p['pedido_id']; ?>)">
<?php echo htmlspecialchars($p['nome'] ?: 'Usuário #' . $p['usuario_id']); ?>
</button>
</td>
<td class="px-4 py-3"><?php echo htmlspecialchars($p['email'] ?: '-'); ?></td>
<td class="px-4 py-3"><?php echo htmlspecialchars($p['cpf'] ?: '-'); ?></td>
<td class="px-4 py-3 text-gray-500"><?php echo date('d/m/Y H:i', strtotime($p['pedido_em'])); ?></td>
<td class="px-4 py-3">
<form method="POST" action="admin_pedidos.php" class="flex justify-end" onsubmit="return confirmarExclusao(event, this, 'Excluir o pedido #<?php echo (int)$p['pedido_id']; ?> de <?php echo htmlspecialchars($p['nome'] ?: 'Usuário #' . $p['usuario_id'], ENT_QUOTES); ?>? Esta ação não pode ser desfeita.');">
<input type="hidden" name="acao" value="excluir"/>
<input type="hidden" name="id" value="<?php echo (int)$p['pedido_id']; ?>"/>
<button type="submit" class="inline-flex items-center justify-center text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 rounded-lg p-2 transition" title="Excluir pedido realizado">
<span class="material-symbols-outlined text-[18px]">delete</span>
</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</div>
</main>

<div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/50" onclick="fecharModal()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
<div class="bg-[#51036d] px-6 py-5 flex items-center justify-between">
<h2 class="text-white font-bold">Dados do Pedido / Usuário</h2>
<button onclick="fecharModal()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<div class="p-6 space-y-3">
<div class="bg-[#51036d]/5 border border-[#51036d]/20 rounded-lg p-3 mb-1">
<p class="text-[10px] uppercase tracking-wider text-[#51036d] font-bold">Pedido de cartão físico</p>
<p class="text-sm font-semibold text-gray-800 mt-1" id="m-pedido">-</p>
</div>
<div class="text-center mb-2">
<div class="w-16 h-16 mx-auto rounded-full bg-[#b6f570]/40 flex items-center justify-center text-[#3e6a00] overflow-hidden">
<span id="m-avatar-icon" class="material-symbols-outlined text-3xl" style="font-variation-settings:'FILL' 1;">person</span>
<img id="m-avatar" class="w-full h-full object-cover hidden" alt="Avatar do usuário"/>
</div>
<p id="m-nome" class="text-lg font-extrabold text-gray-800 mt-2"></p>
</div>
<div class="grid grid-cols-2 gap-3">
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">CPF</p>
<p id="m-cpf" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">E-mail</p>
<p id="m-email" class="text-sm font-semibold text-gray-800 mt-1 break-all"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">RG</p>
<p id="m-rg" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Nascimento</p>
<p id="m-nasc" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">WhatsApp</p>
<p id="m-whats" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Telefone para o cartão</p>
<p id="m-telefone" class="text-sm font-semibold text-gray-800 mt-1">-</p>
</div>
<div class="bg-gray-50 rounded-lg p-3 col-span-2">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Endereço</p>
<p id="m-end" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Cidade</p>
<p id="m-cidade" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">CEP</p>
<p id="m-cep" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3 col-span-2">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Cadastrado em</p>
<p id="m-criado" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
</div>
<div class="mt-4">
<a id="m-contrato" href="#" target="_blank" class="flex items-center justify-center gap-2 text-[#3e6a00] font-bold border border-[#3e6a00]/30 bg-[#3e6a00]/5 hover:bg-[#3e6a00]/10 rounded-lg px-4 py-2.5 transition">
<span class="material-symbols-outlined text-[18px]">description</span> Baixar Contrato de Adesão
</a>
</div>
</div>
</div>
</div>

<script>
const usuarios = <?php echo json_encode($mapa, JSON_UNESCAPED_UNICODE); ?>;

function abrirModal(pedidoId) {
    const p = <?php echo json_encode($dados, JSON_UNESCAPED_UNICODE); ?>.find(x => parseInt(x.id) === pedidoId);
    if (!p) return;
    const u = usuarios[String(p.usuario_id)] || {};
    const av = document.getElementById('m-avatar');
    const avIcon = document.getElementById('m-avatar-icon');
    if (u.avatar) {
        av.src = u.avatar;
        av.classList.remove('hidden');
        avIcon.classList.add('hidden');
    } else {
        av.classList.add('hidden');
        avIcon.classList.remove('hidden');
    }
    document.getElementById('m-pedido').textContent = '#' + String(p.id) + ' — feito em ' + (p.criado_em ? new Date(p.criado_em).toLocaleString('pt-BR') : '-');
    document.getElementById('m-nome').textContent = u.nome || '-';
    document.getElementById('m-cpf').textContent = u.cpf || '-';
    document.getElementById('m-email').textContent = u.email || '-';
    document.getElementById('m-rg').textContent = u.rg || '-';
    document.getElementById('m-nasc').textContent = u.nascimento ? new Date(u.nascimento + 'T00:00:00').toLocaleDateString('pt-BR') : '-';
    document.getElementById('m-whats').textContent = u.whatsapp || '-';
    document.getElementById('m-end').textContent = u.endereco || '-';
    document.getElementById('m-cidade').textContent = u.cidade || '-';
    document.getElementById('m-cep').textContent = u.cep || '-';
    document.getElementById('m-criado').textContent = u.criado_em ? new Date(u.criado_em).toLocaleString('pt-BR') : '-';

    const pr = new URLSearchParams({ preview: '1', download: '1', uid: u.id || '', nome: u.nome || '', cpf: u.cpf || '', email: u.email || '', whatsapp: u.whatsapp || '', endereco: u.endereco || '', cidade: u.cidade || '', cep: u.cep || '' });
    document.getElementById('m-contrato').href = 'gerar_contrato.php?' + pr.toString();

    const modal = document.getElementById('modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function fecharModal() {
    const modal = document.getElementById('modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function confirmarExclusao(event, form, msg) {
    event.preventDefault();
    if (!confirm(msg)) return;
    form.submit();
}
</script>
</body>
</html>