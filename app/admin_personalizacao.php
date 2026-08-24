<?php
require_once 'config.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

$erro = '';
$sucesso = '';
$dirUpload = __DIR__ . '/uploads';

if (!is_dir($dirUpload)) {
    @mkdir($dirUpload, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos = ['logo_login_user', 'logo_login_admin', 'logo_app', 'cartao_ativado', 'cartao_desativado', 'cartao_fisico'];
    $novos = [];
    foreach ($campos as $campo) {
        $atual = trim($_POST['atual_' . $campo] ?? '');
        if (isset($_FILES[$campo]) && is_array($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES[$campo]['tmp_name'];
            $info = @getimagesize($tmp);
            $exts = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/svg+xml' => 'svg'];
            if ($info === false && strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION)) === 'svg') {
                $novos[$campo] = 'uploads/logo_' . $campo . '_' . time() . '.svg';
                move_uploaded_file($tmp, __DIR__ . '/' . $novos[$campo]);
            } elseif ($info !== false && isset($exts[$info['mime']])) {
                $novos[$campo] = 'uploads/logo_' . $campo . '_' . time() . '.' . $exts[$info['mime']];
                move_uploaded_file($tmp, __DIR__ . '/' . $novos[$campo]);
            } else {
                $erro = 'Envie apenas imagens válidas (PNG, JPG, WEBP, GIF ou SVG).';
            }
        } elseif (isset($_POST['remover_' . $campo])) {
            $novos[$campo] = '';
        } else {
            $novos[$campo] = $atual;
        }
    }
    if ($erro === '') {
        $stmt = $conn->prepare("UPDATE personalizacao SET logo_login_user = ?, logo_login_admin = ?, logo_app = ?, cartao_ativado = ?, cartao_desativado = ?, cartao_fisico = ? WHERE id = 1");
        $stmt->bind_param('ssssss', $novos['logo_login_user'], $novos['logo_login_admin'], $novos['logo_app'], $novos['cartao_ativado'], $novos['cartao_desativado'], $novos['cartao_fisico']);
        $stmt->execute();
        $sucesso = 'Personalização salva com sucesso!';
    }
}

$pers = $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Personalização - Admin Economic Card</title>
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
<h1 class="text-xl font-extrabold text-gray-800">Personalização</h1>
<p class="text-sm text-gray-500">Logos exibidas no login do usuário, no login do admin e dentro do app</p>
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

<form method="POST" action="admin_personalizacao.php" enctype="multipart/form-data">
<?php
$configs = [
    'logo_login_user' => ['titulo' => 'Logo do Login do Usuário', 'desc' => 'Exibida na tela de login dos usuários do app.', 'dica' => 'Tamanho ideal: quadrada 512x512px (mínimo 192x192), PNG ou SVG com fundo transparente. É exibida em 96x96px.'],
    'logo_login_admin' => ['titulo' => 'Logo do Login do Admin', 'desc' => 'Exibida na tela de login do administrador.', 'dica' => 'Tamanho ideal: quadrada 256x256px (mínimo 112x112), PNG ou SVG com fundo transparente. É exibida em 56x56px.'],
    'logo_app' => ['titulo' => 'Logo de Dentro do App', 'desc' => 'Exibida no topo do dashboard dos usuários.', 'dica' => 'Tamanho ideal: quadrada 128x128px (mínimo 64x64), PNG ou SVG com fundo transparente. É exibida em 32x32px.'],
    'cartao_ativado' => ['titulo' => 'Cartão Ativado', 'desc' => 'Exibido no dashboard dos usuários quando o cartão está ativo.', 'dica' => 'Envie a imagem do cartão (PNG, JPG, WEBP, GIF ou SVG). Aparecerá no dashboard no lugar do cartão digital quando o cartão do usuário estiver ativo.'],
    'cartao_desativado' => ['titulo' => 'Cartão Desativado', 'desc' => 'Exibido no dashboard dos usuários quando o cartão está desativado.', 'dica' => 'Envie a imagem do cartão (PNG, JPG, WEBP, GIF ou SVG). Aparecerá no dashboard no lugar do cartão digital quando o cartão do usuário estiver desativado.'],
    'cartao_fisico' => ['titulo' => 'Cartão Físico', 'desc' => 'Exibido na tela de Cartão Físico do app.', 'dica' => 'Envie a imagem do cartão (PNG, JPG, WEBP, GIF ou SVG). Aparecerá como fundo do cartão na tela de Cartão Físico do app.'],
];
foreach ($configs as $campo => $cfg):
    $atual = $pers[$campo] ?? '';
?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1"><?php echo $cfg['titulo']; ?></h2>
<p class="text-sm text-gray-500 mb-1"><?php echo $cfg['desc']; ?></p>
<p class="text-xs text-[#3e6a00] bg-[#b6f570]/20 border border-[#3e6a00]/20 rounded-lg px-3 py-2 mb-4"><?php echo $cfg['dica']; ?></p>
<div class="flex flex-col sm:flex-row sm:items-center gap-6">
<div class="w-40 h-24 bg-gray-50 border border-dashed border-gray-300 rounded-lg flex items-center justify-center overflow-hidden shrink-0">
<?php if ($atual): ?>
<img src="<?php echo htmlspecialchars($atual); ?>" alt="Logo atual" class="max-w-full max-h-full object-contain p-2">
<?php else: ?>
<span class="text-xs text-gray-400">Nenhuma logo</span>
<?php endif; ?>
</div>
<div class="flex-1 space-y-3">
<input type="hidden" name="atual_<?php echo $campo; ?>" value="<?php echo htmlspecialchars($atual); ?>"/>
<input type="file" name="<?php echo $campo; ?>" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#51036d] file:text-white file:font-semibold file:cursor-pointer hover:file:bg-[#3a024d]">
<?php if ($atual): ?>
<label class="flex items-center gap-2 text-sm font-semibold text-red-600 cursor-pointer">
<input type="checkbox" name="remover_<?php echo $campo; ?>" class="w-4 h-4 accent-red-600"> Remover logo atual
</label>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
<div class="flex items-center justify-end gap-4">
<button type="submit" class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-8 py-3 rounded-lg transition">SALVAR PERSONALIZAÇÃO</button>
</div>
</form>
</div>
</main>
</body>
</html>
