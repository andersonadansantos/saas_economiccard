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
    if ($acao === 'cadastrar') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $cpf = trim($_POST['cpf'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $valorFixo = round((float)str_replace(',', '.', $_POST['valor_fixo_mensalidade'] ?? '0'), 2);
        if ($valorFixo < 0) { $valorFixo = 0.0; }
        $wallet = trim($_POST['wallet_afiliado'] ?? '');
        if ($nome === '' || $email === '' || $senha === '') {
            $erro = 'Preencha nome, e-mail e senha do vendedor.';
        } else {
            $check = $conn->prepare("SELECT id FROM afiliados WHERE email = ?");
            $check->bind_param('s', $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $erro = 'Já existe um afiliado com este e-mail.';
            } else {
                $token = bin2hex(random_bytes(16));
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                do {
                    $codigo = (string)random_int(1000, 9999);
                    $ck = $conn->prepare("SELECT id FROM afiliados WHERE codigo = ?");
                    $ck->bind_param('s', $codigo);
                    $ck->execute();
                } while ($ck->get_result()->num_rows > 0);
                $stmt = $conn->prepare("INSERT INTO afiliados (codigo, nome, email, telefone, cpf, senha, valor_fixo_mensalidade, wallet_afiliado, token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssssdss', $codigo, $nome, $email, $telefone, $cpf, $hash, $valorFixo, $wallet, $token);
                $stmt->execute();
                registrar_aceite_contrato($conn, 'afiliados', $conn->insert_id);
                $sucesso = 'Afiliado cadastrado! Código colaborador: ' . $codigo . '. Ele já pode acessar o painel em /card/afiliado.';
            }
        }
    } elseif ($acao === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $cpf = trim($_POST['cpf'] ?? '');
        $nascimento = trim($_POST['nascimento'] ?? '');
        if ($nascimento === '') { $nascimento = null; }
        $valorFixo = round((float)str_replace(',', '.', $_POST['valor_fixo_mensalidade'] ?? '0'), 2);
        if ($valorFixo < 0) { $valorFixo = 0.0; }
        $wallet = trim($_POST['wallet_afiliado'] ?? '');
        $senha = $_POST['senha'] ?? '';
        if ($nome === '' || $email === '') {
            $erro = 'Preencha nome e e-mail do afiliado.';
        } else {
            $check = $conn->prepare("SELECT id FROM afiliados WHERE email = ? AND id <> ?");
            $check->bind_param('si', $email, $id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $erro = 'Já existe outro afiliado com este e-mail.';
            } else {
                if ($senha !== '') {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE afiliados SET nome = ?, email = ?, telefone = ?, cpf = ?, nascimento = ?, valor_fixo_mensalidade = ?, wallet_afiliado = ?, senha = ? WHERE id = ?");
                    $stmt->bind_param('sssssdssi', $nome, $email, $telefone, $cpf, $nascimento, $valorFixo, $wallet, $hash, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE afiliados SET nome = ?, email = ?, telefone = ?, cpf = ?, nascimento = ?, valor_fixo_mensalidade = ?, wallet_afiliado = ? WHERE id = ?");
                    $stmt->bind_param('sssssdsi', $nome, $email, $telefone, $cpf, $nascimento, $valorFixo, $wallet, $id);
                }
                $stmt->execute();
                $sucesso = 'Afiliado atualizado com sucesso!';
            }
        }
    } elseif ($acao === 'alternar') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE afiliados SET ativo = 1 - ativo WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $sucesso = 'Status do afiliado alterado.';
    } elseif ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM afiliados WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $sucesso = 'Afiliado excluído.';
    } elseif ($acao === 'logar') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("SELECT id, nome FROM afiliados WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $afLogar = $stmt->get_result()->fetch_assoc();
        if ($afLogar) {
            $_SESSION['afiliado_id'] = (int)$afLogar['id'];
            $_SESSION['afiliado_nome'] = $afLogar['nome'];
            header('Location: afiliado/painel.php');
            exit;
        }
    }
}

