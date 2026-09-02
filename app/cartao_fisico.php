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

$pers = $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc();
$cartaoImg = $pers['cartao_fisico'] ?? '';
$check = $conn->prepare("SELECT id FROM pedidos_cartao WHERE usuario_id = ?");
$check->bind_param('i', $uid);
$check->execute();
$jaPedido = $check->get_result()->num_rows > 0;
?>
<!DOCTYPE html><html class="light" lang="pt-BR"><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Cartão Físico - Economic Card</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;display=swap" rel="stylesheet">
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
                    "fontFamily": { "headline-sm": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "body-lg": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "display-lg": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "body-md": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "headline-md": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "label-caps": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "label-bold": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"] },
                    "fontSize": {
                        "headline-sm": ["18px", { "lineHeight": "1.3", "letterSpacing": "-0.01em", "fontWeight": "600" }], "headline-sm-mobile": ["17px", { "lineHeight": "1.3", "letterSpacing": "-0.01em", "fontWeight": "600" }], "body-lg": ["17px", { "lineHeight": "1.5", "fontWeight": "400" }], "display-lg": ["30px", { "lineHeight": "1.15", "letterSpacing": "-0.03em", "fontWeight": "700" }], "display-lg-mobile": ["26px", { "lineHeight": "1.15", "letterSpacing": "-0.03em", "fontWeight": "700" }], "body-md": ["15px", { "lineHeight": "1.45", "fontWeight": "400" }], "headline-md": ["22px", { "lineHeight": "1.25", "letterSpacing": "-0.02em", "fontWeight": "700" }], "label-caps": ["11px", { "lineHeight": "1", "letterSpacing": "0.06em", "fontWeight": "600" }], "label-bold": ["13px", { "lineHeight": "1", "letterSpacing": "0.01em", "fontWeight": "700" }]
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
<img class="absolute inset-0 w-full h-full object-contain" src="<?php echo htmlspecialchars(asset_url($cartaoImg)); ?>" alt="Cartão Físico Economic Card"/>
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
<?php if ($jaPedido): ?>
<div class="mb-md p-md rounded-xl bg-secondary-container/30 border border-secondary/30 text-center">
<p class="font-headline-sm text-on-secondary-container">Pedido do cartão físico realizado com sucesso!</p>
<p class="font-body-md text-on-secondary-container mt-1">Você receberá seu cartão em breve.</p>
</div>
<?php endif; ?>
<div class="space-y-card-gutter">
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
<p class="text-center font-body-md text-on-surface-variant mb-sm">A emissão e o envio do Cartão Físico possuem uma taxa de R$ 35,00.</p>
<button type="button" onclick="abrirModalPix()" <?php echo $jaPedido ? 'disabled' : ''; ?> class="w-full <?php echo $jaPedido ? 'bg-on-surface-variant/40 cursor-not-allowed opacity-60' : 'bg-secondary'; ?> text-white font-headline-sm py-4 rounded-full shadow-[0_8px_30px_rgba(62,106,0,0.15)] active:scale-95 transition-all duration-200 uppercase flex items-center justify-center gap-2">
                    Pedir Cartão Físico
                    <span class="material-symbols-outlined">contactless</span>
</button>
</div>
</div>
<div class="text-center mt-md">
<p class="font-body-md text-on-surface-variant">O Cartão físico é opcional.</p>
</div>
</main>
<footer class="w-full px-container-padding py-8 flex flex-col items-center gap-4 bg-surface-container-low mt-auto">
<p class="text-outline text-[10px] font-label-caps opacity-60">
            © 2024 ECONOMIC CARD. TODOS OS DIREITOS RESERVADOS.
        </p>
</footer>

<!-- Modal PIX Cartão Físico -->
<div id="pixModal" class="fixed inset-0 z-[60] hidden items-center justify-center p-md">
<div class="absolute inset-0 bg-black/60" onclick="fecharModalPix()"></div>
<div class="relative bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
<div class="bg-secondary px-md py-lg flex items-center justify-between">
<h3 class="text-white font-headline-sm text-[16px]">Pagamento — Cartão Físico</h3>
<button type="button" onclick="fecharModalPix()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<div class="p-md text-center">
<div id="pixLoading" class="py-10 flex flex-col items-center gap-3">
<span class="material-symbols-outlined text-4xl text-on-surface-variant/40">qr_code_2</span>
<p class="font-body-md text-on-surface-variant">Gerando QR Code PIX...</p>
</div>
<div id="pixOk" class="hidden">
<p class="font-body-md text-on-surface-variant">Escaneie o QR Code ou copie o código PIX abaixo</p>
<p class="font-headline-sm font-bold mt-1 text-on-surface">Valor: R$ 35,00</p>
<div class="w-48 h-48 mx-auto mt-md bg-surface-variant/50 rounded-xl p-2"><img id="pixQr" class="w-full h-full object-contain" alt="QR Code PIX"/></div>
<div class="mt-md flex items-center gap-2 bg-surface-container-low rounded-xl border border-outline-variant p-3">
<p id="pixCode" class="text-[11px] break-all text-on-surface-variant text-left flex-1"></p>
<button type="button" onclick="copiarPix()" class="text-secondary font-label-bold text-[12px] whitespace-nowrap">COPIAR</button>
</div>
<div id="pixWait" class="mt-md">
<p class="font-body-md text-on-surface-variant flex items-center justify-center gap-2"><span class="material-symbols-outlined text-lg">hourglass_empty</span> Aguardando confirmação do pagamento...</p>
</div>
<div id="pixSucesso" class="hidden mt-md p-md rounded-xl bg-secondary-container/30 border border-secondary/30 text-left">
<p class="font-headline-sm text-on-secondary-container">Pagamento confirmado!</p>
<p class="font-body-md text-on-secondary-container mt-1">Seu pedido de cartão físico foi enviado ao admin. Você receberá seu cartão em breve.</p>
</div>
</div>
<div id="pixFalha" class="hidden py-6 font-body-md text-on-surface-variant"></div>
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
    fetch('criar_pix_cartao.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'acao=pedir' })
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
        fetch('verifica_pagamento_cartao.php?id=' + pixId)
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
</body></html>