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
        $comissao = (float)str_replace(',', '.', $_POST['comissao'] ?? '0');
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
                $stmt = $conn->prepare("INSERT INTO afiliados (codigo, nome, email, telefone, cpf, senha, comissao, token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssssss', $codigo, $nome, $email, $telefone, $cpf, $hash, $comissao, $token);
                $stmt->execute();
                registrar_aceite_contrato($conn, 'afiliados', $conn->insert_id);
                $sucesso = 'Afiliado cadastrado! Código colaborador: ' . $codigo . '. Ele já pode acessar o painel em /card/afiliado.';
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

$afiliados = $conn->query("SELECT a.*, (SELECT COUNT(*) FROM usuarios u WHERE u.afiliado_token = a.token AND u.cartao_ativo = 1) AS ativos FROM afiliados a ORDER BY a.id DESC");
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/card/';
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
<p class="text-sm text-gray-500">Cadastro de vendedores para comissionamento</p>
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
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Comissão (%)</label>
<input name="comissao" value="0" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Telefone / WhatsApp</label>
<input name="telefone" id="telefone" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">CPF</label>
<input name="cpf" id="cpf" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div class="md:col-span-2">
<button class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition" type="submit">CADASTRAR AFILIADO</button>
</div>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
<h2 class="px-6 py-4 text-lg font-extrabold text-gray-800 border-b border-gray-100">Afiliados cadastrados</h2>
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead>
<tr class="bg-gray-50 text-gray-600 text-left uppercase text-xs tracking-wider">
<th class="px-4 py-3">#</th>
<th class="px-4 py-3">Nome</th>
<th class="px-4 py-3">Código Colaborador</th>
<th class="px-4 py-3">E-mail</th>
<th class="px-4 py-3">Link de Indicação</th>
<th class="px-4 py-3">Comissão</th>
<th class="px-4 py-3">Ativos</th>
<th class="px-4 py-3">Status</th>
<th class="px-4 py-3 text-right">Ações</th>
</tr>
</thead>
<tbody>
<?php if ($afiliados->num_rows === 0): ?>
<tr><td colspan="9" class="px-4 py-10 text-center text-gray-500">Nenhum afiliado cadastrado.</td></tr>
<?php endif; ?>
<?php while ($a = $afiliados->fetch_assoc()): ?>
<tr class="border-t border-gray-100 hover:bg-gray-50">
<td class="px-4 py-3 text-gray-500"><?php echo $a['id']; ?></td>
<td class="px-4 py-3">
<p class="font-semibold text-gray-800"><?php echo htmlspecialchars($a['nome']); ?></p>
<?php if ($a['telefone']): ?><p class="text-xs text-gray-500"><?php echo htmlspecialchars($a['telefone']); ?></p><?php endif; ?>
</td>
<td class="px-4 py-3">
<div class="flex items-center gap-2">
<code class="text-sm font-extrabold tracking-widest text-[#51036d]"><?php echo htmlspecialchars($a['codigo']); ?></code>
<button type="button" class="text-[#51036d] hover:underline text-xs font-semibold shrink-0" onclick="copiarLink('<?php echo htmlspecialchars($a['codigo']); ?>', this)">Copiar</button>
</div>
</td>
<td class="px-4 py-3"><?php echo htmlspecialchars($a['email']); ?></td>
<td class="px-4 py-3">
<div class="flex items-center gap-2">
<code class="text-[11px] text-gray-500 break-all"><?php echo htmlspecialchars($baseUrl . 'cadastro.php?afiliado=' . $a['token']); ?></code>
<button type="button" class="text-[#51036d] hover:underline text-xs font-semibold shrink-0" onclick="copiarLink('<?php echo htmlspecialchars($baseUrl . 'cadastro.php?afiliado=' . $a['token']); ?>', this)">Copiar</button>
</div>
</td>
<td class="px-4 py-3 font-bold text-[#3e6a00]"><?php echo number_format((float)$a['comissao'], 0, ',', '.') . '%'; ?></td>
<td class="px-4 py-3 font-bold text-[#51036d]"><?php echo (int)$a['ativos']; ?></td>
<td class="px-4 py-3">
<span class="px-2 py-1 rounded-full <?php echo $a['ativo'] ? 'bg-[#b6f570]/40 text-[#3e6a00]' : 'bg-gray-200 text-gray-600'; ?> text-[10px] font-bold uppercase"><?php echo $a['ativo'] ? 'Ativo' : 'Inativo'; ?></span>
</td>
<td class="px-4 py-3 text-right space-x-3">
<form method="POST" action="admin_afiliados.php" class="inline">
<input type="hidden" name="acao" value="logar"/>
<input type="hidden" name="id" value="<?php echo $a['id']; ?>"/>
<button class="inline-flex items-center gap-1 text-xs font-semibold text-[#51036d] hover:text-[#3a024d] bg-[#51036d]/10 hover:bg-[#51036d]/20 rounded-lg px-2 py-1 transition" type="submit" title="Logar como afiliado e abrir o painel dele">
<span class="material-symbols-outlined text-[16px]">login</span> Logar
</button>
</form>
<form method="POST" action="admin_afiliados.php" class="inline">
<input type="hidden" name="acao" value="alternar"/>
<input type="hidden" name="id" value="<?php echo $a['id']; ?>"/>
<button class="text-[#51036d] hover:underline font-semibold" type="submit"><?php echo $a['ativo'] ? 'Desativar' : 'Ativar'; ?></button>
</form>
<form method="POST" action="admin_afiliados.php" class="inline" onsubmit="confirmarExclusao(event, this, 'Excluir este afiliado? Esta ação não pode ser desfeita.');">
<input type="hidden" name="acao" value="excluir"/>
<input type="hidden" name="id" value="<?php echo $a['id']; ?>"/>
<button class="text-red-600 hover:text-red-800 font-semibold" type="submit">Excluir</button>
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
function copiarLink(texto, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(() => {
            btn.textContent = 'Copiado!';
            setTimeout(() => btn.textContent = 'Copiar', 2000);
        });
    } else {
        const ta = document.createElement('textarea');
        ta.value = texto;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        btn.textContent = 'Copiado!';
        setTimeout(() => btn.textContent = 'Copiar', 2000);
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
