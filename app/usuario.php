<?php
require_once 'config.php';
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
exigirCartaoAtivo();
$uid = (int)$_SESSION['usuario_id'];
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if (!$u) { header('Location: logout.php'); exit; }

$primeiroNome = explode(' ', trim($u['nome']))[0];
$final = $u['final_cartao'] ?: '4582';
$avatar = $u['avatar'] ?: '';
$pers = $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc();
$logoApp = $pers['logo_app'] ?? '';

$banner = $conn->query("SELECT * FROM banners WHERE ativo = 1 ORDER BY id DESC LIMIT 1")->fetch_assoc();

$msg = $conn->prepare("SELECT * FROM mensagens WHERE (usuario_id = ? OR (usuario_id IS NULL AND criado_em >= ?)) ORDER BY criado_em DESC LIMIT 20");
$msg->bind_param('is', $uid, $u['criado_em']);
$msg->execute();
$mensagens = $msg->get_result();
$naoLidas = 0;
$idsBroadcast = [];
foreach ($mensagens as $m) {
    if ($m['usuario_id'] === null) {
        $chk = $conn->prepare("SELECT 1 FROM mensagens_lidas WHERE mensagem_id = ? AND usuario_id = ?");
        $chk->bind_param('ii', $m['id'], $uid);
        $chk->execute();
        $jaLida = $chk->get_result()->num_rows > 0;
        if (!$jaLida) { $naoLidas++; }
        $idsBroadcast[] = (int)$m['id'];
    } elseif (!$m['lida']) {
        $naoLidas++;
    }
}
if ($mensagens->num_rows > 0) {
    $mensagens->data_seek(0);
}

$conn->query("UPDATE mensagens SET lida = 1 WHERE usuario_id = $uid AND lida = 0");
foreach ($idsBroadcast as $mid) {
    $ins = $conn->prepare("INSERT IGNORE INTO mensagens_lidas (mensagem_id, usuario_id) VALUES (?, ?)");
    $ins->bind_param('ii', $mid, $uid);
    $ins->execute();
}

$parceiros = $conn->query("SELECT * FROM parceiros WHERE ativo = 1 ORDER BY id DESC LIMIT 6");
$totalParceiros = $conn->query("SELECT COUNT(*) AS total FROM parceiros WHERE ativo = 1")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html class="light" lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Economic Card - Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<style>
    body { font-family: 'Manrope', sans-serif; background-color: #f4f5f7; color: #191c1d; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .premium-gradient { background: linear-gradient(135deg, #51036d 0%, #6a2585 55%, #8e3fae 100%); }
    .card-shadow { box-shadow: 0 8px 30px rgba(81, 3, 109, 0.12); }
    .hover-lift { transition: transform .2s ease, box-shadow .2s ease; }
    .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 12px 36px rgba(81, 3, 109, 0.18); }
</style>
</head>
<body>
<div class="min-h-screen flex">
<aside class="hidden lg:flex flex-col fixed inset-y-0 left-0 w-72 bg-[#51036d] text-white">
<div class="px-6 py-6 flex items-center gap-3 border-b border-white/10">
<div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center overflow-hidden">
<?php if ($logoApp): ?>
<img class="w-full h-full object-contain p-1" src="<?php echo htmlspecialchars($logoApp); ?>" alt="Logo"/>
<?php else: ?>
<span class="material-symbols-outlined text-white">credit_card</span>
<?php endif; ?>
</div>
<div>
<h1 class="font-extrabold leading-tight">Economic Card</h1>
<p class="text-[11px] text-white/60"><?php echo htmlspecialchars(strtoupper($u['cidade'] ?: 'Membro')); ?></p>
</div>
</div>
<nav class="flex-1 px-4 py-6 space-y-1">
<a href="usuario.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold bg-white/15">
<span class="material-symbols-outlined">home</span> Início
</a>
<a href="parceiros.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold hover:bg-white/10 transition">
<span class="material-symbols-outlined">storefront</span> Parceiros
</a>
<a href="ativar.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold hover:bg-white/10 transition">
<span class="material-symbols-outlined">credit_score</span> Ativar Cartão
</a>
<a href="perfil.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold hover:bg-white/10 transition">
<span class="material-symbols-outlined">person</span> Perfil
</a>
</nav>
<div class="p-4 border-t border-white/10">
<div class="flex items-center gap-3 mb-3">
<div class="w-11 h-11 rounded-full overflow-hidden bg-white/10 flex items-center justify-center border-2 border-[#b6f570]">
<?php if ($avatar): ?>
<img class="w-full h-full object-cover" src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar"/>
<?php else: ?>
<span class="material-symbols-outlined text-white/80">person</span>
<?php endif; ?>
</div>
<div class="min-w-0">
<p class="text-sm font-bold truncate"><?php echo htmlspecialchars($u['nome']); ?></p>
<p class="text-[11px] text-white/60">Usuário do Cartão</p>
</div>
</div>
<a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold text-white/80 hover:bg-white/10 transition">
<span class="material-symbols-outlined text-[20px]">phone_iphone</span> Versão Mobile
</a>
<a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold text-white/80 hover:bg-white/10 transition">
<span class="material-symbols-outlined text-[20px]">logout</span> Sair
</a>
</div>
</aside>

