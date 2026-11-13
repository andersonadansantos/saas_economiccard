<?php
$paginaAtiva = 'dashboard';
$tituloPagina = 'Início';
require_once __DIR__ . '/_head.php';

$cartaoAtivado = $pers['cartao_ativado'] ?? '';
$cartaoDesativado = $pers['cartao_desativado'] ?? '';
$cartaoImg = $u['cartao_ativo'] ? $cartaoAtivado : $cartaoDesativado;
$bannersTopo = $conn->query("SELECT * FROM banners_topo WHERE ativo = 1 ORDER BY ordem ASC, id DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$parceirosDestaque = $conn->query("SELECT * FROM parceiros WHERE ativo = 1 ORDER BY id DESC LIMIT 6");
$totalParceiros = (int)$conn->query("SELECT COUNT(*) AS t FROM parceiros WHERE ativo = 1")->fetch_assoc()['t'];
?>

<?php if (!empty($bannersTopo)): ?>
<div class="mb-8">
<div class="rounded-2xl overflow-hidden" style="max-width:400px; margin:0 auto;">
<div id="topoTrackWeb" style="display:flex; flex-wrap:nowrap; will-change:transform;">
<?php foreach ($bannersTopo as $bt): ?>
<div style="flex:0 0 100%; min-width:100%;">
<?php if (!empty($bt['link_externo'])): ?>
<a href="<?php echo htmlspecialchars($bt['link_externo']); ?>" target="_blank" rel="noopener">
<img src="<?php echo htmlspecialchars(asset_url($bt['imagem'])); ?>" alt="" class="w-full h-[100px] object-cover rounded-2xl"/>
</a>
<?php else: ?>
<img src="<?php echo htmlspecialchars(asset_url($bt['imagem'])); ?>" alt="" class="w-full h-[100px] object-cover rounded-2xl"/>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php if (count($bannersTopo) > 1): ?>
<div class="flex justify-center gap-1 mt-2" id="topoDotsWeb" style="pointer-events:auto;">
<?php foreach ($bannersTopo as $i => $bt): ?><span class="w-1.5 h-1.5 rounded-full bg-gray-300/70 transition-all duration-300 cursor-pointer <?php echo $i === 0 ? '!w-4 !bg-primary' : ''; ?>" data-index="<?php echo $i; ?>" onclick="topoGoTo(<?php echo $i; ?>)"></span><?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>
<script>
(function(){
var track=document.getElementById('topoTrackWeb');
if(!track)return;
var items=Array.prototype.slice.call(track.children);
if(items.length<2)return;
var idx=0,total=items.length,auto;
window.topoGoTo=function(i){goTo(i);};
function goTo(i){idx=i;track.style.transition='transform .4s ease';track.style.transform='translateX('+(idx*-100)+'%)';updDots();}
function next(){goTo((idx+1)%total);}
function updDots(){var dots=document.querySelectorAll('#topoDotsWeb span');dots.forEach(function(d,i){d.classList.toggle('!w-4',i===idx);d.classList.toggle('!bg-primary',i===idx);d.classList.toggle('!bg-gray-300',i!==idx);});}
function startAuto(){auto=setInterval(next,4000);}
startAuto();
var x0=null;
track.addEventListener('touchstart',function(e){x0=e.touches[0].clientX;clearInterval(auto);},{passive:true});
track.addEventListener('touchend',function(e){if(x0===null)return;var dx=e.changedTouches[0].clientX-x0;if(dx<-40)goTo((idx+1)%total);else if(dx>40)goTo((idx-1+total)%total);startAuto();x0=null;});
})();
</script>
<?php endif; ?>

<!-- Quick Icons Row (3D IconScout) -->
<div class="flex justify-center gap-4 py-4">
<a href="../parceiros.php" class="flex flex-col items-center gap-1 group active:scale-95 transition-transform">
<div class="w-[48px] h-[48px] flex items-center justify-center">
<img src="<?php echo htmlspecialchars(asset_url('../img/icons/parceiros.png')); ?>" alt="Parceiros" class="w-full h-full object-contain drop-shadow-lg group-hover:scale-110 transition-transform" loading="lazy"/>
</div>
<span class="text-[11px] font-semibold text-on-surface-variant">Parceiros</span>
</a>
<?php if (!$ehDep): ?>
<a href="../ativar.php" class="flex flex-col items-center gap-1 group active:scale-95 transition-transform">
<div class="w-[48px] h-[48px] flex items-center justify-center">
<img src="<?php echo htmlspecialchars(asset_url('../img/icons/cartao.png')); ?>" alt="Cartão" class="w-full h-full object-contain drop-shadow-lg group-hover:scale-110 transition-transform" loading="lazy"/>
</div>
<span class="text-[11px] font-semibold text-on-surface-variant">Cartão</span>
</a>
<a href="../perfil.php" class="flex flex-col items-center gap-1 group active:scale-95 transition-transform">
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
<?php if (!$ehDep): ?>
<a href="perfil.php" class="flex flex-col items-center justify-center gap-3 border border-outline-variant rounded-xl p-5 hover:border-primary/30 hover:shadow-lg transition">
<div class="w-10 h-10 rounded-xl bg-primary/10 ring-1 ring-black/5 shadow-sm flex items-center justify-center text-primary"><span class="material-symbols-outlined text-[22px]" style="font-variation-settings:'FILL' 1;">account_circle</span></div>
<span class="text-xs font-bold text-on-surface uppercase">Perfil</span>
</a>
<a href="ativar.php" class="flex flex-col items-center justify-center gap-3 border border-outline-variant rounded-xl p-5 hover:border-secondary/30 hover:shadow-lg transition">
<div class="w-10 h-10 rounded-xl bg-secondary/10 ring-1 ring-black/5 shadow-sm flex items-center justify-center text-secondary"><span class="material-symbols-outlined text-[22px]" style="font-variation-settings:'FILL' 1;">credit_score</span></div>
<span class="text-xs font-bold text-on-surface uppercase">Ativar Cartão</span>
</a>
<?php endif; ?>
<a href="parceiros.php" class="flex flex-col items-center justify-center gap-3 border border-outline-variant rounded-xl p-5 hover:border-primary/30 hover:shadow-lg transition">
<div class="w-10 h-10 rounded-xl bg-primary/10 ring-1 ring-black/5 shadow-sm flex items-center justify-center text-primary"><span class="material-symbols-outlined text-[22px]" style="font-variation-settings:'FILL' 1;">storefront</span></div>
<span class="text-xs font-bold text-on-surface uppercase">Parceiros</span>
</a>
<div class="flex flex-col items-center justify-center gap-3 border border-outline-variant rounded-xl p-5 relative opacity-70 cursor-not-allowed select-none" title="Em breve">
<span class="absolute top-2 right-2 px-1.5 py-0.5 bg-secondary-container text-[#2e5000] text-[8px] font-bold rounded">EM BREVE</span>
<div class="w-10 h-10 rounded-xl bg-primary/10 ring-1 ring-black/5 shadow-sm flex items-center justify-center text-primary"><span class="material-symbols-outlined text-[22px]" style="font-variation-settings:'FILL' 1;">stethoscope</span></div>
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
