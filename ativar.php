<?php
require_once 'config.php';
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
$bloqueado = isset($_GET['bloqueado']);
$uid = (int)$_SESSION['usuario_id'];
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if (!$u) { header('Location: logout.php'); exit; }

$final = $u['final_cartao'] ?: '8829';
$codigo = str_pad($u['id'], 6, '0', STR_PAD_LEFT);

$pers = $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc();
$cartaoAtivado = $pers['cartao_ativado'] ?? '';
$cartaoDesativado = $pers['cartao_desativado'] ?? '';
$cartaoImg = $u['cartao_ativo'] ? $cartaoAtivado : $cartaoDesativado;

$planos = [];
$pr = $conn->query("SELECT * FROM planos WHERE ativo = 1 ORDER BY ordem, id");
while ($p = $pr->fetch_assoc()) { $planos[] = $p; }
$publicKey = '';
$tr = $conn->query("SELECT public_key, parceiro_public_key, parceiro_access_token FROM api_pagamento WHERE id = 1");
if ($tr && ($row = $tr->fetch_assoc())) {
    $publicKey = $row['public_key'] ?? '';
    // Com split ativo, o token do cartão precisa ser gerado com a Public Key do parceiro (coletor)
    if (!empty($row['parceiro_access_token']) && !empty($row['parceiro_public_key'])) {
        $publicKey = $row['parceiro_public_key'];
    }
}
$pix = null;
if (!$u['cartao_ativo']) {
    $stmt = $conn->prepare("SELECT * FROM pagamentos_pix WHERE usuario_id = ? AND status = 'pending' AND pix_validade > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $pix = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html><html class="light" lang="pt-BR"><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title><?php echo $u['cartao_ativo'] ? 'Cartão Ativado' : 'Ativar Cartão'; ?> - Economic Card</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@600;700&amp;family=Manrope:wght@400;600;700;800&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "primary-fixed-dim": "#edb1ff", "on-secondary-fixed": "#0f2000", "error-container": "#ffdad6", "on-secondary-container": "#427000", "on-tertiary": "#ffffff", "primary": "#51036d", "on-tertiary-fixed": "#2e1600", "outline-variant": "#d1c2d1", "surface-container-high": "#e7e8e9", "secondary": "#3e6a00", "on-primary-fixed": "#320046", "background": "#f8f9fa", "on-primary-fixed-variant": "#6b2686", "tertiary-fixed-dim": "#ffb877", "surface-dim": "#d9dadb", "on-primary-container": "#e397fd", "on-tertiary-fixed-variant": "#6c3b00", "secondary-fixed": "#b6f570", "secondary-container": "#b6f570", "inverse-surface": "#2e3132", "surface-tint": "#8540a0", "outline": "#7f7381", "on-surface-variant": "#4e434f", "surface-container-lowest": "#ffffff", "secondary-fixed-dim": "#9cd857", "surface-container-low": "#f3f4f5", "on-surface": "#191c1d", "surface-container-highest": "#e1e3e4", "surface-container": "#edeeef", "on-primary": "#ffffff", "on-error-container": "#93000a", "inverse-primary": "#edb1ff", "surface": "#f8f9fa", "on-secondary-fixed-variant": "#2e5000", "tertiary-fixed": "#ffdcc0", "surface-bright": "#f8f9fa", "inverse-on-surface": "#f0f1f2", "error": "#ba1a1a", "primary-fixed": "#f9d8ff", "tertiary": "#4a2700", "on-error": "#ffffff", "surface-variant": "#e1e3e4", "on-secondary": "#ffffff", "primary-container": "#6a2585", "tertiary-container": "#6a3a00", "on-tertiary-container": "#ff9d35", "on-background": "#191c1d"
                    },
                    "borderRadius": { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
                    "spacing": { "xs": "4px", "card-gutter": "16px", "container-padding": "20px", "xl": "48px", "md": "24px", "sm": "12px", "lg": "32px", "base": "8px" },
                    "fontFamily": { "label-caps": ["Hanken Grotesk"], "label-bold": ["Hanken Grotesk"], "body-md": ["Manrope"], "body-lg": ["Manrope"], "headline-sm": ["Manrope"], "headline-md": ["Manrope"], "display-lg": ["Manrope"] },
                    "fontSize": {
                        "label-caps": ["10px", { "lineHeight": "1", "fontWeight": "600" }], "label-bold": ["12px", { "lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "700" }], "body-md": ["14px", { "lineHeight": "1.5", "fontWeight": "400" }], "body-lg": ["16px", { "lineHeight": "1.6", "fontWeight": "400" }], "headline-sm": ["20px", { "lineHeight": "1.4", "fontWeight": "600" }], "headline-md": ["24px", { "lineHeight": "1.3", "fontWeight": "700" }], "display-lg": ["32px", { "lineHeight": "1.2", "fontWeight": "800" }]
                    }
                },
            },
        }
    </script>
<style>
        body { background-color: #f8f9fa; font-family: 'Manrope', sans-serif; color: #191c1d; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .glass-effect { backdrop-filter: blur(16px); background: rgba(255, 255, 255, 0.7); }
        .digital-card-inactive { background: linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%); filter: grayscale(0.4); }
        .premium-gradient { background: linear-gradient(135deg, #51036d 0%, #6a2585 100%); }
        .active-pill { background: rgba(182, 245, 112, 0.2); color: #3e6a00; border: 1px solid rgba(62, 106, 0, 0.3); }
        .inactive-pill { background: rgba(127, 115, 129, 0.2); color: #4e434f; border: 1px solid rgba(127, 115, 129, 0.4); }
        .pix-shadow { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); }
    </style>
</head>
<body class="min-h-screen flex flex-col pb-24">
<!-- TopAppBar -->
<header class="fixed top-0 w-full z-50 bg-surface/80 dark:bg-surface-dim/80 backdrop-blur-xl shadow-sm flex items-center justify-between px-container-padding h-16">
<a class="text-primary hover:bg-surface-container-high/50 transition-colors active:scale-95 duration-200 rounded-full p-2" href="dashboard.php">
<span class="material-symbols-outlined">arrow_back</span>
</a>
<h1 class="font-headline-md text-headline-md text-primary"><?php echo $u['cartao_ativo'] ? 'Cartão Ativado' : 'Ativar Cartão'; ?></h1>
<div class="flex items-center gap-1">
<a class="text-primary hover:bg-surface-container-high/50 transition-colors active:scale-95 duration-200 rounded-full p-2" href="logout.php" title="Sair">
<span class="material-symbols-outlined">logout</span>
</a>
<a class="text-primary hover:bg-surface-container-high/50 transition-colors active:scale-95 duration-200 rounded-full p-2" href="dashboard.php">
<span class="material-symbols-outlined">notifications</span>
</a>
</div>
</header>
<main class="mt-16 flex-grow flex flex-col px-container-padding pt-md">
<!-- Digital Card Section -->
<section class="w-full max-w-md mx-auto mb-lg">
<div class="mt-[50px] relative rounded-xl shadow-[0_8px_30px_rgba(106,37,133,0.15)] overflow-hidden aspect-[1.58/1]">
<?php if ($cartaoImg): ?>
<img class="absolute inset-0 w-full h-full object-contain" src="<?php echo htmlspecialchars($cartaoImg); ?>" alt="Cartão Economic Card"/>
<div class="absolute inset-0 flex flex-col items-start justify-center gap-1.5 p-6 translate-y-[50px]">
<p class="font-display-lg text-white text-[16px] truncate max-w-full text-left" style="text-shadow: 0 1px 4px rgba(0,0,0,0.6);"><?php echo htmlspecialchars($u['nome']); ?></p>
<?php if ($u['cartao_ativo']): ?>
<div class="bg-secondary text-on-secondary px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-white/80"></span>
                    ATIVADO
                </div>
<?php else: ?>
<div class="bg-error text-on-error px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-white/80"></span>
                    EXPIRADO
                </div>
<?php endif; ?>
</div>
<?php else: ?>
<div class="premium-gradient rounded-xl p-6 shadow-[0_8px_30px_rgba(106,37,133,0.15)] relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between">
<div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
<div class="absolute bottom-0 left-0 w-full h-1/2 bg-gradient-to-t from-black/20 to-transparent"></div>
<div class="flex justify-between items-start z-10">
<div class="space-y-1">
<span class="font-label-caps text-on-primary/60 tracking-widest">MEMBRO PLATINUM</span>
<div class="h-[2px] w-8 bg-secondary-container"></div>
</div>
<?php if ($u['cartao_ativo']): ?>
<div class="active-pill px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
                    ATIVO
                </div>
<?php else: ?>
<div class="inactive-pill px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-outline"></span>
                    INATIVO
                </div>
<?php endif; ?>
</div>
<div class="flex flex-col items-start gap-1.5 z-10 mt-[50px]">
<p class="font-display-lg text-white text-[16px] truncate max-w-full text-left"><?php echo htmlspecialchars($u['nome']); ?></p>
<?php if ($u['cartao_ativo']): ?>
<div class="bg-secondary text-on-secondary px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-white/80"></span>
                    ATIVADO
                </div>
<?php else: ?>
<div class="bg-error text-on-error px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 backdrop-blur-md">
<span class="w-1.5 h-1.5 rounded-full bg-white/80"></span>
                    EXPIRADO
                </div>
<?php endif; ?>
</div>
<div class="z-10 mt-auto">
<p class="font-display-lg text-white text-[18px] mb-4"><?php echo htmlspecialchars(strtoupper($u['nome'])); ?></p>
<div class="flex justify-between items-end">
<div class="space-y-1">
<p class="font-label-bold text-white/70 tracking-widest">•••• •••• •••• <?php echo htmlspecialchars($final); ?></p>
<p class="font-label-caps text-white/50">VALIDADE 08/25</p>
</div>
<div class="flex flex-col items-end">
<span class="font-display-lg text-white/20 text-[28px] font-extrabold italic">Economic</span>
</div>
</div>
</div>
</div>
<?php endif; ?>
</div>
<div class="mt-md text-center">
<?php if ($u['cartao_ativo']): ?>
<h3 class="font-headline-md text-headline-md text-on-surface uppercase tracking-tight flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-secondary" style="font-variation-settings:'FILL' 1;">check_circle</span>
                    Cartão já ativado
                </h3>
<p class="font-body-md text-on-surface-variant mt-1">Você já está liberado para usar seus descontos.</p>
<?php else: ?>
<h3 class="font-headline-md text-headline-md text-on-surface uppercase tracking-tight">Você precisa ativar seu cartão</h3>
<?php endif; ?>
</div>
</section>
<?php if (!$u['cartao_ativo']): ?>
<!-- Payment Methods Section -->
<section class="flex flex-col items-center gap-md mb-xl">
<div class="w-full max-w-sm bg-surface-container-lowest rounded-xl p-md pix-shadow border border-outline-variant/30">
<!-- Planos Section -->
<div class="mb-md">
<p class="font-label-bold text-label-bold text-outline uppercase mb-2 text-center">1. Escolha seu plano</p>
<div class="flex flex-col gap-2">
<?php foreach ($planos as $i => $pl): $plValor = number_format((float)$pl['valor'], 2, ',', '.'); ?>
<div class="flex items-center gap-3 border border-outline-variant/60 rounded-xl px-4 py-3 cursor-pointer transition-all plan-card <?php echo ($i === 0) ? 'border-primary bg-primary/5' : ''; ?>" data-id="<?php echo (int)$pl['id']; ?>" data-valor="<?php echo htmlspecialchars($plValor); ?>" data-dias="<?php echo (int)$pl['dias']; ?>" data-nome="<?php echo htmlspecialchars($pl['nome']); ?>" onclick="selecionarPlano(this)">
<div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 plan-radio <?php echo ($i === 0) ? 'border-primary' : 'border-outline'; ?>">
<?php if ($i === 0): ?><span class="w-2.5 h-2.5 rounded-full bg-primary"></span><?php endif; ?>
</div>
<div class="flex-1">
<p class="font-body-md text-[14px] font-bold text-on-surface"><?php echo htmlspecialchars($pl['nome']); ?></p>
<p class="text-[11px] text-on-surface-variant"><?php echo htmlspecialchars($pl['descricao'] ?: $pl['dias'] . ' dias de desconto'); ?></p>
</div>
<div class="text-right">
<p class="font-display-lg text-[16px] font-extrabold text-primary">R$ <?php echo $plValor; ?></p>
<p class="text-[10px] text-on-surface-variant font-bold uppercase"><?php echo (int)$pl['dias']; ?> dias</p>
</div>
</div>
<?php endforeach; ?>
</div>
</div>

<div class="mb-md">
<p class="font-label-bold text-label-bold text-outline uppercase mb-2 text-center">2. Forma de pagamento</p>
<div class="flex bg-surface-container rounded-xl p-1 mb-md">
<button type="button" class="flex-1 py-2.5 rounded-lg font-label-bold text-label-bold flex items-center justify-center gap-1.5 transition-all" id="tabPixBtn" onclick="mostrarAba('pix')">
<span class="material-symbols-outlined text-[18px]">qr_code_2</span> PIX
</button>
<button type="button" class="flex-1 py-2.5 rounded-lg font-label-bold text-label-bold text-on-surface-variant flex items-center justify-center gap-1.5 transition-all" id="tabCardBtn" onclick="mostrarAba('card')">
<span class="material-symbols-outlined text-[18px]">credit_card</span> Cartão de crédito
</button>
</div>
</div>

<!-- PIX Panel -->
<div id="painelPix">
<img class="w-32 h-12 object-contain mb-md mx-auto" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAT-tQ9XWL3_Pfxc9QMZ9W3An6rHLy83qfPjEyHfiwJuTXGHQ-sFBk6Xxa9go9W3YTlN2NQQVcTLVAIFQmHLBrCusUqbMWihhDAAtkPl3vwp6JXtKyAXFxcd6qNX16tGYeuy3JkWUniaakOVsE1QudDk1p-gocxFfLVk8CKHUU8zBvM79CfF9OBCVROQYB06u7UBACiFfgmptO8oQ8WZT5ogcNtfMGQMQRuP7MvEzeBBlRD69Ngfs-0xg" alt="Logo PIX"/>
<div class="w-48 h-48 bg-surface-container rounded-lg flex items-center justify-center mb-md border-2 border-dashed border-outline-variant mx-auto" id="qrBox">
<?php if ($pix && $pix['qr_code_base64']): ?>
<img id="qrImg" class="w-full h-full object-contain" src="data:image/png;base64,<?php echo $pix['qr_code_base64']; ?>" alt="QR Code PIX"/>
<?php else: ?>
<span class="material-symbols-outlined text-[64px] text-outline opacity-40" id="qrIcon">qr_code_2</span>
<?php endif; ?>
</div>
<p class="font-body-md text-on-surface-variant mb-lg max-w-[280px] mx-auto text-center">
                    Escaneie o QR Code ou use o código PIX abaixo para ativar seu cartão. Valor: <b id="pixValorText"><?php echo isset($planos[0]) ? 'R$ ' . number_format((float)$planos[0]['valor'], 2, ',', '.') : ''; ?></b>. A ativação é automática após a confirmação do pagamento.
                </p>
<?php if ($pix && $pix['qr_code_copia_cola']): ?>
<div class="w-full mt-md" id="pixCodeBox">
<p class="font-label-bold text-label-bold text-outline uppercase mb-1 text-left">Código PIX (copia e cola)</p>
<div class="w-full bg-surface-container-low rounded-lg p-3 flex items-center gap-2">
<p class="font-body-md text-[11px] text-on-surface-variant flex-1 break-all text-left" id="pixCodeText"><?php echo htmlspecialchars($pix['qr_code_copia_cola']); ?></p>
<button class="text-primary font-label-bold text-[12px]" onclick="copiarPix()">COPIAR</button>
</div>
</div>
<div class="w-full mt-md" id="pixWaitBox">
<p class="font-body-md text-on-surface-variant text-center mb-md">Aguardando pagamento...</p>
</div>
<?php else: ?>
<button type="button" id="btnGerarPix" class="w-full bg-secondary hover:bg-on-secondary-fixed-variant text-on-secondary font-headline-sm py-3 rounded-full shadow-lg active:scale-95 transition-all duration-200 flex items-center justify-center gap-2" onclick="gerarPix()">
<span class="material-symbols-outlined text-[20px]">qr_code_2</span> GERAR QR CODE PIX
</button>
<div class="w-full mt-md hidden" id="pixWaitBox">
<p class="font-body-md text-on-surface-variant text-center mb-md">Aguardando pagamento...</p>
</div>
<?php endif; ?>
</div>

<!-- Card Panel -->
<div id="painelCard" class="hidden">
<p class="font-body-md text-on-surface-variant mb-md text-center">Pague com <b>cartão de crédito</b> para ativar seu cartão por <b id="cardDiasText"><?php echo isset($planos[0]) ? (int)$planos[0]['dias'] : '60'; ?></b> dias. Valor: <b id="cardValorText"><?php echo isset($planos[0]) ? 'R$ ' . number_format((float)$planos[0]['valor'], 2, ',', '.') : ''; ?></b>.</p>
<?php if ($publicKey): ?>
<form id="cardForm" class="space-y-3">
<div>
<label class="font-label-bold text-label-bold text-outline uppercase mb-1 block" for="cardholderName">Nome no cartão</label>
<input id="cardholderName" name="cardholderName" type="text" autocomplete="off" class="w-full bg-surface-container-low rounded-lg px-3.5 py-2.5 text-[14px] text-on-surface placeholder:text-outline/60 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="Nome impresso no cartão" required/>
</div>
<div>
<label class="font-label-bold text-label-bold text-outline uppercase mb-1 block" for="cardNumber">Número do cartão</label>
<input id="cardNumber" name="cardNumber" type="text" autocomplete="off" inputmode="numeric" class="w-full bg-surface-container-low rounded-lg px-3.5 py-2.5 text-[14px] text-on-surface placeholder:text-outline/60 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="0000 0000 0000 0000" required/>
</div>
<div class="grid grid-cols-2 gap-3">
<div>
<label class="font-label-bold text-label-bold text-outline uppercase mb-1 block" for="cardExpirationDate">Vencimento</label>
<input id="cardExpirationDate" name="cardExpirationDate" type="text" autocomplete="off" inputmode="numeric" class="w-full bg-surface-container-low rounded-lg px-3.5 py-2.5 text-[14px] text-on-surface placeholder:text-outline/60 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="MM/AA" required/>
</div>
<div>
<label class="font-label-bold text-label-bold text-outline uppercase mb-1 block" for="cardSecurityCode">Código de segurança</label>
<input id="cardSecurityCode" name="cardSecurityCode" type="text" autocomplete="off" inputmode="numeric" class="w-full bg-surface-container-low rounded-lg px-3.5 py-2.5 text-[14px] text-on-surface placeholder:text-outline/60 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="123" required/>
</div>
</div>
<div>
<label class="font-label-bold text-label-bold text-outline uppercase mb-1 block" for="cardInstallments">Parcelas</label>
<select id="cardInstallments" name="cardInstallments" class="w-full bg-surface-container-low rounded-lg px-3.5 py-2.5 text-[14px] text-on-surface focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40">
<?php if (isset($planos[0])): ?>
<option value="1">1x de R$ <?php echo number_format((float)$planos[0]['valor'], 2, ',', '.'); ?></option>
<?php else: ?>
<option value="1">1x de R$ <?php echo isset($planos[0]) ? number_format((float)$planos[0]['valor'], 2, ',', '.') : '5,00'; ?></option>
<?php endif; ?>
</select>
</div>
<div id="cardBinBox" class="hidden">
<label class="font-label-bold text-label-bold text-outline uppercase mb-1 block" for="cardIdentificationType">Tipo de documento</label>
<select id="cardIdentificationType" name="cardIdentificationType" class="w-full bg-surface-container-low rounded-lg px-3.5 py-2.5 text-[14px] text-on-surface focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40">
<option value="CPF">CPF</option>
<option value="CNPJ">CNPJ</option>
</select>
<label class="font-label-bold text-label-bold text-outline uppercase mb-1 block mt-3" for="cardIdentificationNumber">Número do documento</label>
<input id="cardIdentificationNumber" name="cardIdentificationNumber" type="text" autocomplete="off" inputmode="numeric" class="w-full bg-surface-container-low rounded-lg px-3.5 py-2.5 text-[14px] text-on-surface placeholder:text-outline/60 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="Documento do titular" required/>
</div>
<div id="cardErrorBox" class="hidden">
<div class="bg-error-container/60 border border-error/30 text-[#93000a] rounded-lg px-3.5 py-2.5 text-[13px]" id="cardErrorText"></div>
</div>
<button type="submit" id="cardPayBtn" class="w-full bg-primary hover:bg-primary-container text-on-primary font-headline-sm py-3.5 rounded-full shadow-lg active:scale-95 transition-all duration-200 flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-[20px]">lock</span> PAGAR R$ <?php echo isset($planos[0]) ? number_format((float)$planos[0]['valor'], 2, ',', '.') : '5,00'; ?>
</button>
</form>
<?php else: ?>
<div class="bg-error-container/60 border border-error/30 text-[#93000a] rounded-lg px-3.5 py-2.5 text-[13px]">Pagamento por cartão indisponível no momento.</div>
<?php endif; ?>
</div>
</div>
<button class="text-primary font-label-bold text-[16px] hover:underline underline-offset-4 flex items-center gap-1 mx-auto mt-md" onclick="location.href='parceiros.php'">
                Ver lojas parceiras
                <span class="material-symbols-outlined">arrow_forward</span>
</button>
</section>
<?php endif; ?>
</main>
<!-- BottomNavBar -->
<nav class="fixed bottom-0 w-full z-50 rounded-t-xl bg-surface/80 dark:bg-surface-dim/80 backdrop-blur-xl shadow-[0_-4px_20px_rgba(0,0,0,0.05)] border-t border-outline-variant/30">
<div class="flex justify-around items-center w-full py-2 pb-safe px-2">
<a class="flex flex-col items-center justify-center text-on-surface-variant dark:text-outline px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="dashboard.php">
<span class="material-symbols-outlined">home</span>
<span class="font-label-bold text-label-bold mt-1">Home</span>
</a>
<a class="flex flex-col items-center justify-center text-on-surface-variant dark:text-outline px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="parceiros.php">
<span class="material-symbols-outlined">storefront</span>
<span class="font-label-bold text-label-bold mt-1">Parceiros</span>
</a>
<a class="flex flex-col items-center justify-center bg-primary-container dark:bg-primary text-on-primary-container dark:text-on-primary rounded-full px-4 py-1.5 active:scale-90 duration-200" href="ativar.php">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">credit_card</span>
<span class="font-label-bold text-label-bold mt-1">Cartão</span>
</a>
<a class="flex flex-col items-center justify-center text-on-surface-variant dark:text-outline px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="perfil.php">
<span class="material-symbols-outlined">person</span>
<span class="font-label-bold text-label-bold mt-1">Perfil</span>
</a>
</div>
</nav>
<script>
        let planoAtual = <?php echo isset($planos[0]) ? json_encode(['id' => (int)$planos[0]['id'], 'valor' => number_format((float)$planos[0]['valor'], 2, ',', '.'), 'dias' => (int)$planos[0]['dias'], 'nome' => $planos[0]['nome']]) : 'null'; ?>;
        let pixAtivo = <?php echo $pix ? json_encode(['mp_payment_id' => (int)$pix['mp_payment_id'], 'copia_cola' => $pix['qr_code_copia_cola'], 'plano_id' => (int)($pix['plano_id'] ?? 0)]) : 'null'; ?>;
        let mpPaymentId = pixAtivo ? pixAtivo.mp_payment_id : 0;

        function selecionarPlano(el) {
            document.querySelectorAll('.plan-card').forEach(c => {
                c.classList.remove('border-primary', 'bg-primary/5');
                const r = c.querySelector('.plan-radio');
                if (r) { r.className = 'w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 plan-radio border-outline'; r.innerHTML = ''; }
            });
            el.classList.add('border-primary', 'bg-primary/5');
            const radio = el.querySelector('.plan-radio');
            if (radio) { radio.className = 'w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 plan-radio border-primary'; radio.innerHTML = '<span class="w-2.5 h-2.5 rounded-full bg-primary"></span>'; }
            planoAtual = { id: +el.dataset.id, valor: el.dataset.valor, dias: +el.dataset.dias, nome: el.dataset.nome };
            const brl = 'R$ ' + planoAtual.valor;
            if (document.getElementById('pixValorText')) document.getElementById('pixValorText').textContent = brl;
            if (document.getElementById('cardValorText')) document.getElementById('cardValorText').textContent = brl;
            if (document.getElementById('cardDiasText')) document.getElementById('cardDiasText').textContent = planoAtual.dias;
            if (document.getElementById('cardPayBtn')) document.getElementById('cardPayBtn').innerHTML = '<span class="material-symbols-outlined text-[20px]">lock</span> PAGAR ' + brl;
            if (document.getElementById('cardInstallments')) {
                document.getElementById('cardInstallments').innerHTML = '<option value="1">1x de ' + brl + '</option>';
            }
            if (typeof montarCardForm === 'function') { montarCardForm(); }
        }

        function gerarPix() {
            if (!planoAtual) return;
            const btn = document.getElementById('btnGerarPix');
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-outlined text-[20px]">hourglass_top</span> GERANDO...'; }
            const body = new URLSearchParams({ plano_id: planoAtual.id });
            fetch('criar_pix.php', { method: 'POST', body: body })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'pending' && d.pix) {
                        pixAtivo = { mp_payment_id: d.pix.mp_payment_id, copia_cola: d.pix.qr_code_copia_cola, plano_id: planoAtual.id };
                        const qrBox = document.getElementById('qrBox');
                        if (qrBox) {
                            qrBox.innerHTML = '<img id="qrImg" class="w-full h-full object-contain" src="data:image/png;base64,' + d.pix.qr_code_base64 + '" alt="QR Code PIX"/>';
                        }
                        const codeBox = document.getElementById('pixCodeBox');
                        const waitBox = document.getElementById('pixWaitBox');
                        if (!codeBox) {
                            const painel = document.getElementById('painelPix');
                            if (painel) {
                                const div = document.createElement('div');
                                div.className = 'w-full mt-md';
                                div.id = 'pixCodeBox';
                                div.innerHTML = '<p class="font-label-bold text-label-bold text-outline uppercase mb-1 text-left">Código PIX (copia e cola)</p><div class="w-full bg-surface-container-low rounded-lg p-3 flex items-center gap-2"><p class="font-body-md text-[11px] text-on-surface-variant flex-1 break-all text-left" id="pixCodeText"></p><button class="text-primary font-label-bold text-[12px]" onclick="copiarPix()">COPIAR</button></div>';
                                painel.insertBefore(div, waitBox);
                            }
                        }
                        const codeText = document.getElementById('pixCodeText');
                        if (codeText) codeText.textContent = d.pix.qr_code_copia_cola;
                        if (waitBox) waitBox.classList.remove('hidden');
                        if (btn) { btn.remove(); }
                        mpPaymentId = d.pix.mp_payment_id;
                        if (polling) clearInterval(polling);
                        polling = setInterval(verificarPagamento, 5000);
                        verificarPagamento();
                    } else if (d.status === 'approved') {
                        mostrarModalAtivado();
                    } else {
                        if (btn) { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined text-[20px]">qr_code_2</span> GERAR QR CODE PIX'; }
                        alert(d.message || 'Erro ao gerar o PIX. Tente novamente.');
                    }
                })
                .catch(() => {
                    if (btn) { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined text-[20px]">qr_code_2</span> GERAR QR CODE PIX'; }
                    alert('Erro ao gerar o PIX. Tente novamente.');
                });
        }
        const pixCodeText = pixAtivo ? pixAtivo.copia_cola : '';
        function copiarPix() {
            const el = document.getElementById('pixCodeText');
            const valor = el ? el.textContent : pixCodeText;
            if (!valor) return;
            function sucesso() {
                const btn = document.querySelector('#pixCodeBox button');
                if (btn) {
                    const original = btn.textContent;
                    btn.textContent = 'COPIADO!';
                    setTimeout(() => btn.textContent = original, 2000);
                }
            }
            // 1) API moderna de clipboard (requer HTTPS ou localhost)
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(valor).then(sucesso).catch(() => fallbackCopiar(valor, sucesso));
                return;
            }
            // 2) Fallback universal (funciona em HTTP): textarea temporário + execCommand
            fallbackCopiar(valor, sucesso);
        }
        function fallbackCopiar(valor, cb) {
            const ta = document.createElement('textarea');
            ta.value = valor;
            ta.style.position = 'fixed';
            ta.style.top = '-9999px';
            ta.style.left = '-9999px';
            ta.setAttribute('readonly', '');
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            ta.setSelectionRange(0, ta.value.length);
            let ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            document.body.removeChild(ta);
            if (ok) { if (cb) cb(); } else { alert('Não foi possível copiar automaticamente. Selecione o código e copie manualmente.'); }
        }
        function verificarPagamento() {
            if (!mpPaymentId) return;
            fetch('verifica_pagamento.php?id=' + mpPaymentId)
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'approved') {
                        if (document.getElementById('pixWaitBox')) document.getElementById('pixWaitBox').innerText = 'Pagamento confirmado!';
                        clearInterval(polling);
                        mostrarModalAtivado();
                    } else if (d.status === 'rejected' || d.status === 'cancelled') {
                        clearInterval(polling);
                        location.reload();
                    }
                })
                .catch(() => {});
        }
        function mostrarModalAtivado() {
            document.getElementById('modalAtivado').classList.remove('hidden');
            setTimeout(() => location.reload(), 2500);
        }
        let polling = mpPaymentId ? setInterval(verificarPagamento, 5000) : null;
        if (mpPaymentId) verificarPagamento();

        function mostrarAba(aba) {
            const pixBtn = document.getElementById('tabPixBtn');
            const cardBtn = document.getElementById('tabCardBtn');
            const painelPix = document.getElementById('painelPix');
            const painelCard = document.getElementById('painelCard');
            if (aba === 'pix') {
                pixBtn.className = 'flex-1 py-2.5 rounded-lg font-label-bold text-label-bold bg-surface-container-lowest shadow-sm text-primary flex items-center justify-center gap-1.5 transition-all';
                cardBtn.className = 'flex-1 py-2.5 rounded-lg font-label-bold text-label-bold text-on-surface-variant flex items-center justify-center gap-1.5 transition-all';
                painelPix.classList.remove('hidden');
                painelCard.classList.add('hidden');
            } else {
                cardBtn.className = 'flex-1 py-2.5 rounded-lg font-label-bold text-label-bold bg-surface-container-lowest shadow-sm text-primary flex items-center justify-center gap-1.5 transition-all';
                pixBtn.className = 'flex-1 py-2.5 rounded-lg font-label-bold text-label-bold text-on-surface-variant flex items-center justify-center gap-1.5 transition-all';
                painelCard.classList.remove('hidden');
                painelPix.classList.add('hidden');
            }
        }
    </script>
    <script>
        const mpPublicKey = <?php echo json_encode($publicKey); ?>;
        const mpSdk = window.MercadoPago ? new MercadoPago(mpPublicKey, { locale: 'pt-BR' }) : null;
        const cardForm = document.getElementById('cardForm');
        const payBtn = document.getElementById('cardPayBtn');
        const errorBox = document.getElementById('cardErrorBox');
        const errorText = document.getElementById('cardErrorText');
        let cardFormInstance = null;
        function cardErro(msg) {
            if (!errorBox) return;
            errorText.textContent = msg;
            errorBox.classList.remove('hidden');
        }
        function amountMP() {
            return planoAtual ? String(planoAtual.valor).replace('.', '').replace(',', '.') : '5.00';
        }
        function montarCardForm() {
            if (!cardForm || !mpSdk) return;
            if (cardFormInstance && typeof cardFormInstance.destroy === 'function') {
                try { cardFormInstance.destroy(); } catch (e) {}
            }
            cardFormInstance = mpSdk.cardForm({
                amount: amountMP(),
                autoMount: true,
                form: {
                    id: 'cardForm',
                    cardholderName: { id: 'cardholderName', placeholder: 'Nome impresso no cartão' },
                    cardNumber: { id: 'cardNumber', placeholder: '0000 0000 0000 0000' },
                    expirationDate: { id: 'cardExpirationDate', placeholder: 'MM/AA' },
                    securityCode: { id: 'cardSecurityCode', placeholder: '123' },
                    installments: { id: 'cardInstallments' },
                    identificationType: { id: 'cardIdentificationType' },
                    identificationNumber: { id: 'cardIdentificationNumber' }
                },
                callbacks: {
                    onFormMounted: (error) => {
                        if (error) { cardErro('Não foi possível carregar o formulário de cartão: ' + error.message); }
                    },
                    onSubmit: (event) => {
                        event.preventDefault();
                        const formData = cardFormInstance ? cardFormInstance.getCardFormData() : null;
                        if (!formData || !formData.token) { cardErro('Não foi possível processar o cartão. Verifique os dados.'); return; }
                        const token = formData.token;
                        const paymentMethodId = formData.paymentMethodId;
                        const installments = formData.installments || 1;
                        errorBox.classList.add('hidden');
                        payBtn.disabled = true;
                        payBtn.innerHTML = '<span class="material-symbols-outlined text-[20px]">hourglass_top</span> PROCESSANDO...';
                        const body = new URLSearchParams({
                            token: token,
                            payment_method_id: paymentMethodId,
                            installments: installments,
                            plano_id: planoAtual ? planoAtual.id : 0,
                            payer_identification_type: document.getElementById('cardIdentificationType').value,
                            payer_identification_number: document.getElementById('cardIdentificationNumber').value,
                            payer_email: 'usuario' + <?php echo (int)$uid; ?> + '@economiccard.com.br'
                        });
                        fetch('processa_pagamento_cartao.php', { method: 'POST', body: body })
                            .then(r => r.json())
                            .then(d => {
                                if (d.status === 'approved') {
                                    mostrarModalAtivado();
                                } else if (d.status === 'pending') {
                                    setTimeout(() => location.reload(), 3000);
                                } else {
                                    payBtn.disabled = false;
                                    payBtn.innerHTML = '<span class="material-symbols-outlined text-[20px]">lock</span> PAGAR R$ ' + (planoAtual ? planoAtual.valor : '5,00');
                                    cardErro(d.message || 'Pagamento recusado. Tente novamente.');
                                }
                            })
                            .catch(() => {
                                payBtn.disabled = false;
                                payBtn.innerHTML = '<span class="material-symbols-outlined text-[20px]">lock</span> PAGAR R$ ' + (planoAtual ? planoAtual.valor : '5,00');
                                cardErro('Erro ao processar o pagamento. Tente novamente.');
                            });
                    },
                    onFetching: () => {
                        if (payBtn) { payBtn.disabled = true; payBtn.innerHTML = '<span class="material-symbols-outlined text-[20px]">hourglass_top</span> PROCESSANDO...'; }
                    },
                    onError: (error) => {
                        if (payBtn) { payBtn.disabled = false; payBtn.innerHTML = '<span class="material-symbols-outlined text-[20px]">lock</span> PAGAR R$ ' + (planoAtual ? planoAtual.valor : '5,00'); }
                        cardErro(error && error.message ? error.message : 'Verifique os dados do cartão.');
                    }
                }
            });
        }
        montarCardForm();
    </script>    <!-- Modal CARTÃO ATIVADO -->
    <div id="modalAtivado" class="hidden fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm flex items-center justify-center p-6">
    <div class="w-full max-w-sm bg-surface-container-lowest rounded-2xl p-8 text-center shadow-2xl">
    <div class="w-20 h-20 mx-auto rounded-full bg-secondary/15 flex items-center justify-center mb-4">
    <span class="material-symbols-outlined text-secondary text-[48px]" style="font-variation-settings:'FILL' 1;">check_circle</span>
    </div>
    <h2 class="font-headline-md text-headline-md text-on-surface uppercase tracking-tight mb-2">Cartão Ativado</h2>
    <p class="font-body-md text-on-surface-variant mb-6">Seu cartão foi ativado! Aproveite os descontos nas lojas parceiras!</p>
    <a href="parceiros.php" class="block w-full bg-primary text-on-primary font-headline-sm py-4 rounded-full shadow-lg active:scale-95 transition-all duration-200">Ver lojas parceiras</a>
    </div>
    </div>
    <!-- Modal ACESSO BLOQUEADO -->
    <div id="modalBloqueado" class="hidden fixed inset-0 z-[110] bg-black/60 backdrop-blur-sm flex items-center justify-center p-6">
    <div class="w-full max-w-sm bg-surface-container-lowest rounded-2xl p-8 text-center shadow-2xl">
    <div class="w-20 h-20 mx-auto rounded-full bg-primary/15 flex items-center justify-center mb-4">
    <span class="material-symbols-outlined text-primary text-[48px]" style="font-variation-settings:'FILL' 1;">sentiment_very_satisfied</span>
    </div>
    <h2 class="font-headline-md text-headline-md text-on-surface uppercase tracking-tight mb-2">Ative seu cartão</h2>
    <p class="font-body-md text-on-surface-variant mb-6">Para acessar seus benefícios, primeiro ative seu cartão escolhendo um de nossos planos :)</p>
    <button class="block w-full bg-primary text-on-primary font-headline-sm py-4 rounded-full shadow-lg active:scale-95 transition-all duration-200" onclick="document.getElementById('modalBloqueado').classList.add('hidden')">Entendi</button>
    </div>
    </div>
    <script>
    if (<?php echo $bloqueado ? 'true' : 'false'; ?>) {
        document.getElementById('modalBloqueado').classList.remove('hidden');
    }
    </script>
    </body></html>