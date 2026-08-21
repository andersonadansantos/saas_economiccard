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

$pedido = false;
$pers = $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc();
$cartaoImg = $pers['cartao_fisico'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'pedir') {
    $check = $conn->prepare("SELECT id FROM pedidos_cartao WHERE usuario_id = ?");
    $check->bind_param('i', $uid);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO pedidos_cartao (usuario_id) VALUES (?)");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
    }
    $pedido = true;
}
?>
<!DOCTYPE html><html class="light" lang="pt-BR"><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Cartão Físico - Economic Card</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@600;700&amp;family=Manrope:wght@400;600;700;800&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "secondary-container": "#b6f570", "surface": "#f8f9fa", "error-container": "#ffdad6", "secondary-fixed": "#b6f570", "on-tertiary-fixed": "#2e1600", "on-secondary-fixed-variant": "#2e5000", "inverse-on-surface": "#f0f1f2", "background": "#f8f9fa", "surface-bright": "#f8f9fa", "on-tertiary-fixed-variant": "#6c3b00", "primary-fixed-dim": "#edb1ff", "on-primary-container": "#e397fd", "error": "#ba1a1a", "tertiary-fixed": "#ffdcc0", "on-secondary-fixed": "#0f2000", "surface-container-high": "#e7e8e9", "surface-container-lowest": "#ffffff", "outline-variant": "#d1c2d1", "tertiary-fixed-dim": "#ffb877", "inverse-surface": "#2e3132", "on-surface-variant": "#4e434f", "tertiary-container": "#6a3a00", "primary-fixed": "#f9d8ff", "tertiary": "#4a2700", "surface-container": "#edeeef", "on-primary-fixed": "#320046", "inverse-primary": "#edb1ff", "on-error": "#ffffff", "on-tertiary": "#ffffff", "on-secondary-container": "#427000", "primary-container": "#6a2585", "primary": "#51036d", "on-background": "#191c1d", "on-secondary": "#ffffff", "surface-tint": "#8540a0", "on-surface": "#191c1d", "secondary-fixed-dim": "#9cd857", "on-primary-fixed-variant": "#6b2686", "on-error-container": "#93000a", "secondary": "#3e6a00", "surface-container-low": "#f3f4f5", "on-primary": "#ffffff", "on-tertiary-container": "#ff9d35", "surface-variant": "#e1e3e4", "outline": "#7f7381", "surface-dim": "#d9dadb", "surface-container-highest": "#e1e3e4"
                    },
                    "borderRadius": { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
                    "spacing": { "xs": "4px", "lg": "32px", "base": "8px", "sm": "12px", "container-padding": "20px", "xl": "48px", "card-gutter": "16px", "md": "24px" },
                    "fontFamily": { "headline-sm": ["Manrope"], "body-lg": ["Manrope"], "display-lg": ["Manrope"], "body-md": ["Manrope"], "headline-md": ["Manrope"], "label-caps": ["Hanken Grotesk"], "label-bold": ["Hanken Grotesk"] },
                    "fontSize": {
                        "headline-sm": ["20px", { "lineHeight": "1.4", "fontWeight": "600" }], "headline-sm-mobile": ["18px", { "lineHeight": "1.4", "fontWeight": "600" }], "body-lg": ["16px", { "lineHeight": "1.6", "fontWeight": "400" }], "display-lg": ["32px", { "lineHeight": "1.2", "fontWeight": "800" }], "display-lg-mobile": ["28px", { "lineHeight": "1.2", "fontWeight": "800" }], "body-md": ["14px", { "lineHeight": "1.5", "fontWeight": "400" }], "headline-md": ["24px", { "lineHeight": "1.3", "fontWeight": "700" }], "label-caps": ["10px", { "lineHeight": "1", "fontWeight": "600" }], "label-bold": ["12px", { "lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "700" }]
                    }
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
        .glass-header { background: rgba(248, 249, 250, 0.8); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
        .premium-gradient { background: linear-gradient(135deg, #51036d 0%, #6a2585 100%); }
    </style>
</head>
<body class="bg-surface text-on-surface min-h-screen flex flex-col font-body-md">
<!-- TopAppBar -->
<header class="fixed top-0 left-0 w-full z-50 glass-header flex items-center px-container-padding h-16">
<a class="text-primary hover:opacity-80 transition-opacity active:scale-95 transition-transform mr-4" href="dashboard.php">
<span class="material-symbols-outlined">arrow_back</span>
</a>
<h1 class="font-headline-sm text-headline-sm-mobile md:text-headline-sm text-primary">Cartão Físico</h1>
</header>
<main class="flex-grow pt-24 pb-32 px-container-padding max-w-md mx-auto w-full">
<!-- Card Preview -->
<div class="premium-gradient rounded-xl p-6 shadow-[0_8px_30px_rgba(106,37,133,0.15)] relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between mb-lg">
<div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
<?php if ($cartaoImg): ?>
<img class="absolute inset-0 w-full h-full object-contain" src="<?php echo htmlspecialchars($cartaoImg); ?>" alt="Cartão Físico Economic Card"/>
<?php endif; ?>
<div class="flex justify-between items-start z-10">
<div class="space-y-1"></div>
<div class="active-pill px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
                        <?php echo $u['cartao_ativo'] ? 'ATIVO' : 'INATIVO'; ?>
                    </div>
</div>
<div class="z-10 mt-auto">
<p class="font-display-lg text-white text-[16px]"><?php echo htmlspecialchars(strtoupper($u['nome'])); ?></p>
</div>
</div>
<?php if ($pedido): ?>
<div class="mb-md p-md rounded-xl bg-secondary-container/30 border border-secondary/30 text-center">
<p class="font-headline-sm text-on-secondary-container">Pedido do cartão físico realizado com sucesso!</p>
<p class="font-body-md text-on-secondary-container mt-1">Você receberá seu cartão em breve.</p>
</div>
<?php endif; ?>
<form method="POST" action="cartao_fisico.php" class="space-y-card-gutter">
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">Nome Completo</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">person</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface" value="<?php echo htmlspecialchars($u['nome']); ?>" readonly/>
</div>
</div>
<div class="grid grid-cols-2 gap-card-gutter">
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">CPF</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">badge</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface" value="<?php echo htmlspecialchars($u['cpf']); ?>" readonly/>
</div>
</div>
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">WhatsApp</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">chat</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface" value="<?php echo htmlspecialchars($u['whatsapp'] ?: ''); ?>" readonly/>
</div>
</div>
</div>
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">Endereço Completo</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">home</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface" value="<?php echo htmlspecialchars($u['endereco'] ?: ''); ?>" readonly/>
</div>
</div>
<div class="grid grid-cols-2 gap-card-gutter">
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">Cidade</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">apartment</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface" value="<?php echo htmlspecialchars($u['cidade'] ?: ''); ?>" readonly/>
</div>
</div>
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">CEP</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">location_on</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface" value="<?php echo htmlspecialchars($u['cep'] ?: ''); ?>" readonly/>
</div>
</div>
</div>
<div class="pt-base">
<input type="hidden" name="acao" value="pedir"/>
<button class="w-full bg-secondary text-white font-headline-sm py-4 rounded-full shadow-[0_8px_30px_rgba(62,106,0,0.15)] active:scale-95 transition-all duration-200 uppercase flex items-center justify-center gap-2" type="submit">
                    Pedir Cartão Físico
                    <span class="material-symbols-outlined">contactless</span>
</button>
</div>
</form>
<div class="text-center mt-md">
<p class="font-body-md text-on-surface-variant">O Cartão físico é opcional.</p>
</div>
</main>
<footer class="w-full px-container-padding py-8 flex flex-col items-center gap-4 bg-surface-container-low mt-auto">
<p class="text-outline text-[10px] font-label-caps opacity-60">
            © 2024 ECONOMIC CARD. TODOS OS DIREITOS RESERVADOS.
        </p>
</footer>
</body></html>