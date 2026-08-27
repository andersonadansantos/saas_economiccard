<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../usuario');
    exit;
}
$uid = (int)$_SESSION['usuario_id'];
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if (!$u) { header('Location: logout.php'); exit; }

$paginaAtiva = $paginaAtiva ?? 'dashboard';
if (($u['status'] ?? 'ativo') === 'desativado') {
    session_unset();
    session_destroy();
    header('Location: login.php?conta_encerrada=1');
    exit;
}
if ($paginaAtiva !== 'ativar' && !$u['cartao_ativo']) {
    header('Location: ativar.php?bloqueado=1');
    exit;
}

$primeiroNome = explode(' ', trim($u['nome']))[0];
$final = $u['final_cartao'] ?: '4582';
$avatar = $u['avatar'] ?: '';
$pers = $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc();
$logoApp = $pers['logo_login_user'] ?? '';
$atendimento = $conn->query("SELECT * FROM config_atendimento WHERE id = 1")->fetch_assoc();

function webUrl($src) {
    if (!$src) return '';
    if (preg_match('#^https?://#i', $src) || strpos($src, 'data:') === 0) return $src;
    return '../' . ltrim($src, '/');
}

$diasRestantes = null;
if ($u['cartao_ativo'] && !empty($u['cartao_validade'])) {
    $diasRestantes = (int)floor((strtotime($u['cartao_validade']) - strtotime(date('Y-m-d'))) / 86400);
}

$msg = $conn->prepare("SELECT * FROM mensagens WHERE (usuario_id = ? OR (usuario_id IS NULL AND criado_em >= ?)) ORDER BY criado_em DESC LIMIT 20");
$msg->bind_param('is', $uid, $u['criado_em']);
$msg->execute();
$mensagens = $msg->get_result();
$naoLidas = 0;
foreach ($mensagens as $m) {
    if ($m['usuario_id'] === null) {
        $chk = $conn->prepare("SELECT 1 FROM mensagens_lidas WHERE mensagem_id = ? AND usuario_id = ?");
        $chk->bind_param('ii', $m['id'], $uid);
        $chk->execute();
        $jaLida = $chk->get_result()->num_rows > 0;
        if (!$jaLida) { $naoLidas++; }
    } elseif (!$m['lida']) {
        $naoLidas++;
    }
}
if ($mensagens->num_rows > 0) { $mensagens->data_seek(0); }

$tituloPagina = $tituloPagina ?? 'Painel';
?>
<!DOCTYPE html>
<html class="light" lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title><?php echo htmlspecialchars($tituloPagina); ?> - Economic Card</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Hanken+Grotesk:wght@600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#51036d", "primary-dark": "#3a024d", "primary-container": "#6a2585",
                    secondary: "#3e6a00", "secondary-container": "#b6f570",
                    surface: "#f4f5f7", "on-surface": "#191c1d", "surface-variant": "#e1e3e4",
                    "on-surface-variant": "#4e434f", "outline-variant": "#d1c2d1",
                    "surface-container-lowest": "#ffffff", "surface-container-high": "#e7e8e9",
                    error: "#ba1a1a", "on-error": "#ffffff", "error-container": "#ffdad6"
                },
                borderRadius: { lg: "0.5rem", xl: "0.75rem", "2xl": "1rem", full: "9999px" },
                fontFamily: { sans: ["Manrope", "sans-serif"], display: ["Hanken Grotesk", "sans-serif"] }
            }
        }
    };
</script>
<style>
    body { background-color: #f4f5f7; color: #191c1d; font-family: 'Manrope', sans-serif; -webkit-tap-highlight-color: transparent; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .premium-gradient { background: linear-gradient(135deg, #51036d 0%, #6a2585 100%); }
    .card-shadow { box-shadow: 0 8px 30px rgba(81, 3, 109, 0.08); }
    .hover-lift { transition: transform .2s ease, box-shadow .2s ease; }
    .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 12px 36px rgba(81, 3, 109, 0.15); }
