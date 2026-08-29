<?php
$paginaAtiva = 'cartao_fisico';
$tituloPagina = 'Cartão Físico';
require_once __DIR__ . '/_head.php';

$cartaoImg = $pers['cartao_fisico'] ?? '';
$check = $conn->prepare("SELECT id FROM pedidos_cartao WHERE usuario_id = ?");
$check->bind_param('i', $uid);
$check->execute();
$jaPedido = $check->get_result()->num_rows > 0;
?>
<div class="max-w-3xl">
<section class="bg-white rounded-2xl card-shadow overflow-hidden">
<div class="px-6 py-4 border-b border-outline-variant flex items-center justify-between">
<h3 class="font-extrabold text-on-surface">Cartão Físico</h3>
<span class="px-2 py-0.5 bg-secondary-container text-secondary text-[9px] font-bold rounded">OPCIONAL</span>
</div>
<div class="p-6">
<div class="premium-gradient rounded-xl p-6 shadow-lg relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between mb-8">
<div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
<?php if ($cartaoImg): ?>
<img class="absolute inset-0 w-full h-full object-contain" src="<?php echo webUrl($cartaoImg); ?>" alt="Cartão Físico Economic Card"/>
<?php endif; ?>
<div class="flex justify-between items-start z-10">
<div class="space-y-1"></div>
<span class="px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 bg-white/15 backdrop-blur-md text-white"><span class="w-1.5 h-1.5 rounded-full bg-secondary-container"></span> <?php echo $u['cartao_ativo'] ? 'ATIVO' : 'INATIVO'; ?></span>
</div>
<div class="z-10 mt-auto">
<p class="text-white text-xl font-extrabold"><?php echo htmlspecialchars(strtoupper($u['nome'])); ?></p>
</div>
</div>

<?php if ($jaPedido): ?>
<div class="mb-6 p-4 rounded-xl bg-secondary-container/30 border border-secondary/30 text-center">
<p class="font-bold text-secondary">Pedido do cartão físico realizado com sucesso!</p>
<p class="text-sm text-secondary mt-1">Você receberá seu cartão em breve.</p>
</div>
<?php endif; ?>

<div class="space-y-5">
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">NOME COMPLETO</label>
<div class="relative flex items-center bg-surface-variant/50 rounded-xl border border-outline-variant overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant/50 ml-4">person</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-3.5 px-3 text-sm" value="<?php echo htmlspecialchars($u['nome']); ?>" readonly/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">CPF</label>
<div class="relative flex items-center bg-surface-variant/50 rounded-xl border border-outline-variant overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant/50 ml-4">badge</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-3.5 px-3 text-sm" value="<?php echo htmlspecialchars($u['cpf']); ?>" readonly/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">WHATSAPP</label>
<div class="relative flex items-center bg-surface-variant/50 rounded-xl border border-outline-variant overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant/50 ml-4">chat</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-3.5 px-3 text-sm" value="<?php echo htmlspecialchars($u['whatsapp'] ?: ''); ?>" readonly/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">ENDEREÇO COMPLETO</label>
<div class="relative flex items-center bg-surface-variant/50 rounded-xl border border-outline-variant overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant/50 ml-4">home</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-3.5 px-3 text-sm" value="<?php echo htmlspecialchars($u['endereco'] ?: ''); ?>" readonly/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">CIDADE</label>
<div class="relative flex items-center bg-surface-variant/50 rounded-xl border border-outline-variant overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant/50 ml-4">apartment</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-3.5 px-3 text-sm" value="<?php echo htmlspecialchars($u['cidade'] ?: ''); ?>" readonly/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">CEP</label>
<div class="relative flex items-center bg-surface-variant/50 rounded-xl border border-outline-variant overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant/50 ml-4">location_on</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-3.5 px-3 text-sm" value="<?php echo htmlspecialchars($u['cep'] ?: ''); ?>" readonly/>
</div>
</div>
</div>
<div class="pt-2">
<p class="text-center text-sm text-on-surface-variant mb-3">A emissão e o envio do Cartão Físico possuem uma taxa de R$ 35,00.</p>
<button type="button" onclick="abrirModalPix()" <?php echo $jaPedido ? 'disabled' : ''; ?> class="w-full <?php echo $jaPedido ? 'bg-on-surface-variant/40 cursor-not-allowed' : 'bg-secondary hover:brightness-110'; ?> text-white font-bold py-4 rounded-xl shadow-lg transition uppercase flex items-center justify-center gap-2">
Pedir Cartão Físico <span class="material-symbols-outlined">contactless</span>
</button>
</div>
</div>
<p class="text-center text-sm text-on-surface-variant mt-5">O Cartão físico é opcional.</p>
</div>
</section>
</div>

