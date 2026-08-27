<?php
$paginaAtiva = 'dashboard';
$tituloPagina = 'Início';
require_once __DIR__ . '/_head.php';

$cartaoAtivado = $pers['cartao_ativado'] ?? '';
$cartaoDesativado = $pers['cartao_desativado'] ?? '';
$cartaoImg = $u['cartao_ativo'] ? $cartaoAtivado : $cartaoDesativado;
$banner = $conn->query("SELECT * FROM banners WHERE ativo = 1 ORDER BY id DESC LIMIT 1")->fetch_assoc();
$parceirosDestaque = $conn->query("SELECT * FROM parceiros WHERE ativo = 1 ORDER BY id DESC LIMIT 6");
$totalParceiros = (int)$conn->query("SELECT COUNT(*) AS t FROM parceiros WHERE ativo = 1")->fetch_assoc()['t'];
?>

<!-- Stat cards -->
<section class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
<div class="bg-white rounded-2xl card-shadow p-5 hover-lift">
<div class="flex items-center justify-between mb-2">
<p class="text-xs font-bold uppercase text-on-surface-variant/60">Status do Cartão</p>
<span class="material-symbols-outlined text-primary">credit_card</span>
</div>
<?php if ($u['cartao_ativo']): ?>
<p class="text-lg font-extrabold text-secondary">Ativo</p>
<p class="text-xs text-on-surface-variant mt-0.5">Válido até <?php echo $u['cartao_validade'] ? date('d/m/Y', strtotime($u['cartao_validade'])) : '--'; ?></p>
<?php else: ?>
<p class="text-lg font-extrabold text-[#ba1a1a]">Expirado</p>
<p class="text-xs text-on-surface-variant mt-0.5">Ative para liberar seus descontos</p>
<?php endif; ?>
</div>
<div class="bg-white rounded-2xl card-shadow p-5 hover-lift">
<div class="flex items-center justify-between mb-2">
<p class="text-xs font-bold uppercase text-on-surface-variant/60">Parceiros</p>
<span class="material-symbols-outlined text-secondary">storefront</span>
</div>
<p class="text-lg font-extrabold text-on-surface"><?php echo $totalParceiros; ?></p>
<p class="text-xs text-on-surface-variant mt-0.5">empresas com desconto</p>
</div>
<div class="bg-white rounded-2xl card-shadow p-5 hover-lift">
<div class="flex items-center justify-between mb-2">
<p class="text-xs font-bold uppercase text-on-surface-variant/60">Mensagens</p>
<span class="material-symbols-outlined text-secondary">notifications</span>
</div>
<p class="text-lg font-extrabold text-on-surface"><?php echo $naoLidas; ?> nova<?php echo $naoLidas === 1 ? '' : 's'; ?></p>
<p class="text-xs text-on-surface-variant mt-0.5">do Economic Card</p>
</div>
</section>

