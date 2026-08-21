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
    if ($acao === 'cadastrar' || $acao === 'editar') {
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
            $erro = 'Informe o nome do parceiro.';
        } elseif ($acao === 'editar') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare("UPDATE parceiros SET nome = ?, categoria = ?, endereco = ?, whatsapp = ?, instagram = ?, facebook = ?, site = ?, logo = ?, porcentagem = ? WHERE id = ?");
            $stmt->bind_param('ssssssssdi', $nome, $categoria, $endereco, $whatsapp, $instagram, $facebook, $site, $logo, $porcentagem, $id);
            $stmt->execute();
            $sucesso = 'Parceiro atualizado com sucesso!';
        } else {
            $stmt = $conn->prepare("INSERT INTO parceiros (nome, categoria, endereco, whatsapp, instagram, facebook, site, logo, porcentagem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssssssd', $nome, $categoria, $endereco, $whatsapp, $instagram, $facebook, $site, $logo, $porcentagem);
            $stmt->execute();
            registrar_aceite_contrato($conn, 'parceiros', $conn->insert_id);
            $sucesso = 'Parceiro cadastrado com sucesso!';
        }
    } elseif ($acao === 'criar_categoria') {
        $nomeCat = trim($_POST['nova_categoria'] ?? '');
        if ($nomeCat === '') {
            $erro = 'Informe o nome da nova categoria.';
        } else {
            $stmt = $conn->prepare("INSERT INTO categorias (nome) VALUES (?)");
            $stmt->bind_param('s', $nomeCat);
            $stmt->execute();
            $sucesso = 'Categoria criada!';
        }
    } elseif ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM parceiros WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $sucesso = 'Parceiro excluído.';
    }
}

$parceiros = $conn->query("SELECT * FROM parceiros ORDER BY id DESC");
$categoriasLista = $conn->query("SELECT DISTINCT categoria FROM parceiros WHERE categoria <> '' ORDER BY categoria");
$categoriasExtra = $conn->query("SELECT nome FROM categorias ORDER BY nome");

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/card/';

