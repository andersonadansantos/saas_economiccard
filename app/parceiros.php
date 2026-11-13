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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;display=swap" rel="stylesheet">
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
            "fontFamily": { "label-caps": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "label-bold": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "headline-md": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "body-lg": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "body-md": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "headline-sm": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "display-lg": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"] },
            "fontSize": {
                    "label-caps": ["11px", { "lineHeight": "1", "letterSpacing": "0.06em", "fontWeight": "600" }], "label-bold": ["13px", { "lineHeight": "1", "letterSpacing": "0.01em", "fontWeight": "700" }], "headline-md": ["22px", { "lineHeight": "1.25", "letterSpacing": "-0.02em", "fontWeight": "700" }], "body-lg": ["17px", { "lineHeight": "1.5", "fontWeight": "400" }], "body-md": ["15px", { "lineHeight": "1.45", "fontWeight": "400" }], "headline-sm": ["18px", { "lineHeight": "1.3", "letterSpacing": "-0.01em", "fontWeight": "600" }], "display-lg": ["30px", { "lineHeight": "1.15", "letterSpacing": "-0.03em", "fontWeight": "700" }]
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
<link rel="stylesheet" href="ui.css?v=<?php echo (int)@filemtime(__DIR__ . '/ui.css'); ?>"/>
</head>
<body class="bg-background text-on-background min-h-screen font-body-md ec-body">
<!-- Top App Bar -->
<header class="ec-appbar" id="appBar">
<div class="ec-appbar__inner">
<a class="ec-iconbtn" href="dashboard.php" aria-label="Voltar">
<span class="material-symbols-outlined">arrow_back</span>
</a>
<span class="text-[15px] font-extrabold text-on-surface tracking-tight">Parceiros</span>
<div class="ec-appbar__spacer"></div>
<span class="ec-chip ec-chip--neutral"><?php echo (int)$totalParceiros; ?></span>
</div>
</header>
<main class="ec-shell">
<!-- Search Section -->
<section>
<form method="GET" action="parceiros.php" class="ec-search">
<span class="material-symbols-outlined ec-search__icon">search</span>
<input name="busca" value="<?php echo htmlspecialchars($busca); ?>" class="ec-input" placeholder="Buscar por nome, categoria..." type="search">
</form>
</section>
<!-- Categories / Quick Filter -->
<div class="ec-pillbar ec-scroll-x" style="margin-top:14px">
<a href="parceiros.php" class="ec-pill <?php echo ($cat === '' || $cat === 'Tudo') ? 'is-active' : ''; ?>">Tudo</a>
<?php while ($c = $categorias->fetch_assoc()): ?>
<a href="parceiros.php?cat=<?php echo urlencode($c['categoria']); ?>" class="ec-pill <?php echo $cat === $c['categoria'] ? 'is-active' : ''; ?>"><?php echo htmlspecialchars($c['categoria']); ?></a>
<?php endwhile; ?>
</div>
<!-- Partners Grid -->
<section class="ec-section" style="margin-top:20px">
<div class="ec-section__head">
<span class="ec-eyebrow"><?php echo (int)$parceiros->num_rows; ?> resultado<?php echo $parceiros->num_rows === 1 ? '' : 's'; ?></span>
<?php if ($busca !== '' || ($cat !== '' && $cat !== 'Tudo')): ?>
<a href="parceiros.php" class="ec-hint" style="text-decoration:none"><span class="material-symbols-outlined">filter_alt_off</span> Limpar filtros</a>
<?php endif; ?>
</div>
<div class="ec-stack">
<?php if ($parceiros->num_rows === 0): ?>
<div class="ec-card">
<div class="ec-card__body" style="text-align:center;padding:36px 20px">
<span class="ec-tile" style="margin:0 auto;width:52px;height:52px;border-radius:16px"><span class="material-symbols-outlined">storefront</span></span>
<p class="text-[13.5px] font-bold text-on-surface mt-3">Nenhum parceiro encontrado</p>
<p class="ec-meta mt-1">Tente outra busca ou selecione outra categoria.</p>
</div>
</div>
<?php endif; ?>
<?php while ($p = $parceiros->fetch_assoc()): ?>
<div class="ec-card partner-card-shadow" style="transform: scale(1);">
<div style="padding:14px 15px">
<div class="flex items-start gap-3">
<div class="w-16 h-16 rounded-[14px] bg-[#f3f4f5] flex-shrink-0 flex items-center justify-center overflow-hidden" style="box-shadow:inset 0 0 0 1px rgba(25,28,29,.05)">
<?php if ($p['logo']): ?>
<img class="w-full h-full object-contain p-1.5" src="<?php echo htmlspecialchars(asset_url($p['logo'])); ?>" alt="Logo de <?php echo htmlspecialchars($p['nome']); ?>"/>
<?php else: ?>
<span class="material-symbols-outlined text-[28px] text-outline">storefront</span>
<?php endif; ?>
</div>
<div class="min-w-0 flex-1">
<div class="flex items-start justify-between gap-2">
<h3 class="text-[14px] font-extrabold text-on-surface leading-snug truncate"><?php echo htmlspecialchars($p['nome']); ?></h3>
<span class="flex-shrink-0 bg-[#51036d] text-white text-[12px] font-extrabold leading-none px-2.5 py-1.5 rounded-full"><?php echo number_format((float)$p['porcentagem'], 0, ',', '.') . '%'; ?></span>
</div>
<p class="flex items-start gap-1 mt-1 text-[11.5px] leading-snug text-on-surface-variant">
<span class="material-symbols-outlined" style="font-size:14px;margin-top:1px;flex:0 0 auto">location_on</span>
<span class="line-clamp-2"><?php echo htmlspecialchars($p['endereco'] ?: 'Endereço não informado'); ?></span>
</p>
<span class="ec-chip ec-chip--success mt-2"><?php echo htmlspecialchars($p['categoria'] ?: 'Parceiro'); ?></span>
</div>
</div>
<div class="ec-divider" style="margin:12px 0 10px"></div>
<div class="flex flex-wrap items-center justify-between gap-2">
<span class="ec-meta">Fale com o parceiro</span>
<div class="flex flex-wrap items-center justify-end gap-1.5" style="margin-left:auto">
<a class="ec-iconbtn" style="width:36px;height:36px;color:#3e6a00" aria-label="Ligar" target="_blank" rel="noopener" href="<?php echo $p['whatsapp'] ? 'tel:+' . htmlspecialchars($p['whatsapp']) : '#'; ?>"><span class="material-symbols-outlined text-lg">call</span></a>
<?php if ($p['whatsapp']): ?>
<a class="ec-iconbtn" style="width:36px;height:36px;color:#25D366" target="_blank" rel="noopener" aria-label="WhatsApp" href="https://wa.me/<?php echo htmlspecialchars($p['whatsapp']); ?>"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>
<?php endif; ?>
<?php if (!empty($p['instagram'])): $ig = trim($p['instagram']); if (strpos($ig, 'http') !== 0) { if (strpos($ig, '@') === 0) { $ig = substr($ig, 1); } $ig = 'https://instagram.com/' . ltrim($ig, '/'); } ?>
<a class="ec-iconbtn" style="width:36px;height:36px;color:#E4405F" target="_blank" rel="noopener" aria-label="Instagram" href="<?php echo htmlspecialchars($ig); ?>"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>
<?php endif; ?>
<?php if (!empty($p['facebook'])): $fb = trim($p['facebook']); if (strpos($fb, 'http') !== 0) { if (strpos($fb, '@') === 0) { $fb = substr($fb, 1); } $fb = 'https://facebook.com/' . ltrim($fb, '/'); } ?>
<a class="ec-iconbtn" style="width:36px;height:36px;color:#1877F2" target="_blank" rel="noopener" aria-label="Facebook" href="<?php echo htmlspecialchars($fb); ?>"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
<?php endif; ?>
<?php if (!empty($p['site'])): $st = trim($p['site']); if (strpos($st, 'http') !== 0) { $st = 'https://' . ltrim($st, '/'); } ?>
<a class="ec-iconbtn" style="width:36px;height:36px;color:#51036d" target="_blank" rel="noopener" aria-label="Site" href="<?php echo htmlspecialchars($st); ?>"><span class="material-symbols-outlined text-lg">language</span></a>
<?php endif; ?>
</div>
</div>
</div>
</div>
<?php endwhile; ?>
</div>
</section>
<?php if ($totalPaginas > 1): ?>
<?php
$qs = '';
if ($busca !== '') { $qs .= '&busca=' . urlencode($busca); }
if ($cat !== '' && $cat !== 'Tudo') { $qs .= '&cat=' . urlencode($cat); }
?>
<section class="flex items-center justify-center gap-2" style="margin-top:24px">
<?php if ($pag > 1): ?>
<a href="parceiros.php?pag=<?php echo ($pag - 1) . $qs; ?>" class="ec-iconbtn" style="background:#fff;border:1px solid var(--ec-line)" aria-label="Página anterior"><span class="material-symbols-outlined" style="font-size:20px">chevron_left</span></a>
<?php endif; ?>
<?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
<a href="parceiros.php?pag=<?php echo $i . $qs; ?>" class="ec-iconbtn" style="width:40px;height:40px;font-size:13px;font-weight:800;<?php echo $i === $pag ? 'background:var(--ec-primary);color:#fff' : 'background:#fff;border:1px solid var(--ec-line);color:var(--ec-muted)'; ?>"><?php echo $i; ?></a>
<?php endfor; ?>
<?php if ($pag < $totalPaginas): ?>
<a href="parceiros.php?pag=<?php echo ($pag + 1) . $qs; ?>" class="ec-iconbtn" style="background:#fff;border:1px solid var(--ec-line)" aria-label="Próxima página"><span class="material-symbols-outlined" style="font-size:20px">chevron_right</span></a>
<?php endif; ?>
</section>
<?php endif; ?>
</main>
<!-- Bottom Navigation Bar -->
<?php
require_once __DIR__ . '/bottom_nav.php';
app_bottom_nav([
    ['href' => 'dashboard.php', 'icone' => 'home', 'label' => 'Início'],
    ['href' => 'parceiros.php', 'icone' => 'storefront', 'label' => 'Lojas', 'ativo' => true],
    ['href' => 'ativar.php', 'icone' => 'credit_card', 'label' => 'Cartão'],
    ['href' => 'perfil.php', 'icone' => 'person', 'label' => 'Perfil'],
]);
?>
<script>
        document.querySelectorAll('.partner-card-shadow').forEach(card => {
            card.addEventListener('mousedown', () => { card.style.transform = 'scale(0.98)'; });
            card.addEventListener('mouseup', () => { card.style.transform = 'scale(1)'; });
            card.addEventListener('mouseleave', () => { card.style.transform = 'scale(1)'; });
        });
    </script>
</body></html>