<main class="lg:ml-72 flex-1 p-4 md:p-8">
<header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
<div class="flex items-center gap-3">
<?php if ($avatar): ?>
<div class="w-12 h-12 rounded-full overflow-hidden border-2 border-[#b6f570] bg-white">
<img class="w-full h-full object-cover" src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar"/>
</div>
<?php else: ?>
<div class="w-12 h-12 rounded-full bg-white flex items-center justify-center text-[#51036d] border-2 border-[#b6f570]">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">person</span>
</div>
<?php endif; ?>
<div>
<h2 class="text-2xl font-extrabold text-gray-900">Olá, <?php echo htmlspecialchars($primeiroNome); ?> 👋</h2>
<p class="text-sm text-gray-500">Bem-vindo de volta! Confira seu benefício hoje.</p>
</div>
</div>
<div class="flex items-center gap-3">
<?php if ($u['cartao_ativo']): ?>
<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[#b6f570]/40 text-[#3e6a00] text-xs font-bold"><span class="w-1.5 h-1.5 rounded-full bg-[#3e6a00]"></span> CARTÃO ATIVO</span>
<?php else: ?>
<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-gray-200 text-gray-600 text-xs font-bold"><span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span> CARTÃO INATIVO</span>
<?php endif; ?>
<a href="ativar.php" class="bg-[#51036d] hover:bg-[#3a024d] text-white rounded-xl px-4 py-2 text-sm font-semibold transition">Ativar Cartão</a>
</div>
</header>

<section class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
<div class="bg-white rounded-2xl card-shadow p-5">
<div class="flex items-center justify-between mb-2">
<p class="text-xs font-bold uppercase text-gray-400">Status</p>
<span class="material-symbols-outlined text-[#51036d]">credit_card</span>
</div>
<p class="text-lg font-extrabold text-gray-900"><?php echo $u['cartao_ativo'] ? 'Ativo' : 'Inativo'; ?></p>
<p class="text-xs text-gray-500">•••• •••• •••• <?php echo htmlspecialchars($final); ?></p>
</div>
<div class="bg-white rounded-2xl card-shadow p-5">
<div class="flex items-center justify-between mb-2">
<p class="text-xs font-bold uppercase text-gray-400">Parceiros</p>
<span class="material-symbols-outlined text-[#3e6a00]">storefront</span>
</div>
<p class="text-lg font-extrabold text-gray-900"><?php echo (int)$totalParceiros; ?></p>
<p class="text-xs text-gray-500">empresas com desconto</p>
</div>
<div class="bg-white rounded-2xl card-shadow p-5">
<div class="flex items-center justify-between mb-2">
<p class="text-xs font-bold uppercase text-gray-400">Mensagens</p>
<span class="material-symbols-outlined text-[#3e6a00]">notifications</span>
</div>
<p class="text-lg font-extrabold text-gray-900"><?php echo (int)$naoLidas; ?> nova<?php echo $naoLidas === 1 ? '' : 's'; ?></p>
<p class="text-xs text-gray-500">do Economic Card</p>
</div>
</section>

<section class="grid grid-cols-1 xl:grid-cols-2 gap-8">
<div class="space-y-8">
<div class="relative group hover-lift rounded-2xl overflow-hidden cursor-pointer" onclick="abrirFullscreen()">
<div class="premium-gradient rounded-2xl p-8 shadow-[0_8px_30px_rgba(81,3,109,0.25)] relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between">
<div class="absolute -top-10 -right-10 w-48 h-48 bg-white/10 rounded-full blur-2xl"></div>
<div class="absolute bottom-0 left-0 w-full h-1/2 bg-gradient-to-t from-black/20 to-transparent"></div>
<div class="flex justify-between items-start z-10">
<div class="space-y-1">
<span class="text-[10px] text-white/60 tracking-widest font-semibold">MEMBRO PLATINUM</span>
<div class="h-[2px] w-8 bg-[#b6f570]"></div>
</div>
<span class="px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 bg-white/15 backdrop-blur-md text-white">
<span class="w-1.5 h-1.5 rounded-full bg-[#b6f570]"></span>
<?php echo $u['cartao_ativo'] ? 'ATIVO' : 'INATIVO'; ?>
</span>
</div>
<div class="z-10 mt-auto">
<p class="text-white text-2xl font-extrabold mb-4"><?php echo htmlspecialchars(strtoupper($u['nome'])); ?></p>
<div class="flex justify-between items-end">
<div class="space-y-1">
<p class="text-[13px] text-white/70 font-bold tracking-widest">•••• •••• •••• <?php echo htmlspecialchars($final); ?></p>
<p class="text-[11px] text-white/50 font-semibold">VALIDADE 08/25</p>
</div>
<p class="text-white/20 text-4xl font-extrabold italic">Economic</p>
</div>
</div>
</div>
</div>

<div class="bg-white rounded-2xl card-shadow overflow-hidden">
<div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
<h3 class="font-extrabold text-gray-900">Oferta Especial</h3>
<a href="parceiros.php" class="text-sm font-semibold text-[#51036d] hover:underline">Ver todos</a>
</div>
<?php if ($banner): ?>
<div class="p-6">
<div class="bg-[#b6f570]/20 border border-[#3e6a00]/20 rounded-xl overflow-hidden">
<?php if ($banner['imagem']): ?>
<img class="w-full h-44 object-cover" src="<?php echo htmlspecialchars($banner['imagem']); ?>" alt="Banner"/>
<?php endif; ?>
<div class="p-5">
<div class="flex items-center justify-between gap-3 flex-wrap">
<div>
<h4 class="font-extrabold text-gray-900"><?php echo htmlspecialchars($banner['titulo']); ?></h4>
<?php if ($banner['desconto']): ?>
<span class="inline-block mt-1 px-2 py-0.5 rounded-full bg-[#3e6a00] text-white text-[10px] font-bold uppercase"><?php echo htmlspecialchars($banner['desconto']); ?></span>
<?php endif; ?>
<?php if ($banner['descricao']): ?>
<p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($banner['descricao']); ?></p>
<?php endif; ?>
</div>
<a href="parceiros.php" class="bg-[#51036d] hover:bg-[#3a024d] text-white text-sm font-bold px-5 py-2.5 rounded-full transition whitespace-nowrap"><?php echo htmlspecialchars($banner['botao_texto'] ?: 'EU QUERO!'); ?></a>
</div>
</div>
</div>
<?php else: ?>
<p class="px-6 py-8 text-sm text-gray-500 text-center">Nenhuma oferta no momento.</p>
<?php endif; ?>
</div>
</div>

<div class="space-y-8">
<div class="bg-white rounded-2xl card-shadow overflow-hidden">
<div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
<div class="flex items-center gap-2">
<h3 class="font-extrabold text-gray-900">Mensagens</h3>
<?php if ($naoLidas > 0): ?>
<span class="px-2 py-0.5 rounded-full bg-[#ba1a1a] text-white text-[10px] font-bold"><?php echo $naoLidas; ?></span>
<?php endif; ?>
</div>
</div>
<?php if ($mensagens->num_rows === 0): ?>
<p class="px-6 py-8 text-sm text-gray-500 text-center">Nenhuma mensagem no momento.</p>
<?php endif; ?>
<?php while ($m = $mensagens->fetch_assoc()): ?>
<button type="button" class="w-full text-left px-6 py-4 border-b border-gray-100 hover:bg-gray-50 transition flex items-start gap-4" onclick="abrirMensagem('<?php echo addslashes(htmlspecialchars($m['titulo'])); ?>', '<?php echo addslashes(htmlspecialchars($m['mensagem'])); ?>', '<?php echo date('d/m/Y H:i', strtotime($m['criado_em'])); ?>')">
<div class="w-9 h-9 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-[18px]">notifications</span>
</div>
<div class="min-w-0">
<p class="font-bold text-gray-900 text-sm"><?php echo htmlspecialchars($m['titulo']); ?></p>
<p class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($m['mensagem']); ?></p>
<p class="text-[11px] text-gray-400 mt-0.5"><?php echo date('d/m/Y H:i', strtotime($m['criado_em'])); ?></p>
</div>
</button>
<?php endwhile; ?>
</div>

<div class="bg-white rounded-2xl card-shadow overflow-hidden">
<div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
<h3 class="font-extrabold text-gray-900">Parceiros em destaque</h3>
<a href="parceiros.php" class="text-sm font-semibold text-[#51036d] hover:underline">Ver todos</a>
</div>
<?php if ($parceiros->num_rows === 0): ?>
<p class="px-6 py-8 text-sm text-gray-500 text-center">Nenhum parceiro no momento.</p>
<?php endif; ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6">
<?php while ($p = $parceiros->fetch_assoc()): ?>
<div class="flex items-center gap-4 border border-gray-100 rounded-xl p-4 hover:border-[#51036d]/30 transition">
<div class="w-14 h-14 bg-gray-50 rounded-lg flex items-center justify-center overflow-hidden shrink-0">
<?php if ($p['logo']): ?>
<img class="w-full h-full object-contain p-1.5" src="<?php echo htmlspecialchars($p['logo']); ?>" alt="Logo"/>
<?php else: ?>
<span class="material-symbols-outlined text-gray-400">storefront</span>
<?php endif; ?>
</div>
<div class="min-w-0 flex-1">
<p class="font-bold text-gray-900 text-sm truncate"><?php echo htmlspecialchars($p['nome']); ?></p>
<?php if ($p['categoria']): ?>
<span class="text-[11px] font-semibold text-[#3e6a00]"><?php echo htmlspecialchars($p['categoria']); ?></span>
<?php endif; ?>
</div>
<a href="parceiros.php" class="bg-[#b6f570]/30 text-[#3e6a00] text-xs font-bold px-3 py-1.5 rounded-full hover:bg-[#b6f570]/50 transition shrink-0">Ver</a>
</div>
<?php endwhile; ?>
</div>
</div>
</div>
</section>
</main>
</div>

<div class="hidden fixed inset-0 z-[100] bg-black/90 backdrop-blur-md items-center justify-center p-6" id="fullscreenCard">
<button class="absolute top-6 right-6 material-symbols-outlined text-white/80 text-3xl" onclick="fecharFullscreen()">close</button>
<div class="w-full max-w-md premium-gradient rounded-2xl p-8 shadow-2xl relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between">
<div class="absolute -top-10 -right-10 w-48 h-48 bg-white/10 rounded-full blur-2xl"></div>
<div class="flex justify-between items-start z-10">
<div class="space-y-1">
<span class="text-[10px] text-white/60 tracking-widest font-semibold">MEMBRO PLATINUM</span>
<div class="h-[2px] w-8 bg-[#b6f570]"></div>
</div>
<span class="px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 bg-white/15 backdrop-blur-md text-white">
<span class="w-1.5 h-1.5 rounded-full bg-[#b6f570]"></span>
<?php echo $u['cartao_ativo'] ? 'ATIVO' : 'INATIVO'; ?>
</span>
</div>
<div class="z-10 mt-auto">
<p class="text-white text-2xl font-extrabold mb-4"><?php echo htmlspecialchars(strtoupper($u['nome'])); ?></p>
<div class="flex justify-between items-end">
<div class="space-y-1">
<p class="text-[13px] text-white/70 font-bold tracking-widest">•••• •••• •••• <?php echo htmlspecialchars($final); ?></p>
<p class="text-[11px] text-white/50 font-semibold">VALIDADE 08/25</p>
</div>
<p class="text-white/20 text-4xl font-extrabold italic">Economic</p>
</div>
</div>
</div>
</div>

<div class="hidden fixed inset-0 z-[120] bg-black/60 backdrop-blur-sm items-center justify-center p-6" id="mensagemModal">
<div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
<div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-[#51036d] text-[20px] shrink-0">notifications</span>
<span class="font-bold text-gray-900 text-sm uppercase truncate" id="msgModalTitulo">MENSAGEM</span>
</div>
<button class="material-symbols-outlined text-gray-500 hover:bg-gray-100 p-1.5 rounded-full shrink-0 transition" onclick="fecharMensagem()">close</button>
</div>
<div class="px-5 py-5 max-h-[60vh] overflow-y-auto">
<p class="text-gray-700 text-sm whitespace-pre-line" id="msgModalTexto"></p>
</div>
<div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
<span class="text-[11px] text-gray-400" id="msgModalData"></span>
<button class="text-sm font-bold text-[#51036d] hover:opacity-70 transition" onclick="fecharMensagem()">Fechar</button>
</div>
</div>
</div>

<script>
function abrirFullscreen() {
    const el = document.getElementById('fullscreenCard');
    el.classList.remove('hidden');
    el.classList.add('flex');
}
function fecharFullscreen() {
    const el = document.getElementById('fullscreenCard');
    el.classList.add('hidden');
    el.classList.remove('flex');
}
function abrirMensagem(titulo, texto, data) {
    document.getElementById('msgModalTitulo').textContent = titulo;
    document.getElementById('msgModalTexto').textContent = texto;
    document.getElementById('msgModalData').textContent = data;
    const el = document.getElementById('mensagemModal');
    el.classList.remove('hidden');
    el.classList.add('flex');
}
function fecharMensagem() {
    const el = document.getElementById('mensagemModal');
    el.classList.add('hidden');
    el.classList.remove('flex');
}
</script>
</body>
</html>
