<?php
require_once 'config.php';

$erro = '';
$sucesso = '';
$codigoGerado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $cpf = preg_replace('/\D/', '', trim($_POST['cpf'] ?? ''));
    $nascimento = trim($_POST['nascimento'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $cpfMascarado = '';
    if (strlen($cpf) === 11) {
        $cpfMascarado = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    } else {
        $cpfMascarado = $cpf;
    }

    if ($nome === '' || $email === '' || $senha === '' || $nascimento === '' || !cpf_valido($cpf)) {
        $erro = 'Preencha nome, e-mail, WhatsApp, CPF, data de nascimento e senha. Verifique se o CPF digitado é válido.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif (empty($_POST['aceite_contrato'])) {
        $erro = 'Você precisa aceitar o Contrato de Vendas por Comissão para concluir o cadastro.';
    } else {
        $check = $conn->prepare("SELECT id FROM afiliados WHERE email = ? OR cpf = ?");
        $check->bind_param('ss', $email, $cpfMascarado);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $erro = 'Já existe um afiliado com este e-mail ou CPF.';
        } else {
            $token = bin2hex(random_bytes(16));
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            do {
                $codigo = (string)random_int(1000, 9999);
                $ck = $conn->prepare("SELECT id FROM afiliados WHERE codigo = ?");
                $ck->bind_param('s', $codigo);
                $ck->execute();
            } while ($ck->get_result()->num_rows > 0);
            $stmt = $conn->prepare("INSERT INTO afiliados (codigo, nome, email, telefone, cpf, nascimento, senha, comissao, token) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)");
            $stmt->bind_param('ssssssss', $codigo, $nome, $email, $whatsapp, $cpfMascarado, $nascimento, $hash, $token);
            if ($stmt->execute()) {
                registrar_aceite_contrato($conn, 'afiliados', $conn->insert_id);
                $codigoGerado = $codigo;
                $sucesso = 'Cadastro realizado com sucesso! Anote seu código colaborador: <b>' . $codigo . '</b>.';
            } else {
                $erro = 'Erro ao salvar o cadastro. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cadastro de Afiliado - Economic Card</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Manrope', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .premium-gradient { background: linear-gradient(135deg, #51036d 0%, #6a2585 55%, #8e3fae 100%); }
</style>
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
<div class="w-full max-w-md">
<div class="premium-gradient rounded-2xl p-8 shadow-lg mb-6">
<div class="text-center text-white">
<div class="w-14 h-14 mx-auto rounded-2xl bg-white/15 flex items-center justify-center mb-4">
<span class="material-symbols-outlined text-white text-3xl">handshake</span>
</div>
<h1 class="text-xl font-extrabold">Seja um Afiliado</h1>
<p class="text-sm text-white/70 mt-1">Venda assinaturas Economic Card e ganhe comissão.</p>
</div>
</div>
<div class="bg-white rounded-2xl shadow-sm p-8">
<?php if ($sucesso): ?>
<div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm"><?php echo $sucesso; ?></div>
<div class="text-center">
<a href="afiliado/index.php" class="inline-block bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition">ACESSAR PAINEL DO AFILIADO</a>
</div>
<?php else: ?>
<?php if ($erro): ?>
<div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>
<form method="POST" action="cadastro_afiliado.php" class="space-y-4">
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome completo *</label>
<input name="nome" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">E-mail *</label>
<input name="email" type="email" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">WhatsApp *</label>
<input name="whatsapp" id="whatsapp" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="(00) 00000-0000">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">CPF *</label>
<input name="cpf" id="cpf" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="000.000.000-00">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Data de nascimento *</label>
<input name="nascimento" id="nascimento" type="date" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Senha do painel *</label>
<input name="senha" type="password" required minlength="6" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Mínimo 6 caracteres">
</div>
<label class="flex items-start gap-3 cursor-pointer select-none bg-gray-50 border border-gray-200 rounded-lg p-3">
<input type="checkbox" id="aceiteContrato" name="aceite_contrato" value="1" required class="mt-1 w-5 h-5 accent-[#3e6a00]">
<span class="text-sm text-gray-700">
Li e aceito o
<button type="button" onclick="abrirContrato()" class="text-[#51036d] font-bold underline ml-1">Contrato Economic Card Vendas por Comissão</button>
</span>
</label>
<button type="submit" class="w-full bg-[#51036d] hover:bg-[#3a024d] text-white font-bold py-3 rounded-lg transition">CRIAR CONTA DE AFILIADO</button>
</form>
<p class="text-center mt-4 text-sm text-gray-500">Já é afiliado? <a href="afiliado/index.php" class="text-[#51036d] font-bold hover:underline">Entrar no painel</a></p>
<?php endif; ?>
</div>
</div>
</div>
<div id="modalContrato" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/60" onclick="fecharContrato()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl h-[85vh] overflow-hidden flex flex-col">
<div class="bg-[#51036d] px-6 py-4 flex items-center justify-between shrink-0">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-xl bg-white/15 flex items-center justify-center"><span class="material-symbols-outlined text-white">description</span></div>
<h2 class="text-white font-bold">Contrato Economic Card Vendas por Comissão</h2>
</div>
<button onclick="fecharContrato()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<iframe id="modalContratoFrame" src="gerar_contrato_afiliado.php?preview=1" class="flex-1 w-full bg-gray-100" type="application/pdf"></iframe>
<div class="shrink-0 border-t border-gray-200 p-4 flex items-center justify-between gap-4 bg-white">
<div class="flex items-center gap-2 text-sm text-gray-700">
<span class="material-symbols-outlined text-[#3e6a00]">verified_user</span>
<span>Clique em <b>Aceitar</b> para concordar com os termos.</span>
</div>
<button onclick="aceitarContrato()" class="shrink-0 bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-2.5 rounded-lg transition flex items-center gap-2">
<span class="material-symbols-outlined text-[18px]">check</span> ACEITAR CONTRATO
</button>
</div>
</div>
</div>
<script>
const tel = document.getElementById('whatsapp');
if (tel) tel.addEventListener('input', (e) => {
    let v = e.target.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 10) v = v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    else if (v.length > 6) v = v.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    else if (v.length > 2) v = v.replace(/(\d{2})(\d{1,4})/, '($1) $2');
    e.target.value = v;
});
const cpf = document.getElementById('cpf');
if (cpf) cpf.addEventListener('input', (e) => {
    let v = e.target.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
    else if (v.length > 3) v = v.replace(/(\d{3})(\d{3})/, '$1.$2');
    e.target.value = v;
});
const aceiteContrato = document.getElementById('aceiteContrato');
function abrirContrato() {
    const iframe = document.getElementById('modalContratoFrame');
    const nome = encodeURIComponent((document.querySelector('input[name=nome]') || {}).value || '');
    const cpf = encodeURIComponent((document.querySelector('input[name=cpf]') || {}).value || '');
    if (nome || cpf) iframe.src = 'gerar_contrato_afiliado.php?preview=1&nome=' + nome + '&cpf=' + cpf;
    document.getElementById('modalContrato').classList.remove('hidden');
    document.getElementById('modalContrato').classList.add('flex');
}
function fecharContrato() {
    document.getElementById('modalContrato').classList.add('hidden');
    document.getElementById('modalContrato').classList.remove('flex');
}
function aceitarContrato() {
    aceiteContrato.checked = true;
    fecharContrato();
}
</script>
</body>
</html>