<!-- Modal PIX Cartão Físico -->
<div id="pixModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/60" onclick="fecharModalPix()"></div>
<div class="relative bg-white rounded-2xl card-shadow w-full max-w-md overflow-hidden">
<div class="bg-secondary px-6 py-4 flex items-center justify-between">
<h3 class="text-white font-extrabold text-sm">Pagamento — Cartão Físico</h3>
<button type="button" onclick="fecharModalPix()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<div class="p-6 text-center">
<div id="pixLoading" class="py-10 flex flex-col items-center gap-3">
<span class="material-symbols-outlined text-4xl text-on-surface-variant/40">qr_code_2</span>
<p class="text-sm text-on-surface-variant">Gerando QR Code PIX...</p>
</div>
<div id="pixOk" class="hidden">
<p class="text-sm text-on-surface-variant">Escaneie o QR Code ou copie o código PIX abaixo</p>
<p class="font-extrabold mt-1 text-on-surface">Valor: R$ 35,00</p>
<div class="w-48 h-48 mx-auto mt-4 bg-surface-variant/50 rounded-xl p-2"><img id="pixQr" class="w-full h-full object-contain" alt="QR Code PIX"/></div>
<div class="mt-4 flex items-center gap-2 bg-surface rounded-xl border border-outline-variant p-3">
<p id="pixCode" class="text-[11px] break-all text-on-surface-variant text-left flex-1"></p>
<button type="button" onclick="copiarPix()" class="text-secondary font-bold text-xs whitespace-nowrap">COPIAR</button>
</div>
<div id="pixWait" class="mt-4">
<p class="text-sm text-on-surface-variant flex items-center justify-center gap-2"><span class="material-symbols-outlined text-lg">hourglass_empty</span> Aguardando confirmação do pagamento...</p>
</div>
<div id="pixSucesso" class="hidden mt-4 p-4 rounded-xl bg-secondary-container/30 border border-secondary/30 text-left">
<p class="font-bold text-on-secondary-container">Pagamento confirmado!</p>
<p class="text-sm text-on-secondary-container mt-1">Seu pedido de cartão físico foi enviado ao admin. Você receberá seu cartão em breve.</p>
</div>
</div>
<div id="pixFalha" class="hidden py-6 text-sm text-on-surface-variant"></div>
</div>
</div>
</div>

<script>
var pixEstado = 'fechado';
var pixTimer = null;
var pixId = 0;

function abrirModalPix() {
    var modal = document.getElementById('pixModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('pixLoading').classList.remove('hidden');
    document.getElementById('pixOk').classList.add('hidden');
    document.getElementById('pixFalha').classList.add('hidden');
    pixEstado = 'criando';
    fetch('../criar_pix_cartao.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'acao=pedir' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status !== 'pending' || !d.pix) {
                document.getElementById('pixLoading').classList.add('hidden');
                var f = document.getElementById('pixFalha');
                f.classList.remove('hidden');
                f.textContent = d.message || 'Não foi possível gerar o PIX. Tente novamente.';
                return;
            }
            pixId = d.pix.id;
            document.getElementById('pixQr').src = 'data:image/png;base64,' + d.pix.qr_code_base64;
            document.getElementById('pixCode').textContent = d.pix.qr_code_copia_cola;
            document.getElementById('pixLoading').classList.add('hidden');
            document.getElementById('pixOk').classList.remove('hidden');
            pixEstado = 'esperando';
            iniciarPolling();
        })
        .catch(function() {
            document.getElementById('pixLoading').classList.add('hidden');
            var f = document.getElementById('pixFalha');
            f.classList.remove('hidden');
            f.textContent = 'Erro ao gerar o PIX. Tente novamente.';
        });
}

function iniciarPolling() {
    if (pixTimer) { clearInterval(pixTimer); pixTimer = null; }
    pixTimer = setInterval(function() {
        if (pixEstado !== 'esperando') { return; }
        fetch('../verifica_pagamento_cartao.php?id=' + pixId)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.status === 'approved') {
                    pixEstado = 'aprovado';
                    clearInterval(pixTimer);
                    pixTimer = null;
                    document.getElementById('pixWait').classList.add('hidden');
                    document.getElementById('pixSucesso').classList.remove('hidden');
                    setTimeout(function() { fecharModalPix(); location.reload(); }, 2500);
                } else if (d.status === 'cancelled' || d.status === 'refunded') {
                    pixEstado = 'falhou';
                    clearInterval(pixTimer);
                    pixTimer = null;
                    var f = document.getElementById('pixFalha');
                    f.classList.remove('hidden');
                    document.getElementById('pixWait').classList.add('hidden');
                    f.textContent = 'O pagamento foi cancelado ou expirou. Feche e gere um novo PIX.';
                }
            });
    }, 3000);
}

function copiarPix() {
    var texto = document.getElementById('pixCode').textContent;
    var btn = document.getElementById('pixOk').querySelector('button');
    function ok() { if (btn) { btn.textContent = 'COPIADO!'; setTimeout(function(){ btn.textContent = 'COPIAR'; }, 1500); } }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(ok)['catch'](function() { ok(); });
    } else {
        var ta = document.createElement('textarea');
        ta.value = texto;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        ok();
    }
}

function fecharModalPix() {
    if (pixTimer) { clearInterval(pixTimer); pixTimer = null; }
    pixEstado = 'fechado';
    var modal = document.getElementById('pixModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>
<?php require_once __DIR__ . '/_foot.php'; ?>
