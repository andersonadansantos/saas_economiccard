<?php
require_once 'config.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

$erro = '';
$sucesso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $dias = (int)($_POST['dias'] ?? 0);
        $valor = str_replace(',', '.', trim($_POST['valor'] ?? ''));
        $valor = number_format((float)$valor, 2, '.', '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $ordem = (int)($_POST['ordem'] ?? 0);
        if ($nome === '' || $dias <= 0 || $valor <= 0) {
            $erro = 'Preencha nome, dias e valor do plano.';
        } else {
            $stmt = $conn->prepare("UPDATE planos SET nome=?, dias=?, valor=?, descricao=?, ativo=?, ordem=? WHERE id=?");
            $stmt->bind_param('sidssii', $nome, $dias, $valor, $descricao, $ativo, $ordem, $id);
            $stmt->execute();
            $sucesso = 'Plano salvo com sucesso!';
        }
    }
}

$planos = $conn->query("SELECT * FROM planos ORDER BY ordem ASC, id ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Planos - Admin Economic Card</title>
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
<h1 class="text-xl font-extrabold text-gray-800">Planos</h1>
<p class="text-sm text-gray-500">Planos de ativação do cartão Economic Card</p>
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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
<?php while ($p = $planos->fetch_assoc()): ?>
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
<div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-xl bg-[#51036d]/10 text-[#51036d] flex items-center justify-center"><span class="material-symbols-outlined">credit_score</span></div>
<div>
<h2 class="text-lg font-extrabold text-gray-800"><?php echo htmlspecialchars($p['nome']); ?></h2>
<p class="text-xs text-gray-500">#<?php echo $p['id']; ?></p>
</div>
</div>
<?php if ($p['ativo']): ?>
<span class="px-2 py-1 rounded-full bg-[#b6f570]/40 text-[#3e6a00] text-[10px] font-bold uppercase">Ativo</span>
<?php else: ?>
<span class="px-2 py-1 rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold uppercase">Inativo</span>
<?php endif; ?>
</div>
<form method="POST" action="admin_planos.php" class="p-6 space-y-4">
<input type="hidden" name="acao" value="salvar"/>
<input type="hidden" name="id" value="<?php echo $p['id']; ?>"/>
<input type="hidden" name="ordem" value="<?php echo (int)$p['ordem']; ?>"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome do plano *</label>
<input name="nome" value="<?php echo htmlspecialchars($p['nome']); ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div class="grid grid-cols-2 gap-4">
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Dias ativos *</label>
<input name="dias" type="number" min="1" value="<?php echo (int)$p['dias']; ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Valor (R$) *</label>
<input name="valor" type="number" step="0.01" min="0.01" value="<?php echo number_format((float)$p['valor'], 2, '.', ''); ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Descrição</label>
<input name="descricao" value="<?php echo htmlspecialchars($p['descricao'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div class="flex items-center gap-4">
<label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
<input type="checkbox" name="ativo" class="w-4 h-4 accent-[#3e6a00]" <?php echo $p['ativo'] ? 'checked' : ''; ?>> Plano ativo
</label>
<button class="ml-auto bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-5 py-2.5 rounded-lg transition" type="submit">SALVAR</button>
</div>
</form>
</div>
<?php endwhile; ?>
</div>
<p class="text-xs text-gray-500 mt-6">Os planos ativos aparecem na tela de "Ativar Cartão" do app e do painel web. O usuário escolhe o plano e paga o valor correspondente para liberar o cartão pelos dias definidos.</p>
</div>
</main>
</body>
</html>
