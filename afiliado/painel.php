<?php
require_once dirname(__DIR__) . '/config.php';
if (!isset($_SESSION['afiliado_id'])) {
    header('Location: index.php');
    exit;
}
$aid = (int)$_SESSION['afiliado_id'];
$stmt = $conn->prepare("SELECT * FROM afiliados WHERE id = ? AND ativo = 1");
$stmt->bind_param('i', $aid);
$stmt->execute();
$af = $stmt->get_result()->fetch_assoc();
if (!$af) {
    session_destroy();
    header('Location: index.php');
    exit;
}
$linkIndicacao = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/card/cadastro.php?afiliado=' . $af['token'];

$stmtU = $conn->prepare("SELECT * FROM usuarios WHERE afiliado_token = ? ORDER BY id DESC");
$stmtU->bind_param('s', $af['token']);
$stmtU->execute();
$usuarios = $stmtU->get_result();
$totalVinculados = $usuarios->num_rows;
$totalAtivos = 0;
$usuarios->data_seek(0);
while ($uu = $usuarios->fetch_assoc()) {
    if ($uu['cartao_ativo']) { $totalAtivos++; }
}
$usuarios->data_seek(0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel do Afiliado - <?php echo htmlspecialchars($af['nome']); ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Manrope', sans-serif; background-color: #f4f5f7; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .premium-gradient { background: linear-gradient(135deg, #51036d 0%, #6a2585 55%, #8e3fae 100%); }
</style>
</head>
<body class="min-h-screen">
<div class="min-h-screen flex flex-col">
<header class="premium-gradient text-white px-6 py-6">
<div class="max-w-4xl mx-auto flex flex-col sm:flex-row sm:items-center justify-between gap-4">
<div class="flex items-center gap-3">
<div class="w-11 h-11 rounded-full bg-white/15 flex items-center justify-center">
<span class="material-symbols-outlined">handshake</span>
</div>
<div>
<h1 class="font-extrabold">Olá, <?php echo htmlspecialchars(explode(' ', trim($af['nome']))[0]); ?> 👋</h1>
<p class="text-[12px] text-white/70">Painel do Afiliado - Economic Card</p>
</div>
</div>
<div class="flex items-center gap-2">
<a href="../gerar_contrato_afiliado.php" download="Contrato_Economic_Card_Vendas_por_Comissao.pdf" class="bg-white/10 hover:bg-white/20 rounded-lg px-4 py-2 text-sm font-semibold transition flex items-center gap-1">
<span class="material-symbols-outlined text-[16px]">download</span> Baixar Contrato
</a>
<a href="../login.php" class="bg-white/10 hover:bg-white/20 rounded-lg px-4 py-2 text-sm font-semibold transition">Ver app</a>
<a href="logout.php" class="bg-white/10 hover:bg-white/20 rounded-lg px-4 py-2 text-sm font-semibold transition">Sair</a>
</div>
</div>
</header>
<main class="flex-1 max-w-4xl mx-auto w-full p-6">
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Comissão por venda</p>
<p class="text-3xl font-extrabold text-[#3e6a00]"><?php echo number_format((float)$af['comissao'], 0, ',', '.'); ?>%</p>
</div>
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Status</p>
<p class="text-3xl font-extrabold text-[#51036d]"><?php echo $af['ativo'] ? 'Ativo' : 'Inativo'; ?></p>
</div>
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Clientes cadastrados</p>
<p class="text-3xl font-extrabold text-gray-800"><?php echo $totalVinculados; ?></p>
</div>
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Clientes ativos</p>
<p class="text-3xl font-extrabold text-[#3e6a00]"><?php echo $totalAtivos; ?></p>
</div>
</div>
<div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-6">
<div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
<span class="material-symbols-outlined text-[#51036d]">link</span>
<h2 class="font-extrabold text-gray-900">Seu link de indicação</h2>
</div>
<div class="p-6">
<p class="text-sm text-gray-600 mb-3">Compartilhe este link para vender assinaturas. Cada venda feita por este link gera sua comissão de <?php echo number_format((float)$af['comissao'], 0, ',', '.'); ?>%.</p>
<div class="flex flex-col sm:flex-row gap-2">
<input id="linkIndicacao" type="text" readonly value="<?php echo htmlspecialchars($linkIndicacao); ?>" class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<button onclick="copiarLink()" class="bg-[#51036d] hover:bg-[#3a024d] text-white font-bold px-5 py-2.5 rounded-lg transition text-sm">Copiar link</button>
</div>
</div>
</div>
<div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-6">
<div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
<h2 class="font-extrabold text-gray-900">Clientes do seu link</h2>
<span class="text-xs font-bold text-[#3e6a00] bg-[#b6f570]/30 px-2 py-1 rounded-full"><?php echo $totalVinculados; ?> total</span>
</div>
<?php if ($usuarios->num_rows === 0): ?>
<div class="px-6 py-10 text-center">
<p class="text-sm text-gray-500">Nenhum cliente cadastrado pelo seu link ainda. Compartilhe seu link de indicação para começar.</p>
</div>
<?php endif; ?>
<?php while ($u = $usuarios->fetch_assoc()): ?>
<div class="px-6 py-4 border-b border-gray-100 last:border-0 flex items-center justify-between gap-3">
<div class="min-w-0">
<p class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($u['nome']); ?></p>
<p class="text-xs text-gray-500"><?php echo htmlspecialchars($u['cpf']); ?> · <?php echo date('d/m/Y', strtotime($u['criado_em'])); ?></p>
</div>
<span class="shrink-0 px-2 py-1 rounded-full text-[10px] font-bold uppercase text-white <?php echo $u['cartao_ativo'] ? 'bg-[#3e6a00]' : 'bg-[#ba1a1a]'; ?>"><?php echo $u['cartao_ativo'] ? 'ATIVO' : 'INATIVO'; ?></span>
</div>
<?php endwhile; ?>
</div>
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
<div class="px-6 py-4 border-b border-gray-100">
<h2 class="font-extrabold text-gray-900">Seus dados</h2>
</div>
<div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
<div>
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Nome</p>
<p class="font-semibold text-gray-800"><?php echo htmlspecialchars($af['nome']); ?></p>
</div>
<div>
<p class="text-xs font-bold uppercase text-gray-400 mb-1">E-mail</p>
<p class="font-semibold text-gray-800"><?php echo htmlspecialchars($af['email']); ?></p>
</div>
<?php if ($af['telefone']): ?>
<div>
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Telefone</p>
<p class="font-semibold text-gray-800"><?php echo htmlspecialchars($af['telefone']); ?></p>
</div>
<?php endif; ?>
<?php if ($af['cpf']): ?>
<div>
<p class="text-xs font-bold uppercase text-gray-400 mb-1">CPF</p>
<p class="font-semibold text-gray-800"><?php echo htmlspecialchars($af['cpf']); ?></p>
</div>
<?php endif; ?>
<div>
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Cadastrado em</p>
<p class="font-semibold text-gray-800"><?php echo date('d/m/Y', strtotime($af['criado_em'])); ?></p>
</div>
</div>
</div>
</main>
<footer class="border-t border-gray-200 py-4 text-center text-xs text-gray-400">Economic Card &bull; Painel do Afiliado</footer>
</div>
<script>
function copiarLink() {
    const input = document.getElementById('linkIndicacao');
    input.select();
    input.setSelectionRange(0, 99999);
    try { document.execCommand('copy'); } catch (e) {}
    alert('Link copiado!');
}
</script>
</body>
</html>
