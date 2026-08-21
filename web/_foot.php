</main>
<!-- Notification dropdown -->
<div class="hidden fixed top-16 right-4 z-[60] w-80 bg-white rounded-2xl shadow-2xl border border-outline-variant overflow-hidden" id="notifPanel">
<div class="px-4 py-3 border-b border-outline-variant flex items-center justify-between">
<span class="font-bold text-sm text-on-surface uppercase tracking-wide">MENSAGENS</span>
<?php if ($naoLidas > 0): ?><span class="px-2 py-0.5 rounded-full bg-error text-on-error text-[10px] font-bold"><?php echo $naoLidas; ?></span><?php endif; ?>
</div>
<div class="max-h-80 overflow-y-auto">
<?php if ($mensagens->num_rows === 0): ?>
<div class="px-4 py-8 text-center"><p class="text-sm text-on-surface-variant">Nenhuma mensagem no momento.</p></div>
<?php endif; ?>
<?php while ($m = $mensagens->fetch_assoc()): ?>
<button type="button" class="w-full text-left px-4 py-3 border-b border-outline-variant/20 hover:bg-surface-variant/50 transition-colors" onclick="abrirMensagem('<?php echo addslashes(htmlspecialchars($m['titulo'])); ?>', '<?php echo addslashes(htmlspecialchars($m['mensagem'])); ?>', '<?php echo date('d/m/Y H:i', strtotime($m['criado_em'])); ?>')">
<p class="font-bold text-sm text-on-surface truncate"><?php echo htmlspecialchars($m['titulo']); ?></p>
<p class="text-[13px] text-on-surface-variant truncate"><?php echo htmlspecialchars($m['mensagem']); ?></p>
<p class="text-[10px] text-on-surface-variant/70 mt-0.5"><?php echo date('d/m/Y H:i', strtotime($m['criado_em'])); ?></p>
</button>
<?php endwhile; ?>
</div>
</div>
<!-- Mobile drawer -->
<div class="hidden fixed inset-0 z-[70] lg:hidden" id="drawer">
<div class="absolute inset-0 bg-black/40" onclick="toggleDrawer()"></div>
<div class="absolute inset-y-0 left-0 w-72 premium-gradient text-white flex flex-col">
<div class="px-6 py-6 flex items-center justify-between border-b border-white/10">
<div class="flex items-center gap-3 min-w-0">
<?php if ($logoApp): ?>
<img class="w-10 h-10 rounded-xl object-contain bg-white/10 p-1" src="<?php echo webUrl($logoApp); ?>" alt="Logo"/>
<?php else: ?>
<span class="material-symbols-outlined">credit_card</span>
<?php endif; ?>
<div class="min-w-0">
<h2 class="font-bold leading-tight truncate">Economic Card</h2>
<p class="text-[11px] text-white/60">Painel do Membro</p>
</div>
</div>
<button class="material-symbols-outlined p-1 rounded-lg hover:bg-white/10" onclick="toggleDrawer()">close</button>
</div>
<nav class="flex-1 px-4 py-6 space-y-1">
<a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold <?php echo $paginaAtiva === 'dashboard' ? 'bg-white/15' : 'hover:bg-white/10'; ?>"><span class="material-symbols-outlined">home</span> Início</a>
<a href="parceiros.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold <?php echo $paginaAtiva === 'parceiros' ? 'bg-white/15' : 'hover:bg-white/10'; ?>"><span class="material-symbols-outlined">storefront</span> Parceiros</a>
<a href="ativar.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold <?php echo $paginaAtiva === 'ativar' ? 'bg-white/15' : 'hover:bg-white/10'; ?>"><span class="material-symbols-outlined">credit_score</span> Ativar Cartão</a>
<a href="cartao_fisico.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold <?php echo $paginaAtiva === 'cartao_fisico' ? 'bg-white/15' : 'hover:bg-white/10'; ?>"><span class="material-symbols-outlined">contactless</span> Cartão Físico</a>
<a href="perfil.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold <?php echo $paginaAtiva === 'perfil' ? 'bg-white/15' : 'hover:bg-white/10'; ?>"><span class="material-symbols-outlined">person</span> Perfil</a>
<?php if (!empty($atendimento['whatsapp']) || !empty($atendimento['email'])): ?>
<button type="button" onclick="toggleDrawer(); abrirAtendimento();" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold hover:bg-white/10 transition">
<span class="material-symbols-outlined text-[20px]">support_agent</span> Central de atendimento
</button>
<?php endif; ?>
</nav>
<div class="p-4 border-t border-white/10">
<a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold text-white/80 hover:bg-white/10 transition"><span class="material-symbols-outlined text-[20px]">logout</span> Sair</a>
</div>
</div>
</div>
<!-- Message modal -->
<div class="hidden fixed inset-0 z-[90] bg-black/60 backdrop-blur-sm items-center justify-center p-6" id="mensagemModal">
<div class="w-full max-w-md bg-white rounded-2xl shadow-2xl border border-outline-variant overflow-hidden">
<div class="px-5 py-4 border-b border-outline-variant flex items-center justify-between gap-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-primary text-[20px] shrink-0" style="font-variation-settings: 'FILL' 1;">notifications</span>
<span class="font-bold text-sm text-on-surface uppercase truncate" id="msgModalTitulo">MENSAGEM</span>
</div>
<button class="material-symbols-outlined text-on-surface-variant hover:bg-surface-variant/50 p-1.5 rounded-full shrink-0" onclick="fecharMensagem()">close</button>
</div>
<div class="px-5 py-5 max-h-[60vh] overflow-y-auto"><p class="text-sm text-on-surface-variant whitespace-pre-line" id="msgModalTexto"></p></div>
<div class="px-5 py-4 border-t border-outline-variant flex items-center justify-between">
<span class="text-[11px] text-on-surface-variant/70" id="msgModalData"></span>
<button class="text-sm font-bold text-primary hover:opacity-70 transition" onclick="fecharMensagem()">Fechar</button>
</div>
</div>
</div>
<!-- Atendimento modal -->
<div class="hidden fixed inset-0 z-[95] bg-black/60 backdrop-blur-sm items-center justify-center p-6" id="atendimentoModal">
<div class="w-full max-w-md bg-white rounded-2xl shadow-2xl border border-outline-variant overflow-hidden">
<div class="px-5 py-4 border-b border-outline-variant flex items-center justify-between gap-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-primary text-[20px] shrink-0" style="font-variation-settings: 'FILL' 1;">support_agent</span>
<span class="font-bold text-sm text-on-surface uppercase truncate">Central de atendimento</span>
</div>
<button class="material-symbols-outlined text-on-surface-variant hover:bg-surface-variant/50 p-1.5 rounded-full shrink-0" onclick="fecharAtendimento()">close</button>
</div>
<div class="px-5 py-6 flex flex-col gap-3">
<?php if (!empty($atendimento['whatsapp'])): ?>
<a href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/\D/', '', $atendimento['whatsapp'])); ?>" target="_blank" class="flex items-center justify-center gap-3 px-4 py-4 rounded-xl bg-[#25D366]/10 border border-[#25D366]/30 text-[#128C7E] font-bold text-sm transition hover:bg-[#25D366]/20">
<span class="material-symbols-outlined text-[22px]">chat</span> Falar pelo WhatsApp
</a>
<?php endif; ?>
<?php if (!empty($atendimento['email'])): ?>
<a href="mailto:<?php echo htmlspecialchars($atendimento['email']); ?>" class="flex items-center justify-center gap-3 px-4 py-4 rounded-xl bg-[#51036d]/10 border border-[#51036d]/30 text-[#51036d] font-bold text-sm transition hover:bg-[#51036d]/20">
<span class="material-symbols-outlined text-[22px]">mail</span> Enviar e-mail
</a>
<?php endif; ?>
</div>
<div class="px-5 py-4 border-t border-outline-variant flex items-center justify-end">
<button class="text-sm font-bold text-primary hover:opacity-70 transition" onclick="fecharAtendimento()">Fechar</button>
</div>
</div>
</div>
<script>
function toggleDrawer() { document.getElementById('drawer').classList.toggle('hidden'); }
function toggleNotif() { document.getElementById('notifPanel').classList.toggle('hidden'); }
function abrirAtendimento() {
    const el = document.getElementById('atendimentoModal');
    el.classList.remove('hidden'); el.classList.add('flex');
}
function fecharAtendimento() {
    const el = document.getElementById('atendimentoModal');
    el.classList.add('hidden'); el.classList.remove('flex');
}
document.getElementById('atendimentoModal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('atendimentoModal')) fecharAtendimento();
});
document.addEventListener('click', (e) => {
    if (!document.getElementById('notifPanel').contains(e.target) && !e.target.closest('button[onclick="toggleNotif()"]')) {
        document.getElementById('notifPanel').classList.add('hidden');
    }
});
function abrirMensagem(titulo, texto, data) {
    document.getElementById('msgModalTitulo').textContent = titulo;
    document.getElementById('msgModalTexto').textContent = texto;
    document.getElementById('msgModalData').textContent = data;
    const el = document.getElementById('mensagemModal');
    el.classList.remove('hidden'); el.classList.add('flex');
    document.getElementById('notifPanel').classList.add('hidden');
}
function fecharMensagem() {
    const el = document.getElementById('mensagemModal');
    el.classList.add('hidden'); el.classList.remove('flex');
}
</script>
</body>
</html>
