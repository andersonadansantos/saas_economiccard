<?php
require_once 'config.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

$erro = '';
$sucesso = '';

function parce_valor($v) {
    $v = trim((string)$v);
    $v = str_replace('R$', '', $v);
    $v = str_replace(' ', '', $v);
    // Formatação brasileira com vírgula decimal (ex.: 1.234,5678)
    if (strpos($v, ',') !== false) {
        $v = str_replace('.', '', $v);      // remove milhar
        $v = str_replace(',', '.', $v);     // vírgula -> ponto decimal
    }
    return number_format((float)$v, 4, '.', '');
}

// Formata para exibição com até 4 casas decimais (removendo zeros à direita)
function fmt_valor($v) {
    $s = rtrim(rtrim(number_format((float)$v, 4, '.', ''), '0'), '.');
    return $s === '' ? '0' : $s;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar_adesao') {
        $valorAdesao = parce_valor($_POST['valor_adesao'] ?? '0');
        $conn->query("UPDATE personalizacao SET valor_adesao = " . (float)$valorAdesao . " WHERE id = 1");
        $sucesso = 'Valor de adesão salvo com sucesso!';
    } elseif ($acao === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        $dias = (int)($_POST['dias'] ?? 0);
        $valorPlano = parce_valor($_POST['valor_mensal'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $ordem = (int)($_POST['ordem'] ?? 0);
        if ($nome === '' || $dias <= 0 || $valorPlano <= 0) {
            $erro = 'Preencha nome, dias e o valor do plano.';
        } else {
            $stmt = $conn->prepare("INSERT INTO planos (nome, dias, valor, valor_adesao, valor_mensal, descricao, ativo, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('siddssii', $nome, $dias, $valorPlano, $valorPlano, $valorPlano, $descricao, $ativo, $ordem);
            $stmt->execute();
            $sucesso = 'Plano criado com sucesso!';
        }
    } elseif ($acao === 'salvar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $dias = (int)($_POST['dias'] ?? 0);
        $valorPlano = parce_valor($_POST['valor_mensal'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $ordem = (int)($_POST['ordem'] ?? 0);
        if ($nome === '' || $dias <= 0 || $valorPlano <= 0) {
            $erro = 'Preencha nome, dias e o valor do plano.';
        } else {
            $stmt = $conn->prepare("UPDATE planos SET nome=?, dias=?, valor=?, valor_adesao=?, valor_mensal=?, descricao=?, ativo=?, ordem=? WHERE id=?");
            $stmt->bind_param('siddssiii', $nome, $dias, $valorPlano, $valorPlano, $valorPlano, $descricao, $ativo, $ordem, $id);
            $stmt->execute();
            $sucesso = 'Plano salvo com sucesso!';
        }
    } elseif ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        if ($conn->query("DELETE FROM planos WHERE id = " . (int)$id)) {
            $sucesso = 'Plano excluído com sucesso!';
        } else {
            $erro = 'Não foi possível excluir o plano: ' . $conn->error;
        }
    }
}

// Garante que as colunas novas existam (segurança extra em banco já migrado)
$temAdesao = $conn->query("SHOW COLUMNS FROM planos LIKE 'valor_adesao'")->num_rows > 0;
$temMensal = $conn->query("SHOW COLUMNS FROM planos LIKE 'valor_mensal'")->num_rows > 0;
if (!$temAdesao || !$temMensal) {
    $erro = 'Execute a migração (migracao_planos_adesao.php) para criar as colunas de adesão e valor mensal.';
}

$planos = $conn->query("SELECT * FROM planos ORDER BY ordem ASC, id ASC");

$pers = $conn->query("SELECT valor_adesao FROM personalizacao WHERE id = 1")->fetch_assoc();
$valorAdesaoAtual = fmt_valor($pers['valor_adesao'] ?? 0);
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
<h1 class="text-xl font-extrabold text-gray-800">Planos Recorrentes</h1>
<p class="text-sm text-gray-500">Planos de ativação do cartão Economic Card</p>
</div>
<button onclick="abrirNovo()" class="bg-[#51036d] hover:bg-[#3a024d] text-white rounded-lg px-4 py-2 text-sm font-semibold transition">+ NOVO PLANO</button>
</div>
</header>
<div class="p-6">
<?php if ($sucesso): ?>
<div class="mb-6 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm"><?php echo htmlspecialchars($sucesso); ?></div>
<?php endif; ?>
<?php if ($erro): ?>
<div class="mb-6 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<div class="mb-6 flex gap-2 border-b border-gray-200">
<button type="button" id="tabPlanoBtn" onclick="alternarAba('planos')" class="px-5 py-3 text-sm font-bold uppercase text-[#51036d] border-b-2 border-[#51036d] transition">Planos Recorrentes</button>
<button type="button" id="tabAdesaoBtn" onclick="alternarAba('adesao')" class="px-5 py-3 text-sm font-bold uppercase text-gray-500 border-b-2 border-transparent hover:text-gray-700 transition">Adesão</button>
</div>

<div id="abPlanos">
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
<?php while ($p = $planos->fetch_assoc()): ?>
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
<div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-xl bg-[#51036d]/10 text-[#51036d] flex items-center justify-center"><span class="material-symbols-outlined">credit_score</span></div>
<div>
<h2 class="text-lg font-extrabold text-gray-800"><?php echo htmlspecialchars($p['nome']); ?></h2>
<p class="text-xs text-gray-500">#<?php echo $p['id']; ?> · <?php echo (int)$p['dias']; ?> dias</p>
</div>
</div>
<div class="flex items-center gap-2">
<?php if ($p['ativo']): ?>
<span class="px-2 py-1 rounded-full bg-[#b6f570]/40 text-[#3e6a00] text-[10px] font-bold uppercase">Ativo</span>
<?php else: ?>
<span class="px-2 py-1 rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold uppercase">Inativo</span>
<?php endif; ?>
</div>
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
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Valor do plano (R$) *</label>
<input name="valor_mensal" type="number" step="0.0001" min="0.0001" value="<?php echo fmt_valor($p['valor_mensal']); ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
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
<button type="button" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold px-4 py-2.5 rounded-lg transition btn-excluir" title="Excluir plano" data-id="<?php echo (int)$p['id']; ?>" data-nome="<?php echo htmlspecialchars($p['nome'], ENT_QUOTES); ?>">EXCLUIR</button>
</div>
</form>
</div>
<?php endwhile; ?>
<?php if ($planos->num_rows === 0): ?>
<div class="col-span-2 bg-white rounded-xl shadow-sm p-10 text-center text-gray-500">Nenhum plano cadastrado. Clique em "+ NOVO PLANO" para criar o primeiro.</div>
<?php endif; ?>
</div>
</div>

<!-- Aba Adesão -->
<div id="abAdesao" class="hidden">
<div class="bg-white rounded-xl shadow-sm overflow-hidden max-w-xl">
<div class="px-6 py-5 border-b border-gray-100 flex items-center gap-3">
<div class="w-10 h-10 rounded-xl bg-[#3e6a00]/10 text-[#3e6a00] flex items-center justify-center"><span class="material-symbols-outlined">how_to_reg</span></div>
<div>
<h2 class="text-lg font-extrabold text-gray-800">Adesão</h2>
<p class="text-xs text-gray-500">Valor cobrado no momento do cadastro do usuário</p>
</div>
</div>
<form method="POST" action="admin_planos.php" class="p-6 space-y-4">
<input type="hidden" name="acao" value="salvar_adesao"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Valor da adesão (R$)</label>
<input name="valor_adesao" type="number" step="0.0001" min="0" value="<?php echo $valorAdesaoAtual; ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#3e6a00]" placeholder="ex.: 20.00">
<p class="text-xs text-gray-400 mt-1">Deixe 0 (zero) para não cobrar taxa de adesão no cadastro.</p>
</div>
<button class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-5 py-2.5 rounded-lg transition" type="submit">SALVAR ADESÃO</button>
</form>
</div>
</div>

<!-- Modal novo plano -->
<div id="modalNovo" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/50" onclick="fecharNovo()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden max-h-[90vh] overflow-y-auto">
<div class="bg-[#51036d] px-6 py-5 flex items-center justify-between">
<h2 class="text-white font-bold">Criar Novo Plano</h2>
<button onclick="fecharNovo()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<div class="p-6">
<form method="POST" action="admin_planos.php" class="space-y-4">
<input type="hidden" name="acao" value="criar"/>
<input type="hidden" name="ordem" value="0"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome do plano *</label>
<input name="nome" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="ex.: Mensal">
</div>
<div class="grid grid-cols-2 gap-4">
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Dias ativos *</label>
<input name="dias" type="number" min="1" value="30" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Valor do plano (R$) *</label>
<input name="valor_mensal" type="number" step="0.0001" min="0.0001" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="ex.: 30.00">
</div>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Descrição</label>
<input name="descricao" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div class="flex items-center gap-2 mb-4">
<label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
<input type="checkbox" name="ativo" checked class="w-4 h-4 accent-[#3e6a00]"> Plano ativo
</label>
</div>
<div class="flex justify-end gap-3">
<button type="button" onclick="fecharNovo()" class="px-5 py-2.5 rounded-lg font-semibold bg-gray-200 hover:bg-gray-300 text-gray-700 transition">Cancelar</button>
<button type="submit" class="px-5 py-2.5 rounded-lg font-bold bg-[#51036d] hover:bg-[#3a024d] text-white transition">CRIAR PLANO</button>
</div>
</form>
</div>
</div>
</div>

<form id="formExcluir" method="POST" action="admin_planos.php">
<input type="hidden" name="acao" value="excluir"/>
<input type="hidden" name="id" id="excluirId"/>
</form>

</div>
</main>

<div id="modalConfirm" class="fixed inset-0 z-[60] hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/50" onclick="fecharConfirm()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
<div class="bg-red-600 px-6 py-5">
<h2 class="text-white font-bold">Excluir plano</h2>
</div>
<div class="p-6">
<p id="confirmMsg" class="text-sm text-gray-600 mb-6">Tem certeza que deseja excluir este plano?</p>
<div class="flex gap-3">
<button onclick="fecharConfirm()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2.5 rounded-lg transition">Cancelar</button>
<button onclick="confirmar()" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold px-4 py-2.5 rounded-lg transition">Excluir</button>
</div>
</div>
</div>
</div>

<script>
function alternarAba(aba) {
    var mostraPlano = aba === 'planos';
    document.getElementById('abPlanos').classList.toggle('hidden', !mostraPlano);
    document.getElementById('abAdesao').classList.toggle('hidden', mostraPlano);
    var tbP = document.getElementById('tabPlanoBtn');
    var tbA = document.getElementById('tabAdesaoBtn');
    tbP.className = 'px-5 py-3 text-sm font-bold uppercase ' + (mostraPlano ? 'text-[#51036d] border-b-2 border-[#51036d]' : 'text-gray-500 border-b-2 border-transparent hover:text-gray-700') + ' transition';
    tbA.className = 'px-5 py-3 text-sm font-bold uppercase ' + (!mostraPlano ? 'text-[#3e6a00] border-b-2 border-[#3e6a00]' : 'text-gray-500 border-b-2 border-transparent hover:text-gray-700') + ' transition';
}
function abrirNovo() {
    document.getElementById('modalNovo').classList.remove('hidden');
    document.getElementById('modalNovo').classList.add('flex');
}
function fecharNovo() {
    document.getElementById('modalNovo').classList.add('hidden');
    document.getElementById('modalNovo').classList.remove('flex');
}
let pendenteId = null;
function excluirPlano(id, nome) {
    pendenteId = id;
    document.getElementById('confirmMsg').textContent = 'Tem certeza que deseja excluir o plano "' + nome + '"?';
    document.getElementById('modalConfirm').classList.remove('hidden');
    document.getElementById('modalConfirm').classList.add('flex');
}
function fecharConfirm() {
    document.getElementById('modalConfirm').classList.add('hidden');
    document.getElementById('modalConfirm').classList.remove('flex');
    pendenteId = null;
}
function confirmar() {
    if (pendenteId) {
        document.getElementById('excluirId').value = pendenteId;
        document.getElementById('formExcluir').submit();
    }
}
document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.btn-excluir') : null;
    if (btn) {
        e.preventDefault();
        excluirPlano(btn.getAttribute('data-id'), btn.getAttribute('data-nome') || 'este plano');
    }
});
</script>
</body>
</html>
