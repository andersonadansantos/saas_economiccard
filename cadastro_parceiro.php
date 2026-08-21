<?php
require_once 'config.php';

$erro = '';
$sucesso = '';
$categoriasLista = $conn->query("SELECT DISTINCT categoria FROM parceiros WHERE categoria <> '' ORDER BY categoria");
$categoriasExtra = $conn->query("SELECT nome FROM categorias ORDER BY nome");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $whatsapp = trim(preg_replace('/\D/', '', $_POST['whatsapp'] ?? ''));
    $instagram = trim($_POST['instagram'] ?? '');
    $facebook = trim($_POST['facebook'] ?? '');
    $site = trim($_POST['site'] ?? '');
    $logo = trim($_POST['logo'] ?? '');
    $porcentagem = (float)str_replace(',', '.', $_POST['porcentagem'] ?? '0');
    if (isset($_FILES['logo_file']) && is_array($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['logo_file']['tmp_name'];
        $info = @getimagesize($tmp);
        $exts = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/svg+xml' => 'svg'];
        if ($info !== false && isset($exts[$info['mime']])) {
            $dirUpload = __DIR__ . '/uploads';
            if (!is_dir($dirUpload)) { @mkdir($dirUpload, 0777, true); }
            $nomeArq = 'parceiro_' . time() . '.' . $exts[$info['mime']];
            move_uploaded_file($tmp, $dirUpload . '/' . $nomeArq);
            $logo = 'uploads/' . $nomeArq;
        }
    }
    if ($nome === '') {
        $erro = 'Informe o nome da empresa.';
    } elseif (empty($_POST['aceite_contrato'])) {
        $erro = 'Você precisa aceitar o Contrato de Parceria para concluir o cadastro.';
    } else {
        $stmt = $conn->prepare("INSERT INTO parceiros (nome, categoria, endereco, whatsapp, instagram, facebook, site, logo, porcentagem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssssssd', $nome, $categoria, $endereco, $whatsapp, $instagram, $facebook, $site, $logo, $porcentagem);
        if ($stmt->execute()) {
            registrar_aceite_contrato($conn, 'parceiros', $conn->insert_id);
            $sucesso = 'Cadastro de parceiro enviado com sucesso! Em breve entraremos em contato.';
        } else {
            $erro = 'Erro ao salvar o cadastro. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seja um Parceiro - Economic Card</title>
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
<span class="material-symbols-outlined text-white text-3xl">storefront</span>
</div>
<h1 class="text-xl font-extrabold">Seja um Parceiro</h1>
<p class="text-sm text-white/70 mt-1">Cadastre sua empresa e tenha destaque no app Economic Card.</p>
</div>
</div>
<div class="bg-white rounded-2xl shadow-sm p-8">
<?php if ($sucesso): ?>
<div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm"><?php echo htmlspecialchars($sucesso); ?></div>
<div class="text-center">
<a href="parceiros.php" class="inline-block bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition">VER LOJAS PARCEIRAS</a>
</div>
<?php else: ?>
<?php if ($erro): ?>
<div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>
<form method="POST" action="cadastro_parceiro.php" enctype="multipart/form-data" class="space-y-4">
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome da empresa *</label>
<input name="nome" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Categoria</label>
<input name="categoria" list="listaCategorias" placeholder="Escolha ou digite uma nova..." class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<datalist id="listaCategorias">
<?php if ($categoriasExtra && $categoriasExtra->num_rows > 0): while ($catExtra = $categoriasExtra->fetch_assoc()): ?>
<option value="<?php echo htmlspecialchars($catExtra['nome']); ?>"></option>
<?php endwhile; endif; ?>
<?php while ($catLista = $categoriasLista->fetch_assoc()): ?>
<option value="<?php echo htmlspecialchars($catLista['categoria']); ?>"></option>
<?php endwhile; ?>
<option value="Saúde"></option>
<option value="Gastronomia"></option>
<option value="Educação"></option>
<option value="Beleza"></option>
<option value="Outros"></option>
</datalist>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Endereço</label>
<input name="endereco" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div class="grid grid-cols-2 gap-3">
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">WhatsApp</label>
<input name="whatsapp" id="whatsapp" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="(00) 00000-0000">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Porcentagem (%)</label>
<input name="porcentagem" value="0" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Instagram</label>
<input name="instagram" placeholder="https://instagram.com/..." class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Facebook</label>
<input name="facebook" placeholder="https://facebook.com/..." class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Site</label>
<input name="site" placeholder="https://..." class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Logo da empresa</label>
<input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#51036d] file:text-white file:font-semibold file:cursor-pointer hover:file:bg-[#3a024d]">
</div>
<label class="flex items-start gap-3 cursor-pointer select-none bg-gray-50 border border-gray-200 rounded-lg p-3">
<input type="checkbox" id="aceiteContrato" name="aceite_contrato" value="1" required class="mt-1 w-5 h-5 accent-[#3e6a00]">
<span class="text-sm text-gray-700">
Li e aceito o
<button type="button" onclick="abrirContrato()" class="text-[#51036d] font-bold underline ml-1">Contrato de Parceria Economic Card</button>
</span>
</label>
<button type="submit" class="w-full bg-[#51036d] hover:bg-[#3a024d] text-white font-bold py-3 rounded-lg transition">ENVIAR CADASTRO</button>
</form>
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
<h2 class="text-white font-bold">Contrato de Parceria Economic Card</h2>
</div>
<button onclick="fecharContrato()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<iframe id="modalContratoFrame" src="gerar_contrato_parceiro.php?preview=1" class="flex-1 w-full bg-gray-100" type="application/pdf"></iframe>
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
const wa = document.getElementById('whatsapp');
if (wa) wa.addEventListener('input', (e) => {
    let v = e.target.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 10) v = v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    else if (v.length > 6) v = v.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    else if (v.length > 2) v = v.replace(/(\d{2})(\d{1,4})/, '($1) $2');
    e.target.value = v;
});
const aceiteContrato = document.getElementById('aceiteContrato');
function abrirContrato() {
    const iframe = document.getElementById('modalContratoFrame');
    const nome = encodeURIComponent((document.querySelector('input[name=nome]') || {}).value || '');
    if (nome) iframe.src = 'gerar_contrato_parceiro.php?preview=1&nome=' + nome;
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
