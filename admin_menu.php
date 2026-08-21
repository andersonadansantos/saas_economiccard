<?php
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}
$abaAtual = basename($_SERVER['PHP_SELF']);
?>
<aside class="fixed inset-y-0 left-0 w-60 bg-[#51036d] text-white flex flex-col z-40 hidden md:flex">
<div class="px-5 py-5 flex items-center gap-3">
<div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center">
<span class="material-symbols-outlined">shield_person</span>
</div>
<div>
<h1 class="font-extrabold leading-tight">Economic Card</h1>
<p class="text-[11px] text-white/60">Painel Administrativo</p>
</div>
</div>
<nav class="flex-1 px-3 space-y-1 mt-4">
<a href="admin.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $abaAtual === 'admin.php' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">dashboard</span> Dashboard
</a>
<a href="admin_pedidos.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $abaAtual === 'admin_pedidos.php' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">credit_card</span> Pedidos/Cartão
</a>
<a href="admin_parceiros.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $abaAtual === 'admin_parceiros.php' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">storefront</span> Parceiros
</a>
<a href="admin_banners.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $abaAtual === 'admin_banners.php' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">campaign</span> Banners Anúncios
</a>
<a href="admin_mensagens.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $abaAtual === 'admin_mensagens.php' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">mail</span> Mensagens
</a>
<a href="admin_template_email.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $abaAtual === 'admin_template_email.php' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">email</span> Template de email
</a>
<a href="admin_personalizacao.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $abaAtual === 'admin_personalizacao.php' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">palette</span> Personalização
</a>
<a href="admin_planos.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $abaAtual === 'admin_planos.php' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">credit_score</span> Planos
</a>
<a href="admin_api_pagamento.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $abaAtual === 'admin_api_pagamento.php' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">payments</span> API Pagamento
</a>
<a href="admin_api_whatsapp.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $abaAtual === 'admin_api_whatsapp.php' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">chat</span> API WhatsApp
</a>
<a href="admin_afiliados.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $abaAtual === 'admin_afiliados.php' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">handshake</span> Afiliados
</a>
<a href="admin_configuracao.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition <?php echo $abaAtual === 'admin_configuracao.php' ? 'bg-white/15' : 'hover:bg-white/10'; ?>">
<span class="material-symbols-outlined">settings</span> Configuração
</a>
</nav>
<div class="p-3">
<a href="logout.php?admin=1" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold text-white/80 hover:bg-white/10 transition">
<span class="material-symbols-outlined">logout</span> Sair
</a>
</div>
</aside>
