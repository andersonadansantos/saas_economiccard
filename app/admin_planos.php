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
    // Formatação brasileira com vírgula decimal (ex.: 1.234,56)
    if (strpos($v, ',') !== false) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return (float)$v;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar_plano') {
        $valorAdesao = parce_valor($_POST['valor_adesao'] ?? '0');
        $valorMensal = parce_valor($_POST['valor_mensal'] ?? '0');
        $qtdMensalidades = (int)($_POST['qtd_mensalidades'] ?? PLANO_QTD_MENSALIDADES);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $plano = plano_mensal($conn);
        if (!$plano) {
            $erro = 'Plano Mensal não encontrado. Execute a migração do sistema.';
        } elseif ($valorAdesao <= 0) {
            $erro = 'Informe um valor de adesão maior que zero.';
        } elseif ($valorMensal <= 0) {
            $erro = 'Informe um valor de mensalidade maior que zero.';
        } elseif ($qtdMensalidades < 1 || $qtdMensalidades > 24) {
            $erro = 'A quantidade de mensalidades deve ser entre 1 e 24.';
        } else {
            $stmt = $conn->prepare("UPDATE planos SET valor_adesao = ?, valor_mensal = ?, valor = ?, qtd_mensalidades = ?, ativo = ?, dias = 30 WHERE id = ?");
            $idPlano = (int)$plano['id'];
            $stmt->bind_param('dddiii', $valorAdesao, $valorMensal, $valorMensal, $qtdMensalidades, $ativo, $idPlano);
            $stmt->execute();
            $sucesso = 'Configuração do plano salva com sucesso!';
        }
    }
}

$plano = plano_mensal($conn);
$valores = plano_valores_atuais($conn);
$valorAdesao = (float)$valores['adesao'];
$valorMensal = (float)$valores['mensal'];
$qtdMensalidades = (int)$valores['qtd'];
$totalCobrancas = (int)$valores['total_cobrancas'];
$planoAtivo = (int)($plano['ativo'] ?? 0) === 1;
$total = (float)$valores['total'];