<section class="grid grid-cols-1 xl:grid-cols-2 gap-8">
<div class="space-y-8">
<!-- Digital card -->
<div class="relative group hover-lift rounded-2xl overflow-hidden cursor-pointer" onclick="abrirFullscreen()">
<?php if ($cartaoImg): ?>
<img class="w-full h-auto rounded-2xl object-contain" src="<?php echo webUrl($cartaoImg); ?>" alt="Cartão Economic Card"/>
<?php else: ?>
<div class="premium-gradient rounded-2xl p-8 shadow-lg relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between">
<div class="absolute -top-10 -right-10 w-48 h-48 bg-white/10 rounded-full blur-2xl"></div>
<div class="flex justify-between items-start z-10">
<div class="space-y-1">
<span class="text-[10px] text-white/60 tracking-widest font-semibold">MEMBRO PLATINUM</span>
<div class="h-[2px] w-8 bg-secondary-container"></div>
</div>
<?php if ($u['cartao_ativo']): ?>
<span class="px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 bg-secondary text-white"><span class="w-1.5 h-1.5 rounded-full bg-white/80"></span> ATIVADO</span>
<?php else: ?>
<span class="px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 bg-[#ba1a1a] text-white"><span class="w-1.5 h-1.5 rounded-full bg-white/80"></span> EXPIRADO</span>
<?php endif; ?>
</div>
<div class="z-10 mt-auto">
<p class="text-white text-2xl font-extrabold mb-4"><?php echo htmlspecialchars(strtoupper($u['nome'])); ?></p>
<div class="flex justify-between items-end">
<div class="space-y-1">
<p class="text-[13px] text-white/70 font-bold tracking-widest">•••• •••• •••• <?php echo htmlspecialchars($final); ?></p>
<p class="text-[11px] text-white/50 font-semibold">VALIDADE 08/25</p>
</div>
<p class="text-white/20 text-4xl font-extrabold italic">Economic</p>
</div>
</div>
</div>
<?php endif; ?>
</div>
<p class="text-center text-xs font-bold text-secondary flex items-center justify-center gap-1 mt-2"><span class="material-symbols-outlined text-[16px]">touch_app</span> Clique no cartão para ampliar</p>

<!-- Quick access -->
<div class="bg-white rounded-2xl card-shadow overflow-hidden">
<div class="px-6 py-4 border-b border-outline-variant flex items-center justify-between">
<h3 class="font-extrabold text-on-surface">Acesso Rápido</h3>
</div>
<div class="grid grid-cols-2 gap-4 p-6">
<a href="perfil.php" class="flex flex-col items-center justify-center gap-3 border border-outline-variant rounded-xl p-5 hover:border-primary/30 hover:shadow-lg transition">
<div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary"><span class="material-symbols-outlined">account_circle</span></div>
<span class="text-xs font-bold text-on-surface uppercase">Perfil</span>
</a>
<a href="ativar.php" class="flex flex-col items-center justify-center gap-3 border border-outline-variant rounded-xl p-5 hover:border-secondary/30 hover:shadow-lg transition">
<div class="w-12 h-12 rounded-full bg-secondary/10 flex items-center justify-center text-secondary"><span class="material-symbols-outlined">credit_score</span></div>
<span class="text-xs font-bold text-on-surface uppercase">Ativar Cartão</span>
</a>
<a href="parceiros.php" class="flex flex-col items-center justify-center gap-3 border border-outline-variant rounded-xl p-5 hover:border-primary/30 hover:shadow-lg transition">
<div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary"><span class="material-symbols-outlined">storefront</span></div>
<span class="text-xs font-bold text-on-surface uppercase">Parceiros</span>
</a>
<a href="cartao_fisico.php" class="flex flex-col items-center justify-center gap-3 border border-outline-variant rounded-xl p-5 hover:border-primary/30 hover:shadow-lg transition relative">
<span class="absolute top-2 right-2 px-1.5 py-0.5 bg-secondary-container text-secondary text-[8px] font-bold rounded">OPCIONAL</span>
<div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary"><span class="material-symbols-outlined">contactless</span></div>
<span class="text-xs font-bold text-on-surface uppercase">Cartão Físico</span>
</a>
<div class="flex flex-col items-center justify-center gap-3 border border-outline-variant rounded-xl p-5 relative opacity-70 cursor-not-allowed select-none" title="Em breve">
<span class="absolute top-2 right-2 px-1.5 py-0.5 bg-secondary-container text-[#2e5000] text-[8px] font-bold rounded">EM BREVE</span>
<div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary"><span class="material-symbols-outlined">stethoscope</span></div>
<span class="text-xs font-bold text-on-surface uppercase">Telemedicina</span>
</div>
</div>
</div>
</div>

