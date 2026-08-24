<?php
require_once 'config.php';
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
exigirCartaoAtivo();
$busca = trim($_GET['busca'] ?? '');
$cat   = trim($_GET['cat'] ?? '');

$where = "WHERE ativo = 1";
$params = [];
$types = '';
if ($busca !== '') {
    $where .= " AND (nome LIKE ? OR endereco LIKE ? OR categoria LIKE ?)";
    $like = '%' . $busca . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
if ($cat !== '' && $cat !== 'Tudo') {
    $where .= " AND categoria = ?";
    $params[] = $cat;
    $types .= 's';
}

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM parceiros $where");
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalParceiros = (int)$stmt->get_result()->fetch_assoc()['total'];

$limite = 10;
$totalPaginas = max(1, (int)ceil($totalParceiros / $limite));
$pag = max(1, (int)($_GET['pag'] ?? 1));
if ($pag > $totalPaginas) {
    $pag = $totalPaginas;
}
$offset = ($pag - 1) * $limite;

$stmt = $conn->prepare("SELECT * FROM parceiros $where ORDER BY id DESC LIMIT ? OFFSET ?");
$bind = array_merge($params, [$limite, $offset]);
$stmt->bind_param($types . 'ii', ...$bind);
$stmt->execute();
$parceiros = $stmt->get_result();
$categorias = $conn->query("SELECT DISTINCT categoria FROM parceiros WHERE categoria <> ''");
?>
<!DOCTYPE html><html class="light" lang="pt-BR"><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Economic Card - Parceiros</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Hanken+Grotesk:wght@600;700&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                    "tertiary-fixed": "#ffdcc0", "surface-container-high": "#e7e8e9", "on-primary-container": "#e397fd", "on-background": "#191c1d", "on-surface": "#191c1d", "surface-container-low": "#f3f4f5", "on-primary": "#ffffff", "inverse-surface": "#2e3132", "surface-dim": "#d9dadb", "outline-variant": "#d1c2d1", "surface-container-highest": "#e1e3e4", "primary-fixed": "#f9d8ff", "on-tertiary-container": "#ff9d35", "secondary-fixed-dim": "#9cd857", "secondary": "#3e6a00", "inverse-on-surface": "#f0f1f2", "surface": "#f8f9fa", "on-secondary-fixed-variant": "#2e5000", "primary-container": "#6a2585", "inverse-primary": "#edb1ff", "surface-container": "#edeeef", "on-secondary-fixed": "#0f2000", "on-tertiary": "#ffffff", "surface-bright": "#f8f9fa", "surface-variant": "#e1e3e4", "tertiary-container": "#6a3a00", "error-container": "#ffdad6", "on-surface-variant": "#4e434f", "primary": "#51036d", "tertiary-fixed-dim": "#ffb877", "on-tertiary-fixed-variant": "#6c3b00", "surface-tint": "#8540a0", "on-error": "#ffffff", "secondary-fixed": "#b6f570", "tertiary": "#4a2700", "on-tertiary-fixed": "#2e1600", "on-primary-fixed-variant": "#6b2686", "on-secondary": "#ffffff", "primary-fixed-dim": "#edb1ff", "on-secondary-container": "#427000", "on-error-container": "#93000a", "outline": "#7f7381", "secondary-container": "#b6f570", "on-primary-fixed": "#320046", "background": "#f8f9fa", "surface-container-lowest": "#ffffff", "error": "#ba1a1a"
            },
            "borderRadius": { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
            "spacing": { "sm": "12px", "card-gutter": "16px", "base": "8px", "xs": "4px", "lg": "32px", "md": "24px", "xl": "48px", "container-padding": "20px" },
            "fontFamily": { "label-caps": ["Hanken Grotesk"], "label-bold": ["Hanken Grotesk"], "headline-md": ["Manrope"], "body-lg": ["Manrope"], "body-md": ["Manrope"], "headline-sm": ["Manrope"], "display-lg": ["Manrope"] },
            "fontSize": {
                    "label-caps": ["10px", { "lineHeight": "1", "fontWeight": "600" }], "label-bold": ["12px", { "lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "700" }], "headline-md": ["24px", { "lineHeight": "1.3", "fontWeight": "700" }], "body-lg": ["16px", { "lineHeight": "1.6", "fontWeight": "400" }], "body-md": ["14px", { "lineHeight": "1.5", "fontWeight": "400" }], "headline-sm": ["20px", { "lineHeight": "1.4", "fontWeight": "600" }], "display-lg": ["32px", { "lineHeight": "1.2", "fontWeight": "800" }]
            }
          },
        },
      }
    </script>
