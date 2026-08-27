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

$primeiroNome = explode(' ', trim($u['nome']))[0];
$final = $u['final_cartao'] ?: '4582';
$avatar = $u['avatar'] ?: '';
$pers = $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc();
$logoApp = $pers['logo_app'] ?? '';
$cartaoAtivado = $pers['cartao_ativado'] ?? '';
$cartaoDesativado = $pers['cartao_desativado'] ?? '';
$cartaoImg = $u['cartao_ativo'] ? $cartaoAtivado : $cartaoDesativado;
$atendimento = $conn->query("SELECT * FROM config_atendimento WHERE id = 1")->fetch_assoc();

$diasRestantes = null;
if ($u['cartao_ativo'] && !empty($u['cartao_validade'])) {
    $diasRestantes = (int)(strtotime($u['cartao_validade']) - strtotime(date('Y-m-d'))) / 86400;
    $diasRestantes = (int)floor($diasRestantes);
}

$banner = $conn->query("SELECT * FROM banners WHERE ativo = 1 ORDER BY id DESC LIMIT 1")->fetch_assoc();

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
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Hanken+Grotesk:wght@600;700&amp;display=swap" rel="stylesheet"/>
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
                        "label-caps": ["Hanken Grotesk"],
                        "headline-sm": ["Manrope"],
                        "body-md": ["Manrope"],
                        "headline-md": ["Manrope"],
                        "display-lg": ["Manrope"],
                        "body-lg": ["Manrope"],
                        "label-bold": ["Hanken Grotesk"]
                    },
                    "fontSize": {
                        "label-caps": ["10px", {"lineHeight": "1", "fontWeight": "600"}],
                        "headline-sm": ["20px", {"lineHeight": "1.4", "fontWeight": "600"}],
                        "body-md": ["14px", {"lineHeight": "1.5", "fontWeight": "400"}],
                        "headline-md": ["24px", {"lineHeight": "1.3", "fontWeight": "700"}],
                        "display-lg": ["32px", {"lineHeight": "1.2", "fontWeight": "800"}],
                        "body-lg": ["16px", {"lineHeight": "1.6", "fontWeight": "400"}],
                        "label-bold": ["12px", {"lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "700"}]
                    }
                },
            },
        }
    </script>
