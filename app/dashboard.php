<?php
require_once 'config.php';
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
exigirCartaoAtivo();
$uid = (int)$_SESSION['usuario_id'];
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if (!$u) { header('Location: logout.php'); exit; }

$ehDep = ehDependente();
$nomeExib = ($ehDep && !empty($_SESSION['dependente_nome'])) ? $_SESSION['dependente_nome'] : $u['nome'];
$primeiroNome = explode(' ', trim($nomeExib))[0];
$final = $u['final_cartao'] ?: '4582';
$avatar = $u['avatar'] ?: '';
$avatarExib = $ehDep ? asset_url('../img/icons/ico_dependente.png') : ($avatar ? asset_url($avatar) : '');
$pers = $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc();
$logoApp = $pers['logo_app'] ?? '';
$cartaoAtivado = $pers['cartao_ativado'] ?? '';
$cartaoDesativado = $pers['cartao_desativado'] ?? '';
$cartaoImg = $u['cartao_ativo'] ? $cartaoAtivado : $cartaoDesativado;
$atendimento = $conn->query("SELECT * FROM config_atendimento WHERE id = 1")->fetch_assoc();

$diasRestantes = null;
if ($u['cartao_ativo'] && !empty($u['cartao_validade'])) {
    $d1 = new DateTime(date('Y-m-d'));
    $d2 = new DateTime($u['cartao_validade']);
    $diasRestantes = (int)$d1->diff($d2)->days + 1;
    if ($d2 < $d1) $diasRestantes = 0;
}

$bannersTopo = $conn->query("SELECT * FROM banners_topo WHERE ativo = 1 ORDER BY ordem ASC, id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$msg = $conn->prepare("SELECT * FROM mensagens WHERE (usuario_id = ? OR (usuario_id IS NULL AND criado_em >= ?)) ORDER BY criado_em DESC LIMIT 20");
$msg->bind_param('is', $uid, $u['criado_em']);
$msg->execute();
$mensagens = $msg->get_result();
$naoLidas = 0;
$idsBroadcast = [];
foreach ($mensagens as $m) {
    if ($m['usuario_id'] === null) {
        $chk = $conn->prepare("SELECT 1 FROM mensagens_lidas WHERE mensagem_id = ? AND usuario_id = ?");
        $chk->bind_param('ii', $m['id'], $uid);
        $chk->execute();
        $jaLida = $chk->get_result()->num_rows > 0;
        if (!$jaLida) { $naoLidas++; }
        $idsBroadcast[] = (int)$m['id'];
    } elseif (!$m['lida']) {
        $naoLidas++;
    }
}
if ($mensagens->num_rows > 0) {
    $mensagens->data_seek(0);
}

$conn->query("UPDATE mensagens SET lida = 1 WHERE usuario_id = $uid AND lida = 0");
foreach ($idsBroadcast as $mid) {
    $ins = $conn->prepare("INSERT IGNORE INTO mensagens_lidas (mensagem_id, usuario_id) VALUES (?, ?)");
    $ins->bind_param('ii', $mid, $uid);
    $ins->execute();
}
?>
<!DOCTYPE html>

<html class="light" lang="pt-br"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Economic Card Dashboard</title>
<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;display=swap" rel="stylesheet"/>
<!-- Icons -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "error-container": "#ffdad6",
                        "tertiary": "#4a2700",
                        "surface-tint": "#8540a0",
                        "primary-container": "#6a2585",
                        "surface-bright": "#f8f9fa",
                        "on-secondary-fixed-variant": "#2e5000",
                        "surface-variant": "#e1e3e4",
                        "on-surface-variant": "#4e434f",
                        "surface-container-high": "#e7e8e9",
                        "surface-container-low": "#f3f4f5",
                        "secondary": "#3e6a00",
                        "outline": "#7f7381",
                        "on-tertiary-fixed": "#2e1600",
                        "on-surface": "#191c1d",
                        "surface-dim": "#d9dadb",
                        "surface-container": "#edeeef",
                        "tertiary-fixed-dim": "#ffb877",
                        "secondary-container": "#b6f570",
                        "on-error": "#ffffff",
                        "inverse-on-surface": "#f0f1f2",
                        "tertiary-fixed": "#ffdcc0",
                        "background": "#f8f9fa",
                        "on-primary": "#ffffff",
                        "on-tertiary-container": "#ff9d35",
                        "primary-fixed-dim": "#edb1ff",
                        "primary-fixed": "#f9d8ff",
                        "on-primary-fixed-variant": "#6b2686",
                        "on-tertiary-fixed-variant": "#6c3b00",
                        "secondary-fixed-dim": "#9cd857",
                        "outline-variant": "#d1c2d1",
                        "on-secondary-fixed": "#0f2000",
                        "on-secondary-container": "#427000",
                        "surface": "#f8f9fa",
                        "surface-container-highest": "#e1e3e4",
                        "primary": "#51036d",
                        "inverse-surface": "#2e3132",
                        "on-background": "#191c1d",
                        "error": "#ba1a1a",
                        "on-primary-container": "#e397fd",
                        "surface-container-lowest": "#ffffff",
                        "on-primary-fixed": "#320046",
                        "secondary-fixed": "#b6f570",
                        "tertiary-container": "#6a3a00",
                        "on-secondary": "#ffffff",
                        "inverse-primary": "#edb1ff",
                        "on-tertiary": "#ffffff",
                        "on-error-container": "#93000a"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "sm": "12px",
                        "md": "24px",
                        "lg": "32px",
                        "card-gutter": "16px",
                        "xl": "48px",
                        "base": "8px",
                        "xs": "4px",
                        "container-padding": "20px"
                    },