<div class="space-y-8">
<!-- Mensagens -->
<div class="bg-white rounded-2xl card-shadow overflow-hidden">
<div class="px-6 py-4 border-b border-outline-variant flex items-center justify-between">
<div class="flex items-center gap-2">
<h3 class="font-extrabold text-on-surface">Mensagens</h3>
<?php if ($naoLidas > 0): ?>
<span class="px-2 py-0.5 rounded-full bg-error text-on-error text-[10px] font-bold"><?php echo $naoLidas; ?></span>
<?php endif; ?>
</div>
</div>
<?php if ($mensagens->num_rows === 0): ?>
<p class="px-6 py-8 text-sm text-on-surface-variant text-center">Nenhuma mensagem no momento.</p>
<?php endif; ?>
<?php while ($m = $mensagens->fetch_assoc()): ?>
<button type="button" class="w-full text-left px-6 py-4 border-b border-outline-variant/20 hover:bg-surface-variant/50 transition flex items-start gap-4" onclick="abrirMensagem('<?php echo addslashes(htmlspecialchars($m['titulo'])); ?>', '<?php echo addslashes(htmlspecialchars($m['mensagem'])); ?>', '<?php echo date('d/m/Y H:i', strtotime($m['criado_em'])); ?>')">
<div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[18px]">notifications</span></div>
<div class="min-w-0">
<p class="font-bold text-on-surface text-sm"><?php echo htmlspecialchars($m['titulo']); ?></p>
<p class="text-sm text-on-surface-variant truncate"><?php echo htmlspecialchars($m['mensagem']); ?></p>
<p class="text-[11px] text-on-surface-variant/70 mt-0.5"><?php echo date('d/m/Y H:i', strtotime($m['criado_em'])); ?></p>
</div>
</button>
<?php endwhile; ?>
</div>

<!-- Banner -->
<div class="bg-white rounded-2xl card-shadow overflow-hidden">
<div class="px-6 py-4 border-b border-outline-variant flex items-center justify-between">
<h3 class="font-extrabold text-on-surface">Oferta Especial</h3>
<a href="parceiros.php" class="text-sm font-semibold text-primary hover:underline">Ver todos</a>
</div>
<?php if ($banner): ?>
<div class="p-6">
<div class="bg-secondary-container/20 border border-secondary/20 rounded-xl overflow-hidden">
<?php if ($banner['imagem']): ?>
<img class="w-full h-44 object-cover" src="<?php echo webUrl($banner['imagem']); ?>" alt="Banner"/>
<?php endif; ?>
<div class="p-5">
<div class="flex items-center justify-between gap-3 flex-wrap">
<div>
<h4 class="font-extrabold text-on-surface"><?php echo htmlspecialchars($banner['titulo']); ?></h4>
<?php if ($banner['desconto']): ?>
<span class="inline-block mt-1 px-2 py-0.5 rounded-full bg-secondary text-white text-[10px] font-bold uppercase"><?php echo htmlspecialchars($banner['desconto']); ?></span>
<?php endif; ?>
<?php if ($banner['descricao']): ?>
<p class="text-sm text-on-surface-variant mt-1"><?php echo htmlspecialchars($banner['descricao']); ?></p>
<?php endif; ?>
</div>
<a href="parceiros.php" class="bg-primary hover:bg-primary-dark text-white text-sm font-bold px-5 py-2.5 rounded-full transition whitespace-nowrap"><?php echo htmlspecialchars($banner['botao_texto'] ?: 'EU QUERO!'); ?></a>
</div>
</div>
</div>
</div>
<?php else: ?>
<p class="px-6 py-8 text-sm text-on-surface-variant text-center">Nenhuma oferta no momento.</p>
<?php endif; ?>
</div>

