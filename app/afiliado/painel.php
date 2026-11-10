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

// Valores da remuneração DESTE vendedor: individual quando definido, senão o
// padrão global configurado pelo administrador (Admin > Afiliados).
$afiliadoCfg = afiliado_config_global($conn);
$valorAdesaoAf = afiliado_valor_parcela($af, 1, $afiliadoCfg);
$valorP2Af = afiliado_valor_parcela($af, 2, $afiliadoCfg);
$valorP3Af = afiliado_valor_parcela($af, 3, $afiliadoCfg);
$valorP4Af = afiliado_valor_parcela($af, 4, $afiliadoCfg);
$totalPorUsuario = $valorAdesaoAf + $valorP2Af + $valorP3Af + $valorP4Af;
$remuneracaoAtiva = (int)$afiliadoCfg['remuneracao_ativa'] === 1;

// Resumo financeiro: saldo disponível, já pago, estornos, adesões e parcelas.
$totais = afiliado_totais_creditos($conn, $aid);

// Histórico de créditos (uma linha por parcela paga de cliente indicado).
$stmtC = $conn->prepare("SELECT c.*, u.nome AS cliente_nome, u.cpf AS cliente_cpf, u.criado_em AS cliente_desde, p.valor AS valor_pago
    FROM creditos_afiliado c
    INNER JOIN usuarios u ON u.id = c.usuario_id
    INNER JOIN pagamentos_pix p ON p.id = c.pagamento_id
    WHERE c.afiliado_id = ?
    ORDER BY c.criado_em DESC, c.id DESC");
$stmtC->bind_param('i', $aid);
$stmtC->execute();
$creditos = $stmtC->get_result();

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

<?php if (!$remuneracaoAtiva): ?>
<div class="mb-6 p-3 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm">
A remuneração automática está temporariamente desativada pelo administrador — novas parcelas pagas não geram comissão enquanto estiver desativada.
</div>
<?php endif; ?>

<!-- ====== Resumo financeiro ====== -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Usuários indicados</p>
<p class="text-3xl font-extrabold text-gray-800"><?php echo $totalVinculados; ?></p>
<p class="text-[11px] text-gray-400 mt-1">cadastrados pelo seu link — <?php echo $totalAtivos; ?> com cartão ativo</p>
</div>
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Adesões pagas</p>
<p class="text-3xl font-extrabold text-[#3e6a00]"><?php echo (int)$totais['adesoes']; ?></p>
<p class="text-[11px] text-gray-400 mt-1">1ªs parcelas que geraram comissão</p>
</div>
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Parcelas seguintes pagas</p>
<p class="text-3xl font-extrabold text-[#3e6a00]"><?php echo (int)$totais['parcelas']; ?></p>
<p class="text-[11px] text-gray-400 mt-1">2ª, 3ª e 4ª parcelas com comissão</p>
</div>
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Total de comissões geradas</p>
<p class="text-3xl font-extrabold text-gray-800">R$ <?php echo number_format((float)$totais['total_gerado'], 2, ',', '.'); ?></p>
<p class="text-[11px] text-gray-400 mt-1">soma das comissões válidas</p>
</div>
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Saldo disponível</p>
<p class="text-3xl font-extrabold text-[#51036d]">R$ <?php echo number_format((float)$totais['saldo'], 2, ',', '.'); ?></p>
<p class="text-[11px] text-gray-400 mt-1">comissões ainda não pagas a você</p>
</div>
<div class="bg-white rounded-2xl shadow-sm p-5">
<p class="text-xs font-bold uppercase text-gray-400 mb-1">Valores já pagos a você</p>
<p class="text-3xl font-extrabold text-[#3e6a00]">R$ <?php echo number_format((float)$totais['pago'], 2, ',', '.'); ?></p>
<p class="text-[11px] text-gray-400 mt-1">registrados pelo administrador</p>
</div>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-6">
<div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
<span class="material-symbols-outlined text-[#51036d]">link</span>
<h2 class="font-extrabold text-gray-900">Seu link de indicação</h2>
</div>
<div class="p-6">
<p class="text-sm text-gray-600 mb-3">Compartilhe este link para vender assinaturas. Você recebe <b class="text-[#3e6a00]">R$ <?php echo number_format($valorAdesaoAf, 2, ',', '.'); ?></b> quando o cliente paga a <b>adesão (1ª parcela)</b> e <b class="text-[#3e6a00]">R$ <?php echo number_format($valorP2Af, 2, ',', '.'); ?></b> em cada uma das <b>parcelas 2ª, 3ª e 4ª</b> — até <b class="text-[#3e6a00]">R$ <?php echo number_format($totalPorUsuario, 2, ',', '.'); ?></b> por cliente com as 4 parcelas pagas.</p>
<div class="flex flex-col sm:flex-row gap-2">
<input id="linkIndicacao" type="text" readonly value="<?php echo htmlspecialchars($linkIndicacao); ?>" class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<button onclick="copiarLink()" class="bg-[#51036d] hover:bg-[#3a024d] text-white font-bold px-5 py-2.5 rounded-lg transition text-sm">Copiar link</button>
</div>
</div>
</div>

<!-- ====== Extrato financeiro ====== -->
<div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-6">
<div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-[#3e6a00]">account_balance_wallet</span>
<h2 class="font-extrabold text-gray-900">Extrato financeiro</h2>
</div>
<span class="text-xs font-bold text-[#3e6a00] bg-[#b6f570]/30 px-2 py-1 rounded-full">Total acumulado: R$ <?php echo number_format((float)$totais['total_gerado'], 2, ',', '.'); ?></span>
</div>
<?php if ((float)$totais['estornado'] > 0): ?>
<div class="px-6 py-3 bg-yellow-50 border-b border-yellow-100 text-xs text-yellow-800">
<b>Estornados:</b> R$ <?php echo number_format((float)$totais['estornado'], 2, ',', '.'); ?> em comissões desfeitas por reembolso ou ajuste de parcelas.
</div>
<?php endif; ?>
<?php if ($creditos->num_rows === 0): ?>
<div class="px-6 py-10 text-center">
<p class="text-sm text-gray-500">Nenhuma comissão registrada ainda. Seus ganhos aparecem aqui automaticamente conforme os clientes indicados pelo seu link pagam a adesão e as parcelas seguintes (2ª, 3ª e 4ª).</p>
</div>
<?php else: ?>
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead>
<tr class="bg-gray-50 text-gray-500 text-left uppercase text-[10px] tracking-wider">
<th class="px-6 py-3">Usuário indicado</th>
<th class="px-4 py-3">Parcela</th>
<th class="px-4 py-3 text-right">Valor pago</th>
<th class="px-4 py-3 text-right">Valor recebido</th>
<th class="px-4 py-3">Confirmação</th>
<th class="px-6 py-3 text-right">Status</th>
</tr>
</thead>
<tbody>
<?php while ($c = $creditos->fetch_assoc()): ?>
<tr class="border-t border-gray-100">
<td class="px-6 py-3">
<p class="font-semibold text-gray-800"><?php echo htmlspecialchars($c['cliente_nome']); ?></p>
<p class="text-[11px] text-gray-400"><?php echo htmlspecialchars($c['cliente_cpf']); ?> • cadastrado em <?php echo date('d/m/Y', strtotime($c['cliente_desde'])); ?></p>
</td>
<td class="px-4 py-3 text-gray-600"><?php echo afiliado_rotulo_parcela($c['parcela'], $c['tipo_cobranca']); ?></td>
<td class="px-4 py-3 text-right text-gray-600">R$ <?php echo number_format((float)$c['valor_pago'], 2, ',', '.'); ?></td>
<td class="px-4 py-3 text-right font-bold <?php echo $c['status'] === 'estornado' ? 'text-gray-400 line-through' : 'text-[#3e6a00]'; ?>">R$ <?php echo number_format((float)$c['valor'], 2, ',', '.'); ?></td>
<td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?php echo date('d/m/Y H:i', strtotime($c['criado_em'])); ?></td>
<td class="px-6 py-3 text-right">
<?php if ($c['status'] === 'ativo'): ?>
<span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase bg-[#b6f570]/40 text-[#3e6a00]">Ativo</span>
<?php elseif ($c['status'] === 'pago'): ?>
<span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase bg-[#51036d]/10 text-[#51036d]">Pago<?php echo !empty($c['pago_em']) ? ' em ' . date('d/m/Y', strtotime($c['pago_em'])) : ''; ?></span>
<?php else: ?>
<span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase bg-gray-200 text-gray-600">Estornado</span>
<?php if (!empty($c['motivo_estorno'])): ?>
<p class="mt-1 text-[10px] text-red-500 max-w-[220px] ml-auto leading-tight"><?php echo htmlspecialchars(mb_strimwidth($c['motivo_estorno'], 0, 90, '…')); ?><?php echo !empty($c['estornado_em']) ? ' (' . date('d/m/Y', strtotime($c['estornado_em'])) . ')' : ''; ?></p>
<?php endif; ?>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
<tfoot>
<tr class="border-t-2 border-gray-200 bg-gray-50">
<td class="px-6 py-3 font-extrabold text-gray-700" colspan="3">Total acumulado</td>
<td class="px-4 py-3 text-right font-extrabold text-[#3e6a00]">R$ <?php echo number_format((float)$totais['total_gerado'], 2, ',', '.'); ?></td>
<td class="px-4 py-3 text-[11px] text-gray-400" colspan="2">disponível R$ <?php echo number_format((float)$totais['saldo'], 2, ',', '.'); ?> • pago R$ <?php echo number_format((float)$totais['pago'], 2, ',', '.'); ?> • estornado R$ <?php echo number_format((float)$totais['estornado'], 2, ',', '.'); ?></td>
</tr>
</tfoot>
</table>
</div>
<?php endif; ?>
</div>

<!-- ====== Clientes do link ====== -->
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

<!-- ====== Seus dados ====== -->
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
