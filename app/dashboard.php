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

$banners = $conn->query("SELECT * FROM banners WHERE ativo = 1 ORDER BY id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
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
</head>
<body class="font-body-md text-on-surface antialiased">
<!-- Side Drawer Menu -->
<div class="hidden fixed inset-0 z-[90]" id="drawerMenu">
<div class="absolute inset-0 bg-black/40" onclick="fecharMenu()"></div>
<div class="absolute inset-y-0 left-0 w-72 bg-surface-container-lowest shadow-2xl flex flex-col">
<div class="px-5 py-6 flex items-center justify-between border-b border-outline-variant/30">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-xl premium-gradient flex items-center justify-center">
<span class="material-symbols-outlined text-white text-[22px]">credit_card</span>
</div>
<div>
<h2 class="font-headline-sm text-headline-sm text-primary leading-tight">Economic Card</h2>
<p class="font-label-caps text-label-caps text-on-surface-variant">Menu principal</p>
</div>
</div>
<button class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container-high/50 p-2 rounded-full" onclick="fecharMenu()">close</button>
</div>
<div class="px-3 py-5 border-b border-outline-variant/30">
<div class="flex items-center gap-3 px-3">
<div class="w-12 h-12 rounded-full bg-surface-container-high overflow-hidden flex items-center justify-center text-primary border-2 border-secondary-container">
<?php if ($avatarExib): ?>
<img class="w-full h-full object-cover av-fallback-img" data-fallback="sidebar" src="<?php echo htmlspecialchars($avatarExib); ?>" alt="Foto de perfil" onerror="this.style.display='none';document.getElementById('av-fallback-sidebar').style.display='flex';"/>
<span id="av-fallback-sidebar" class="material-symbols-outlined text-[26px] hidden" style="font-variation-settings:'FILL' 1;">person</span>
<?php else: ?>
<span class="material-symbols-outlined text-[26px]" style="font-variation-settings:'FILL' 1;">person</span>
<?php endif; ?>
</div>
<div>
<p class="font-headline-sm text-[15px] text-on-surface"><?php echo htmlspecialchars($nomeExib); ?><?php if ($ehDep): ?> <span class="inline-flex items-center align-middle bg-secondary-container/40 text-secondary text-[10px] font-bold px-2 py-0.5 rounded-full">Dependente</span><?php endif; ?></p>
<p class="font-label-caps text-label-caps text-on-surface-variant"><?php echo $ehDep ? 'Dependente' : 'Usuário do Cartão'; ?></p>
</div>
</div>
</div>
<nav class="flex-1 px-3 py-4 space-y-1">
<?php if (!$ehDep): ?>
<a href="perfil.php" class="flex items-center gap-3 px-4 py-3 rounded-xl font-body-md text-on-surface hover:bg-surface-container-high/60 transition-colors">
<span class="material-symbols-outlined text-primary">account_circle</span>
                        Perfil
                    </a>
<a href="ativar.php" class="flex items-center gap-3 px-4 py-3 rounded-xl font-body-md text-on-surface hover:bg-surface-container-high/60 transition-colors">
<span class="material-symbols-outlined text-secondary">credit_score</span>
                        Ativar Cartão
                    </a>
<a href="dependentes.php" class="flex items-center gap-3 px-4 py-3 rounded-xl font-body-md text-on-surface hover:bg-surface-container-high/60 transition-colors">
<span class="material-symbols-outlined text-primary">groups</span>
                        Dependentes
                    </a>
<?php endif; ?>
<?php if (!empty($atendimento['whatsapp']) || !empty($atendimento['email'])): ?>
<button type="button" onclick="fecharMenu(); abrirAtendimento();" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl font-body-md text-on-surface hover:bg-surface-container-high/60 transition-colors">
<span class="material-symbols-outlined text-primary">support_agent</span>
                        Central de atendimento
                    </button>
<?php endif; ?>
</nav>
<div class="p-3 border-t border-outline-variant/30">
<a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl font-body-md text-error hover:bg-error-container/30 transition-colors">
<span class="material-symbols-outlined">logout</span>
                        Sair
                    </a>
</div>
</div>
</div>
<!-- TopAppBar -->
<header class="fixed top-0 w-full z-50 bg-surface/80 backdrop-blur-xl shadow-sm flex items-center justify-between px-container-padding h-20">
<div class="flex items-center gap-4">
<button class="material-symbols-outlined text-primary hover:bg-surface-container-high/50 transition-colors p-2 rounded-full active:scale-95 duration-200" onclick="abrirMenu()">
            menu
        </button>
<?php if ($logoApp): ?>
<img class="w-[100px] h-auto object-contain" src="<?php echo htmlspecialchars(asset_url($logoApp)); ?>" alt="Logo Economic Card"/>
<?php else: ?>
<div class="w-12 h-12 rounded-lg premium-gradient flex items-center justify-center">
<span class="material-symbols-outlined text-white text-[24px]">credit_card</span>
</div>
<?php endif; ?>
<p class="text-[11px] text-on-surface-variant text-left leading-tight"><span class="block whitespace-nowrap">Não precisa estar doente para</span><span class="block whitespace-nowrap">usar o seu Economic card.</span></p>
</div>
<div class="flex items-center gap-2">
<div class="relative" id="notifWrap">
<button class="material-symbols-outlined text-primary hover:bg-surface-container-high/50 transition-colors p-2 rounded-full active:scale-95 duration-200" id="notifBtn">
            notifications
        </button>
<?php if ($naoLidas > 0): ?>
<span class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-error text-on-error text-[9px] font-bold rounded-full flex items-center justify-center" id="notifBadge"><?php echo $naoLidas; ?></span>
<?php endif; ?>
<div class="hidden absolute right-0 top-12 w-80 bg-surface-container-lowest rounded-2xl shadow-xl border border-outline-variant/30 overflow-hidden z-50" id="notifPanel">
<div class="px-4 py-3 border-b border-outline-variant/30">
<span class="font-label-bold text-label-bold text-on-surface uppercase">MENSAGENS</span>
</div>
<div class="max-h-80 overflow-y-auto">
<?php if ($mensagens->num_rows === 0): ?>
<div class="px-4 py-8 text-center">
<p class="font-body-md text-on-surface-variant">Nenhuma mensagem no momento.</p>
</div>
<?php endif; ?>
<?php while ($m = $mensagens->fetch_assoc()): ?>
<button type="button" class="w-full text-left px-4 py-3 border-b border-outline-variant/20 hover:bg-surface-container-high/40 transition-colors" onclick="abrirMensagem('<?php echo addslashes(htmlspecialchars($m['titulo'])); ?>', '<?php echo addslashes(htmlspecialchars($m['mensagem'])); ?>', '<?php echo date('d/m/Y H:i', strtotime($m['criado_em'])); ?>')">
<p class="font-headline-sm text-[14px] text-on-surface"><?php echo htmlspecialchars($m['titulo']); ?></p>
<p class="font-body-md text-[13px] text-on-surface-variant truncate"><?php echo htmlspecialchars($m['mensagem']); ?></p>
<p class="font-label-caps text-[10px] text-outline mt-1"><?php echo date('d/m/Y H:i', strtotime($m['criado_em'])); ?></p>
</button>
<?php endwhile; ?>
</div>
</div>
</div>
</div>
</header>
<main class="pt-24 pb-28 px-container-padding space-y-md">
<!-- Slide Topo Banners -->
<?php if (!empty($bannersTopo)): ?>
<div class="rounded-xl overflow-hidden" style="max-width:400px; margin:0 auto;">
<div id="topoTrackApp" style="display:flex; flex-wrap:nowrap; will-change:transform;">
<?php foreach ($bannersTopo as $bt): ?>
<div style="flex:0 0 100%; min-width:100%;">
<?php if (!empty($bt['link_externo'])): ?>
<a href="<?php echo htmlspecialchars($bt['link_externo']); ?>" target="_blank" rel="noopener">
<img src="<?php echo htmlspecialchars(asset_url($bt['imagem'])); ?>" alt="" class="w-full h-[100px] object-cover rounded-xl"/>
</a>
<?php else: ?>
<img src="<?php echo htmlspecialchars(asset_url($bt['imagem'])); ?>" alt="" class="w-full h-[100px] object-cover rounded-xl"/>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php if (count($bannersTopo) > 1): ?>
<div class="flex justify-center gap-1 mt-2" id="topoDotsApp" style="pointer-events:auto;">
<?php foreach ($bannersTopo as $i => $bt): ?><span class="w-1.5 h-1.5 rounded-full bg-gray-300/70 transition-all duration-300 cursor-pointer <?php echo $i === 0 ? '!w-4 !bg-primary' : ''; ?>" data-index="<?php echo $i; ?>" onclick="topoGoTo(<?php echo $i; ?>)"></span><?php endforeach; ?>
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
function goTo(i){idx=i;track.style.transition='transform .4s ease';track.style.transform='translateX('+(idx*-100)+'%)';updDots();}
function next(){goTo((idx+1)%total);}
function updDots(){var dots=document.querySelectorAll('#topoDotsApp span');dots.forEach(function(d,i){d.classList.toggle('!w-4',i===idx);d.classList.toggle('!bg-primary',i===idx);d.classList.toggle('!bg-gray-300',i!==idx);});}
function startAuto(){auto=setInterval(next,4000);}
startAuto();
var x0=null;
track.addEventListener('touchstart',function(e){x0=e.touches[0].clientX;clearInterval(auto);},{passive:true});
track.addEventListener('touchend',function(e){if(x0===null)return;var dx=e.changedTouches[0].clientX-x0;if(dx<-40)goTo((idx+1)%total);else if(dx>40)goTo((idx-1+total)%total);startAuto();x0=null;});
})();
</script>
<?php endif; ?>
<!-- Quick Icons Row (3D IconScout) -->
<div class="flex justify-center gap-4 py-2" style="max-width:400px; margin:0 auto;">
<a href="parceiros.php" class="flex flex-col items-center gap-1 group active:scale-95 transition-transform">
<div class="w-[48px] h-[48px] flex items-center justify-center">
<img src="<?php echo htmlspecialchars(asset_url('../img/icons/parceiros.png')); ?>" alt="Parceiros" class="w-full h-full object-contain drop-shadow-lg group-hover:scale-110 transition-transform" loading="lazy"/>
</div>
<span class="text-[11px] font-semibold text-on-surface-variant">Parceiros</span>
</a>
<?php if (!$ehDep): ?>
<a href="ativar.php" class="flex flex-col items-center gap-1 group active:scale-95 transition-transform">
<div class="w-[48px] h-[48px] flex items-center justify-center">
<img src="<?php echo htmlspecialchars(asset_url('../img/icons/cartao.png')); ?>" alt="Cartão" class="w-full h-full object-contain drop-shadow-lg group-hover:scale-110 transition-transform" loading="lazy"/>
</div>
<span class="text-[11px] font-semibold text-on-surface-variant">Cartão</span>
</a>
<a href="perfil.php" class="flex flex-col items-center gap-1 group active:scale-95 transition-transform">
<div class="w-[48px] h-[48px] flex items-center justify-center">
<img src="<?php echo htmlspecialchars(asset_url('../img/icons/perfil.png')); ?>" alt="Perfil" class="w-full h-full object-contain drop-shadow-lg group-hover:scale-110 transition-transform" loading="lazy"/>
</div>
<span class="text-[11px] font-semibold text-on-surface-variant">Perfil</span>
</a>
<?php endif; ?>
<div class="flex flex-col items-center gap-1 relative">
<div class="w-[48px] h-[48px] flex items-center justify-center">
<img src="<?php echo htmlspecialchars(asset_url('../img/icons/cupon.png')); ?>" alt="Cupons" class="w-full h-full object-contain drop-shadow-lg"/>
</div>
<span class="text-[11px] font-semibold text-on-surface-variant">Cupons</span>
<span class="absolute -top-1 -right-1 bg-primary text-white text-[7px] font-bold px-1.5 py-0.5 rounded-full shadow">Em Breve</span>
</div>
<div class="flex flex-col items-center gap-1 relative">
<div class="w-[48px] h-[48px] flex items-center justify-center">
<img src="<?php echo htmlspecialchars(asset_url('../img/icons/cursos.png')); ?>" alt="Cursos" class="w-full h-full object-contain drop-shadow-lg"/>
</div>
<span class="text-[11px] font-semibold text-on-surface-variant">Cursos</span>
<span class="absolute -top-1 -right-1 bg-primary text-white text-[7px] font-bold px-1.5 py-0.5 rounded-full shadow">Em Breve</span>
</div>
</div>
<!-- User Greeting -->
<section class="flex items-center gap-3">
<div class="w-12 h-12 rounded-full overflow-hidden shrink-0 border-2 border-secondary-container bg-surface-container-high flex items-center justify-center">
<?php if ($avatarExib): ?>
<img class="w-full h-full object-cover av-fallback-img" data-fallback="greeting" src="<?php echo htmlspecialchars($avatarExib); ?>" alt="Foto de perfil" onerror="this.style.display='none';document.getElementById('av-fallback-greeting').style.display='flex';"/>
<span id="av-fallback-greeting" class="material-symbols-outlined text-primary text-[24px] hidden">person</span>
<?php else: ?>
<span class="material-symbols-outlined text-primary text-[24px]">person</span>
<?php endif; ?>
</div>
<div class="space-y-xs min-w-0">
<h2 class="font-headline-md text-headline-md text-on-surface truncate">Olá, <?php echo htmlspecialchars($primeiroNome); ?><?php if ($ehDep): ?> <span class="inline-flex items-center align-middle bg-secondary-container/40 text-secondary text-[11px] font-bold px-2 py-0.5 rounded-full">Dependente</span><?php endif; ?></h2>
<p class="font-body-md text-on-surface-variant">Confira o status do seu benefício hoje.</p>
</div>
</section>
<!-- Digital Card (The Centerpiece) -->
<section class="relative group active:scale-[0.98] transition-transform duration-300" onclick="abrirFullscreen()">
<?php if ($cartaoImg): ?>
<div class="relative rounded-xl shadow-[0_8px_30px_rgba(106,37,133,0.15)] overflow-hidden aspect-[1.58/1] cursor-pointer">
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
<div class="premium-gradient rounded-xl p-6 shadow-[0_8px_30px_rgba(106,37,133,0.15)] relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between cursor-pointer">
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
<p class="font-label-caps text-white/50">VALIDADE 08/25</p>
</div>
<div class="flex flex-col items-end">
<span class="font-display-lg text-white/20 text-[28px] font-extrabold italic">Economic</span>
</div>
</div>
</div>
</div>
<?php endif; ?>
</section>
<!-- Interactive Hint -->
<div class="mt-4 bg-secondary-container/20 py-3 rounded-lg flex items-center justify-center gap-2 border border-secondary-container/30">
<span class="material-symbols-outlined text-secondary text-[18px]">touch_app</span>
<span class="font-label-bold text-secondary">Clique no seu cartão para ampliar</span>
</div>
<?php if ($u['cartao_ativo'] && $diasRestantes !== null): ?>
<!-- Dias restantes -->
<div class="mt-4 bg-surface-container-lowest border border-outline-variant/30 rounded-xl p-4 flex items-center justify-between shadow-sm">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings:'FILL' 1;">schedule</span>
</div>
<div>
<p class="font-label-bold text-on-surface"><?php echo $diasRestantes > 0 ? ($diasRestantes === 1 ? '1 dia restante' : $diasRestantes . ' dias restantes') : 'Cartão expirado'; ?></p>
<p class="font-label-caps text-on-surface-variant">Validade: <?php echo date('d/m/Y', strtotime($u['cartao_validade'])); ?></p>
</div>
</div>
</div>
<?php endif; ?>
<!-- Quick Access Bento Grid -->
<section class="space-y-sm">
<h3 class="font-label-bold text-on-surface-variant uppercase tracking-wider">ACESSO RÁPIDO</h3>
<div class="grid grid-cols-2 gap-card-gutter">
<!-- Perfil -->
<?php if (!$ehDep): ?>
<a href="perfil.php" class="bg-surface-container-lowest border border-outline-variant/30 rounded-xl p-5 flex flex-col items-center justify-center gap-3 shadow-sm hover:shadow-md transition-shadow active:scale-95 duration-200">
<div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-[28px]">account_circle</span>
</div>
<span class="font-label-bold text-on-surface">PERFIL</span>
</a>
<?php endif; ?>
<!-- Ativar Cartão -->
<?php if (!$ehDep): ?>
<a href="ativar.php" class="bg-surface-container-lowest border border-outline-variant/30 rounded-xl p-5 flex flex-col items-center justify-center gap-3 shadow-sm hover:shadow-md transition-shadow active:scale-95 duration-200">
<div class="w-12 h-12 rounded-full bg-secondary-container/40 flex items-center justify-center text-secondary">
<span class="material-symbols-outlined text-[28px]">credit_score</span>
</div>
<span class="font-label-bold text-on-surface">ATIVAR CARTÃO</span>
</a>
<?php endif; ?>
<!-- Parceiros -->
<a href="parceiros.php" class="bg-surface-container-lowest border border-outline-variant/30 rounded-xl p-5 flex flex-col items-center justify-center gap-3 shadow-sm hover:shadow-md transition-shadow active:scale-95 duration-200">
<div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-[28px]">storefront</span>
</div>
<span class="font-label-bold text-on-surface">PARCEIROS</span>
</a>
<!-- Cartão Físico -->
<?php if (!$ehDep): ?>
<a href="cartao_fisico.php" class="bg-surface-container-lowest border border-outline-variant/30 rounded-xl p-5 flex flex-col items-center justify-center gap-3 shadow-sm hover:shadow-md transition-shadow active:scale-95 duration-200 relative overflow-hidden">
<div class="absolute top-2 right-2 px-1.5 py-0.5 bg-secondary-container text-secondary text-[8px] font-bold rounded">OPCIONAL</div>
<div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-[28px]">contactless</span>
</div>
<span class="font-label-bold text-on-surface">CARTÃO FÍSICO</span>
</a>
<?php endif; ?>
<!-- Telemedicina -->
<div class="bg-surface-container-lowest border border-outline-variant/30 rounded-xl p-5 flex flex-col items-center justify-center gap-3 relative overflow-hidden opacity-70 cursor-not-allowed">
<div class="absolute top-2 right-2 px-1.5 py-0.5 bg-secondary-container text-secondary text-[8px] font-bold rounded">EM BREVE</div>
<div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-[28px]">stethoscope</span>
</div>
<span class="font-label-bold text-on-surface">TELEMEDICINA</span>
</div>
</div>
</section>
<!-- Promotional Banner -->
<section class="space-y-sm">
<h3 class="font-label-bold text-on-surface-variant uppercase tracking-wider">OFERTA ESPECIAL</h3>
<?php if (!empty($banners)): ?>
<div class="relative overflow-hidden rounded-xl shadow-lg">
<div style="position:absolute; top:0; left:0; right:0; z-index:10; height:3px; background:rgba(0,0,0,.15); pointer-events:none;"><div id="bannerProgressApp" style="height:100%; width:0%;"></div></div>
<div id="bannerTrackApp" style="display:flex; flex-wrap:nowrap; will-change:transform; gap:16px;">
<?php foreach ($banners as $b): ?>
<div class="relative group rounded-xl overflow-hidden shadow-sm" style="flex:0 0 85%; min-width:85%; position:relative;">
<?php if (!empty($b['imagem'])): ?>
<img class="w-full h-auto block object-contain" src="<?php echo htmlspecialchars(asset_url($b['imagem'] ?? '')); ?>" alt="Oferta especial"/>
<?php endif; ?>
<?php if (!empty($b['titulo']) || !empty($b['desconto']) || !empty($b['botao_texto'])): ?>
<div class="absolute inset-0 bg-gradient-to-r from-primary/80 via-primary/40 to-transparent p-6 flex flex-col justify-center">
<h4 class="text-white font-headline-sm max-w-[160px]"><?php echo htmlspecialchars($b['titulo'] ?? ''); ?></h4>
<p class="text-secondary-container font-label-bold text-[16px] mt-1"><?php echo htmlspecialchars($b['desconto'] ?? ''); ?></p>
<a class="mt-4 bg-secondary-container text-on-secondary-container px-6 py-2 rounded-full font-label-bold w-fit active:scale-95 duration-200 transition-all hover:brightness-110" href="<?php echo htmlspecialchars(!empty($b['link_externo']) ? $b['link_externo'] : asset_url($b['imagem'] ?? '')); ?>" target="_blank" rel="noopener">
<?php echo htmlspecialchars($b['botao_texto'] ?? 'EU QUERO!'); ?>
</a>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php if (count($banners) > 1): ?>
<div class="absolute bottom-3 left-0 right-0 z-10 flex items-center justify-center gap-1.5" style="pointer-events:none;">
<?php foreach ($banners as $i => $b): ?><button type="button" class="ec-dot<?php echo $i === 0 ? ' ec-dot-ativo' : ''; ?>" data-dot="<?php echo $i; ?>" style="pointer-events:auto;" aria-label="Slide <?php echo $i + 1; ?>"></button><?php endforeach; ?>
</div>
<style>
    .ec-dot { width:6px; height:6px; border-radius:9999px; background:rgba(255,255,255,.45); transition:all .35s cubic-bezier(.22,1,.36,1); cursor:pointer; padding:0; border:none; box-shadow:0 1px 4px rgba(0,0,0,.3); }
    .ec-dot-ativo { width:22px; background:#b6f570; }
    #bannerProgressApp { background:#b6f570; }
    @keyframes ec-progress { from { width:0%; } to { width:100%; } }
</style>
<script>
(function(){
    var track = document.getElementById('bannerTrackApp');
    if (!track) return;
    var feeds = Array.prototype.slice.call(track.children);
    if (feeds.length < 2) return;
    var realCount = feeds.length;
    track.appendChild(feeds[0].cloneNode(true));
    track.insertBefore(feeds[realCount - 1].cloneNode(true), track.firstChild);
    var idx = 1;
    var gap = 0;
    var bar = document.getElementById('bannerProgressApp');
    var dots = document.querySelectorAll('.ec-dot');
    var container = track.parentElement;
    var INTERVALO = 5000;
    var hover = false;
    function passo() { return track.children[1].offsetWidth + gap; }
    function moverComTransicao() {
        track.style.transition = 'transform 650ms cubic-bezier(0.22,1,0.36,1)';
        track.style.transform = 'translateX(' + (-(idx * passo())) + 'px)';
    }
    function moverImediato() {
        track.style.transition = 'none';
        track.style.transform = 'translateX(' + (-(idx * passo())) + 'px)';
        void track.offsetWidth;
    }
    function realAtual() { return (idx - 1 + realCount) % realCount; }
    function atualizarDots() {
        var r = realAtual();
        dots.forEach(function(d, i) { d.classList.toggle('ec-dot-ativo', i === r); });
    }
    function reiniciarBarra() {
        if (!bar) return;
        bar.style.animation = 'none';
        void bar.offsetWidth;
        bar.style.animation = 'ec-progress ' + (INTERVALO / 1000) + 's linear forwards';
        if (hover) bar.style.animationPlayState = 'paused';
    }
    function estabilizar() {
        var mudou = false;
        if (idx <= 0) { idx = realCount; mudou = true; }
        else if (idx >= realCount + 1) { idx = 1; mudou = true; }
        if (mudou) moverImediato();
        atualizarDots();
        reiniciarBarra();
    }
    function proximo() { idx++; moverComTransicao(); }
    function anterior() { idx--; moverComTransicao(); }
    function goPara(n) { idx = n + 1; moverComTransicao(); }
    track.addEventListener('transitionend', estabilizar);
    if (bar) bar.addEventListener('animationend', proximo);
    container.addEventListener('mouseenter', function(){ hover = true; if (bar) bar.style.animationPlayState = 'paused'; });
    container.addEventListener('mouseleave', function(){ hover = false; if (bar) bar.style.animationPlayState = 'running'; });
    var x0 = null;
    container.addEventListener('touchstart', function(e){ x0 = e.touches[0].clientX; if (bar) bar.style.animationPlayState = 'paused'; }, {passive:true});
    container.addEventListener('touchend', function(e){
        if (x0 === null) { if (bar) bar.style.animationPlayState = 'running'; return; }
        var dx = e.changedTouches[0].clientX - x0;
        if (dx <= -40) proximo();
        else if (dx >= 40) anterior();
        else if (bar && !hover) bar.style.animationPlayState = 'running';
        x0 = null;
    });
    dots.forEach(function(d, i) { d.addEventListener('click', function(){ goPara(i); }); });
    function medir() {
        gap = parseFloat(getComputedStyle(track).columnGap) || 0;
        moverImediato();
    }
    window.addEventListener('resize', medir);
    medir();
    atualizarDots();
    reiniciarBarra();
})();
</script>
<?php endif; ?>
<?php else: ?>
<p class="text-sm text-on-surface-variant">Nenhuma oferta no momento.</p>
<?php endif; ?>
</section>
</main>
<!-- BottomNavBar -->
<nav class="fixed bottom-0 w-full z-50 bg-surface/80 backdrop-blur-xl border-t border-outline-variant/30 shadow-[0_-4px_20px_rgba(0,0,0,0.05)] pb-safe">
<div class="flex justify-around items-center w-full py-2 px-2">
<a class="flex flex-col items-center justify-center bg-primary-container text-on-primary-container rounded-full px-4 py-1.5 active:scale-90 duration-200" href="dashboard.php">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">home</span>
<span class="font-label-bold mt-1">Home</span>
</a>
<a class="flex flex-col items-center justify-center text-on-surface-variant px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="parceiros.php">
<span class="material-symbols-outlined">storefront</span>
<span class="font-label-bold mt-1">Parceiros</span>
</a>
<?php if ($ehDep): ?>
<button type="button" onclick="abrirAtendimento()" class="flex flex-col items-center justify-center text-on-surface-variant px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200">
<span class="material-symbols-outlined">support_agent</span>
<span class="font-label-bold mt-1">Atendimento</span>
</button>
<?php else: ?>
<a class="flex flex-col items-center justify-center text-on-surface-variant px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="ativar.php">
<span class="material-symbols-outlined">credit_card</span>
<span class="font-label-bold mt-1">Cartão</span>
</a>
<a class="flex flex-col items-center justify-center text-on-surface-variant px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="perfil.php">
<span class="material-symbols-outlined">person</span>
<span class="font-label-bold mt-1">Perfil</span>
</a>
<?php endif; ?>
</div>
</nav>
<!-- Fullscreen Card Overlay -->
<div class="hidden fixed inset-0 z-[100] bg-black/90 backdrop-blur-md items-center justify-center p-6" id="fullscreenCard">
<button class="absolute top-6 right-6 material-symbols-outlined text-white/80 text-3xl" onclick="fecharFullscreen()">close</button>
<div class="w-full max-w-md premium-gradient rounded-2xl p-8 shadow-2xl relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between">
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
<p class="font-label-caps text-white/50">VALIDADE 08/25</p>
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
<div class="hidden fixed inset-0 z-[120] bg-black/60 backdrop-blur-sm items-center justify-center p-6" id="mensagemModal">
<div class="w-full max-w-md bg-surface-container-lowest rounded-2xl shadow-2xl border border-outline-variant/30 overflow-hidden">
<div class="px-5 py-4 border-b border-outline-variant/30 flex items-center justify-between gap-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-primary text-[20px] shrink-0" style="font-variation-settings: 'FILL' 1;">notifications</span>
<span class="font-label-bold text-label-bold text-on-surface uppercase truncate" id="msgModalTitulo">MENSAGEM</span>
</div>
<button class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container-high/50 transition-colors p-1.5 rounded-full shrink-0" onclick="fecharMensagem()">close</button>
</div>
<div class="px-5 py-5 max-h-[60vh] overflow-y-auto">
<p class="font-body-md text-on-surface-variant whitespace-pre-line" id="msgModalTexto"></p>
</div>
<div class="px-5 py-4 border-t border-outline-variant/30 flex items-center justify-between">
<span class="font-label-caps text-[11px] text-outline" id="msgModalData"></span>
<button class="font-label-bold text-label-bold text-primary hover:opacity-80 transition-opacity active:scale-95" onclick="fecharMensagem()">Fechar</button>
</div>
</div>
</div>
<!-- Atendimento Modal -->
<div class="hidden fixed inset-0 z-[130] bg-black/60 backdrop-blur-sm items-center justify-center p-6" id="atendimentoModal">
<div class="w-full max-w-md bg-surface-container-lowest rounded-2xl shadow-2xl border border-outline-variant/30 overflow-hidden">
<div class="px-5 py-4 border-b border-outline-variant/30 flex items-center justify-between gap-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-primary text-[20px] shrink-0" style="font-variation-settings: 'FILL' 1;">support_agent</span>
<span class="font-label-bold text-label-bold text-on-surface uppercase truncate">Central de atendimento</span>
</div>
<button class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container-high/50 transition-colors p-1.5 rounded-full shrink-0" onclick="fecharAtendimento()">close</button>
</div>
<div class="px-5 py-6 flex flex-col gap-3">
<?php if (!empty($atendimento['whatsapp'])): ?>
<a href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/\D/', '', $atendimento['whatsapp'])); ?>" target="_blank" class="flex items-center justify-center gap-3 px-4 py-4 rounded-xl bg-[#25D366]/10 border border-[#25D366]/30 text-[#128C7E] font-label-bold text-label-bold transition-colors active:scale-95 duration-200">
<span class="material-symbols-outlined text-[22px]">chat</span> Falar pelo WhatsApp
</a>
<?php endif; ?>
<?php if (!empty($atendimento['email'])): ?>
<a href="mailto:<?php echo htmlspecialchars($atendimento['email']); ?>" class="flex items-center justify-center gap-3 px-4 py-4 rounded-xl bg-primary/10 border border-primary/30 text-primary font-label-bold text-label-bold transition-colors active:scale-95 duration-200">
<span class="material-symbols-outlined text-[22px]">mail</span> Enviar e-mail
</a>
<?php endif; ?>
</div>
<div class="px-5 py-4 border-t border-outline-variant/30 flex items-center justify-end">
<button class="font-label-bold text-label-bold text-primary hover:opacity-80 transition-opacity active:scale-95" onclick="fecharAtendimento()">Fechar</button>
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
    </script>
</body></html>
