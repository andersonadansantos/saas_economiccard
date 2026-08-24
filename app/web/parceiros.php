<?php
$paginaAtiva = 'parceiros';
$tituloPagina = 'Parceiros';
require_once __DIR__ . '/_head.php';

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
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$totalParceiros = (int)$stmt->get_result()->fetch_assoc()['total'];

$limite = 9;
$totalPaginas = max(1, (int)ceil($totalParceiros / $limite));
$pag = max(1, (int)($_GET['pag'] ?? 1));
if ($pag > $totalPaginas) { $pag = $totalPaginas; }
$offset = ($pag - 1) * $limite;

$stmt = $conn->prepare("SELECT * FROM parceiros $where ORDER BY id DESC LIMIT ? OFFSET ?");
$bind = array_merge($params, [$limite, $offset]);
$stmt->bind_param($types . 'ii', ...$bind);
$stmt->execute();
$parceiros = $stmt->get_result();
$categorias = $conn->query("SELECT DISTINCT categoria FROM parceiros WHERE categoria <> ''");
?>
<div class="bg-white rounded-2xl card-shadow p-5 mb-6">
<form method="GET" action="parceiros.php" class="flex flex-col md:flex-row gap-3">
<div class="relative flex-1">
<span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><span class="material-symbols-outlined text-on-surface-variant/50">search</span></span>
<input name="busca" value="<?php echo htmlspecialchars($busca); ?>" class="w-full pl-12 pr-4 py-3 bg-surface-variant/50 border border-outline-variant rounded-xl focus:ring-2 focus:ring-primary focus:border-primary transition-all font-sans text-sm" placeholder="Buscar por nome ou categoria..." type="text">
</div>
<button class="bg-primary hover:bg-primary-dark text-white font-bold px-6 py-3 rounded-xl transition flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[18px]">search</span> Buscar</button>
</form>
</div>

<div class="flex gap-3 overflow-x-auto pb-4 mb-6">
<a href="parceiros.php" class="whitespace-nowrap px-4 py-2 rounded-full text-xs font-bold transition <?php echo ($cat === '' || $cat === 'Tudo') ? 'bg-primary text-white' : 'bg-white text-on-surface-variant border border-outline-variant hover:border-primary'; ?>">Tudo</a>
<?php while ($c = $categorias->fetch_assoc()): ?>
<a href="parceiros.php?cat=<?php echo urlencode($c['categoria']); ?>" class="whitespace-nowrap px-4 py-2 rounded-full text-xs font-bold transition <?php echo $cat === $c['categoria'] ? 'bg-primary text-white' : 'bg-white text-on-surface-variant border border-outline-variant hover:border-primary'; ?>"><?php echo htmlspecialchars($c['categoria']); ?></a>
<?php endwhile; ?>
</div>