<style>
        body {
            background-color: theme('colors.background');
            -webkit-tap-highlight-color: transparent;
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
<?php if ($avatar): ?>
<img class="w-full h-full object-cover" src="<?php echo htmlspecialchars($avatar); ?>" alt="Foto de perfil"/>
<?php else: ?>
<span class="material-symbols-outlined text-[26px]" style="font-variation-settings:'FILL' 1;">person</span>
<?php endif; ?>
</div>
<div>
<p class="font-headline-sm text-[15px] text-on-surface"><?php echo htmlspecialchars($u['nome']); ?></p>
<p class="font-label-caps text-label-caps text-on-surface-variant">Usuário do Cartão</p>
</div>
</div>
</div>
<nav class="flex-1 px-3 py-4 space-y-1">
<a href="perfil.php" class="flex items-center gap-3 px-4 py-3 rounded-xl font-body-md text-on-surface hover:bg-surface-container-high/60 transition-colors">
<span class="material-symbols-outlined text-primary">account_circle</span>
                        Perfil
                    </a>
<a href="ativar.php" class="flex items-center gap-3 px-4 py-3 rounded-xl font-body-md text-on-surface hover:bg-surface-container-high/60 transition-colors">
<span class="material-symbols-outlined text-secondary">credit_score</span>
                        Ativar Cartão
                    </a>
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
<div class="flex items-center gap-2">
<button class="material-symbols-outlined text-primary hover:bg-surface-container-high/50 transition-colors p-2 rounded-full active:scale-95 duration-200" onclick="abrirMenu()">
            menu
        </button>
<?php if ($logoApp): ?>
<img class="w-[100px] h-auto object-contain" src="<?php echo htmlspecialchars($logoApp); ?>" alt="Logo Economic Card"/>
<?php else: ?>
<div class="w-12 h-12 rounded-lg premium-gradient flex items-center justify-center">
<span class="material-symbols-outlined text-white text-[24px]">credit_card</span>
</div>
<?php endif; ?>
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
<!-- User Greeting -->
<section class="flex items-center gap-3">
<div class="w-12 h-12 rounded-full overflow-hidden shrink-0 border-2 border-secondary-container bg-surface-container-high flex items-center justify-center">
<?php if ($avatar): ?>
<img class="w-full h-full object-cover" src="<?php echo htmlspecialchars($avatar); ?>" alt="Foto de perfil"/>
<?php else: ?>
<span class="material-symbols-outlined text-primary text-[24px]">person</span>
<?php endif; ?>
</div>
<div class="space-y-xs min-w-0">
<h2 class="font-headline-md text-headline-md text-on-surface truncate">Olá, <?php echo htmlspecialchars($primeiroNome); ?></h2>
<p class="font-body-md text-on-surface-variant">Confira o status do seu benefício hoje.</p>
</div>
</section>
<!-- Digital Card (The Centerpiece) -->
<section class="relative group active:scale-[0.98] transition-transform duration-300" onclick="abrirFullscreen()">
<?php if ($cartaoImg): ?>
<div class="relative rounded-xl shadow-[0_8px_30px_rgba(106,37,133,0.15)] overflow-hidden aspect-[1.58/1] cursor-pointer">
<img class="absolute inset-0 w-full h-full object-contain" src="<?php echo htmlspecialchars($cartaoImg); ?>" alt="Cartão Economic Card"/>
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
<a href="perfil.php" class="bg-surface-container-lowest border border-outline-variant/30 rounded-xl p-5 flex flex-col items-center justify-center gap-3 shadow-sm hover:shadow-md transition-shadow active:scale-95 duration-200">
<div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-[28px]">account_circle</span>
</div>
<span class="font-label-bold text-on-surface">PERFIL</span>
</a>
<!-- Ativar Cartão -->
<a href="ativar.php" class="bg-surface-container-lowest border border-outline-variant/30 rounded-xl p-5 flex flex-col items-center justify-center gap-3 shadow-sm hover:shadow-md transition-shadow active:scale-95 duration-200">
<div class="w-12 h-12 rounded-full bg-secondary-container/40 flex items-center justify-center text-secondary">
<span class="material-symbols-outlined text-[28px]">credit_score</span>
</div>
<span class="font-label-bold text-on-surface">ATIVAR CARTÃO</span>
</a>
<!-- Parceiros -->
<a href="parceiros.php" class="bg-surface-container-lowest border border-outline-variant/30 rounded-xl p-5 flex flex-col items-center justify-center gap-3 shadow-sm hover:shadow-md transition-shadow active:scale-95 duration-200">
<div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-[28px]">storefront</span>
</div>
<span class="font-label-bold text-on-surface">PARCEIROS</span>
</a>
<!-- Cartão Físico -->
<a href="cartao_fisico.php" class="bg-surface-container-lowest border border-outline-variant/30 rounded-xl p-5 flex flex-col items-center justify-center gap-3 shadow-sm hover:shadow-md transition-shadow active:scale-95 duration-200 relative overflow-hidden">
<div class="absolute top-2 right-2 px-1.5 py-0.5 bg-secondary-container text-secondary text-[8px] font-bold rounded">OPCIONAL</div>
<div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-[28px]">contactless</span>
</div>
<span class="font-label-bold text-on-surface">CARTÃO FÍSICO</span>
</a>
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
<div class="relative rounded-xl overflow-hidden shadow-lg h-44 group">
<img class="w-full h-full object-cover" src="<?php echo htmlspecialchars($banner['imagem'] ?? ''); ?>" alt="Oferta especial"/>
<div class="absolute inset-0 bg-gradient-to-r from-primary/80 via-primary/40 to-transparent p-6 flex flex-col justify-center">
<h4 class="text-white font-headline-sm max-w-[160px]"><?php echo htmlspecialchars($banner['titulo'] ?? ''); ?></h4>
<p class="text-secondary-container font-label-bold text-[16px] mt-1"><?php echo htmlspecialchars($banner['desconto'] ?? ''); ?></p>
<button class="mt-4 bg-secondary-container text-on-secondary-container px-6 py-2 rounded-full font-label-bold w-fit active:scale-95 duration-200 transition-all hover:brightness-110">
                        <?php echo htmlspecialchars($banner['botao_texto'] ?? 'EU QUERO!'); ?>
                    </button>
</div>
</div>
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
<a class="flex flex-col items-center justify-center text-on-surface-variant px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="ativar.php">
<span class="material-symbols-outlined">credit_card</span>
<span class="font-label-bold mt-1">Cartão</span>
</a>
<a class="flex flex-col items-center justify-center text-on-surface-variant px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="perfil.php">
<span class="material-symbols-outlined">person</span>
<span class="font-label-bold mt-1">Perfil</span>
</a>
</div>
</nav>
<!-- Fullscreen Card Overlay -->
<div class="hidden fixed inset-0 z-[100] bg-black/90 backdrop-blur-md items-center justify-center p-6" id="fullscreenCard">
<button class="absolute top-6 right-6 material-symbols-outlined text-white/80 text-3xl" onclick="fecharFullscreen()">close</button>
<div class="w-full max-w-md premium-gradient rounded-2xl p-8 shadow-2xl relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between">
<?php if ($cartaoImg): ?>
<img class="w-full h-full object-contain rounded-2xl" src="<?php echo htmlspecialchars($cartaoImg); ?>" alt="Cartão Economic Card"/>
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