$qtdContratos = 0;
$qtdConcluidos = 0;
$qtdAndamento = 0;
$r = @$conn->query("SELECT
    COUNT(*) AS total,
    SUM(status = 'concluido') AS concluidos,
    SUM(status = 'em_andamento') AS andamento
    FROM planos_contratos");
if ($r && ($x = $r->fetch_assoc())) {
    $qtdContratos = (int)$x['total'];
    $qtdConcluidos = (int)$x['concluidos'];
    $qtdAndamento = (int)$x['andamento'];
}

function moeda($v) {
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
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
<div class="px-6 py-4">
<h1 class="text-xl font-extrabold text-gray-800">Planos Recorrentes</h1>
<p class="text-sm text-gray-500">Plano mensal: adesão + <?php echo $qtdMensalidades; ?> mensalidade<?php echo $qtdMensalidades === 1 ? '' : 's'; ?> · <?php echo $totalCobrancas; ?> cobranças · ativação por 36 meses</p>
</div>
</header>
<div class="p-6 max-w-5xl">

<?php if ($sucesso): ?>
<div class="mb-6 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm"><?php echo htmlspecialchars($sucesso); ?></div>
<?php endif; ?>
<?php if ($erro): ?>
<div class="mb-6 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
<div class="bg-white rounded-xl shadow-sm p-4">
<p class="text-[11px] uppercase font-bold text-gray-400">Contratos</p>
<p class="text-2xl font-extrabold text-gray-800"><?php echo $qtdContratos; ?></p>
<p class="text-xs text-gray-500">total de ciclos</p>
</div>
<div class="bg-white rounded-xl shadow-sm p-4">
<p class="text-[11px] uppercase font-bold text-gray-400">Em andamento</p>
<p class="text-2xl font-extrabold text-[#51036d]"><?php echo $qtdAndamento; ?></p>
<p class="text-xs text-gray-500">cobranças ativas</p>
</div>
<div class="bg-white rounded-xl shadow-sm p-4">
<p class="text-[11px] uppercase font-bold text-gray-400">Concluídos</p>
<p class="text-2xl font-extrabold text-[#3e6a00]"><?php echo $qtdConcluidos; ?></p>
<p class="text-xs text-gray-500">ciclos quitados</p>
</div>
<div class="bg-white rounded-xl shadow-sm p-4">
<p class="text-[11px] uppercase font-bold text-gray-400">Total do ciclo</p>
<p class="text-2xl font-extrabold text-gray-800"><?php echo moeda($total); ?></p>
<p class="text-xs text-gray-500">adesão + <?php echo $qtdMensalidades; ?> mensalidade<?php echo $qtdMensalidades === 1 ? '' : 's'; ?></p>
</div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

<div class="lg:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden">
<div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-xl bg-[#51036d]/10 text-[#51036d] flex items-center justify-center"><span class="material-symbols-outlined">credit_score</span></div>
<div>
<h2 class="text-lg font-extrabold text-gray-800"><?php echo htmlspecialchars($plano['nome'] ?? 'Mensal'); ?></h2>
<p class="text-xs text-gray-500">Plano único do sistema · #<?php echo (int)($plano['id'] ?? 0); ?></p>
</div>
</div>
<?php if ($planoAtivo): ?>
<span class="px-2 py-1 rounded-full bg-[#b6f570]/40 text-[#3e6a00] text-[10px] font-bold uppercase">Ativo</span>
<?php else: ?>
<span class="px-2 py-1 rounded-full bg-red-100 text-red-700 text-[10px] font-bold uppercase">Inativo</span>
<?php endif; ?>
</div>

<form method="POST" action="admin_planos.php" class="p-6 space-y-5">
<input type="hidden" name="acao" value="salvar_plano"/>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Taxa de adesão (R$) *</label>
<input id="valorAdesao" name="valor_adesao" type="number" step="0.01" min="0.01" value="<?php echo number_format($valorAdesao, 2, '.', ''); ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<p class="text-xs text-gray-400 mt-1">Cobrada uma única vez na contratação.</p>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Valor da mensalidade (R$) *</label>
<input id="valorMensal" name="valor_mensal" type="number" step="0.01" min="0.01" value="<?php echo number_format($valorMensal, 2, '.', ''); ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<p class="text-xs text-gray-400 mt-1">Cobrado em cada mensalidade do ciclo.</p>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Quantidade de mensalidades *</label>
<input id="qtdMensalidades" name="qtd_mensalidades" type="number" step="1" min="1" max="24" value="<?php echo (int)$qtdMensalidades; ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<p class="text-xs text-gray-400 mt-1">A 1ª é liberada após a adesão; a última ativa o cartão.</p>
</div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
<div>
<label class="block text-xs font-bold text-gray-500 uppercase mb-1">Total previsto do ciclo</label>
<div id="totalCiclo" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-gray-700 font-semibold"><?php echo moeda($total); ?></div>
</div>
<div>
<label class="block text-xs font-bold text-gray-500 uppercase mb-1">Cobranças do ciclo</label>
<div id="totalCobrancas" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-gray-700 font-semibold"><?php echo $totalCobrancas; ?> cobranças</div>
</div>
<div>
<label class="block text-xs font-bold text-gray-500 uppercase mb-1">Validade após ativação</label>
<div class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-gray-700 font-semibold">36 meses (fixo)</div>
</div>
</div>

<label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
<input type="checkbox" name="ativo" class="w-4 h-4 accent-[#3e6a00]" <?php echo $planoAtivo ? 'checked' : ''; ?>>
Plano ativo (permite novas contratações)
</label>

<div class="flex justify-end">
<button class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-2.5 rounded-lg transition" type="submit">SALVAR CONFIGURAÇÃO</button>
</div>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
<div class="px-6 py-5 border-b border-gray-100">
<h2 class="text-lg font-extrabold text-gray-800">Cobranças do ciclo</h2>
<p class="text-xs text-gray-500">Adesão + <?php echo $qtdMensalidades; ?> mensalidade<?php echo $qtdMensalidades === 1 ? '' : 's'; ?> · <?php echo $totalCobrancas; ?> cobranças · sem renovação automática</p>
</div>
<ul class="divide-y divide-gray-100">
<li class="px-6 py-3 flex items-center justify-between gap-3">
<div>
<p class="text-sm font-bold text-gray-800">Adesão</p>
<p class="text-[11px] text-gray-500">Cobrada no momento da contratação</p>
</div>
<span class="text-sm font-extrabold text-[#51036d] whitespace-nowrap"><?php echo moeda($valorAdesao); ?></span>
</li>
<?php for ($seq = 1; $seq <= $qtdMensalidades; $seq++):
    if ($seq === 1) {
        $descricaoCob = 'Liberada 1 mês após o pagamento da adesão';
    } else {
        $descricaoCob = 'Vence 1 mês após o pagamento da ' . ($seq - 1) . 'ª mensalidade';
    }
    if ($seq === $qtdMensalidades) {
        $descricaoCob .= ' · ativa o cartão por 36 meses';
    }
?>
<li class="px-6 py-3 flex items-center justify-between gap-3">
<div>
<p class="text-sm font-bold text-gray-800"><?php echo $seq . 'ª mensalidade'; ?></p>
<p class="text-[11px] text-gray-500"><?php echo htmlspecialchars($descricaoCob); ?></p>
</div>
<span class="text-sm font-extrabold text-[#51036d] whitespace-nowrap"><?php echo moeda($valorMensal); ?></span>
</li>
<?php endfor; ?>
<li class="px-6 py-3 bg-[#b6f570]/10 flex items-center justify-between">
<span class="text-sm font-extrabold text-[#3e6a00]">Total do ciclo</span>
<span class="text-sm font-extrabold text-[#3e6a00]"><?php echo moeda($total); ?></span>
</li>
</ul>
<div class="px-6 py-4 text-[12px] text-gray-500 leading-relaxed border-t border-gray-100">
A próxima cobrança só é liberada após a confirmação da anterior. Os valores e a quantidade contratados ficam gravados no cadastro de cada cliente e não mudam quando o plano é alterado.
</div>
</div>
</div>

<div class="mt-6 bg-white rounded-xl shadow-sm p-6">
<h2 class="text-sm font-extrabold text-gray-800 uppercase tracking-wide mb-3">Como funciona</h2>
<ul class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[13px] text-gray-600">
<li class="flex gap-2"><span class="material-symbols-outlined text-[#51036d] text-[20px]">looks_one</span> 1ª cobrança: taxa de adesão paga no momento da contratação.</li>
<li class="flex gap-2"><span class="material-symbols-outlined text-[#51036d] text-[20px]">event_repeat</span> Mensalidades consecutivas: cada uma é liberada 1 mês após o pagamento da anterior.</li>
<li class="flex gap-2"><span class="material-symbols-outlined text-[#3e6a00] text-[20px]">verified</span> Plano quitado após a última mensalidade confirmada.</li>
<li class="flex gap-2"><span class="material-symbols-outlined text-[#3e6a00] text-[20px]">calendar_month</span> Cartão ativo por 36 meses contados da data do último pagamento.</li>
<li class="flex gap-2"><span class="material-symbols-outlined text-[#ba1a1a] text-[20px]">block</span> Durante a validade não há novas cobranças nem renovação automática.</li>
<li class="flex gap-2"><span class="material-symbols-outlined text-[#ba1a1a] text-[20px]">lock_clock</span> Ao vencer a validade, o cartão é bloqueado até uma nova contratação manual.</li>
</ul>
</div>

</div>
</main>
<script>
(function () {
    const adesao = document.getElementById('valorAdesao');
    const mensal = document.getElementById('valorMensal');
    const qtd = document.getElementById('qtdMensalidades');
    const totalEl = document.getElementById('totalCiclo');
    const cobEl = document.getElementById('totalCobrancas');
    if (!adesao || !mensal || !qtd || !totalEl) return;
    function fmt(v) {
        return 'R$ ' + (Number(v) || 0).toFixed(2).replace('.', ',');
    }
    function atualizar() {
        const a = parseFloat(adesao.value) || 0;
        const m = parseFloat(mensal.value) || 0;
        const q = Math.max(1, parseInt(qtd.value, 10) || 1);
        totalEl.textContent = fmt(a + (m * q));
        if (cobEl) cobEl.textContent = (q + 1) + ' cobranças';
    }
    [adesao, mensal, qtd].forEach(function (el) { el.addEventListener('input', atualizar); });
})();
</script>
</body>
</html>
