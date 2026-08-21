<?php
require_once 'config.php';
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
exigirCartaoAtivo();
$uid = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cpf  = trim($_POST['cpf'] ?? '');
    $rg   = trim($_POST['rg'] ?? '');
    $nasc = trim($_POST['nascimento'] ?? '');
    $end  = trim($_POST['endereco'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $cep     = trim($_POST['cep'] ?? '');
    $whats   = trim($_POST['whatsapp'] ?? '');
    $avatar  = trim($_POST['avatar_atual'] ?? '');
    $salvo = false;
    $avErro = '';
    if (isset($_FILES['avatar']) && is_array($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['avatar']['tmp_name'];
        $info = @getimagesize($tmp);
        $exts = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if ($info !== false && isset($exts[$info['mime']])) {
            $dirUpload = __DIR__ . '/uploads';
            if (!is_dir($dirUpload)) { @mkdir($dirUpload, 0777, true); }
            $nomeArq = 'avatar_' . $uid . '_' . time() . '.' . $exts[$info['mime']];
            if (move_uploaded_file($tmp, $dirUpload . '/' . $nomeArq)) {
                $avatar = 'uploads/' . $nomeArq;
            } else {
                $avErro = 'Não foi possível salvar a foto. Verifique a permissão da pasta uploads.';
            }
        } else {
            $avErro = 'Formato de imagem inválido. Envie uma foto PNG, JPG, WEBP ou GIF.';
        }
    } elseif (isset($_FILES['avatar']) && is_array($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $avErro = 'Falha no envio da foto (código ' . (int)$_FILES['avatar']['error'] . '). Tente novamente com uma imagem menor.';
    }
    if (isset($_POST['remover_avatar'])) {
        $avatar = '';
    }
    if ($nome !== '') {
        $stmt = $conn->prepare("UPDATE usuarios SET nome=?, email=?, cpf=?, rg=?, nascimento=?, endereco=?, cidade=?, cep=?, whatsapp=?, avatar=? WHERE id=?");
        $stmt->bind_param('ssssssssssi', $nome, $email, $cpf, $rg, $nasc, $end, $cidade, $cep, $whats, $avatar, $uid);
        $stmt->execute();
        $salvo = true;
    }
}

$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if (!$u) { header('Location: logout.php'); exit; }
?>
<!DOCTYPE html><html class="light" lang="pt-BR"><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport">
<title>Economic Card - Perfil</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Hanken+Grotesk:wght@600;700&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "primary-container": "#6a2585", "surface-dim": "#d9dadb", "outline-variant": "#d1c2d1", "error": "#ba1a1a", "tertiary-fixed-dim": "#ffb877", "tertiary": "#4a2700", "on-secondary-fixed": "#0f2000", "on-tertiary": "#ffffff", "on-background": "#191c1d", "tertiary-container": "#6a3a00", "surface": "#f8f9fa", "tertiary-fixed": "#ffdcc0", "on-primary-container": "#e397fd", "on-secondary-container": "#427000", "surface-container": "#edeeef", "on-primary-fixed": "#320046", "error-container": "#ffdad6", "surface-tint": "#8540a0", "on-secondary": "#ffffff", "on-surface-variant": "#4e434f", "on-error": "#ffffff", "surface-container-low": "#f3f4f5", "surface-container-lowest": "#ffffff", "secondary-fixed-dim": "#9cd857", "on-tertiary-fixed": "#2e1600", "inverse-on-surface": "#f0f1f2", "primary-fixed": "#f9d8ff", "surface-variant": "#e1e3e4", "on-secondary-fixed-variant": "#2e5000", "secondary-fixed": "#b6f570", "on-error-container": "#93000a", "on-primary": "#ffffff", "surface-container-high": "#e7e8e9", "on-primary-fixed-variant": "#6b2686", "on-surface": "#191c1d", "secondary-container": "#b6f570", "inverse-surface": "#2e3132", "on-tertiary-fixed-variant": "#6c3b00", "surface-bright": "#f8f9fa", "background": "#f8f9fa", "surface-container-highest": "#e1e3e4", "primary-fixed-dim": "#edb1ff", "on-tertiary-container": "#ff9d35", "outline": "#7f7381", "secondary": "#3e6a00", "primary": "#51036d", "inverse-primary": "#edb1ff"
                    },
                    "borderRadius": { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
                    "spacing": { "container-padding": "20px", "xl": "48px", "xs": "4px", "card-gutter": "16px", "md": "24px", "lg": "32px", "sm": "12px", "base": "8px" },
                    "fontFamily": { "display-lg": ["Manrope"], "body-md": ["Manrope"], "label-caps": ["Hanken Grotesk"], "label-bold": ["Hanken Grotesk"], "body-lg": ["Manrope"], "headline-sm": ["Manrope"], "headline-md": ["Manrope"] },
                    "fontSize": {
                        "display-lg": ["32px", { "lineHeight": "1.2", "fontWeight": "800" }], "body-md": ["14px", { "lineHeight": "1.5", "fontWeight": "400" }], "label-caps": ["10px", { "lineHeight": "1", "fontWeight": "600" }], "label-bold": ["12px", { "lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "700" }], "body-lg": ["16px", { "lineHeight": "1.6", "fontWeight": "400" }], "headline-sm": ["20px", { "lineHeight": "1.4", "fontWeight": "600" }], "headline-md": ["24px", { "lineHeight": "1.3", "fontWeight": "700" }]
                    }
                },
            },
        }
    </script>
<style>
        body { background-color: #f8f9fa; color: #191c1d; font-family: 'Manrope', sans-serif; -webkit-tap-highlight-color: transparent; }
        .glass-header { background: rgba(248, 249, 250, 0.8); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .input-group:focus-within label { color: #51036d; }
        .custom-shadow { box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .fab-shadow { box-shadow: 0 8px 30px rgba(106, 37, 133, 0.15); }
    </style>
</head>
<body class="min-h-screen pb-32">
<!-- TopAppBar -->
<header class="fixed top-0 w-full z-50 bg-surface/80 dark:bg-surface-dim/80 backdrop-blur-xl shadow-sm h-16 flex items-center justify-between px-container-padding">
<a class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-surface-container-high/50 transition-colors active:scale-95 duration-200" href="dashboard.php">
<span class="material-symbols-outlined text-primary">arrow_back</span>
</a>
<h1 class="font-headline-md text-headline-md text-primary">Perfil</h1>
<a class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-surface-container-high/50 transition-colors active:scale-95 duration-200" href="dashboard.php">
<span class="material-symbols-outlined text-on-surface-variant">notifications</span>
</a>
</header>
<main class="pt-24 px-container-padding max-w-2xl mx-auto">
<section class="flex flex-col items-center mb-lg">
<div class="relative group">
<div class="w-32 h-32 rounded-full overflow-hidden border-4 border-white custom-shadow bg-surface-container-high flex items-center justify-center">
<?php if ($u['avatar']): ?>
<img class="w-full h-full object-cover" src="<?php echo htmlspecialchars($u['avatar']); ?>" alt="Foto de perfil"/>
<?php else: ?>
<span class="material-symbols-outlined text-primary text-[48px]" style="font-variation-settings: 'FILL' 1;">person</span>
<?php endif; ?>
</div>
<button type="button" class="absolute bottom-1 right-1 bg-primary text-white w-10 h-10 rounded-full flex items-center justify-center fab-shadow border-2 border-white hover:scale-110 transition-transform active:scale-95" onclick="document.getElementById('avatarInput').click()">
<span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">edit</span>
</button>
</div>
<div class="mt-sm text-center">
<h2 class="font-headline-sm text-headline-sm text-on-surface"><?php echo htmlspecialchars($u['nome']); ?></h2>
<p class="font-label-caps text-label-caps text-on-surface-variant tracking-wider uppercase">Usuário do Cartão</p>
</div>
</section>
<?php if (isset($salvo) && $salvo): ?>
<div class="mb-md p-md rounded-xl bg-secondary-container/30 border border-secondary/30 text-on-secondary-container font-headline-sm text-center">Dados salvos com sucesso!</div>
<?php endif; ?>
<?php if (isset($avErro) && $avErro !== ''): ?>
<div class="mb-md p-md rounded-xl bg-error-container/30 border border-error/30 text-on-error-container font-headline-sm text-center"><?php echo htmlspecialchars($avErro); ?></div>
<?php endif; ?>
<form class="space-y-md" method="POST" action="perfil.php" enctype="multipart/form-data">
<input type="hidden" name="avatar_atual" value="<?php echo htmlspecialchars($u['avatar'] ?? ''); ?>"/>
<input type="file" id="avatarInput" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden"/>
<?php if ($u['avatar']): ?>
<label class="flex items-center gap-2 justify-center text-sm font-semibold text-error cursor-pointer mb-md">
<input type="checkbox" name="remover_avatar" class="w-4 h-4 accent-error"> Remover foto de perfil
</label>
<?php endif; ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-card-gutter">
<div class="input-group relative">
<label class="font-label-bold text-label-bold text-on-surface-variant ml-1 mb-base block">NOME COMPLETO</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">person</span>
<input class="w-full bg-[#F1F3F5] border-transparent border-b-2 border-b-transparent focus:border-b-primary focus:ring-0 rounded-xl py-3.5 pl-12 pr-4 font-body-md text-on-surface transition-all" name="nome" type="text" value="<?php echo htmlspecialchars($u['nome']); ?>" required/>
</div>
</div>
<div class="input-group relative">
<label class="font-label-bold text-label-bold text-on-surface-variant ml-1 mb-base block">E-MAIL</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">mail</span>
<input class="w-full bg-[#F1F3F5] border-transparent border-b-2 border-b-transparent focus:border-b-primary focus:ring-0 rounded-xl py-3.5 pl-12 pr-4 font-body-md text-on-surface transition-all" name="email" type="email" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>"/>
</div>
</div>
<div class="input-group relative">
<label class="font-label-bold text-label-bold text-on-surface-variant ml-1 mb-base block">CPF</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">badge</span>
<input class="w-full bg-[#F1F3F5] border-transparent border-b-2 border-b-transparent focus:border-b-primary focus:ring-0 rounded-xl py-3.5 pl-12 pr-4 font-body-md text-on-surface transition-all" id="cpf" name="cpf" type="text" value="<?php echo htmlspecialchars($u['cpf']); ?>" required/>
</div>
</div>
<div class="input-group relative">
<label class="font-label-bold text-label-bold text-on-surface-variant ml-1 mb-base block">RG</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">fingerprint</span>
<input class="w-full bg-[#F1F3F5] border-transparent border-b-2 border-b-transparent focus:border-b-primary focus:ring-0 rounded-xl py-3.5 pl-12 pr-4 font-body-md text-on-surface transition-all" id="rg" name="rg" type="text" value="<?php echo htmlspecialchars($u['rg'] ?? ''); ?>"/>
</div>
</div>
<div class="input-group relative">
<label class="font-label-bold text-label-bold text-on-surface-variant ml-1 mb-base block">DATA DE NASC.</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">calendar_month</span>
<input class="w-full bg-[#F1F3F5] border-transparent border-b-2 border-b-transparent focus:border-b-primary focus:ring-0 rounded-xl py-3.5 pl-12 pr-4 font-body-md text-on-surface transition-all" name="nascimento" type="date" value="<?php echo htmlspecialchars($u['nascimento'] ?? ''); ?>"/>
</div>
</div>
</div>
<div class="input-group relative">
<label class="font-label-bold text-label-bold text-on-surface-variant ml-1 mb-base block">ENDEREÇO COMPLETO</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">location_on</span>
<input class="w-full bg-[#F1F3F5] border-transparent border-b-2 border-b-transparent focus:border-b-primary focus:ring-0 rounded-xl py-3.5 pl-12 pr-4 font-body-md text-on-surface transition-all" name="endereco" type="text" value="<?php echo htmlspecialchars($u['endereco'] ?? ''); ?>"/>
</div>
</div>
<div class="grid grid-cols-2 gap-card-gutter">
<div class="input-group relative">
<label class="font-label-bold text-label-bold text-on-surface-variant ml-1 mb-base block">CIDADE</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">map</span>
<input class="w-full bg-[#F1F3F5] border-transparent border-b-2 border-b-transparent focus:border-b-primary focus:ring-0 rounded-xl py-3.5 pl-12 pr-4 font-body-md text-on-surface transition-all" name="cidade" type="text" value="<?php echo htmlspecialchars($u['cidade'] ?? ''); ?>"/>
</div>
</div>
<div class="input-group relative">
<label class="font-label-bold text-label-bold text-on-surface-variant ml-1 mb-base block">CEP</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">explore</span>
<input class="w-full bg-[#F1F3F5] border-transparent border-b-2 border-b-transparent focus:border-b-primary focus:ring-0 rounded-xl py-3.5 pl-12 pr-4 font-body-md text-on-surface transition-all" id="cep" name="cep" type="text" value="<?php echo htmlspecialchars($u['cep'] ?? ''); ?>"/>
</div>
</div>
</div>
<div class="input-group relative">
<label class="font-label-bold text-label-bold text-on-surface-variant ml-1 mb-base block">WHATSAPP</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">call</span>
<input class="w-full bg-[#F1F3F5] border-transparent border-b-2 border-b-transparent focus:border-b-primary focus:ring-0 rounded-xl py-3.5 pl-12 pr-4 font-body-md text-on-surface transition-all" id="whatsapp" name="whatsapp" type="tel" value="<?php echo htmlspecialchars($u['whatsapp'] ?? ''); ?>"/>
</div>
</div>
<div class="pt-sm">
<a class="w-full flex items-center justify-center gap-base font-label-bold text-label-bold text-primary py-2 hover:opacity-70 transition-opacity" href="gerar_contrato.php">
<span class="material-symbols-outlined text-[18px]">download</span>
                    BAIXAR CONTRATO DE USO DO CARTÃO
                </a>
</div>
<div class="pt-md pb-lg">
<button class="w-full bg-secondary hover:bg-on-secondary-container text-white rounded-full py-4 font-headline-sm text-headline-sm flex items-center justify-center gap-sm fab-shadow transition-all hover:scale-[1.02] active:scale-95" type="submit">
                    SALVAR
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">save</span>
</button>
</div>
<div class="border-t border-outline-variant/30 pt-md flex justify-center">
<button class="flex items-center gap-xs font-label-bold text-label-bold text-error py-2 hover:bg-error-container/20 px-4 rounded-lg transition-colors" type="button">
<span class="material-symbols-outlined text-[18px]">delete</span>
                    EXCLUIR CONTA
                </button>
</div>
</form>
</main>
<!-- BottomNavBar -->
<nav class="fixed bottom-0 w-full z-50 rounded-t-xl bg-surface/80 dark:bg-surface-dim/80 backdrop-blur-md border-t border-outline-variant/30 shadow-[0_-4px_20px_rgba(0,0,0,0.05)]">
<div class="flex justify-around items-center w-full py-2 pb-safe px-2">
<a class="flex flex-col items-center justify-center text-on-surface-variant dark:text-outline px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="dashboard.php">
<span class="material-symbols-outlined">home</span>
<span class="font-label-bold text-label-bold mt-1">Home</span>
</a>
<a class="flex flex-col items-center justify-center text-on-surface-variant dark:text-outline px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="parceiros.php">
<span class="material-symbols-outlined">storefront</span>
<span class="font-label-bold text-label-bold mt-1">Parceiros</span>
</a>
<a class="flex flex-col items-center justify-center text-on-surface-variant dark:text-outline px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="ativar.php">
<span class="material-symbols-outlined">credit_card</span>
<span class="font-label-bold text-label-bold mt-1">Cartão</span>
</a>
<a class="flex flex-col items-center justify-center bg-primary-container dark:bg-primary text-on-primary-container dark:text-on-primary rounded-full px-4 py-1.5 active:scale-90 duration-200" href="perfil.php">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">person</span>
<span class="font-label-bold text-label-bold mt-1">Perfil</span>
</a>
</div>
</nav>
<script>
        const cpfInput = document.getElementById('cpf');
        if (cpfInput) cpfInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            if (value.length > 9) value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            else if (value.length > 6) value = value.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
            else if (value.length > 3) value = value.replace(/(\d{3})(\d{3})/, '$1.$2');
            e.target.value = value;
        });
        const cepInput = document.getElementById('cep');
        if (cepInput) cepInput.addEventListener('input', (e) => { let value = e.target.value.replace(/\D/g, ''); if (value.length > 8) value = value.slice(0,8); if (value.length > 5) value = value.replace(/(\d{5})(\d{3})/, '$1-$2'); e.target.value = value; });
        const avatarInput = document.getElementById('avatarInput');
        if (avatarInput) avatarInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const img = new Image();
            img.onload = function() { img.classList.add('w-full', 'h-full', 'object-cover'); };
            img.src = URL.createObjectURL(file);
            const wrapper = document.querySelector('.w-32.h-32');
            if (wrapper) {
                wrapper.innerHTML = '';
                wrapper.appendChild(img);
            }
        });
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => { input.addEventListener('focus', () => input.parentElement.parentElement.classList.add('scale-[1.01]')); input.addEventListener('blur', () => input.parentElement.parentElement.classList.remove('scale-[1.01]')); });
    </script>
</body></html>