$afiliados = $conn->query("SELECT a.*,
        (SELECT COUNT(*) FROM usuarios u WHERE u.afiliado_token = a.token) AS indicados,
        (SELECT COUNT(*) FROM usuarios u WHERE u.afiliado_token = a.token AND u.cartao_ativo = 1) AS ativos,
        (SELECT COUNT(*) FROM creditos_afiliado c WHERE c.afiliado_id = a.id AND c.status = 'ativo') AS mensalidades,
        (SELECT COALESCE(SUM(c.valor), 0) FROM creditos_afiliado c WHERE c.afiliado_id = a.id AND c.status = 'ativo') AS saldo
    FROM afiliados a ORDER BY a.id DESC");
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . site_base() . '/';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Afiliados - Admin Economic Card</title>
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
<h1 class="text-xl font-extrabold text-gray-800">Afiliados</h1>
<p class="text-sm text-gray-500">Vendedores remunerados por valor fixo por mensalidade paga</p>
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
<div class="flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
<div>
<h2 class="text-lg font-extrabold text-gray-800">Cadastro de afiliados</h2>
<p class="text-sm text-gray-500 mt-1">Compartilhe este link para que vendedores façam o próprio cadastro (Nome, E-mail, WhatsApp, CPF e Senha do painel).</p>
</div>
<a href="cadastro_afiliado.php" target="_blank" class="shrink-0 inline-flex items-center gap-2 text-sm font-bold text-[#51036d] hover:text-[#3a024d] bg-[#51036d]/10 hover:bg-[#51036d]/20 rounded-lg px-4 py-2.5 transition">
<span class="material-symbols-outlined text-[18px]">open_in_new</span> Abrir página
</a>
</div>
<div class="mt-4 flex flex-col sm:flex-row gap-2">
<input id="linkCadastroAfiliado" type="text" readonly value="<?php echo htmlspecialchars($baseUrl . 'cadastro_afiliado.php'); ?>" class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<button type="button" onclick="copiarLink('<?php echo htmlspecialchars($baseUrl . 'cadastro_afiliado.php'); ?>', this)" class="shrink-0 bg-[#51036d] hover:bg-[#3a024d] text-white font-bold px-5 py-2.5 rounded-lg transition text-sm">Copiar link</button>
</div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-4">Cadastrar novo vendedor</h2>
<form method="POST" action="admin_afiliados.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
<input type="hidden" name="acao" value="cadastrar"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome completo *</label>
<input name="nome" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">E-mail (login) *</label>
<input name="email" type="email" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Senha *</label>
<input name="senha" type="text" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Defina a senha de acesso ao painel">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Valor Fixo por Mensalidade (R$)</label>
<input name="valor_fixo_mensalidade" value="0,00" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Ex.: 10,00 — ganho por mensalidade paga de usuário indicado">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Telefone / WhatsApp</label>
<input name="telefone" id="telefone" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">CPF</label>
<input name="cpf" id="cpf" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Wallet ID Afiliado</label>
<input name="wallet_afiliado" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Carteira Asaas para receber os repasses (UUID)">
</div>
<div class="md:col-span-2">
<button class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition" type="submit">CADASTRAR AFILIADO</button>
</div>
</form>
</div>

<!-- ====== Afiliados cadastrados ====== -->
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
<div>
<h2 class="text-lg font-extrabold text-gray-800">Afiliados cadastrados</h2>
<p class="text-sm text-gray-500 mt-0.5">Remuneração, indicações e histórico de cada afiliado.</p>
</div>
<span class="shrink-0 text-xs font-bold text-[#51036d] bg-[#51036d]/10 rounded-full px-3 py-1.5"><?php echo (int)$afiliados->num_rows; ?> no total</span>
</div>

<?php if ($afiliados->num_rows === 0): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
<span class="material-symbols-outlined text-gray-200" style="font-size:56px">group_add</span>
<p class="mt-3 text-sm font-bold text-gray-600">Nenhum afiliado cadastrado ainda</p>
<p class="mt-1 text-xs text-gray-400">Use o formulário acima ou compartilhe o link de cadastro para começar.</p>
</div>
<?php endif; ?>

<div class="space-y-5">
<?php while ($a = $afiliados->fetch_assoc()): ?>
<?php
    // Iniciais do nome para o avatar do card.
    $partesNome = preg_split('/\s+/', trim((string)$a['nome']));
    $iniciais = mb_substr($partesNome[0] ?? 'A', 0, 1);
    if (count($partesNome) > 1) { $iniciais .= mb_substr($partesNome[count($partesNome) - 1], 0, 1); }
    $linkIndicacao = $baseUrl . 'indicacao/' . $a['token'];
    $valorFixo = (float)$a['valor_fixo_mensalidade'];
    $saldoAfiliado = (float)$a['saldo'];
    $temWallet = trim((string)($a['wallet_afiliado'] ?? '')) !== '';
    $dataCadastro = !empty($a['criado_em']) ? date('d/m/Y', strtotime($a['criado_em'])) : '—';
?>
<article class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

    <!-- Identidade -->
    <div class="p-5 sm:p-6 pb-4 flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
        <div class="flex items-center gap-3.5 min-w-0">
            <div class="w-12 h-12 rounded-2xl bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold select-none" title="Afiliado #<?php echo (int)$a['id']; ?>"><?php echo htmlspecialchars($iniciais); ?></div>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <p class="font-extrabold text-gray-800 leading-snug truncate"><?php echo htmlspecialchars($a['nome']); ?></p>
                    <span class="text-[10px] font-bold text-gray-300 shrink-0">#<?php echo (int)$a['id']; ?></span>
                </div>
                <p class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($a['email']); ?></p>
                <?php if (!empty($a['telefone'])): ?>
                <p class="mt-0.5 text-xs text-gray-400 truncate flex items-center gap-1"><span class="material-symbols-outlined text-[13px] text-gray-300">call</span><?php echo htmlspecialchars($a['telefone']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <span class="shrink-0 self-start sm:self-center inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide <?php echo $a['ativo'] ? 'bg-[#b6f570]/40 text-[#3e6a00]' : 'bg-gray-200 text-gray-600'; ?>">
            <span class="w-1.5 h-1.5 rounded-full <?php echo $a['ativo'] ? 'bg-[#3e6a00]' : 'bg-gray-400'; ?>"></span>
            <?php echo $a['ativo'] ? 'Ativo' : 'Inativo'; ?>
        </span>
    </div>

    <!-- Números -->
    <div class="px-5 sm:px-6 pb-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1"><span class="material-symbols-outlined text-[15px] text-gray-400">payments</span> Valor por mensalidade</p>
            <?php if ($valorFixo > 0): ?>
            <p class="text-lg font-extrabold text-gray-800 leading-tight">R$ <?php echo number_format($valorFixo, 2, ',', '.'); ?></p>
            <?php else: ?>
            <p class="text-lg font-extrabold text-gray-300 leading-tight">Não definido</p>
            <?php endif; ?>
        </div>
        <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1"><span class="material-symbols-outlined text-[15px] text-gray-400">group</span> Usuários indicados</p>
            <p class="text-lg font-extrabold text-gray-800 leading-tight"><?php echo (int)$a['indicados']; ?></p>
            <p class="text-[11px] text-gray-400 leading-tight mt-0.5"><?php echo (int)$a['ativos']; ?> ativo<?php echo (int)$a['ativos'] === 1 ? '' : 's'; ?></p>
        </div>
        <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1"><span class="material-symbols-outlined text-[15px] text-gray-400">event_repeat</span> Mensalidades pagas</p>
            <p class="text-lg font-extrabold text-gray-800 leading-tight <?php echo (int)$a['mensalidades'] === 0 ? 'text-gray-300' : ''; ?>"><?php echo (int)$a['mensalidades']; ?></p>
        </div>
        <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1"><span class="material-symbols-outlined text-[15px] text-gray-400">account_balance_wallet</span> Total acumulado</p>
            <?php if ($saldoAfiliado > 0): ?>
            <p class="text-lg font-extrabold text-[#3e6a00] leading-tight">R$ <?php echo number_format($saldoAfiliado, 2, ',', '.'); ?></p>
            <?php else: ?>
            <p class="text-lg font-extrabold text-gray-300 leading-tight">R$ 0,00</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Identificação -->
    <div class="px-5 sm:px-6 pb-5 grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Código colaborador</p>
            <div class="flex items-center gap-2 rounded-xl bg-gray-50 border border-gray-100 px-3.5 py-2.5">
                <span class="text-sm font-extrabold tracking-[0.25em] text-[#51036d]"><?php echo htmlspecialchars($a['codigo']); ?></span>
                <button type="button" onclick="copiarLink('<?php echo htmlspecialchars($a['codigo']); ?>', this)" class="ml-auto shrink-0 inline-flex items-center gap-1 text-[11px] font-bold text-[#51036d] hover:text-[#3a024d]">
                    <span class="material-symbols-outlined text-[14px]">content_copy</span><span class="rotulo-copia">Copiar</span>
                </button>
            </div>
        </div>
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Link de indicação</p>
            <div class="flex items-center gap-2 rounded-xl bg-gray-50 border border-gray-100 px-3.5 py-2.5 min-w-0">
                <span class="text-xs text-gray-500 truncate flex-1 min-w-0" title="<?php echo htmlspecialchars($linkIndicacao); ?>"><?php echo htmlspecialchars($linkIndicacao); ?></span>
                <button type="button" onclick="copiarLink('<?php echo htmlspecialchars($linkIndicacao); ?>', this)" class="shrink-0 inline-flex items-center gap-1 text-[11px] font-bold text-[#51036d] hover:text-[#3a024d]">
                    <span class="material-symbols-outlined text-[14px]">content_copy</span><span class="rotulo-copia">Copiar</span>
                </button>
            </div>
        </div>
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Wallet ID Asaas</p>
            <div class="flex items-center gap-2 rounded-xl bg-gray-50 border border-gray-100 px-3.5 py-2.5 min-w-0">
                <?php if ($temWallet): ?>
                <span class="text-xs text-gray-600 truncate flex-1 min-w-0 font-mono"><?php echo htmlspecialchars($a['wallet_afiliado']); ?></span>
                <?php else: ?>
                <span class="text-xs font-semibold text-yellow-700 flex-1 min-w-0">Pendente — sem repasse automático no split</span>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Cadastrado em</p>
            <div class="flex items-center gap-2 rounded-xl bg-gray-50 border border-gray-100 px-3.5 py-2.5">
                <span class="material-symbols-outlined text-[15px] text-gray-400">calendar_month</span>
                <span class="text-sm text-gray-600 font-semibold"><?php echo $dataCadastro; ?></span>
            </div>
        </div>
    </div>

    <!-- Ações -->
    <div class="px-5 sm:px-6 py-4 bg-gray-50/70 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center gap-2.5 justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button"
            data-id="<?php echo (int)$a['id']; ?>"
            data-nome="<?php echo htmlspecialchars($a['nome'], ENT_QUOTES); ?>"
            data-email="<?php echo htmlspecialchars($a['email'], ENT_QUOTES); ?>"
            data-telefone="<?php echo htmlspecialchars($a['telefone'] ?? '', ENT_QUOTES); ?>"
            data-cpf="<?php echo htmlspecialchars($a['cpf'] ?? '', ENT_QUOTES); ?>"
            data-nascimento="<?php echo htmlspecialchars($a['nascimento'] ?? '', ENT_QUOTES); ?>"
            data-wallet="<?php echo htmlspecialchars($a['wallet_afiliado'] ?? '', ENT_QUOTES); ?>"
            data-valor-fixo="<?php echo number_format((float)($a['valor_fixo_mensalidade'] ?? 0), 2, ',', '.'); ?>"
            onclick="abrirEditarAfiliado(this)"
            class="inline-flex items-center gap-1.5 text-xs font-bold text-[#51036d] hover:text-[#3a024d] bg-[#51036d]/10 hover:bg-[#51036d]/20 rounded-lg px-3 py-2 transition" title="Editar informações do afiliado">
                <span class="material-symbols-outlined text-[16px]">edit</span> Editar
            </button>
            <form method="POST" action="admin_afiliados.php" class="inline">
                <input type="hidden" name="acao" value="logar"/>
                <input type="hidden" name="id" value="<?php echo $a['id']; ?>"/>
                <button class="inline-flex items-center gap-1.5 text-xs font-bold text-[#51036d] hover:text-[#3a024d] bg-[#51036d]/10 hover:bg-[#51036d]/20 rounded-lg px-3 py-2 transition" type="submit" title="Logar como afiliado e abrir o painel dele">
                    <span class="material-symbols-outlined text-[16px]">login</span> Logar
                </button>
            </form>
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            <form method="POST" action="admin_afiliados.php" class="inline">
                <input type="hidden" name="acao" value="alternar"/>
                <input type="hidden" name="id" value="<?php echo $a['id']; ?>"/>
                <button class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-600 hover:text-gray-800 bg-gray-200/70 hover:bg-gray-200 rounded-lg px-3 py-2 transition" type="submit">
                    <span class="material-symbols-outlined text-[16px]"><?php echo $a['ativo'] ? 'pause_circle' : 'play_circle'; ?></span>
                    <?php echo $a['ativo'] ? 'Desativar' : 'Ativar'; ?>
                </button>
            </form>
            <form method="POST" action="admin_afiliados.php" class="inline" onsubmit="confirmarExclusao(event, this, 'Excluir este afiliado? Esta ação não pode ser desfeita.');">
                <input type="hidden" name="acao" value="excluir"/>
                <input type="hidden" name="id" value="<?php echo $a['id']; ?>"/>
                <button class="inline-flex items-center gap-1.5 text-xs font-bold text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 border border-red-100 rounded-lg px-3 py-2 transition" type="submit">
                    <span class="material-symbols-outlined text-[16px]">delete</span> Excluir
                </button>
            </form>
        </div>
    </div>
</article>
<?php endwhile; ?>
</div>
</div>
</main>

<div id="modalConfirmacao" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/50" onclick="fecharModalConfirmacao()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
<div class="bg-[#51036d] px-6 py-5 flex items-center justify-between">
<h2 class="text-white font-bold">Confirmar ação</h2>
<button onclick="fecharModalConfirmacao()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<div class="p-6">
<p id="modalConfirmacaoMsg" class="text-sm text-gray-600 mb-6">Tem certeza?</p>
<div class="flex gap-3">
<button onclick="fecharModalConfirmacao()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2.5 rounded-lg transition">Cancelar</button>
<button onclick="confirmarModal()" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold px-4 py-2.5 rounded-lg transition">Confirmar</button>
</div>
</div>
</div>
</div>

<div id="modalEdicaoAfiliado" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/50" onclick="fecharModalEdicaoAfiliado()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden max-h-[92vh] flex flex-col">
<div class="bg-[#51036d] px-6 py-4 flex items-center justify-between">
<h2 class="text-white font-bold flex items-center gap-2"><span class="material-symbols-outlined">edit</span> Editar afiliado</h2>
<button type="button" onclick="fecharModalEdicaoAfiliado()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<form method="POST" action="admin_afiliados.php" class="overflow-y-auto p-6 space-y-4">
<input type="hidden" name="acao" value="editar"/>
<input type="hidden" name="id" id="edit_id"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome completo *</label>
<input id="edit_nome" name="nome" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">E-mail (login) *</label>
<input id="edit_email" name="email" type="email" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Telefone / WhatsApp</label>
<input id="edit_telefone" name="telefone" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">CPF</label>
<input id="edit_cpf" name="cpf" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Data de nascimento</label>
<input id="edit_nascimento" name="nascimento" type="date" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Valor Fixo por Mensalidade (R$)</label>
<input id="edit_valor_fixo" name="valor_fixo_mensalidade" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Ex.: 10,00 — ganho por mensalidade paga de usuário indicado">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Wallet ID Afiliado</label>
<input id="edit_wallet" name="wallet_afiliado" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Carteira Asaas para receber os repasses (UUID)">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nova senha (opcional)</label>
<input id="edit_senha" name="senha" type="text" placeholder="Deixe em branco para manter a atual" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div class="flex gap-3 pt-2">
<button type="button" onclick="fecharModalEdicaoAfiliado()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2.5 rounded-lg transition">Cancelar</button>
<button class="flex-1 bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-4 py-2.5 rounded-lg transition" type="submit">SALVAR ALTERAÇÕES</button>
</div>
</form>
</div>
</div>

<script>
function abrirEditarAfiliado(btn) {
    document.getElementById('edit_id').value = btn.dataset.id;
    document.getElementById('edit_nome').value = btn.dataset.nome;
    document.getElementById('edit_email').value = btn.dataset.email;
    document.getElementById('edit_telefone').value = btn.dataset.telefone;
    document.getElementById('edit_cpf').value = btn.dataset.cpf;
    document.getElementById('edit_nascimento').value = btn.dataset.nascimento || '';
    document.getElementById('edit_wallet').value = btn.dataset.wallet;
    document.getElementById('edit_valor_fixo').value = btn.dataset.valorFixo || '0,00';
    document.getElementById('edit_senha').value = '';
    const m = document.getElementById('modalEdicaoAfiliado');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function fecharModalEdicaoAfiliado() {
    const m = document.getElementById('modalEdicaoAfiliado');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
</script>

<script>
const tel = document.getElementById('telefone');
if (tel) tel.addEventListener('input', (e) => { e.target.value = e.target.value.replace(/\D/g, '').slice(0, 15); });
const cpf = document.getElementById('cpf');
if (cpf) cpf.addEventListener('input', (e) => {
    let v = e.target.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
    else if (v.length > 3) v = v.replace(/(\d{3})(\d{3})/, '$1.$2');
    e.target.value = v;
});
const editTel = document.getElementById('edit_telefone');
if (editTel) editTel.addEventListener('input', (e) => { e.target.value = e.target.value.replace(/\D/g, '').slice(0, 15); });
const editCpf = document.getElementById('edit_cpf');
if (editCpf) editCpf.addEventListener('input', (e) => {
    let v = e.target.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
    else if (v.length > 3) v = v.replace(/(\d{3})(\d{3})/, '$1.$2');
    e.target.value = v;
});
function copiarLink(texto, btn) {
    // O rótulo pode estar num span próprio (botões com ícone) para o ícone
    // não ser apagado pelo feedback "Copiado!".
    const rotulo = btn.querySelector('.rotulo-copia');
    const alvo = rotulo || btn;
    const original = alvo.textContent;
    const finalizar = () => {
        alvo.textContent = 'Copiado!';
        setTimeout(() => { alvo.textContent = original; }, 2000);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(finalizar);
    } else {
        const ta = document.createElement('textarea');
        ta.value = texto;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        finalizar();
    }
}
let formPendente = null;
function confirmarExclusao(ev, form, msg) {
    ev.preventDefault();
    formPendente = form;
    document.getElementById('modalConfirmacaoMsg').textContent = msg;
    const m = document.getElementById('modalConfirmacao');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function confirmarModal() {
    if (formPendente) formPendente.submit();
    fecharModalConfirmacao();
}
function fecharModalConfirmacao() {
    formPendente = null;
    const m = document.getElementById('modalConfirmacao');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
</script>
</body>
</html>