<style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .partner-card-shadow { box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .glass-nav { backdrop-filter: blur(16px); }
    </style>
</head>
<body class="bg-background text-on-background min-h-screen font-body-md">
<!-- Top App Bar -->
<header class="bg-surface dark:bg-inverse-surface flex items-center w-full px-container-padding h-16 sticky top-0 z-50">
<a class="text-primary dark:text-inverse-primary hover:opacity-80 transition-opacity active:scale-95 transition-transform mr-4" href="dashboard.php">
<span class="material-symbols-outlined">arrow_back</span>
</a>
<h1 class="font-headline-sm text-headline-sm text-primary dark:text-inverse-primary">Parceiros</h1>
</header>
<main class="pb-32 px-container-padding">
<!-- Search Section -->
<section class="mt-md">
<form method="GET" action="parceiros.php" class="relative group">
<div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
<span class="material-symbols-outlined text-outline">search</span>
</div>
<input name="busca" value="<?php echo htmlspecialchars($busca); ?>" class="block w-full pl-12 pr-4 py-4 bg-surface-container-lowest border border-outline-variant rounded-xl focus:ring-2 focus:ring-primary focus:border-primary transition-all shadow-sm font-body-md" placeholder="Buscar por nome ou categoria..." type="text">
</form>
</section>
<!-- Categories / Quick Filter -->
<div class="flex gap-sm overflow-x-auto py-md no-scrollbar">
<a href="parceiros.php" class="whitespace-nowrap px-4 py-2 rounded-full <?php echo ($cat === '' || $cat === 'Tudo') ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant hover:bg-surface-variant'; ?> font-label-bold text-label-bold transition-transform active:scale-95">Tudo</a>
<?php while ($c = $categorias->fetch_assoc()): ?>
<a href="parceiros.php?cat=<?php echo urlencode($c['categoria']); ?>" class="whitespace-nowrap px-4 py-2 rounded-full <?php echo $cat === $c['categoria'] ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant hover:bg-surface-variant'; ?> font-label-bold text-label-bold transition-transform active:scale-95"><?php echo htmlspecialchars($c['categoria']); ?></a>
<?php endwhile; ?>
</div>
<!-- Partners Grid -->
<section class="flex flex-col gap-card-gutter">
<?php if ($parceiros->num_rows === 0): ?>
<div class="bg-surface-container-lowest partner-card-shadow rounded-xl p-8 text-center">
<p class="font-body-md text-on-surface-variant">Nenhum parceiro encontrado.</p>
</div>
<?php endif; ?>
<?php while ($p = $parceiros->fetch_assoc()): ?>
<div class="bg-surface-container-lowest partner-card-shadow rounded-xl p-4 flex flex-col md:flex-row gap-4 border border-outline-variant/30 hover:border-primary/20 transition-all relative" style="transform: scale(1);">
<span class="absolute top-2 right-3 bg-[#51036d] text-white font-display-lg text-display-lg font-extrabold rounded-lg px-3 py-1"><?php echo number_format((float)$p['porcentagem'], 0, ',', '.') . '%'; ?></span>
<div class="w-20 h-20 md:w-24 md:h-24 bg-surface-container-low rounded-lg flex-shrink-0 flex items-center justify-center overflow-hidden">
<?php if ($p['logo']): ?>
<img class="w-full h-full object-contain p-2" src="<?php echo htmlspecialchars($p['logo']); ?>" alt="Logo de <?php echo htmlspecialchars($p['nome']); ?>"/>
<?php else: ?>
<span class="material-symbols-outlined text-[32px] text-outline">storefront</span>
<?php endif; ?>
</div>
<div class="flex-grow">
<h3 class="font-headline-sm text-headline-sm text-on-surface mb-xs"><?php echo htmlspecialchars($p['nome']); ?></h3>
<p class="font-body-md text-on-surface-variant text-sm mb-sm"><?php echo htmlspecialchars($p['endereco'] ?: '-'); ?></p>
<div class="flex gap-3">
<a class="text-secondary hover:opacity-70 transition-opacity" target="_blank" rel="noopener" href="<?php echo $p['whatsapp'] ? 'tel:+' . htmlspecialchars($p['whatsapp']) : '#'; ?>"><span class="material-symbols-outlined text-lg">call</span></a>
<?php if ($p['whatsapp']): ?>
<a class="text-[#25D366] hover:opacity-70 transition-opacity" target="_blank" rel="noopener" aria-label="WhatsApp" href="https://wa.me/<?php echo htmlspecialchars($p['whatsapp']); ?>"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>
<?php endif; ?>
<?php if (!empty($p['instagram'])): $ig = trim($p['instagram']); if (strpos($ig, 'http') !== 0) { if (strpos($ig, '@') === 0) { $ig = substr($ig, 1); } $ig = 'https://instagram.com/' . ltrim($ig, '/'); } ?>
<a class="text-[#E4405F] hover:opacity-70 transition-opacity" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($ig); ?>"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>
<?php endif; ?>
<?php if (!empty($p['facebook'])): $fb = trim($p['facebook']); if (strpos($fb, 'http') !== 0) { if (strpos($fb, '@') === 0) { $fb = substr($fb, 1); } $fb = 'https://facebook.com/' . ltrim($fb, '/'); } ?>
<a class="text-[#1877F2] hover:opacity-70 transition-opacity" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($fb); ?>"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
<?php endif; ?>
<?php if (!empty($p['site'])): $st = trim($p['site']); if (strpos($st, 'http') !== 0) { $st = 'https://' . ltrim($st, '/'); } ?>
<a class="text-primary hover:opacity-70 transition-opacity" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($st); ?>"><span class="material-symbols-outlined text-lg">language</span></a>
<?php endif; ?>
</div>
</div>
<div class="flex md:flex-col justify-end items-center gap-2">
<span class="px-3 py-1 rounded-full bg-secondary-fixed text-on-secondary-fixed font-label-caps text-label-caps uppercase"><?php echo htmlspecialchars($p['categoria'] ?: 'Parceiro'); ?></span>
</div>
</div>
<?php endwhile; ?>
</section>
<?php if ($totalPaginas > 1): ?>
<?php
$qs = '';
if ($busca !== '') { $qs .= '&busca=' . urlencode($busca); }
if ($cat !== '' && $cat !== 'Tudo') { $qs .= '&cat=' . urlencode($cat); }
?>
<section class="flex items-center justify-center gap-2 mt-lg">
<?php if ($pag > 1): ?>
<a href="parceiros.php?pag=<?php echo ($pag - 1) . $qs; ?>" class="w-10 h-10 flex items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant font-label-bold transition-transform active:scale-95"><span class="material-symbols-outlined text-lg">chevron_left</span></a>
<?php endif; ?>
<?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
<a href="parceiros.php?pag=<?php echo $i . $qs; ?>" class="w-10 h-10 flex items-center justify-center rounded-full font-label-bold transition-transform active:scale-95 <?php echo $i === $pag ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant'; ?>"><?php echo $i; ?></a>
<?php endfor; ?>
<?php if ($pag < $totalPaginas): ?>
<a href="parceiros.php?pag=<?php echo ($pag + 1) . $qs; ?>" class="w-10 h-10 flex items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant font-label-bold transition-transform active:scale-95"><span class="material-symbols-outlined text-lg">chevron_right</span></a>
<?php endif; ?>
</section>
<?php endif; ?>
</main>
<!-- Bottom Navigation Bar -->
<nav class="fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-2 py-3 pb-safe bg-surface/80 dark:bg-inverse-surface/80 backdrop-blur-xl shadow-md rounded-t-xl">
<a class="flex flex-col items-center justify-center text-on-surface-variant dark:text-surface-variant px-4 py-1 hover:bg-surface-container-high dark:hover:bg-surface-container-highest rounded-full transition-all active:scale-90 duration-200" href="dashboard.php">
<span class="material-symbols-outlined mb-1">home</span>
<span class="font-label-bold text-label-bold">Início</span>
</a>
<a class="flex flex-col items-center justify-center bg-secondary-container dark:bg-secondary text-on-secondary-container dark:text-on-secondary rounded-full px-4 py-1 transition-all active:scale-90 duration-200" href="parceiros.php">
<span class="material-symbols-outlined mb-1" style="font-variation-settings: 'FILL' 1;">storefront</span>
<span class="font-label-bold text-label-bold">Lojas</span>
</a>
<a class="flex flex-col items-center justify-center text-on-surface-variant dark:text-surface-variant px-4 py-1 hover:bg-surface-container-high dark:hover:bg-surface-container-highest rounded-full transition-all active:scale-90 duration-200" href="ativar.php">
<span class="material-symbols-outlined mb-1">credit_card</span>
<span class="font-label-bold text-label-bold">Cartão</span>
</a>
<a class="flex flex-col items-center justify-center text-on-surface-variant dark:text-surface-variant px-4 py-1 hover:bg-surface-container-high dark:hover:bg-surface-container-highest rounded-full transition-all active:scale-90 duration-200" href="perfil.php">
<span class="material-symbols-outlined mb-1">person</span>
<span class="font-label-bold text-label-bold">Perfil</span>
</a>
</nav>
<script>
        document.querySelectorAll('.partner-card-shadow').forEach(card => {
            card.addEventListener('mousedown', () => { card.style.transform = 'scale(0.98)'; });
            card.addEventListener('mouseup', () => { card.style.transform = 'scale(1)'; });
            card.addEventListener('mouseleave', () => { card.style.transform = 'scale(1)'; });
        });
    </script>
</body></html>