$editando = null;
if (isset($_GET['editar'])) {
    $eid = (int)$_GET['editar'];
    $st = $conn->prepare("SELECT * FROM parceiros WHERE id = ?");
    $st->bind_param('i', $eid);
    $st->execute();
    $editando = $st->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parceiros - Admin Economic Card</title>
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
<h1 class="text-xl font-extrabold text-gray-800">Parceiros</h1>
<p class="text-sm text-gray-500">Cadastro de empresas parceiras</p>
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
<h2 class="text-lg font-extrabold text-gray-800">Cadastro de parceiros</h2>
<p class="text-sm text-gray-500 mt-1">Compartilhe este link para que empresas façam o próprio cadastro (Nome, Categoria, Endereço, WhatsApp, Porcentagem, redes sociais, Site e Logo).</p>
</div>
<a href="cadastro_parceiro.php" target="_blank" class="shrink-0 inline-flex items-center gap-2 text-sm font-bold text-[#51036d] hover:text-[#3a024d] bg-[#51036d]/10 hover:bg-[#51036d]/20 rounded-lg px-4 py-2.5 transition">
<span class="material-symbols-outlined text-[18px]">open_in_new</span> Abrir página
</a>
</div>
<div class="mt-4 flex flex-col sm:flex-row gap-2">
<input id="linkCadastroParceiro" type="text" readonly value="<?php echo htmlspecialchars($baseUrl . 'cadastro_parceiro.php'); ?>" class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<button type="button" onclick="copiarLink('<?php echo htmlspecialchars($baseUrl . 'cadastro_parceiro.php'); ?>', this)" class="shrink-0 bg-[#51036d] hover:bg-[#3a024d] text-white font-bold px-5 py-2.5 rounded-lg transition text-sm">Copiar link</button>
</div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-4">Criar nova categoria</h2>
<form method="POST" action="admin_parceiros.php" class="flex flex-col sm:flex-row gap-3">
<input type="hidden" name="acao" value="criar_categoria"/>
<input name="nova_categoria" required placeholder="Digite o nome da nova categoria..." class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<button class="bg-[#51036d] hover:bg-[#3a024d] text-white font-bold px-6 py-2.5 rounded-lg transition" type="submit">ADICIONAR CATEGORIA</button>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-4"><?php echo $editando ? 'Editar parceiro' : 'Cadastrar novo parceiro'; ?></h2>
<form method="POST" action="admin_parceiros.php" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
<?php if ($editando): ?>
<input type="hidden" name="acao" value="editar"/>
<input type="hidden" name="id" value="<?php echo (int)$editando['id']; ?>"/>
<?php else: ?>
<input type="hidden" name="acao" value="cadastrar"/>
<?php endif; ?>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome da empresa *</label>
<input name="nome" required value="<?php echo htmlspecialchars($editando['nome'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Categoria</label>
<input name="categoria" list="listaCategorias" value="<?php echo htmlspecialchars($editando['categoria'] ?? ''); ?>" placeholder="Escolha ou digite uma nova..." class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
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
<div class="md:col-span-2">
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Endereço</label>
<input name="endereco" value="<?php echo htmlspecialchars($editando['endereco'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">WhatsApp</label>
<input name="whatsapp" id="whatsapp" value="<?php echo htmlspecialchars($editando['whatsapp'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Instagram</label>
<input name="instagram" placeholder="https://instagram.com/..." value="<?php echo htmlspecialchars($editando['instagram'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Facebook</label>
<input name="facebook" placeholder="https://facebook.com/..." value="<?php echo htmlspecialchars($editando['facebook'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Site</label>
<input name="site" placeholder="https://..." value="<?php echo htmlspecialchars($editando['site'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">URL do Logo</label>
<input name="logo" value="<?php echo htmlspecialchars($editando['logo'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Porcentagem (%)</label>
<input name="porcentagem" value="<?php echo htmlspecialchars($editando['porcentagem'] ?? '0'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Enviar imagem do parceiro</label>
<input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#51036d] file:text-white file:font-semibold file:cursor-pointer hover:file:bg-[#3a024d]">
</div>
<div class="md:col-span-2 flex items-center gap-3">
<button class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition" type="submit"><?php echo $editando ? 'SALVAR ALTERAÇÕES' : 'CADASTRAR PARCEIRO'; ?></button>
<?php if ($editando): ?>
<a href="admin_parceiros.php" class="text-[#51036d] hover:underline font-semibold">Cancelar edição</a>
<?php endif; ?>
</div>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
<h2 class="px-6 py-4 text-lg font-extrabold text-gray-800 border-b border-gray-100">Parceiros cadastrados</h2>
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead>
<tr class="bg-gray-50 text-gray-600 text-left uppercase text-xs tracking-wider">
<th class="px-4 py-3">#</th>
<th class="px-4 py-3">Nome</th>
<th class="px-4 py-3">Categoria</th>
<th class="px-4 py-3">WhatsApp</th>
<th class="px-4 py-3">Porcentagem</th>
<th class="px-4 py-3">Status</th>
<th class="px-4 py-3 text-right">Ações</th>
</tr>
</thead>
<tbody>
<?php if ($parceiros->num_rows === 0): ?>
<tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">Nenhum parceiro cadastrado.</td></tr>
<?php endif; ?>
<?php while ($p = $parceiros->fetch_assoc()): ?>
<tr class="border-t border-gray-100 hover:bg-gray-50">
<td class="px-4 py-3 text-gray-500"><?php echo $p['id']; ?></td>
<td class="px-4 py-3">
<div class="flex items-center justify-start">
<div class="w-10 h-10 bg-gray-100 rounded-lg flex-shrink-0 flex items-center justify-center overflow-hidden mr-3">
<?php if ($p['logo']): ?>
<img class="w-full h-full object-contain p-1" src="<?php echo htmlspecialchars($p['logo']); ?>" alt="Logo de <?php echo htmlspecialchars($p['nome']); ?>"/>
<?php else: ?>
<span class="material-symbols-outlined text-lg text-outline">storefront</span>
<?php endif; ?>
</div>
<span class="font-semibold text-gray-800"><?php echo htmlspecialchars($p['nome']); ?></span>
</div>
</td>
<td class="px-4 py-3"><?php echo htmlspecialchars($p['categoria'] ?: '-'); ?></td>
<td class="px-4 py-3"><?php echo htmlspecialchars($p['whatsapp'] ?: '-'); ?></td>
<td class="px-4 py-3 font-bold text-[#3e6a00]"><?php echo number_format((float)$p['porcentagem'], 0, ',', '.') . '%'; ?></td>
<td class="px-4 py-3">
<span class="px-2 py-1 rounded-full bg-[#b6f570]/40 text-[#3e6a00] text-[10px] font-bold uppercase"><?php echo $p['ativo'] ? 'Ativo' : 'Inativo'; ?></span>
</td>
<td class="px-4 py-3 text-right space-x-3">
<?php if ($p['whatsapp']): ?>
<a class="text-[#25D366] hover:opacity-70 transition-opacity inline-flex align-middle" target="_blank" rel="noopener" href="https://wa.me/<?php echo htmlspecialchars($p['whatsapp']); ?>" title="Falar no WhatsApp"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>
<?php endif; ?>
<a href="admin_parceiros.php?editar=<?php echo $p['id']; ?>" class="text-[#51036d] hover:underline font-semibold">Editar</a>
<form method="POST" action="admin_parceiros.php" class="inline" onsubmit="confirmarExclusao(event, this, 'Excluir este parceiro? Esta ação não pode ser desfeita.');">
<input type="hidden" name="acao" value="excluir"/>
<input type="hidden" name="id" value="<?php echo $p['id']; ?>"/>
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
function copiarLink(texto, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(() => {
            btn.textContent = 'Copiado!';
            setTimeout(() => btn.textContent = 'Copiar link', 2000);
        });
    } else {
        const ta = document.createElement('textarea');
        ta.value = texto;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        btn.textContent = 'Copiado!';
        setTimeout(() => btn.textContent = 'Copiar link', 2000);
    }
}
const wa = document.getElementById('whatsapp');
if (wa) wa.addEventListener('input', (e) => {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 11) value = value.slice(0, 11);
    e.target.value = value;
});
</script>
</body>
</html>