<div class="flex items-center justify-between mb-4">
<h3 class="font-extrabold text-on-surface"><?php echo $totalParceiros; ?> parceiro<?php echo $totalParceiros === 1 ? '' : 's'; ?> encontrado<?php echo $totalParceiros === 1 ? '' : 's'; ?></h3>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
<?php if ($parceiros->num_rows === 0): ?>
<div class="col-span-full bg-white rounded-2xl card-shadow p-10 text-center">
<p class="text-on-surface-variant">Nenhum parceiro encontrado.</p>
</div>
<?php endif; ?>
<?php while ($p = $parceiros->fetch_assoc()): ?>
<div class="bg-white rounded-2xl card-shadow p-5 hover-lift border border-outline-variant/30 relative">
<span class="absolute top-4 right-4 bg-primary text-white font-extrabold rounded-lg px-3 py-1"><?php echo number_format((float)$p['porcentagem'], 0, ',', '.') . '%'; ?></span>
<div class="w-20 h-20 bg-surface-variant/50 rounded-xl flex items-center justify-center overflow-hidden mb-4">
<?php if ($p['logo']): ?>
<img class="w-full h-full object-contain p-2" src="<?php echo webUrl($p['logo']); ?>" alt="Logo de <?php echo htmlspecialchars($p['nome']); ?>"/>
<?php else: ?>
<span class="material-symbols-outlined text-3xl text-on-surface-variant">storefront</span>
<?php endif; ?>
</div>
<h4 class="font-extrabold text-on-surface mb-1"><?php echo htmlspecialchars($p['nome']); ?></h4>
<p class="text-sm text-on-surface-variant mb-4"><?php echo htmlspecialchars($p['endereco'] ?: '-'); ?></p>
<div class="flex items-center gap-2 flex-wrap">
<a class="text-secondary hover:opacity-70 transition" target="_blank" rel="noopener" href="<?php echo $p['whatsapp'] ? 'tel:+' . htmlspecialchars($p['whatsapp']) : '#'; ?>"><span class="material-symbols-outlined">call</span></a>
<?php if ($p['whatsapp']): ?>
<a class="text-[#25D366] hover:opacity-70 transition" target="_blank" rel="noopener" aria-label="WhatsApp" href="https://wa.me/<?php echo htmlspecialchars($p['whatsapp']); ?>"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>
<?php endif; ?>
<?php if (!empty($p['instagram'])): $ig = trim($p['instagram']); if (strpos($ig, 'http') !== 0) { if (strpos($ig, '@') === 0) { $ig = substr($ig, 1); } $ig = 'https://instagram.com/' . ltrim($ig, '/'); } ?>
<a class="text-[#E4405F] hover:opacity-70 transition" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($ig); ?>"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>
<?php endif; ?>
<?php if (!empty($p['facebook'])): $fb = trim($p['facebook']); if (strpos($fb, 'http') !== 0) { if (strpos($fb, '@') === 0) { $fb = substr($fb, 1); } $fb = 'https://facebook.com/' . ltrim($fb, '/'); } ?>
<a class="text-[#1877F2] hover:opacity-70 transition" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($fb); ?>"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
<?php endif; ?>
<?php if (!empty($p['site'])): $st = trim($p['site']); if (strpos($st, 'http') !== 0) { $st = 'https://' . ltrim($st, '/'); } ?>
<a class="text-primary hover:opacity-70 transition" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($st); ?>"><span class="material-symbols-outlined">language</span></a>
<?php endif; ?>
<span class="ml-auto px-3 py-1 rounded-full bg-secondary-container/40 text-secondary font-bold text-[10px] uppercase"><?php echo htmlspecialchars($p['categoria'] ?: 'Parceiro'); ?></span>
</div>
</div>
<?php endwhile; ?>
</div>

<?php if ($totalPaginas > 1): ?>
<?php
$qs = '';
if ($busca !== '') { $qs .= '&busca=' . urlencode($busca); }
if ($cat !== '' && $cat !== 'Tudo') { $qs .= '&cat=' . urlencode($cat); }
?>
<div class="flex items-center justify-center gap-2 mt-8">
<?php if ($pag > 1): ?>
<a href="parceiros.php?pag=<?php echo ($pag - 1) . $qs; ?>" class="w-10 h-10 flex items-center justify-center rounded-full bg-white card-shadow text-on-surface-variant font-bold hover:shadow-lg transition"><span class="material-symbols-outlined text-lg">chevron_left</span></a>
<?php endif; ?>
<?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
<a href="parceiros.php?pag=<?php echo $i . $qs; ?>" class="w-10 h-10 flex items-center justify-center rounded-full font-bold transition <?php echo $i === $pag ? 'bg-primary text-white' : 'bg-white card-shadow text-on-surface-variant hover:shadow-lg'; ?>"><?php echo $i; ?></a>
<?php endfor; ?>
<?php if ($pag < $totalPaginas): ?>
<a href="parceiros.php?pag=<?php echo ($pag + 1) . $qs; ?>" class="w-10 h-10 flex items-center justify-center rounded-full bg-white card-shadow text-on-surface-variant font-bold hover:shadow-lg transition"><span class="material-symbols-outlined text-lg">chevron_right</span></a>
<?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/_foot.php'; ?>