</style>
</head>
<body class="min-h-screen font-sans antialiased">
<div class="min-h-screen flex">
<!-- Sidebar -->
<aside class="hidden lg:flex flex-col fixed inset-y-0 left-0 w-72 premium-gradient text-white z-40">
<div class="px-6 py-6 flex items-center justify-center border-b border-white/10">
<?php if ($logoApp): ?>
<img class="w-[200px] h-auto object-contain" src="<?php echo webUrl($logoApp); ?>" alt="Logo Economic Card"/>
<?php else: ?>
<div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center"><span class="material-symbols-outlined text-white">credit_card</span></div>
<?php endif; ?>
</div>
<nav class="flex-1 px-4 py-6 space-y-1">
<a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $paginaAtiva === 'dashboard' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">home</span> Início
</a>
<a href="parceiros.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $paginaAtiva === 'parceiros' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">storefront</span> Parceiros
</a>
<a href="ativar.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $paginaAtiva === 'ativar' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">credit_score</span> Ativar Cartão
</a>
<a href="cartao_fisico.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $paginaAtiva === 'cartao_fisico' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">contactless</span> Cartão Físico
</a>
<a href="perfil.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $paginaAtiva === 'perfil' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">person</span> Perfil
</a>
<?php if (!empty($atendimento['whatsapp']) || !empty($atendimento['email'])): ?>
<button type="button" onclick="abrirAtendimento()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition hover:bg-white/10">
<span class="material-symbols-outlined text-[20px]">support_agent</span> Central de atendimento
</button>
<?php endif; ?>
</nav>
<div class="p-4 border-t border-white/10">
<div class="flex items-center gap-3 mb-3">
<div class="w-11 h-11 rounded-full overflow-hidden bg-white/10 flex items-center justify-center border-2 border-secondary-container shrink-0">
<?php if ($avatar): ?>
<img class="w-full h-full object-cover" src="<?php echo webUrl($avatar); ?>" alt="Foto de perfil"/>
<?php else: ?>
<span class="material-symbols-outlined text-white/80">person</span>
<?php endif; ?>
</div>
<div class="min-w-0">
<p class="text-sm font-bold truncate"><?php echo htmlspecialchars($u['nome']); ?></p>
<p class="text-[11px] text-white/60">Usuário do Cartão</p>
</div>
</div>
<a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold text-white/80 hover:bg-white/10 transition">
<span class="material-symbols-outlined text-[20px]">logout</span> Sair
</a>
</div>
</aside>
<!-- Mobile top bar -->
<header class="lg:hidden fixed top-0 left-0 right-0 z-50 premium-gradient text-white px-4 h-16 flex items-center justify-between">
<div class="flex items-center gap-3">
<button class="p-2 -ml-2 rounded-lg hover:bg-white/10" onclick="toggleDrawer()"><span class="material-symbols-outlined">menu</span></button>
<?php if ($logoApp): ?>
<img class="w-8 h-8 rounded-lg object-contain bg-white/10 p-0.5" src="<?php echo webUrl($logoApp); ?>" alt="Logo"/>
<?php else: ?>
<span class="material-symbols-outlined">credit_card</span>
<?php endif; ?>
</div>
<span class="font-bold text-sm truncate"><?php echo htmlspecialchars($tituloPagina); ?></span>
<div class="relative">
<button class="p-2 rounded-lg hover:bg-white/10 relative" onclick="toggleNotif()"><span class="material-symbols-outlined">notifications</span>
<?php if ($naoLidas > 0): ?>
<span class="absolute top-1 right-1 w-4 h-4 bg-error text-on-error text-[9px] font-bold rounded-full flex items-center justify-center"><?php echo $naoLidas; ?></span>
<?php endif; ?>
</button>
</div>
</header>
<main class="flex-1 lg:ml-72 px-4 md:px-8 pt-20 lg:pt-8 pb-16 max-w-6xl mx-auto w-full">
<!-- Desktop topbar -->
<div class="hidden lg:flex items-center justify-between gap-4 mb-8">
<div>
<h2 class="text-2xl font-extrabold text-on-surface"><?php echo htmlspecialchars($tituloPagina); ?></h2>
<p class="text-sm text-on-surface-variant mt-0.5">Bem-vindo de volta, <?php echo htmlspecialchars($primeiroNome); ?>!</p>
</div>
<div class="flex items-center gap-3">
<div class="relative">
<button class="bg-white card-shadow p-3 rounded-full hover:shadow-lg transition relative" onclick="toggleNotif()">
<span class="material-symbols-outlined text-primary">notifications</span>
<?php if ($naoLidas > 0): ?>
<span class="absolute top-1 right-1 w-4 h-4 bg-error text-on-error text-[9px] font-bold rounded-full flex items-center justify-center"><?php echo $naoLidas; ?></span>
<?php endif; ?>
</button>
</div>
</div>
</div>