<!-- Parceiros destaque -->
<div class="bg-white rounded-2xl card-shadow overflow-hidden">
<div class="px-6 py-4 border-b border-outline-variant flex items-center justify-between">
<h3 class="font-extrabold text-on-surface">Parceiros em Destaque</h3>
<a href="parceiros.php" class="text-sm font-semibold text-primary hover:underline">Ver todos</a>
</div>
<?php if ($parceirosDestaque->num_rows === 0): ?>
<p class="px-6 py-8 text-sm text-on-surface-variant text-center">Nenhum parceiro no momento.</p>
<?php endif; ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6">
<?php while ($p = $parceirosDestaque->fetch_assoc()): ?>
<div class="flex items-center gap-4 border border-outline-variant rounded-xl p-4 hover:border-primary/30 transition">
<div class="w-14 h-14 bg-surface-variant/50 rounded-lg flex items-center justify-center overflow-hidden shrink-0">
<?php if ($p['logo']): ?>
<img class="w-full h-full object-contain p-1.5" src="<?php echo webUrl($p['logo']); ?>" alt="Logo"/>
<?php else: ?>
<span class="material-symbols-outlined text-on-surface-variant">storefront</span>
<?php endif; ?>
</div>
<div class="min-w-0 flex-1">
<p class="font-bold text-on-surface text-sm truncate"><?php echo htmlspecialchars($p['nome']); ?></p>
<?php if ($p['categoria']): ?>
<span class="text-[11px] font-semibold text-secondary"><?php echo htmlspecialchars($p['categoria']); ?></span>
<?php endif; ?>
</div>
<a href="parceiros.php" class="bg-secondary-container/30 text-secondary text-xs font-bold px-3 py-1.5 rounded-full hover:bg-secondary-container/50 transition shrink-0"><?php echo number_format((float)$p['porcentagem'], 0, ',', '.') . '%'; ?></a>
</div>
<?php endwhile; ?>
</div>
</div>
</div>
</section>

<!-- Fullscreen card -->
<div class="hidden fixed inset-0 z-[100] bg-black/90 backdrop-blur-md items-center justify-center p-6" id="fullscreenCard">
<button class="absolute top-6 right-6 material-symbols-outlined text-white/80 text-3xl" onclick="fecharFullscreen()">close</button>
<div class="w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">
<?php if ($cartaoImg): ?>
<img class="w-full h-auto" src="<?php echo webUrl($cartaoImg); ?>" alt="Cartão Economic Card"/>
<?php else: ?>
<div class="premium-gradient rounded-2xl p-8 shadow-2xl relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between">
<div class="absolute -top-10 -right-10 w-48 h-48 bg-white/10 rounded-full blur-2xl"></div>
<div class="flex justify-between items-start z-10">
<div class="space-y-1"><span class="text-[10px] text-white/60 tracking-widest font-semibold">MEMBRO PLATINUM</span><div class="h-[2px] w-8 bg-secondary-container"></div></div>
<span class="px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 bg-white/15 backdrop-blur-md text-white"><span class="w-1.5 h-1.5 rounded-full bg-secondary-container"></span><?php echo $u['cartao_ativo'] ? 'ATIVO' : 'INATIVO'; ?></span>
</div>
<div class="z-10 mt-auto">
<p class="text-white text-2xl font-extrabold mb-4"><?php echo htmlspecialchars(strtoupper($u['nome'])); ?></p>
<div class="flex justify-between items-end">
<div class="space-y-1"><p class="text-[13px] text-white/70 font-bold tracking-widest">•••• •••• •••• <?php echo htmlspecialchars($final); ?></p><p class="text-[11px] text-white/50 font-semibold">VALIDADE 08/25</p></div>
<p class="text-white/20 text-4xl font-extrabold italic">Economic</p>
</div>
</div>
</div>
<?php endif; ?>
</div>
</div>
<script>
function abrirFullscreen() {
    const el = document.getElementById('fullscreenCard');
    el.classList.remove('hidden'); el.classList.add('flex');
}
function fecharFullscreen() {
    const el = document.getElementById('fullscreenCard');
    el.classList.add('hidden'); el.classList.remove('flex');
}
</script>
<?php require_once __DIR__ . '/_foot.php'; ?>