"fontFamily": {
                        "label-caps": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"],
                        "headline-sm": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"],
                        "body-md": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"],
                        "headline-md": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"],
                        "display-lg": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"],
                        "body-lg": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"],
                        "label-bold": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"]
                    },
                    "fontSize": {
                        "label-caps": ["11px", {"lineHeight": "1", "letterSpacing": "0.06em", "fontWeight": "600"}],
                        "headline-sm": ["18px", {"lineHeight": "1.3", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                        "body-md": ["15px", {"lineHeight": "1.45", "fontWeight": "400"}],
                        "headline-md": ["22px", {"lineHeight": "1.25", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "display-lg": ["30px", {"lineHeight": "1.15", "letterSpacing": "-0.03em", "fontWeight": "700"}],
                        "body-lg": ["17px", {"lineHeight": "1.5", "fontWeight": "400"}],
                        "label-bold": ["13px", {"lineHeight": "1", "letterSpacing": "0.01em", "fontWeight": "700"}]
                    }
                },
            },
        }
    </script>
<style>
        body {
            background-color: theme('colors.background');
            -webkit-tap-highlight-color: transparent;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .premium-gradient {
            background: linear-gradient(135deg, #51036d 0%, #6a2585 100%);
        }
        .active-pill {
            background: rgba(182, 245, 112, 0.2);
            color: #3e6a00;
            border: 1px solid rgba(62, 106, 0, 0.3);
        }
        .inactive-pill {
            background: rgba(127, 115, 129, 0.2);
            color: #4e434f;
            border: 1px solid rgba(127, 115, 129, 0.4);
        }
    </style>
<link rel="stylesheet" href="ui.css?v=<?php echo (int)@filemtime(__DIR__ . '/ui.css'); ?>"/>
</head>
<body class="font-body-md text-on-surface antialiased ec-body">
<!-- Side Drawer Menu -->
<div class="hidden fixed inset-0 z-[90]" id="drawerMenu">
<div class="absolute inset-0 bg-black/40" onclick="fecharMenu()"></div>
<div class="absolute inset-y-0 left-0 w-[300px] max-w-[86vw] bg-white rounded-r-[26px] flex flex-col" style="box-shadow:0 30px 70px -24px rgba(25,28,29,.5)">
<div class="px-4 pt-5 pb-4 flex items-center justify-between gap-2 border-b border-outline-variant/20">
<div class="flex items-center gap-2.5">
<div class="w-9 h-9 rounded-xl premium-gradient flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-white text-[19px]">credit_card</span>
</div>
<div class="min-w-0">
<h2 class="text-[14px] font-extrabold text-on-surface leading-tight truncate">Economic Card</h2>
<p class="ec-meta">Menu principal</p>
</div>
</div>
<button class="ec-iconbtn ec-iconbtn--muted" onclick="fecharMenu()" aria-label="Fechar menu"><span class="material-symbols-outlined">close</span></button>
</div>
<div class="px-4 py-4 border-b border-outline-variant/20">
<div class="flex items-center gap-3 px-1">
<div class="w-12 h-12 rounded-full bg-surface-container-high overflow-hidden flex items-center justify-center text-primary shrink-0">
<?php if ($avatarExib): ?>
<img class="w-full h-full object-cover av-fallback-img" data-fallback="sidebar" src="<?php echo htmlspecialchars($avatarExib); ?>" alt="Foto de perfil" onerror="this.style.display='none';document.getElementById('av-fallback-sidebar').style.display='flex';"/>
<span id="av-fallback-sidebar" class="material-symbols-outlined text-[24px] hidden" style="font-variation-settings:'FILL' 1;">person</span>
<?php else: ?>
<span class="material-symbols-outlined text-[24px]" style="font-variation-settings:'FILL' 1;">person</span>
<?php endif; ?>
</div>
<div class="min-w-0">
<p class="text-[14px] font-extrabold text-on-surface truncate"><?php echo htmlspecialchars($nomeExib); ?><?php if ($ehDep): ?> <span class="ec-chip ec-chip--primary" style="font-size:9px;padding:3px 7px;">Dependente</span><?php endif; ?></p>
<p class="ec-meta mt-0.5"><?php echo $ehDep ? 'Dependente' : 'Usuário do Cartão'; ?></p>
</div>
</div>
</div>
<nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
<?php if (!$ehDep): ?>
<a href="perfil.php" class="ec-navitem">
<span class="material-symbols-outlined">account_circle</span>
                        Perfil
                    </a>
<a href="ativar.php" class="ec-navitem">
<span class="material-symbols-outlined" style="color:#3e6a00">credit_score</span>
                        Ativar Cartão
                    </a>
<a href="dependentes.php" class="ec-navitem">
<span class="material-symbols-outlined">groups</span>
                        Dependentes
                    </a>
<?php endif; ?>
<?php if (!empty($atendimento['whatsapp']) || !empty($atendimento['email'])): ?>
<button type="button" onclick="fecharMenu(); abrirAtendimento();" class="ec-navitem w-full text-left">
<span class="material-symbols-outlined">support_agent</span>
                        Central de atendimento
                    </button>
<?php endif; ?>
</nav>
<div class="p-3 border-t border-outline-variant/20">
<a href="logout.php" class="ec-navitem ec-navitem--danger">
<span class="material-symbols-outlined">logout</span>
                        Sair
                    </a>
</div>
</div>
</div>
<!-- TopAppBar -->
<header class="ec-appbar" id="appBar">
<div class="ec-appbar__inner">
<button class="ec-iconbtn" onclick="abrirMenu()" aria-label="Abrir menu">
<span class="material-symbols-outlined">menu</span>
</button>
<?php if ($logoApp): ?>
<a href="dashboard.php" class="ec-brand" aria-label="Início">
<img src="<?php echo htmlspecialchars(asset_url($logoApp)); ?>" alt="Economic Card"/>
</a>
<?php else: ?>
<a href="dashboard.php" class="ec-brand" aria-label="Início">
<span class="ec-brand__mark"><span class="material-symbols-outlined text-[20px]">credit_card</span></span>
<span class="text-[14px] font-extrabold text-on-surface tracking-tight">Economic Card</span>
</a>
<?php endif; ?>
<div class="ec-appbar__spacer"></div>
<div class="relative" id="notifWrap">
<button class="ec-iconbtn" id="notifBtn" aria-label="Mensagens e avisos">
<span class="material-symbols-outlined">notifications</span>
</button>
<?php if ($naoLidas > 0): ?>
<span class="absolute top-1 right-1 min-w-[17px] h-[17px] px-1 bg-error text-on-error text-[9px] font-extrabold rounded-full flex items-center justify-center ring-2 ring-[#f8f9fa]" id="notifBadge"><?php echo $naoLidas; ?></span>
<?php endif; ?>
<div class="hidden absolute right-0 top-[52px] w-[320px] max-w-[88vw] bg-white rounded-[20px] border border-outline-variant/40 z-50 overflow-hidden" style="box-shadow:0 24px 60px -28px rgba(25,28,29,.45)" id="notifPanel">
<div class="px-4 py-3 border-b border-outline-variant/25 flex items-center justify-between">
<span class="ec-eyebrow">Mensagens</span>
<span class="material-symbols-outlined text-outline text-[18px]">mark_email_unread</span>
</div>
<div class="max-h-[min(60vh,360px)] overflow-y-auto">
<?php if ($mensagens->num_rows === 0): ?>
<div class="px-4 py-10 text-center">
<span class="material-symbols-outlined text-outline/60 text-[28px]">inbox</span>
<p class="ec-meta mt-2">Nenhuma mensagem no momento.</p>
</div>
<?php endif; ?>
<?php while ($m = $mensagens->fetch_assoc()): ?>
<button type="button" class="w-full text-left px-4 py-3 border-b border-outline-variant/15 hover:bg-surface-container-high/40 transition-colors" onclick="abrirMensagem('<?php echo addslashes(htmlspecialchars($m['titulo'])); ?>', '<?php echo addslashes(htmlspecialchars($m['mensagem'])); ?>', '<?php echo date('d/m/Y H:i', strtotime($m['criado_em'])); ?>')">
<p class="text-[13px] font-bold text-on-surface leading-snug"><?php echo htmlspecialchars($m['titulo']); ?></p>
<p class="text-[12px] text-on-surface-variant truncate mt-0.5"><?php echo htmlspecialchars($m['mensagem']); ?></p>
<p class="text-[10px] text-outline mt-1"><?php echo date('d/m/Y H:i', strtotime($m['criado_em'])); ?></p>
</button>
<?php endwhile; ?>
</div>
</div>
</div>
</div>
</header>
<main class="ec-shell">
<!-- Greeting -->
<section class="ec-hero ec-fade-in">
<div class="ec-hero__avatar">
<?php if ($avatarExib): ?>
<img class="av-fallback-img" data-fallback="greeting" src="<?php echo htmlspecialchars($avatarExib); ?>" alt="Foto de perfil" onerror="this.style.display='none';document.getElementById('av-fallback-greeting').style.display='flex';"/>
<span id="av-fallback-greeting" class="material-symbols-outlined hidden" style="font-variation-settings:'FILL' 1;">person</span>
<?php else: ?>
<span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">person</span>
<?php endif; ?>
</div>
<div class="min-w-0 flex-1">
<p class="ec-eyebrow">Bem-vindo de volta</p>
<h1 class="ec-h1 truncate mt-0.5">Olá, <?php echo htmlspecialchars($primeiroNome); ?></h1>
<div class="flex items-center gap-2 mt-0.5">
<p class="ec-sub truncate">Confira o status do seu benefício hoje.</p>
<?php if ($ehDep): ?><span class="ec-chip ec-chip--primary shrink-0">Dependente</span><?php endif; ?>
</div>
</div>
</section>
<!-- Digital Card (The Centerpiece) -->
<section class="ec-section" style="margin-top:20px">
<div class="ec-section__head">
<span class="ec-eyebrow">Seu cartão</span>
<span class="ec-hint"><span class="material-symbols-outlined">touch_app</span> Toque para ampliar</span>
</div>
<div class="relative group active:scale-[0.99] transition-transform duration-300" onclick="abrirFullscreen()">
<?php if ($cartaoImg): ?>
<div class="relative rounded-[20px] overflow-hidden aspect-[1.58/1] cursor-pointer" style="box-shadow:0 18px 42px -26px rgba(81,3,109,.55)">
<img class="absolute inset-0 w-full h-full object-contain" src="<?php echo htmlspecialchars(asset_url($cartaoImg)); ?>" alt="Cartão Economic Card"/>
<div class="absolute inset-0 flex flex-col items-start justify-center gap-1.5 p-6 translate-y-[50px]">
<p class="font-display-lg text-white text-[16px] truncate max-w-full text-left" style="text-shadow: 0 1px 4px rgba(0,0,0,0.6);"><?php echo htmlspecialchars($u['nome']); ?></p>
<?php if ($u['cartao_ativo']): ?>
<div class="bg-secondary text-on-secondary px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-white/80"></span>
                    ATIVADO
                </div>
<?php else: ?>
<div class="bg-error text-on-error px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-white/80"></span>
                    EXPIRADO
                </div>
<?php endif; ?>
</div>
</div>
<?php else: ?>
<div class="premium-gradient rounded-[20px] p-6 relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between cursor-pointer" style="box-shadow:0 18px 42px -26px rgba(81,3,109,.55)">
<!-- Decorative Shapes -->
<div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
<div class="absolute bottom-0 left-0 w-full h-1/2 bg-gradient-to-t from-black/20 to-transparent"></div>
<div class="flex justify-between items-start z-10">
<div class="space-y-1">
<span class="font-label-caps text-on-primary/60 tracking-widest">MEMBRO PLATINUM</span>
<div class="h-[2px] w-8 bg-secondary-container"></div>
</div>
<?php if ($u['cartao_ativo']): ?>
<div class="active-pill px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
                        ATIVO
                    </div>
<?php else: ?>
<div class="inactive-pill px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-outline"></span>
                        INATIVO
                    </div>
<?php endif; ?>
</div>
<div class="flex flex-col items-start gap-1.5 z-10 mt-[50px]">
<p class="font-display-lg text-white text-[16px] truncate max-w-full text-left"><?php echo htmlspecialchars($u['nome']); ?></p>
<?php if ($u['cartao_ativo']): ?>
<div class="bg-secondary text-on-secondary px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-white/80"></span>
                    ATIVADO
                </div>
<?php else: ?>
<div class="bg-error text-on-error px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-white/80"></span>
                    EXPIRADO
                </div>
<?php endif; ?>
</div>
<div class="z-10 mt-auto">
<p class="font-display-lg text-white text-[18px] mb-4"><?php echo htmlspecialchars(strtoupper($u['nome'])); ?></p>
<div class="flex justify-between items-end">
<div class="space-y-1">
<p class="font-label-bold text-white/70 tracking-widest">•••• •••• •••• <?php echo htmlspecialchars($final); ?></p>
<p class="font-label-caps text-white/50">VALIDADE <?php echo !empty($u['cartao_validade']) ? date('m/y', strtotime($u['cartao_validade'])) : '--/--'; ?></p>
</div>
<div class="flex flex-col items-end">
<span class="font-display-lg text-white/20 text-[28px] font-extrabold italic">Economic</span>
</div>
</div>
</div>
</div>
<?php endif; ?>
</div>
</section>
<?php if ($u['cartao_ativo'] && $diasRestantes !== null): ?>
<div class="ec-inline-stats">
<span class="ec-inline-stats__icon"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">schedule</span></span>
<div class="min-w-0">
<p class="text-[12.5px] font-extrabold text-on-surface leading-tight"><?php echo $diasRestantes > 0 ? ($diasRestantes === 1 ? '1 dia restante' : $diasRestantes . ' dias restantes') : 'Cartão expirado'; ?></p>
<p class="ec-meta mt-0.5">Validade em <?php echo date('d/m/Y', strtotime($u['cartao_validade'])); ?></p>
</div>
</div>
<?php endif; ?>
<!-- Atalhos -->
<section class="ec-section">
<div class="ec-card ec-card--flush">
<div class="ec-shortcuts">
<a href="parceiros.php" class="ec-shortcut">
<span class="ec-shortcut__icon"><img src="<?php echo htmlspecialchars(asset_url('../img/icons/parceiros.png')); ?>" alt="" loading="lazy"/></span>
<span class="ec-shortcut__label">Parceiros</span>
</a>
<?php if (!$ehDep): ?>
<a href="ativar.php" class="ec-shortcut">
<span class="ec-shortcut__icon"><img src="<?php echo htmlspecialchars(asset_url('../img/icons/cartao.png')); ?>" alt="" loading="lazy"/></span>
<span class="ec-shortcut__label">Cartão</span>
</a>
<a href="perfil.php" class="ec-shortcut">
<span class="ec-shortcut__icon"><img src="<?php echo htmlspecialchars(asset_url('../img/icons/perfil.png')); ?>" alt="" loading="lazy"/></span>
<span class="ec-shortcut__label">Perfil</span>
</a>
<?php endif; ?>
<div class="ec-shortcut" aria-label="Cupons — em breve">
<span class="ec-shortcut__icon"><img src="<?php echo htmlspecialchars(asset_url('../img/icons/cupon.png')); ?>" alt=""/><span class="ec-shortcut__flag">Breve</span></span>
<span class="ec-shortcut__label">Cupons</span>
</div>
<div class="ec-shortcut" aria-label="Cursos — em breve">
<span class="ec-shortcut__icon"><img src="<?php echo htmlspecialchars(asset_url('../img/icons/cursos.png')); ?>" alt=""/><span class="ec-shortcut__flag">Breve</span></span>
<span class="ec-shortcut__label">Cursos</span>
</div>
</div>
</div>
</section>
<!-- Quick Access -->
<section class="ec-section">
<div class="ec-section__head">
<span class="ec-eyebrow">Acesso rápido</span>
</div>
<div class="ec-actions">
<?php if (!$ehDep): ?>
<a href="perfil.php" class="ec-action">
<span class="ec-tile"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">account_circle</span></span>
<span class="ec-action__label">Perfil</span>
<span class="material-symbols-outlined ec-action__chev">chevron_right</span>
</a>
<a href="ativar.php" class="ec-action">
<span class="ec-tile ec-tile--lime"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">credit_score</span></span>
<span class="ec-action__label">Ativar cartão</span>
<span class="material-symbols-outlined ec-action__chev">chevron_right</span>
</a>
<?php endif; ?>
<a href="parceiros.php" class="ec-action">
<span class="ec-tile"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">storefront</span></span>
<span class="ec-action__label">Parceiros</span>
<span class="material-symbols-outlined ec-action__chev">chevron_right</span>
</a>
<div class="ec-action ec-action--off ec-action--flag" aria-label="Telemedicina — em breve">
<span class="ec-tile"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">stethoscope</span></span>
<span class="ec-action__label">Telemedicina</span>
<span class="ec-chip ec-chip--neutral ec-action__flag">Breve</span>
</div>
</div>
</section>
<?php if (!empty($bannersTopo)): ?>
<!-- Destaques -->
<section class="ec-section">
<div class="ec-section__head">
<span class="ec-eyebrow">Destaques</span>
</div>
<div class="relative overflow-hidden rounded-[20px]" style="box-shadow:0 16px 38px -28px rgba(25,28,29,.55)">
<div id="topoTrackApp" style="display:flex; flex-wrap:nowrap; will-change:transform;">
<?php foreach ($bannersTopo as $bt): ?>
<div style="flex:0 0 100%; min-width:100%;">
<?php if (!empty($bt['link_externo'])): ?>
<a href="<?php echo htmlspecialchars($bt['link_externo']); ?>" target="_blank" rel="noopener">
<img src="<?php echo htmlspecialchars(asset_url($bt['imagem'])); ?>" alt="" class="w-full h-[136px] object-cover block"/>
</a>
<?php else: ?>
<img src="<?php echo htmlspecialchars(asset_url($bt['imagem'])); ?>" alt="" class="w-full h-[136px] object-cover block"/>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php if (count($bannersTopo) > 1): ?>
<div class="absolute bottom-2.5 left-0 right-0 z-10 flex items-center justify-center gap-1.5" id="topoDotsApp" style="pointer-events:none;">
<?php foreach ($bannersTopo as $i => $bt): ?><span class="rounded-full transition-all duration-300 cursor-pointer" data-index="<?php echo $i; ?>" onclick="topoGoTo(<?php echo $i; ?>)" style="pointer-events:auto;width:6px;height:6px;background:rgba(255,255,255,.5);box-shadow:0 1px 4px rgba(0,0,0,.3);<?php echo $i === 0 ? 'width:20px;background:#b6f570;' : ''; ?>"></span><?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<script>
(function(){
var track=document.getElementById('topoTrackApp');
if(!track)return;
var items=Array.prototype.slice.call(track.children);
if(items.length<2)return;
var idx=0,total=items.length,auto;
window.topoGoTo=function(i){goTo(i);};
function goTo(i){idx=i;track.style.transition='transform .4s cubic-bezier(.22,1,.36,1)';track.style.transform='translateX('+(idx*-100)+'%)';updDots();}
function next(){goTo((idx+1)%total);}
function updDots(){var dots=document.querySelectorAll('#topoDotsApp span');dots.forEach(function(d,i){d.style.width=i===idx?'20px':'6px';d.style.background=i===idx?'#b6f570':'rgba(255,255,255,.5)';});}
function startAuto(){auto=setInterval(next,4000);}
startAuto();
var x0=null;
track.addEventListener('touchstart',function(e){x0=e.touches[0].clientX;clearInterval(auto);},{passive:true});
track.addEventListener('touchend',function(e){if(x0===null)return;var dx=e.changedTouches[0].clientX-x0;if(dx<-40)goTo((idx+1)%total);else if(dx>40)goTo((idx-1+total)%total);startAuto();x0=null;});
})();
</script>
</section>
<?php endif; ?>
</main>
<!-- BottomNavBar -->
<?php
require_once __DIR__ . '/bottom_nav.php';
$navInferior = [
    ['href' => 'dashboard.php', 'icone' => 'home', 'label' => 'Home', 'ativo' => true],
    ['href' => 'parceiros.php', 'icone' => 'storefront', 'label' => 'Parceiros'],
];
if ($ehDep) {
    $navInferior[] = ['tipo' => 'button', 'onclick' => 'abrirAtendimento()', 'icone' => 'support_agent', 'label' => 'Atendimento'];
} else {
    $navInferior[] = ['href' => 'ativar.php', 'icone' => 'credit_card', 'label' => 'Cartão'];
    $navInferior[] = ['href' => 'perfil.php', 'icone' => 'person', 'label' => 'Perfil'];
}
app_bottom_nav($navInferior);
unset($navInferior);
?>
<!-- Fullscreen Card Overlay -->
<div class="hidden fixed inset-0 z-[100] bg-black/90 backdrop-blur-md items-center justify-center p-5" id="fullscreenCard">
<button class="ec-iconbtn" style="position:absolute;top:18px;right:18px;color:rgba(255,255,255,.85);background:rgba(255,255,255,.12)" onclick="fecharFullscreen()" aria-label="Fechar"><span class="material-symbols-outlined">close</span></button>
<div class="w-full max-w-md premium-gradient rounded-[22px] p-8 shadow-2xl relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between">
<?php if ($cartaoImg): ?>
<img class="w-full h-full object-contain rounded-2xl" src="<?php echo htmlspecialchars(asset_url($cartaoImg)); ?>" alt="Cartão Economic Card"/>
<?php else: ?>
<div class="absolute -top-10 -right-10 w-48 h-48 bg-white/10 rounded-full blur-2xl"></div>
<div class="flex justify-between items-start z-10">
<div class="space-y-1">
<span class="font-label-caps text-on-primary/60 tracking-widest">MEMBRO PLATINUM</span>
<div class="h-[2px] w-8 bg-secondary-container"></div>
</div>
<div class="active-pill px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
<?php echo $u['cartao_ativo'] ? 'ATIVO' : 'INATIVO'; ?>
</div>
</div>
<div class="z-10 mt-auto">
<p class="font-display-lg text-white text-[22px] mb-4"><?php echo htmlspecialchars(strtoupper($u['nome'])); ?></p>
<div class="flex justify-between items-end">
<div class="space-y-1">
<p class="font-label-bold text-white/70 tracking-widest">•••• •••• •••• <?php echo htmlspecialchars($final); ?></p>
<p class="font-label-caps text-white/50">VALIDADE <?php echo !empty($u['cartao_validade']) ? date('m/y', strtotime($u['cartao_validade'])) : '--/--'; ?></p>
</div>
<div class="flex flex-col items-end">
<span class="font-display-lg text-white/20 text-[40px] font-extrabold italic">Economic</span>
</div>
</div>
</div>
<?php endif; ?>
</div>
</div>
<!-- Mensagem Modal -->
<div class="hidden fixed inset-0 z-[120] bg-black/60 backdrop-blur-sm items-center justify-center p-5" id="mensagemModal">
<div class="ec-sheet">
<div class="ec-sheet__head">
<div class="flex items-center gap-2 min-w-0">
<span class="ec-tile" style="width:34px;height:34px;border-radius:11px;"><span class="material-symbols-outlined" style="font-size:18px;font-variation-settings:'FILL' 1;">notifications</span></span>
<span class="text-[12.5px] font-extrabold text-on-surface uppercase tracking-wide truncate" id="msgModalTitulo">MENSAGEM</span>
</div>
<button class="ec-iconbtn ec-iconbtn--muted" style="width:34px;height:34px" onclick="fecharMensagem()" aria-label="Fechar"><span class="material-symbols-outlined" style="font-size:20px">close</span></button>
</div>
<div class="ec-sheet__body max-h-[58vh] overflow-y-auto">
<p class="text-[13.5px] leading-relaxed text-on-surface-variant whitespace-pre-line" id="msgModalTexto"></p>
</div>
<div class="ec-sheet__foot">
<span class="ec-meta" id="msgModalData"></span>
<button class="ec-btn ec-btn--ghost ec-btn--sm" onclick="fecharMensagem()">Fechar</button>
</div>
</div>
</div>
<!-- Atendimento Modal -->
<div class="hidden fixed inset-0 z-[130] bg-black/60 backdrop-blur-sm items-center justify-center p-5" id="atendimentoModal">
<div class="ec-sheet">
<div class="ec-sheet__head">
<div class="flex items-center gap-2 min-w-0">
<span class="ec-tile" style="width:34px;height:34px;border-radius:11px;"><span class="material-symbols-outlined" style="font-size:18px;font-variation-settings:'FILL' 1;">support_agent</span></span>
<span class="text-[12.5px] font-extrabold text-on-surface uppercase tracking-wide truncate">Central de atendimento</span>
</div>
<button class="ec-iconbtn ec-iconbtn--muted" style="width:34px;height:34px" onclick="fecharAtendimento()" aria-label="Fechar"><span class="material-symbols-outlined" style="font-size:20px">close</span></button>
</div>
<div class="ec-sheet__body flex flex-col gap-2.5">
<?php if (!empty($atendimento['whatsapp'])): ?>
<a href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/\D/', '', $atendimento['whatsapp'])); ?>" target="_blank" class="ec-btn ec-btn--outline ec-btn--block" style="justify-content:flex-start;color:#128C7E;border-color:rgba(37,211,102,.35);background:rgba(37,211,102,.08)">
<span class="material-symbols-outlined">chat</span> Falar pelo WhatsApp
</a>
<?php endif; ?>
<?php if (!empty($atendimento['email'])): ?>
<a href="mailto:<?php echo htmlspecialchars($atendimento['email']); ?>" class="ec-btn ec-btn--outline ec-btn--block" style="justify-content:flex-start;color:var(--ec-primary);border-color:rgba(81,3,109,.25);background:rgba(81,3,109,.06)">
<span class="material-symbols-outlined">mail</span> Enviar e-mail
</a>
<?php endif; ?>
</div>
<div class="ec-sheet__foot" style="justify-content:flex-end">
<button class="ec-btn ec-btn--ghost ec-btn--sm" onclick="fecharAtendimento()">Fechar</button>
</div>
</div>
</div>
<script>
        // Micro-interactions and touch feedback
        document.querySelectorAll('a, button, .shadow-sm').forEach(el => {
            el.addEventListener('touchstart', () => {
                el.classList.add('opacity-80');
            });
            el.addEventListener('touchend', () => {
                el.classList.remove('opacity-80');
            });
        });

        const card = document.querySelector('.premium-gradient');
        if (card) {
            window.addEventListener('deviceorientation', (e) => {
                const tiltX = e.beta / 20;
                const tiltY = -e.gamma / 20;
                card.style.transform = `perspective(1000px) rotateX(${tiltX}deg) rotateY(${tiltY}deg)`;
            });
        }

        function abrirFullscreen() {
            const el = document.getElementById('fullscreenCard');
            el.classList.remove('hidden');
            el.classList.add('flex');
        }
        function fecharFullscreen() {
            const el = document.getElementById('fullscreenCard');
            el.classList.add('hidden');
            el.classList.remove('flex');
        }

        function abrirMenu() {
            const el = document.getElementById('drawerMenu');
            el.classList.remove('hidden');
            el.classList.add('flex');
        }
        function fecharMenu() {
            const el = document.getElementById('drawerMenu');
            el.classList.add('hidden');
            el.classList.remove('flex');
        }

        const notifBtn = document.getElementById('notifBtn');
        const notifPanel = document.getElementById('notifPanel');
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notifPanel.classList.toggle('hidden');
        });
        document.addEventListener('click', (e) => {
            if (!document.getElementById('notifWrap').contains(e.target)) {
                notifPanel.classList.add('hidden');
            }
        });

        function abrirAtendimento() {
            const el = document.getElementById('atendimentoModal');
            el.classList.remove('hidden');
            el.classList.add('flex');
        }
        function fecharAtendimento() {
            const el = document.getElementById('atendimentoModal');
            el.classList.add('hidden');
            el.classList.remove('flex');
        }
        document.getElementById('atendimentoModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('atendimentoModal')) fecharAtendimento();
        });

        function abrirMensagem(titulo, texto, data) {
            document.getElementById('msgModalTitulo').textContent = titulo;
            document.getElementById('msgModalTexto').textContent = texto;
            document.getElementById('msgModalData').textContent = data;
            const el = document.getElementById('mensagemModal');
            el.classList.remove('hidden');
            el.classList.add('flex');
            notifPanel.classList.add('hidden');
        }
        function fecharMensagem() {
            const el = document.getElementById('mensagemModal');
            el.classList.add('hidden');
            el.classList.remove('flex');
        }

        // Estado de rolagem da barra superior (apenas apresentação)
        (function () {
            const barra = document.getElementById('appBar');
            if (!barra) return;
            const aoRolar = () => barra.classList.toggle('is-scrolled', window.scrollY > 8);
            window.addEventListener('scroll', aoRolar, { passive: true });
            aoRolar();
        })();
    </script>
</body></html>
