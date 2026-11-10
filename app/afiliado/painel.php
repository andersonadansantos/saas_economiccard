<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/afiliado_creditos.php';
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
$esquema = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');
$linkIndicacao = $esquema . '://' . $_SERVER['HTTP_HOST'] . site_base() . '/indicacao/' . $af['token'];

// Resumo financeiro: saldo a receber, estornos e mensalidades pagas.
$totais = afiliado_totais_creditos($conn, $aid);

// Histórico de créditos (uma linha por mensalidade paga de cliente indicado).
$stmtC = $conn->prepare("SELECT c.*, u.nome AS cliente_nome, u.cpf AS cliente_cpf, p.valor AS valor_pago
    FROM creditos_afiliado c
    INNER JOIN usuarios u ON u.id = c.usuario_id
    INNER JOIN pagamentos_pix p ON p.id = c.pagamento_id
    WHERE c.afiliado_id = ?
    ORDER BY c.criado_em DESC, c.id DESC");
$stmtC->bind_param('i', $aid);
$stmtC->execute();
$creditos = $stmtC->get_result();

function rotulo_mensalidade($tipo) {
    if ($tipo === 'adesao') return 'Adesão (1º pagamento)';
    if ($tipo === '1a_mensalidade') return '1ª mensalidade';
    if ($tipo === 'recorrencia') return 'Recorrência';
    return 'Mensalidade';
}

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
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Valor por mensalidade</p>
<p class="text-3xl font-extrabold text-[#3e6a00]">R$ <?php echo number_format((float)$af['valor_fixo_mensalidade'], 2, ',', '.'); ?></p>
<p class="text-[11px] text-gray-400 mt-1">por mensalidade paga de cliente indicado</p>
</div>
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Saldo a receber</p>
<p class="text-3xl font-extrabold text-[#51036d]">R$ <?php echo number_format((float)$totais['saldo'], 2, ',', '.'); ?></p>
<p class="text-[11px] text-gray-400 mt-1">soma dos créditos ativos</p>
</div>
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Mensalidades pagas</p>
<p class="text-3xl font-extrabold text-gray-800"><?php echo (int)$totais['mensalidades']; ?></p>
<p class="text-[11px] text-gray-400 mt-1">que geraram crédito</p>
</div>
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Clientes ativos</p>
<p class="text-3xl font-extrabold text-[#3e6a00]"><?php echo $totalAtivos; ?></p>
<p class="text-[11px] text-gray-400 mt-1">de <?php echo $totalVinculados; ?> cadastrados pelo seu link</p>
</div>
</div>
<div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-6">
<div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
<span class="material-symbols-outlined text-[#51036d]">link</span>
<h2 class="font-extrabold text-gray-900">Seu link de indicação</h2>
</div>
<div class="p-6">
<p class="text-sm text-gray-600 mb-3">Compartilhe este link para vender assinaturas. Cada mensalidade paga por cliente que se cadastrar pelo seu link gera <b class="text-[#3e6a00]">R$ <?php echo number_format((float)$af['valor_fixo_mensalidade'], 2, ',', '.'); ?></b> para você — inclusive as recorrências seguintes.</p>
<div class="flex flex-col sm:flex-row gap-2">
<input id="linkIndicacao" type="text" readonly value="<?php echo htmlspecialchars($linkIndicacao); ?>" class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<button onclick="copiarLink()" class="bg-[#51036d] hover:bg-[#3a024d] text-white font-bold px-5 py-2.5 rounded-lg transition text-sm">Copiar link</button>
</div>
</div>
</div>
<div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-6">
<div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-[#3e6a00]">account_balance_wallet</span>
<h2 class="font-extrabold text-gray-900">Extrato financeiro</h2>
</div>
<span class="text-xs font-bold text-[#3e6a00] bg-[#b6f570]/30 px-2 py-1 rounded-full">Saldo: R$ <?php echo number_format((float)$totais['saldo'], 2, ',', '.'); ?></span>
</div>
<?php if ((float)$totais['estornado'] > 0): ?>
<div class="px-6 py-3 bg-yellow-50 border-b border-yellow-100 text-xs text-yellow-800">
<b>Estornados:</b> R$ <?php echo number_format((float)$totais['estornado'], 2, ',', '.'); ?> em créditos desfeitos por reembolso de mensalidade.
</div>
<?php endif; ?>
<?php if ($creditos->num_rows === 0): ?>
<div class="px-6 py-10 text-center">
<p class="text-sm text-gray-500">Nenhum crédito registrado ainda. Seus ganhos aparecem aqui automaticamente a cada mensalidade paga por clientes indicados pelo seu link.</p>
</div>
<?php else: ?>
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead>
<tr class="bg-gray-50 text-gray-500 text-left uppercase text-[10px] tracking-wider">
<th class="px-6 py-3">Data</th>
<th class="px-4 py-3">Cliente</th>
<th class="px-4 py-3">Mensalidade</th>
<th class="px-4 py-3 text-right">Valor pago</th>
<th class="px-4 py-3 text-right">Seu ganho</th>
<th class="px-6 py-3 text-right">Status</th>
</tr>
</thead>
<tbody>
<?php while ($c = $creditos->fetch_assoc()): ?>
<tr class="border-t border-gray-100">
<td class="px-6 py-3 text-gray-600 whitespace-nowrap"><?php echo date('d/m/Y H:i', strtotime($c['criado_em'])); ?></td>
<td class="px-4 py-3">
<p class="font-semibold text-gray-800"><?php echo htmlspecialchars($c['cliente_nome']); ?></p>
<p class="text-[11px] text-gray-400"><?php echo htmlspecialchars($c['cliente_cpf']); ?></p>
</td>
<td class="px-4 py-3 text-gray-600"><?php echo rotulo_mensalidade($c['tipo_cobranca']); ?></td>
<td class="px-4 py-3 text-right text-gray-600">R$ <?php echo number_format((float)$c['valor_pago'], 2, ',', '.'); ?></td>
<td class="px-4 py-3 text-right font-bold <?php echo $c['status'] === 'ativo' ? 'text-[#3e6a00]' : 'text-gray-400 line-through'; ?>">R$ <?php echo number_format((float)$c['valor'], 2, ',', '.'); ?></td>
<td class="px-6 py-3 text-right">
<span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase <?php echo $c['status'] === 'ativo' ? 'bg-[#b6f570]/40 text-[#3e6a00]' : 'bg-gray-200 text-gray-600'; ?>"><?php echo $c['status'] === 'ativo' ? 'Ativo' : 'Estornado'; ?></span>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
<?php endif; ?>
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
