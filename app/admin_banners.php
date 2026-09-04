<?php
require_once 'config.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS banners_topo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL DEFAULT '',
    imagem VARCHAR(500) NOT NULL DEFAULT '',
    link_externo VARCHAR(500) NOT NULL DEFAULT '',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$aba = $_GET['aba'] ?? 'oferta';
if (!in_array($aba, ['oferta', 'topo'])) $aba = 'oferta';

$erro = '';
$sucesso = '';
function count_banners_ativos($conn) {
    return (int)$conn->query("SELECT COUNT(*) FROM banners WHERE ativo = 1")->fetch_row()[0];
}
function count_topo_ativos($conn) {
    return (int)$conn->query("SELECT COUNT(*) FROM banners_topo WHERE ativo = 1")->fetch_row()[0];
}
function processa_imagem_banner($prefixo = 'banner') {
    $imagem = trim($_POST['imagem'] ?? '');
    if (isset($_FILES['imagem_file']) && is_array($_FILES['imagem_file']) && $_FILES['imagem_file']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['imagem_file']['tmp_name'];
        $info = @getimagesize($tmp);
        $exts = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if ($info !== false && isset($exts[$info['mime']])) {
            $dirUpload = __DIR__ . '/uploads';
            if (!is_dir($dirUpload)) { @mkdir($dirUpload, 0777, true); }
            $nomeArq = $prefixo . '_' . time() . '.' . $exts[$info['mime']];
            move_uploaded_file($tmp, $dirUpload . '/' . $nomeArq);
            $imagem = 'uploads/' . $nomeArq;
        }
    }
    return $imagem;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cadastrar') {
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $desconto = trim($_POST['desconto'] ?? '');
        $botao = trim($_POST['botao_texto'] ?? 'EU QUERO!');
        $imagem = processa_imagem_banner();
        $linkExterno = trim($_POST['link_externo'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        if ($titulo === '') {
            $erro = 'Informe o título do banner.';
        } elseif ($ativo && count_banners_ativos($conn) >= 5) {
            $erro = 'Limite de 5 banners ativos na Oferta Especial atingido. Desative um banner antes de ativar outro.';
        } else {
            $stmt = $conn->prepare("INSERT INTO banners (titulo, descricao, desconto, botao_texto, imagem, link_externo, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssssi', $titulo, $descricao, $desconto, $botao, $imagem, $linkExterno, $ativo);
            $stmt->execute();
            $sucesso = 'Banner cadastrado com sucesso!';
        }
    } elseif ($acao === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $desconto = trim($_POST['desconto'] ?? '');
        $botao = trim($_POST['botao_texto'] ?? 'EU QUERO!');
        $imagem = processa_imagem_banner();
        $linkExterno = trim($_POST['link_externo'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        if ($titulo === '') {
            $erro = 'Informe o título do banner.';
        } elseif ($id <= 0) {
            $erro = 'Banner inválido.';
        } else {
            if ($ativo) {
                $st = $conn->prepare("SELECT COUNT(*) FROM banners WHERE ativo = 1 AND id <> ?");
                $st->bind_param('i', $id);
                $st->execute();
                if ((int)$st->get_result()->fetch_row()[0] >= 5) {
                    $erro = 'Limite de 5 banners ativos na Oferta Especial atingido. Desative um banner antes de ativar outro.';
                }
            }
            if ($erro === '') {
                $stmt = $conn->prepare("UPDATE banners SET titulo = ?, descricao = ?, desconto = ?, botao_texto = ?, imagem = ?, link_externo = ?, ativo = ? WHERE id = ?");
                $stmt->bind_param('ssssssii', $titulo, $descricao, $desconto, $botao, $imagem, $linkExterno, $ativo, $id);
                $stmt->execute();
                $sucesso = 'Banner atualizado com sucesso!';
            }
        }
    } elseif ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM banners WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $sucesso = 'Banner excluído.';
    } elseif ($acao === 'alternar') {
        $id = (int)($_POST['id'] ?? 0);
        $linha = $conn->query("SELECT ativo FROM banners WHERE id = " . $id)->fetch_assoc();
        if ($linha && !$linha['ativo'] && count_banners_ativos($conn) >= 5) {
            $erro = 'Limite de 5 banners ativos na Oferta Especial atingido. Desative um banner antes de ativar outro.';
        } else {
            $stmt = $conn->prepare("UPDATE banners SET ativo = 1 - ativo WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $sucesso = 'Status do banner alterado.';
        }
    } elseif ($acao === 'cadastrar_topo') {
        $titulo = trim($_POST['titulo'] ?? '');
        $imagem = processa_imagem_banner('topo');
        $linkExterno = trim($_POST['link_externo'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        if ($titulo === '') {
            $erro = 'Informe o título do banner.';
        } elseif ($ativo && count_topo_ativos($conn) >= 5) {
            $erro = 'Limite de 5 banners ativos no Slide Topo atingido. Desative um banner antes de ativar outro.';
        } else {
            $maxOrdem = (int)$conn->query("SELECT COALESCE(MAX(ordem),0) FROM banners_topo")->fetch_row()[0];
            $stmt = $conn->prepare("INSERT INTO banners_topo (titulo, imagem, link_externo, ativo, ordem) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('sssii', $titulo, $imagem, $linkExterno, $ativo, $maxOrdem);
            $stmt->execute();
            $sucesso = 'Banner do Slide Topo cadastrado com sucesso!';
        }
        $aba = 'topo';
    } elseif ($acao === 'editar_topo') {
        $id = (int)($_POST['id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $imagem = processa_imagem_banner('topo');
        $linkExterno = trim($_POST['link_externo'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        if ($titulo === '') {
            $erro = 'Informe o título do banner.';
        } elseif ($id <= 0) {
            $erro = 'Banner inválido.';
        } else {
            if ($ativo) {
                $st = $conn->prepare("SELECT COUNT(*) FROM banners_topo WHERE ativo = 1 AND id <> ?");
                $st->bind_param('i', $id);
                $st->execute();
                if ((int)$st->get_result()->fetch_row()[0] >= 5) {
                    $erro = 'Limite de 5 banners ativos no Slide Topo atingido. Desative um banner antes de ativar outro.';
                }
            }
            if ($erro === '') {
                if ($imagem !== '') {
                    $stmt = $conn->prepare("UPDATE banners_topo SET titulo = ?, imagem = ?, link_externo = ?, ativo = ? WHERE id = ?");
                    $stmt->bind_param('sssii', $titulo, $imagem, $linkExterno, $ativo, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE banners_topo SET titulo = ?, link_externo = ?, ativo = ? WHERE id = ?");
                    $stmt->bind_param('ssii', $titulo, $linkExterno, $ativo, $id);
                }
                $stmt->execute();
                $sucesso = 'Banner do Slide Topo atualizado com sucesso!';
            }
        }
        $aba = 'topo';
    } elseif ($acao === 'excluir_topo') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM banners_topo WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $sucesso = 'Banner do Slide Topo excluído.';
        $aba = 'topo';
    } elseif ($acao === 'alternar_topo') {
        $id = (int)($_POST['id'] ?? 0);
        $linha = $conn->query("SELECT ativo FROM banners_topo WHERE id = " . (int)$id)->fetch_assoc();
        if ($linha && !$linha['ativo'] && count_topo_ativos($conn) >= 5) {
            $erro = 'Limite de 5 banners ativos no Slide Topo atingido. Desative um banner antes de ativar outro.';
        } else {
            $stmt = $conn->prepare("UPDATE banners_topo SET ativo = 1 - ativo WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $sucesso = 'Status do banner do Slide Topo alterado.';
        }
        $aba = 'topo';
    } elseif ($acao === 'reordenar_topo') {
        $id = (int)($_POST['id'] ?? 0);
        $direcao = (int)($_POST['direcao'] ?? 0);
        if ($id > 0 && $direcao !== 0) {
            $atual = $conn->query("SELECT id, ordem FROM banners_topo WHERE id = " . $id)->fetch_assoc();
            if ($atual) {
                $novoOrdem = (int)$atual['ordem'] + $direcao;
                if ($novoOrdem < 0) $novoOrdem = 0;
                $conn->query("UPDATE banners_topo SET ordem = " . $novoOrdem . " WHERE id = " . $id);
            }
        }
        $aba = 'topo';
    }
}

$banners = $conn->query("SELECT * FROM banners ORDER BY id DESC");
$qtdAtivos = count_banners_ativos($conn);
$bannersTopo = $conn->query("SELECT * FROM banners_topo ORDER BY ordem ASC, id DESC");
$qtdTopoAtivos = count_topo_ativos($conn);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Banners Anúncios - Admin Economic Card</title>
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
<h1 class="text-xl font-extrabold text-gray-800">Banners Anúncios</h1>
<p class="text-sm text-gray-500">Gerencie os banners exibidos no aplicativo</p>
</div>
<a href="logout.php?admin=1" class="bg-[#51036d] hover:bg-[#3a024d] text-white rounded-lg px-4 py-2 text-sm font-semibold transition">Sair</a>
</div>
</header>

<div class="px-6 pt-4">
<div class="flex gap-1 bg-gray-200 rounded-lg p-1 w-fit">
<a href="admin_banners.php?aba=oferta" class="px-5 py-2 rounded-lg text-sm font-bold transition <?php echo $aba === 'oferta' ? 'bg-[#51036d] text-white shadow' : 'text-gray-600 hover:text-gray-800'; ?>">
<span class="material-symbols-outlined text-[18px] align-middle mr-1">campaign</span> Banners Oferta
</a>
<a href="admin_banners.php?aba=topo" class="px-5 py-2 rounded-lg text-sm font-bold transition <?php echo $aba === 'topo' ? 'bg-[#51036d] text-white shadow' : 'text-gray-600 hover:text-gray-800'; ?>">
<span class="material-symbols-outlined text-[18px] align-middle mr-1">view_carousel</span> Slide Topo
</a>
</div>
</div>

<div class="p-6">
<?php if ($sucesso): ?>
<div class="mb-6 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm"><?php echo htmlspecialchars($sucesso); ?></div>
<?php endif; ?>
<?php if ($erro): ?>
<div class="mb-6 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<?php if ($aba === 'oferta'): ?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<div class="flex items-center justify-between mb-4">
<h2 class="text-lg font-extrabold text-gray-800">Cadastrar novo banner</h2>
<span class="text-xs font-bold text-[#51036d] bg-[#51036d]/10 px-3 py-1.5 rounded-full">Banners ativos: <?php echo $qtdAtivos; ?>/5</span>
</div>
<form method="POST" action="admin_banners.php?aba=oferta" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
<input type="hidden" name="acao" value="cadastrar"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Título *</label>
<input name="titulo" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Desconto / Destaque</label>
<input name="desconto" placeholder="ex: 20% OFF" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div class="md:col-span-2">
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Descrição</label>
<input name="descricao" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Texto do botão</label>
<input name="botao_texto" value="EU QUERO!" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">URL da imagem do banner</label>
<input name="imagem" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Link externo</label>
<input name="link_externo" placeholder="ex: https://site.com/oferta" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Enviar imagem do banner</label>
<input type="file" name="imagem_file" accept="image/png,image/jpeg,image/webp,image/gif" class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#51036d] file:text-white file:font-semibold file:cursor-pointer hover:file:bg-[#3a024d]">
<p class="mt-1 text-xs text-gray-500">Imagem recomendada: <strong class="text-gray-700">1080x1350 px</strong> (proporção 4:5)</p>
</div>
<div class="md:col-span-2 flex items-center gap-4">
<label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
<input type="checkbox" name="ativo" checked class="w-4 h-4 accent-[#3e6a00]"> Banner ativo (aparece no app)
</label>
<button class="ml-auto bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition" type="submit">CADASTRAR BANNER</button>
</div>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
<h2 class="px-6 py-4 text-lg font-extrabold text-gray-800 border-b border-gray-100">Banners cadastrados</h2>
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead>
<tr class="bg-gray-50 text-gray-600 text-left uppercase text-xs tracking-wider">
<th class="px-4 py-3">#</th>
<th class="px-4 py-3">Prévia</th>
<th class="px-4 py-3">Título</th>
<th class="px-4 py-3">Desconto</th>
<th class="px-4 py-3">Status</th>
<th class="px-4 py-3 text-right">Ações</th>
</tr>
</thead>
<tbody>
<?php if ($banners->num_rows === 0): ?>
<tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">Nenhum banner cadastrado.</td></tr>
<?php endif; ?>
<?php while ($b = $banners->fetch_assoc()): ?>
<tr class="border-t border-gray-100 hover:bg-gray-50">
<td class="px-4 py-3 text-gray-500"><?php echo $b['id']; ?></td>
<td class="px-4 py-3">
<?php if ($b['imagem']): ?>
<img class="w-16 h-20 rounded-lg object-cover border border-gray-200" src="<?php echo htmlspecialchars(asset_url($b['imagem'])); ?>" alt="Prévia"/>
<?php else: ?>
<span class="w-16 h-20 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400"><span class="material-symbols-outlined">image</span></span>
<?php endif; ?>
</td>
<td class="px-4 py-3">
<p class="font-semibold text-gray-800"><?php echo htmlspecialchars($b['titulo']); ?></p>
<?php if ($b['descricao']): ?><p class="text-xs text-gray-500"><?php echo htmlspecialchars($b['descricao']); ?></p><?php endif; ?>
</td>
<td class="px-4 py-3"><?php echo htmlspecialchars($b['desconto'] ?: '-'); ?></td>
<td class="px-4 py-3">
<span class="px-2 py-1 rounded-full <?php echo $b['ativo'] ? 'bg-[#b6f570]/40 text-[#3e6a00]' : 'bg-gray-200 text-gray-600'; ?> text-[10px] font-bold uppercase"><?php echo $b['ativo'] ? 'Ativo' : 'Inativo'; ?></span>
</td>
<td class="px-4 py-3">
<div class="flex items-center justify-end gap-3">
<button type="button"
data-id="<?php echo (int)$b['id']; ?>"
data-titulo="<?php echo htmlspecialchars($b['titulo'], ENT_QUOTES); ?>"
data-descricao="<?php echo htmlspecialchars($b['descricao'] ?? '', ENT_QUOTES); ?>"
data-desconto="<?php echo htmlspecialchars($b['desconto'] ?? '', ENT_QUOTES); ?>"
data-botao="<?php echo htmlspecialchars($b['botao_texto'] ?? 'EU QUERO!', ENT_QUOTES); ?>"
data-imagem="<?php echo htmlspecialchars($b['imagem'] ?? '', ENT_QUOTES); ?>"
data-imagem-url="<?php echo htmlspecialchars(asset_url($b['imagem'] ?? ''), ENT_QUOTES); ?>"
data-link="<?php echo htmlspecialchars($b['link_externo'] ?? '', ENT_QUOTES); ?>"
data-ativo="<?php echo $b['ativo'] ? 1 : 0; ?>"
onclick="abrirEditarBanner(this)"
class="text-[#51036d] hover:text-[#3a024d] font-semibold flex items-center gap-1 hover:underline" title="Editar banner">
<span class="material-symbols-outlined text-[18px]">edit</span> Editar
</button>
<form method="POST" action="admin_banners.php?aba=oferta" class="inline">
<input type="hidden" name="acao" value="alternar"/>
<input type="hidden" name="id" value="<?php echo $b['id']; ?>"/>
<button class="text-[#51036d] hover:underline font-semibold" type="submit"><?php echo $b['ativo'] ? 'Desativar' : 'Ativar'; ?></button>
</form>
<form method="POST" action="admin_banners.php?aba=oferta" class="inline" onsubmit="confirmarExclusao(event, this, 'Excluir este banner? Esta ação não pode ser desfeita.');">
<input type="hidden" name="acao" value="excluir"/>
<input type="hidden" name="id" value="<?php echo $b['id']; ?>"/>
<button class="text-red-600 hover:text-red-800 font-semibold" type="submit">Excluir</button>
</form>
</div>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<?php else: ?>
<!-- ABA SLIDE TOPO -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<div class="flex items-center justify-between mb-4">
<h2 class="text-lg font-extrabold text-gray-800">Cadastrar banner Slide Topo</h2>
<span class="text-xs font-bold text-[#51036d] bg-[#51036d]/10 px-3 py-1.5 rounded-full">Banners ativos: <?php echo $qtdTopoAtivos; ?>/5</span>
</div>
<form method="POST" action="admin_banners.php?aba=topo" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
<input type="hidden" name="acao" value="cadastrar_topo"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Título *</label>
<input name="titulo" required placeholder="Título do banner" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Link externo (ao clicar no banner)</label>
<input name="link_externo" placeholder="ex: https://site.com" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">URL da imagem</label>
<input name="imagem" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Enviar imagem do banner</label>
<input type="file" name="imagem_file" accept="image/png,image/jpeg,image/webp,image/gif" class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#51036d] file:text-white file:font-semibold file:cursor-pointer hover:file:bg-[#3a024d]">
<p class="mt-1 text-xs text-gray-500">Imagem recomendada: <strong class="text-gray-700">400x100 px</strong> com cantos arredondados</p>
</div>
<div class="md:col-span-2 flex items-center gap-4">
<label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
<input type="checkbox" name="ativo" checked class="w-4 h-4 accent-[#3e6a00]"> Banner ativo (aparece no topo do dashboard)
</label>
<button class="ml-auto bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition" type="submit">CADASTRAR BANNER</button>
</div>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
<h2 class="px-6 py-4 text-lg font-extrabold text-gray-800 border-b border-gray-100">Banners do Slide Topo cadastrados</h2>
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead>
<tr class="bg-gray-50 text-gray-600 text-left uppercase text-xs tracking-wider">
<th class="px-4 py-3">#</th>
<th class="px-4 py-3">Prévia</th>
<th class="px-4 py-3">Título</th>
<th class="px-4 py-3">Status</th>
<th class="px-4 py-3 text-right">Ações</th>
</tr>
</thead>
<tbody>
<?php if ($bannersTopo->num_rows === 0): ?>
<tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">Nenhum banner cadastrado no Slide Topo.</td></tr>
<?php endif; ?>
<?php while ($b = $bannersTopo->fetch_assoc()): ?>
<tr class="border-t border-gray-100 hover:bg-gray-50">
<td class="px-4 py-3 text-gray-500"><?php echo $b['id']; ?></td>
<td class="px-4 py-3">
<?php if ($b['imagem']): ?>
<img class="w-24 h-12 rounded-lg object-cover border border-gray-200" src="<?php echo htmlspecialchars(asset_url($b['imagem'])); ?>" alt="Prévia"/>
<?php else: ?>
<span class="w-24 h-12 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400"><span class="material-symbols-outlined">image</span></span>
<?php endif; ?>
</td>
<td class="px-4 py-3">
<p class="font-semibold text-gray-800"><?php echo htmlspecialchars($b['titulo']); ?></p>
<?php if ($b['link_externo']): ?><p class="text-xs text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($b['link_externo']); ?></p><?php endif; ?>
</td>
<td class="px-4 py-3">
<span class="px-2 py-1 rounded-full <?php echo $b['ativo'] ? 'bg-[#b6f570]/40 text-[#3e6a00]' : 'bg-gray-200 text-gray-600'; ?> text-[10px] font-bold uppercase"><?php echo $b['ativo'] ? 'Ativo' : 'Inativo'; ?></span>
</td>
<td class="px-4 py-3">
<div class="flex items-center justify-end gap-2">
<button type="button"
data-id="<?php echo (int)$b['id']; ?>"
data-titulo="<?php echo htmlspecialchars($b['titulo'], ENT_QUOTES); ?>"
data-imagem="<?php echo htmlspecialchars($b['imagem'] ?? '', ENT_QUOTES); ?>"
data-imagem-url="<?php echo htmlspecialchars(asset_url($b['imagem'] ?? ''), ENT_QUOTES); ?>"
data-link="<?php echo htmlspecialchars($b['link_externo'] ?? '', ENT_QUOTES); ?>"
data-ativo="<?php echo $b['ativo'] ? 1 : 0; ?>"
onclick="abrirEditarTopo(this)"
class="text-[#51036d] hover:text-[#3a024d] font-semibold flex items-center gap-1 hover:underline text-xs" title="Editar">
<span class="material-symbols-outlined text-[16px]">edit</span>
</button>
<form method="POST" action="admin_banners.php?aba=topo" class="inline">
<input type="hidden" name="acao" value="alternar_topo"/>
<input type="hidden" name="id" value="<?php echo $b['id']; ?>"/>
<button class="text-[#51036d] hover:underline font-semibold text-xs" type="submit"><?php echo $b['ativo'] ? 'Desativar' : 'Ativar'; ?></button>
</form>
<form method="POST" action="admin_banners.php?aba=topo" class="inline" onsubmit="confirmarExclusao(event, this, 'Excluir este banner do Slide Topo?');">
<input type="hidden" name="acao" value="excluir_topo"/>
<input type="hidden" name="id" value="<?php echo $b['id']; ?>"/>
<button class="text-red-600 hover:text-red-800 font-semibold text-xs" type="submit">Excluir</button>
</form>
</div>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

</div>
</main>

<!-- Modal Confirmação -->
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

<!-- Modal Editar Banner Oferta -->
<div id="modalEdicao" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/50" onclick="fecharModalEdicao()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden max-h-[92vh] flex flex-col">
<div class="bg-[#51036d] px-6 py-4 flex items-center justify-between">
<h2 class="text-white font-bold flex items-center gap-2"><span class="material-symbols-outlined">edit</span> Editar banner</h2>
<button type="button" onclick="fecharModalEdicao()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<form method="POST" action="admin_banners.php?aba=oferta" enctype="multipart/form-data" class="overflow-y-auto p-6 space-y-4">
<input type="hidden" name="acao" value="editar"/>
<input type="hidden" name="id" id="edit_id"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Título *</label>
<input id="edit_titulo" name="titulo" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Desconto / Destaque</label>
<input id="edit_desconto" name="desconto" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Descrição</label>
<input id="edit_descricao" name="descricao" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Texto do botão</label>
<input id="edit_botao" name="botao_texto" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">URL da imagem</label>
<input id="edit_imagem" name="imagem" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<img id="edit_preview" class="mt-2 w-28 h-auto rounded-lg border border-gray-200 hidden" alt="Prévia"/>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Link externo</label>
<input id="edit_link" name="link_externo" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Enviar nova imagem</label>
<input type="file" name="imagem_file" accept="image/png,image/jpeg,image/webp,image/gif" class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#51036d] file:text-white file:font-semibold file:cursor-pointer hover:file:bg-[#3a024d]">
</div>
<label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
<input id="edit_ativo" type="checkbox" name="ativo" class="w-4 h-4 accent-[#3e6a00]"> Banner ativo
</label>
<div class="flex gap-3 pt-2">
<button type="button" onclick="fecharModalEdicao()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2.5 rounded-lg transition">Cancelar</button>
<button class="flex-1 bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-4 py-2.5 rounded-lg transition" type="submit">SALVAR</button>
</div>
</form>
</div>
</div>

<!-- Modal Editar Banner Topo -->
<div id="modalEdicaoTopo" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/50" onclick="fecharModalEdicaoTopo()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden max-h-[92vh] flex flex-col">
<div class="bg-[#51036d] px-6 py-4 flex items-center justify-between">
<h2 class="text-white font-bold flex items-center gap-2"><span class="material-symbols-outlined">edit</span> Editar banner Slide Topo</h2>
<button type="button" onclick="fecharModalEdicaoTopo()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<form method="POST" action="admin_banners.php?aba=topo" enctype="multipart/form-data" class="overflow-y-auto p-6 space-y-4">
<input type="hidden" name="acao" value="editar_topo"/>
<input type="hidden" name="id" id="edit_topo_id"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Título *</label>
<input id="edit_topo_titulo" name="titulo" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">URL da imagem</label>
<input id="edit_topo_imagem" name="imagem" placeholder="Deixe vazio para manter a atual" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<img id="edit_topo_preview" class="mt-2 w-40 h-auto rounded-lg border border-gray-200 hidden" alt="Prévia"/>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Link externo</label>
<input id="edit_topo_link" name="link_externo" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Enviar nova imagem</label>
<input type="file" name="imagem_file" accept="image/png,image/jpeg,image/webp,image/gif" class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#51036d] file:text-white file:font-semibold file:cursor-pointer hover:file:bg-[#3a024d]">
<p class="mt-1 text-xs text-gray-500">Imagem recomendada: <strong class="text-gray-700">400x100 px</strong></p>
</div>
<label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
<input id="edit_topo_ativo" type="checkbox" name="ativo" class="w-4 h-4 accent-[#3e6a00]"> Banner ativo
</label>
<div class="flex gap-3 pt-2">
<button type="button" onclick="fecharModalEdicaoTopo()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2.5 rounded-lg transition">Cancelar</button>
<button class="flex-1 bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-4 py-2.5 rounded-lg transition" type="submit">SALVAR</button>
</div>
</form>
</div>
</div>

<script>
function abrirEditarBanner(btn) {
    document.getElementById('edit_id').value = btn.dataset.id;
    document.getElementById('edit_titulo').value = btn.dataset.titulo;
    document.getElementById('edit_descricao').value = btn.dataset.descricao || '';
    document.getElementById('edit_desconto').value = btn.dataset.desconto || '';
    document.getElementById('edit_botao').value = btn.dataset.botao || '';
    document.getElementById('edit_imagem').value = btn.dataset.imagem || '';
    document.getElementById('edit_link').value = btn.dataset.link || '';
    document.getElementById('edit_ativo').checked = btn.dataset.ativo === '1';
    var prev = document.getElementById('edit_preview');
    if (btn.dataset.imagemUrl) { prev.src = btn.dataset.imagemUrl; prev.classList.remove('hidden'); } else { prev.classList.add('hidden'); }
    var m = document.getElementById('modalEdicao'); m.classList.remove('hidden'); m.classList.add('flex');
}
function fecharModalEdicao() { var m = document.getElementById('modalEdicao'); m.classList.add('hidden'); m.classList.remove('flex'); }

function abrirEditarTopo(btn) {
    document.getElementById('edit_topo_id').value = btn.dataset.id;
    document.getElementById('edit_topo_titulo').value = btn.dataset.titulo || '';
    document.getElementById('edit_topo_imagem').value = '';
    document.getElementById('edit_topo_link').value = btn.dataset.link || '';
    document.getElementById('edit_topo_ativo').checked = btn.dataset.ativo === '1';
    var prev = document.getElementById('edit_topo_preview');
    if (btn.dataset.imagemUrl) { prev.src = btn.dataset.imagemUrl; prev.classList.remove('hidden'); } else { prev.classList.add('hidden'); }
    var m = document.getElementById('modalEdicaoTopo'); m.classList.remove('hidden'); m.classList.add('flex');
}
function fecharModalEdicaoTopo() { var m = document.getElementById('modalEdicaoTopo'); m.classList.add('hidden'); m.classList.remove('flex'); }
</script>
<script>
var formPendente = null;
function confirmarExclusao(ev, form, msg) {
    ev.preventDefault(); formPendente = form;
    document.getElementById('modalConfirmacaoMsg').textContent = msg;
    var m = document.getElementById('modalConfirmacao'); m.classList.remove('hidden'); m.classList.add('flex');
}
function confirmarModal() { if (formPendente) formPendente.submit(); fecharModalConfirmacao(); }
function fecharModalConfirmacao() {
    formPendente = null;
    var m = document.getElementById('modalConfirmacao'); m.classList.add('hidden'); m.classList.remove('flex');
}
</script>
</body>
</html>
