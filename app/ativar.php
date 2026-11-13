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
if (($u['status'] ?? 'ativo') === 'desativado') {
    session_unset();
    session_destroy();
    header('Location: login.php?conta_encerrada=1');
    exit;
}
if (ehDependente()) {
    header('Location: dashboard.php');
    exit;
}

$final = $u['final_cartao'] ?: '8829';
$pers = $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc();
$cartaoAtivado = $pers['cartao_ativado'] ?? '';
$cartaoDesativado = $pers['cartao_desativado'] ?? '';
$cartaoImg = $u['cartao_ativo'] ? $cartaoAtivado : $cartaoDesativado;

require_once 'asaas_pix.php';
$asaasOk = asaas_config($conn) !== null;
$cepUsuario = preg_replace('/\D/', '', (string)($u['cep'] ?? ''));
$numEndereco = '';
if (!empty($u['endereco']) && preg_match('/(\d+)/', (string)$u['endereco'], $mNum)) {
    $numEndereco = $mNum[1];
}

$hoje = date('Y-m-d');

// Garante o contrato do ciclo atual. Se já existe contrato em andamento (com o
// cartão já ativado pela adesão), ele é reaproveitado; se o ciclo terminou e a
// validade venceu, abre um novo ciclo. Para usuários sem contrato, cria as
// cobranças do ciclo (adesão + mensalidades) antes de exibir a tela.
$contrato = plano_garantir_contrato($conn, $uid, $u);

// Meu Plano: o resumo é sempre carregado, esteja o cartão ativo ou não.
// Assim o usuário paga as mensalidades mesmo depois da ativação pela adesão.
$resumoPlano = plano_resumo_usuario($conn, $uid);
$contrato = $resumoPlano['contrato'] ?? null;
$cartaoValido = ((int)($u['cartao_ativo'] ?? 0) === 1) && (!empty($u['cartao_validade']) && $u['cartao_validade'] >= $hoje);
$cobrancaAtual = $resumoPlano['cobranca_atual'] ?? null;
$podePagar = !empty($resumoPlano['cobranca_disponivel']) && $cobrancaAtual !== null;
$valorAtual = 0.0;
$rotuloAtual = '';
if ($cobrancaAtual) {
    $valorAtual = (float)$cobrancaAtual['valor'];
    $rotuloAtual = plano_rotulo_cobranca((int)$cobrancaAtual['sequencia'], (string)$cobrancaAtual['tipo']);
}

$pix = null;
$stmt = $conn->prepare("SELECT * FROM pagamentos_pix WHERE usuario_id = ? AND tipo = 'plano' AND status = 'pending' AND pix_validade > NOW() ORDER BY id DESC LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$pix = $stmt->get_result()->fetch_assoc();

$planoId = (int)($contrato['plano_id'] ?? 0);
$valorFmt = number_format($valorAtual, 2, ',', '.');
$situacao = $resumoPlano['situacao'] ?? '';
$motivo = $resumoPlano['motivo_disponibilidade'] ?? '';
$valsPlano = plano_valores_atuais($conn);
if (!$contrato && empty($valsPlano['ativo'])) {
    $podePagar = false;
    $motivo = 'Plano temporariamente indisponível para novas contratações. Fale com o suporte.';
}

$totalCobrancas = max(1, (int)($resumoPlano['total_cobrancas'] ?? 1));
$pagasPlano = (int)($resumoPlano['pagas'] ?? 0);
$validadeMeses = max(1, (int)($resumoPlano['validade_meses'] ?? 36));
$progressoPct = $totalCobrancas > 0 ? min(100, round(($pagasPlano / $totalCobrancas) * 100)) : 0;
$planoQuitado = !empty($resumoPlano['quitado']) || $pagasPlano >= $totalCobrancas;
$etapaAtual = $cobrancaAtual ? (int)$cobrancaAtual['sequencia'] : min($totalCobrancas, $pagasPlano + 1);

$adesaoPaga = false;
$mensalidadesPagas = 0;
foreach (($resumoPlano['cobrancas'] ?? []) as $cobResumo) {
    if (($cobResumo['status'] ?? '') !== 'pago') continue;
    if (($cobResumo['tipo'] ?? '') === 'adesao') $adesaoPaga = true;
    elseif (($cobResumo['tipo'] ?? '') === 'mensalidade') $mensalidadesPagas++;
}

// Destaque secundário: última mensalidade do ciclo (no padrão, a 2ª de R$ 70,00).
// É ela que quita o plano e renova a validade do cartão por 3 anos.
$ultimaMensalidade = null;
$ultimaSeqMensalidade = 0;
foreach (($resumoPlano['cobrancas'] ?? []) as $seqC => $cobC) {
    if (($cobC['tipo'] ?? '') !== 'mensalidade') continue;
    if ((int)$seqC >= $ultimaSeqMensalidade) {
        $ultimaSeqMensalidade = (int)$seqC;
        $ultimaMensalidade = $cobC;
    }
}
$rotuloUltimaMensalidade = $ultimaMensalidade
    ? plano_rotulo_cobranca($ultimaSeqMensalidade, 'mensalidade')
    : '';
$ultimaMensalidadePaga = $ultimaMensalidade && ($ultimaMensalidade['status'] ?? '') === 'pago';
$validadeCartao = (string)($resumoPlano['validade_ate'] ?? ($u['cartao_validade'] ?? ''));
$ativadoEm = (string)($resumoPlano['ativado_em'] ?? '');
$validadeProvisoria = !empty($resumoPlano['validade_provisoria']);
$statusAtualVisual = ($cobrancaAtual ? ($cobrancaAtual['status_visual'] ?? plano_status_visual_cobranca($cobrancaAtual)) : '');
?>
<!DOCTYPE html>
<html class="light" lang="pt-BR">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<meta name="theme-color" content="#51036d">
<title><?php echo $cartaoValido ? 'Meu Plano' : 'Ativação e Pagamento'; ?> - Economic Card</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                "colors": {
                    "primary": "#51036d", "on-primary": "#ffffff", "primary-container": "#6a2585",
                    "secondary": "#3e6a00", "on-secondary": "#ffffff", "secondary-container": "#b6f570",
                    "surface": "#f8f9fa", "on-surface": "#191c1d", "surface-container": "#edeeef",
                    "surface-container-low": "#f3f4f5", "surface-container-lowest": "#ffffff",
                    "surface-container-high": "#e7e8e9", "on-surface-variant": "#4e434f",
                    "outline": "#7f7381", "outline-variant": "#d1c2d1", "error": "#ba1a1a",
                    "on-error": "#ffffff", "error-container": "#ffdad6", "on-error-container": "#93000a"
                },
                "borderRadius": { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1rem", "3xl": "1.5rem", "full": "9999px" },
                "spacing": { "xs": "4px", "card-gutter": "16px", "container-padding": "20px", "xl": "48px", "md": "24px", "sm": "12px", "lg": "32px", "base": "8px" },
                "fontFamily": {
                    "sans": ["Inter", "sans-serif"],
                    "display": ["Sora", "Inter", "sans-serif"],
                    "label-caps": ["Inter", "sans-serif"], "label-bold": ["Inter", "sans-serif"],
                    "body-md": ["Inter", "sans-serif"], "headline-sm": ["Inter", "sans-serif"],
                    "headline-md": ["Sora", "sans-serif"], "display-lg": ["Sora", "sans-serif"]
                },
                "fontSize": {
                    "label-caps": ["11px", { "lineHeight": "1", "letterSpacing": "0.06em", "fontWeight": "600" }],
                    "label-bold": ["13px", { "lineHeight": "1", "letterSpacing": "0.01em", "fontWeight": "700" }],
                    "body-md": ["15px", { "lineHeight": "1.45", "fontWeight": "400" }],
                    "headline-sm": ["18px", { "lineHeight": "1.3", "fontWeight": "600" }],
                    "headline-md": ["22px", { "lineHeight": "1.25", "fontWeight": "700" }],
                    "display-lg": ["30px", { "lineHeight": "1.15", "fontWeight": "700" }]
                },
                "boxShadow": {
                    "premium": "0 18px 50px -20px rgba(81, 3, 109, 0.35)",
                    "soft": "0 10px 30px -18px rgba(25, 28, 29, 0.28)"
                }
            }
        }
    }
</script>
<style>
    :root { --brand: #51036d; --brand-2: #7a2b9c; --brand-soft: #f1e7f7; --gold: #e7c873; }
    html { scroll-behavior: smooth; }
    body {
        background:
            radial-gradient(900px 520px at 8% -10%, rgba(122, 43, 156, 0.16), transparent 60%),
            radial-gradient(760px 460px at 100% 0%, rgba(182, 245, 112, 0.18), transparent 55%),
            #f6f4f9;
        font-family: 'Inter', sans-serif;
        color: #191c1d;
        -webkit-font-smoothing: antialiased;
        overflow-x: hidden;
    }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .premium-gradient { background: linear-gradient(135deg, #3c0352 0%, #51036d 45%, #7a2b9c 100%); }
    .premium-gradient::after {
        content: ""; position: absolute; inset: 0; pointer-events: none;
        background: radial-gradient(420px 220px at 88% -10%, rgba(231, 200, 115, 0.28), transparent 65%);
    }
    .glass { background: rgba(255, 255, 255, 0.78); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); }
    .pix-shadow { box-shadow: 0 10px 30px -18px rgba(25, 28, 29, 0.28); }
    .premium-shadow { box-shadow: 0 18px 50px -20px rgba(81, 3, 109, 0.35); }
    .tab-active { background: #ffffff; color: #51036d; box-shadow: 0 4px 14px -6px rgba(81, 3, 109, 0.4); }
    .reveal { animation: floatIn 0.55s cubic-bezier(0.22, 1, 0.36, 1) both; }
    .reveal-1 { animation-delay: 0.06s; }
    .reveal-2 { animation-delay: 0.12s; }
    .reveal-3 { animation-delay: 0.18s; }
    .reveal-4 { animation-delay: 0.24s; }
    @keyframes floatIn {
        from { opacity: 0; transform: translateY(14px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .pulse-dot { position: relative; }
    .pulse-dot::after {
        content: ""; position: absolute; inset: -4px; border-radius: 9999px;
        border: 2px solid currentColor; opacity: 0; animation: pulseRing 2s ease-out infinite;
    }
    @keyframes pulseRing {
        0% { transform: scale(0.85); opacity: 0.5; }
        70% { transform: scale(1.5); opacity: 0; }
        100% { opacity: 0; }
    }
    .shimmer-line {
        background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.55) 50%, rgba(255,255,255,0) 100%);
        background-size: 220% 100%; animation: shimmer 2.4s linear infinite;
    }
    @keyframes shimmer { from { background-position: 180% 0; } to { background-position: -40% 0; } }
    .qr-frame { background: linear-gradient(160deg, #ffffff, #f6f1fa); }
    .status-approved { background: rgba(182, 245, 112, 0.28); color: #2f5200; border-color: rgba(62, 106, 0, 0.28); }
    .status-waiting { background: rgba(231, 200, 115, 0.28); color: #7a5a00; border-color: rgba(122, 90, 0, 0.25); }
    .pill { display: inline-flex; align-items: center; gap: 0.35rem; border: 1px solid transparent; border-radius: 9999px; font-weight: 700; }
    .timeline-row { transition: background-color .2s ease, transform .2s ease; }
    .timeline-row:hover { background-color: rgba(81, 3, 109, 0.035); }
    .copy-btn { transition: all .18s ease; }
    .copy-btn:active { transform: scale(0.96); }
    .premium-input {
        background: #f7f5fa; border: 1px solid rgba(81, 3, 109, 0.12);
        transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
    }
    .premium-input:focus { background: #fff; border-color: #51036d; box-shadow: 0 0 0 4px rgba(81, 3, 109, 0.1); outline: none; }
    @media (prefers-reduced-motion: reduce) {
        .reveal, .pulse-dot::after, .shimmer-line { animation: none !important; }
        * { scroll-behavior: auto !important; }
    }
</style>
<link rel="stylesheet" href="ui.css?v=<?php echo (int)@filemtime(__DIR__ . '/ui.css'); ?>"/>
</head>
<body class="min-h-screen flex flex-col pb-24">

<header class="sticky top-0 z-50 glass border-b border-white/60">
<div class="max-w-6xl mx-auto w-full px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3">
<a class="flex items-center gap-2 text-primary hover:opacity-80 transition-opacity rounded-full p-2" href="dashboard.php" aria-label="Voltar ao início">
<span class="material-symbols-outlined">arrow_back</span>
</a>
<div class="flex items-center gap-2.5 min-w-0">
<div class="w-9 h-9 rounded-xl premium-gradient relative overflow-hidden flex items-center justify-center shadow-premium">
<span class="material-symbols-outlined text-white text-[20px] relative z-10" style="font-variation-settings:'FILL' 1;">credit_card</span>
</div>
<div class="min-w-0">
<p class="font-display font-extrabold text-[15px] leading-tight text-primary truncate">Economic Card</p>
<p class="text-[10px] font-bold uppercase tracking-[0.16em] text-on-surface-variant/70 truncate"><?php echo $cartaoValido ? 'Meu plano' : 'Ativação e pagamento'; ?></p>
</div>
</div>
<div class="flex items-center gap-1">
<span class="hidden sm:inline-flex pill status-approved px-2.5 py-1 text-[10px] uppercase tracking-wide">
<span class="material-symbols-outlined text-[13px]" style="font-variation-settings:'FILL' 1;">lock</span> Ambiente seguro
</span>
<a class="text-primary hover:bg-white/70 transition-colors rounded-full p-2" href="logout.php" title="Sair" aria-label="Sair">
<span class="material-symbols-outlined">logout</span>
</a>
</div>
</div>
</header>

<main class="w-full max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 sm:pt-8 flex-grow">
<?php if ($bloqueado): ?>
<div class="reveal mb-5 rounded-2xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-[13px] text-amber-900 flex items-start gap-2">
<span class="material-symbols-outlined text-[18px] mt-0.5" style="font-variation-settings:'FILL' 1;">info</span>
<span>Finalize a ativação do seu cartão para liberar todas as vantagens. O acesso às lojas parceiras é liberado após a quitação do plano.</span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-[1.06fr_0.94fr] gap-6 lg:gap-8 items-start">

<!-- ============================ COLUNA ESQUERDA ============================ -->
<div class="space-y-6 min-w-0">

<section class="reveal">
<div class="flex items-end justify-between gap-4 mb-3 px-1">
<div>
<p class="text-[10px] font-extrabold uppercase tracking-[0.22em] text-primary/70"><?php echo $cartaoValido ? 'Membro Economic' : 'Bem-vindo de volta'; ?></p>
<h1 class="font-display text-[26px] sm:text-[32px] font-extrabold leading-tight text-on-surface mt-1">
<?php echo htmlspecialchars(explode(' ', trim((string)$u['nome']))[0]); ?>, seu cartão
<span class="text-primary"><?php echo $cartaoValido ? 'está ativo' : 'está a poucos passos'; ?></span>
</h1>
<p class="text-[13px] text-on-surface-variant mt-1.5 max-w-md">
<?php echo $cartaoValido ? ($planoQuitado ? 'Aproveite seus descontos exclusivos. Seu plano está quitado e sem novas cobranças durante a validade.' : 'Seu cartão está liberado. Existem cobranças pendentes do plano registradas no seu histórico.') : 'Conclua as cobranças do plano para ativar seu cartão e liberar os descontos exclusivos.'; ?>
</p>
</div>
</div>

<div class="mt-1 mb-2 flex items-center gap-1.5 px-1">
<span class="material-symbols-outlined text-[15px] text-primary">contactless</span>
<span class="text-[10px] font-extrabold uppercase tracking-[0.2em] text-primary/70">Seu cartão</span>
</div>

<div class="relative">
<div class="pointer-events-none absolute -inset-4 rounded-[40px] bg-[radial-gradient(60%_60%_at_50%_50%,rgba(122,43,156,0.26),transparent_72%)] blur-2xl opacity-80" aria-hidden="true"></div>
<div class="relative rounded-3xl shadow-premium overflow-hidden aspect-[1.58/1] bg-primary ring-1 ring-white/50 transition-transform duration-500 hover:scale-[1.01]">
<?php if ($cartaoImg): ?>
<img class="absolute inset-0 w-full h-full object-cover" src="<?php echo htmlspecialchars(asset_url($cartaoImg)); ?>" alt="Cartão Economic Card"/>
<div class="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-black/10"></div>
<div class="absolute inset-0 flex flex-col items-start justify-end gap-2 p-5 sm:p-6">
<p class="font-display text-white text-[18px] truncate max-w-full text-left drop-shadow" style="text-shadow: 0 1px 6px rgba(0,0,0,0.55);"><?php echo htmlspecialchars($u['nome']); ?></p>
<div class="flex items-center gap-2">
<?php if ($cartaoValido): ?>
<span class="pill bg-secondary text-white px-3 py-1 text-[10px] uppercase tracking-wide"><span class="material-symbols-outlined text-[13px]" style="font-variation-settings:'FILL' 1;">check_circle</span> Ativado</span>
<?php else: ?>
<span class="pill bg-error text-white px-3 py-1 text-[10px] uppercase tracking-wide"><span class="material-symbols-outlined text-[13px]" style="font-variation-settings:'FILL' 1;">schedule</span> Inativo</span>
<?php endif; ?>
<span class="pill bg-white/15 text-white px-3 py-1 text-[10px] uppercase tracking-wide backdrop-blur">•••• <?php echo htmlspecialchars($final); ?></span>
</div>
</div>
<?php else: ?>
<div class="premium-gradient absolute inset-0 p-5 sm:p-6 flex flex-col justify-between overflow-hidden">
<div class="relative z-10 flex justify-between items-start">
<div class="space-y-2">
<span class="text-[9px] font-extrabold uppercase tracking-[0.26em] text-white/60">Membro Platinum</span>
<div class="h-[2px] w-10 bg-[#e7c873]"></div>
</div>
<?php if ($cartaoValido): ?>
<div class="pill bg-white/15 text-white px-3 py-1 text-[10px] font-bold uppercase tracking-wide backdrop-blur border border-white/20">Ativo</div>
<?php else: ?>
<div class="pill bg-black/20 text-white/90 px-3 py-1 text-[10px] font-bold uppercase tracking-wide backdrop-blur border border-white/15">Inativo</div>
<?php endif; ?>
</div>
<div class="relative z-10 mt-auto">
<p class="font-display text-white text-[17px] sm:text-[19px] font-bold mb-4 uppercase tracking-wide"><?php echo htmlspecialchars($u['nome']); ?></p>
<div class="flex justify-between items-end gap-4">
<p class="text-[12px] font-bold text-white/75 tracking-[0.18em]">•••• •••• •••• <?php echo htmlspecialchars($final); ?></p>
<div class="text-right">
<p class="text-[8px] font-bold uppercase tracking-[0.2em] text-white/45">Validade</p>
<p class="text-[11px] font-bold text-white/80"><?php echo !empty($u['cartao_validade']) ? date('m/y', strtotime($u['cartao_validade'])) : '--/--'; ?></p>
</div>
</div>
</div>
</div>
<?php endif; ?>
</div>
</div>

<div class="mt-3 flex items-center justify-between gap-3 px-1">
<span class="inline-flex items-center gap-1.5 text-[11px] text-on-surface-variant">
<span class="material-symbols-outlined text-[15px] <?php echo $cartaoValido ? 'text-secondary' : 'text-primary'; ?>">verified_user</span>
<?php echo $cartaoValido ? ($planoQuitado ? 'Cartão válido e plano quitado' : 'Cartão ativo · mensalidades em aberto') : 'Ativação simples, segura e sem renovação automática'; ?>
</span>
<span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant/60">Economic Card</span>
</div>
</section>

<!-- Resumo da contratação -->
<section class="reveal reveal-1 rounded-3xl bg-white/90 border border-white/70 pix-shadow p-5 sm:p-6">
<div class="flex items-start justify-between gap-3">
<div>
<p class="text-[10px] font-extrabold uppercase tracking-[0.2em] text-on-surface-variant/60">Resumo da contratação</p>
<h2 class="font-display text-[20px] font-extrabold text-on-surface mt-1">Plano Mensal</h2>
</div>
<span class="pill <?php echo $planoQuitado ? 'status-approved' : 'text-primary bg-primary/10 border-primary/10'; ?> px-3 py-1.5 text-[10px] uppercase tracking-wide whitespace-nowrap">
<span class="material-symbols-outlined text-[14px]" style="font-variation-settings:'FILL' 1;"><?php echo $planoQuitado ? 'verified' : 'receipt_long'; ?></span>
<?php echo $planoQuitado ? 'Quitado' : ($pagasPlano . '/' . $totalCobrancas . ' pagas'); ?>
</span>
</div>

<div class="mt-5">
<div class="flex items-center justify-between text-[11px] font-bold text-on-surface-variant mb-2">
<span>Progresso do plano</span>
<span><?php echo $pagasPlano; ?> de <?php echo $totalCobrancas; ?> cobranças</span>
</div>
<div class="h-2.5 w-full rounded-full bg-surface-container overflow-hidden">
<div class="h-full rounded-full premium-gradient relative transition-all duration-700" style="width: <?php echo (int)$progressoPct; ?>%">
<div class="absolute inset-0 shimmer-line"></div>
</div>
</div>
</div>

<div class="mt-5 space-y-1.5">
<?php if (!empty($resumoPlano['cobrancas'])): ?>
<?php foreach ($resumoPlano['cobrancas'] as $seq => $cob):
    $cobPaga = ($cob['status'] === 'pago');
    $cobAtual = $cobrancaAtual && (int)$cobrancaAtual['sequencia'] === (int)$seq;
    $cobLiberada = $cobAtual && $podePagar;
    $statusVisual = $cob['status_visual'] ?? plano_status_visual_cobranca($cob);
    $icone = $cobPaga ? 'check_circle' : ($cobLiberada ? 'radio_button_checked' : 'lock');
    $corIcone = $cobPaga ? 'text-secondary' : ($cobLiberada ? 'text-primary' : 'text-outline/60');
    $descricao = $cobPaga
        ? 'Paga' . (!empty($cob['pago_em']) ? ' em ' . date('d/m/Y', strtotime($cob['pago_em'])) : '')
        : ($cobAtual
            ? ($cobLiberada ? 'Cobrança atual' : (!empty($cob['vencimento']) ? 'Disponível a partir de ' . date('d/m/Y', strtotime($cob['vencimento'])) : 'Aguardando liberação'))
            : 'Liberada após a etapa anterior');
?>
<div class="timeline-row flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 <?php echo $cobAtual ? 'bg-primary/[0.055] ring-1 ring-primary/10' : ''; ?>">
<div class="flex items-center gap-3 min-w-0">
<span class="material-symbols-outlined text-[20px] shrink-0 <?php echo $corIcone; ?>" style="font-variation-settings:'FILL' 1;"><?php echo $icone; ?></span>
<div class="min-w-0">
<p class="text-[13px] font-bold text-on-surface truncate"><?php echo htmlspecialchars(plano_rotulo_cobranca($seq, $cob['tipo'] ?? '')); ?></p>
<p class="text-[10.5px] text-on-surface-variant leading-snug"><?php echo htmlspecialchars($descricao); ?><?php echo (!$cobPaga && !$cobAtual && !empty($cob['vencimento'])) ? ' · ' . date('d/m/Y', strtotime($cob['vencimento'])) : ''; ?></p>
</div>
</div>
<div class="text-right shrink-0">
<p class="text-[13px] font-extrabold text-on-surface">R$ <?php echo number_format((float)$cob['valor'], 2, ',', '.'); ?></p>
<span class="text-[9.5px] font-bold uppercase tracking-wide <?php echo $cobPaga ? 'text-secondary' : ($statusVisual === 'atrasada' ? 'text-error' : ($cobLiberada ? 'text-primary' : 'text-on-surface-variant/60')); ?>"><?php echo htmlspecialchars(plano_rotulo_status_cobranca($statusVisual)); ?></span>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<p class="text-[12px] text-on-surface-variant">Nenhuma cobrança registrada.</p>
<?php endif; ?>
</div>

<?php if ($ultimaMensalidade): ?>
<!-- Destaque secundário: última mensalidade libera a validade de 3 anos -->
<div class="mt-4 relative overflow-hidden rounded-2xl border border-secondary/25 bg-gradient-to-r from-secondary-container/25 via-white to-white p-4">
<div class="absolute inset-y-0 left-0 w-1.5 bg-gradient-to-b from-secondary via-[#7bc42e] to-[#b6f570]"></div>
<div class="flex items-start gap-3 pl-1">
<div class="w-10 h-10 rounded-xl bg-secondary-container/50 text-secondary flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings:'FILL' 1;">workspace_premium</span>
</div>
<div class="min-w-0 flex-1">
<div class="flex items-center justify-between gap-3">
<div class="min-w-0">
<p class="text-[9.5px] font-extrabold uppercase tracking-[0.16em] text-secondary/80">Quitação e validade de 3 anos</p>
<p class="text-[13px] font-extrabold text-on-surface truncate"><?php echo htmlspecialchars($rotuloUltimaMensalidade); ?> <span class="text-secondary">· R$ <?php echo number_format((float)$ultimaMensalidade['valor'], 2, ',', '.'); ?></span></p>
</div>
<span class="pill status-approved px-2.5 py-1 text-[9px] uppercase tracking-wide whitespace-nowrap"><?php echo $ultimaMensalidadePaga ? 'Paga' : 'Etapa final'; ?></span>
</div>
<p class="text-[11.5px] text-on-surface-variant leading-snug mt-1.5">
<?php if ($ultimaMensalidadePaga): ?>
Plano quitado. Seu cartão tem validade de <b class="text-on-surface">3 anos (<?php echo $validadeMeses; ?> meses)</b> a partir desta cobrança.
<?php else: ?>
Após a confirmação da <?php echo htmlspecialchars($rotuloUltimaMensalidade); ?>, o plano será considerado <b class="text-on-surface">quitado</b> e o cartão terá validade de <b class="text-on-surface">3 anos (<?php echo $validadeMeses; ?> meses)</b>.
<?php endif; ?>
</p>
</div>
</div>
</div>
<?php endif; ?>

<div class="mt-4 pt-4 border-t border-outline-variant/25 flex flex-wrap gap-x-4 gap-y-2 text-[10.5px] text-on-surface-variant">
<span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-primary">event_repeat</span> Sem renovação automática</span>
<span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-primary">calendar_month</span> Válido por <?php echo $validadeMeses; ?> meses</span>
<span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-primary">bolt</span> Liberação após cada pagamento</span>
</div>
</section>

</div>

<!-- ============================ COLUNA DIREITA ============================ -->
<div class="space-y-6 min-w-0">

<!-- ===================== STATUS DO CARTÃO / MEU PLANO ===================== -->
<section class="reveal reveal-2 rounded-3xl bg-white/95 border border-white/70 premium-shadow p-5 sm:p-6">
<div class="flex items-start justify-between gap-3">
<div class="flex items-center gap-3 min-w-0">
<div class="w-11 h-11 rounded-2xl <?php echo $cartaoValido ? 'bg-secondary-container/40 text-secondary' : 'bg-primary/10 text-primary'; ?> flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-[24px]" style="font-variation-settings:'FILL' 1;"><?php echo $cartaoValido ? 'verified' : 'credit_card'; ?></span>
</div>
<div class="min-w-0">
<p class="text-[10px] font-extrabold uppercase tracking-[0.18em] text-on-surface-variant/60">Meu Plano</p>
<h2 class="font-display text-[19px] font-extrabold text-on-surface truncate"><?php echo $cartaoValido ? 'Cartão ativo' : 'Cartão inativo'; ?></h2>
</div>
</div>
<?php if ($planoQuitado): ?>
<span class="pill status-approved px-3 py-1.5 text-[10px] uppercase tracking-wide whitespace-nowrap">Plano quitado</span>
<?php elseif ($cartaoValido): ?>
<span class="pill status-waiting px-3 py-1.5 text-[10px] uppercase tracking-wide whitespace-nowrap">Em andamento</span>
<?php else: ?>
<span class="pill px-3 py-1.5 text-[10px] uppercase tracking-wide whitespace-nowrap text-primary bg-primary/10 border-primary/10"><?php echo $pagasPlano; ?>/<?php echo $totalCobrancas; ?> pagas</span>
<?php endif; ?>
</div>

<div class="mt-5 grid grid-cols-2 gap-3 text-left">
<div class="rounded-2xl bg-surface-container-low border border-outline-variant/20 p-4">
<p class="text-[9.5px] font-extrabold uppercase tracking-[0.16em] text-on-surface-variant/70">Ativação</p>
<p class="text-[14px] font-extrabold text-on-surface mt-1"><?php echo $ativadoEm !== '' ? date('d/m/Y', strtotime($ativadoEm)) : '-'; ?></p>
</div>
<div class="rounded-2xl bg-surface-container-low border border-outline-variant/20 p-4">
<p class="text-[9.5px] font-extrabold uppercase tracking-[0.16em] text-on-surface-variant/70">Validade</p>
<p class="text-[14px] font-extrabold text-on-surface mt-1"><?php echo $validadeCartao !== '' ? date('d/m/Y', strtotime($validadeCartao)) : '-'; ?></p>
</div>
</div>

<div class="mt-3 flex items-center justify-between gap-3 rounded-2xl border border-outline-variant/20 bg-surface-container-low/50 px-4 py-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-[18px] <?php echo $adesaoPaga ? 'text-secondary' : 'text-outline'; ?>" style="font-variation-settings:'FILL' 1;"><?php echo $adesaoPaga ? 'check_circle' : 'radio_button_unchecked'; ?></span>
<p class="text-[12px] font-bold text-on-surface truncate">Taxa de adesão <span class="text-on-surface-variant font-semibold">· R$ <?php echo number_format((float)$resumoPlano['valor_adesao'], 2, ',', '.'); ?></span></p>
</div>
<span class="text-[10px] font-extrabold uppercase tracking-wide <?php echo $adesaoPaga ? 'text-secondary' : 'text-primary'; ?>"><?php echo $adesaoPaga ? 'Paga' : 'Pendente'; ?></span>
</div>

<div class="mt-3 grid grid-cols-2 gap-3 text-left">
<div class="rounded-2xl border border-outline-variant/20 p-3">
<p class="text-[9px] font-extrabold uppercase tracking-[0.14em] text-on-surface-variant/70">Mensalidades pagas</p>
<p class="text-[13px] font-extrabold text-on-surface mt-1"><?php echo $mensalidadesPagas; ?> de <?php echo max(1, (int)$resumoPlano['qtd_mensalidades']); ?></p>
</div>
<div class="rounded-2xl border border-outline-variant/20 p-3">
<p class="text-[9px] font-extrabold uppercase tracking-[0.14em] text-on-surface-variant/70">Histórico</p>
<p class="text-[13px] font-extrabold text-on-surface mt-1"><?php echo $pagasPlano; ?> de <?php echo $totalCobrancas; ?> cobranças</p>
</div>
</div>

<?php if ($validadeProvisoria): ?>
<div class="mt-4 rounded-2xl border border-secondary/20 bg-secondary-container/15 p-4 text-left flex items-start gap-2.5">
<span class="material-symbols-outlined text-secondary text-[20px] mt-0.5" style="font-variation-settings:'FILL' 1;">lock_clock</span>
<p class="text-[12px] text-on-surface-variant leading-relaxed">Validade provisória. Após a quitação da última mensalidade, o cartão é renovado por <b class="text-on-surface"><?php echo $validadeMeses; ?> meses</b>.</p>
</div>
<?php elseif ($planoQuitado && $validadeCartao !== ''): ?>
<div class="mt-4 rounded-2xl border border-secondary/20 bg-secondary-container/15 p-4 text-left flex items-start gap-2.5">
<span class="material-symbols-outlined text-secondary text-[20px] mt-0.5" style="font-variation-settings:'FILL' 1;">verified_user</span>
<p class="text-[12px] text-on-surface-variant leading-relaxed">Plano quitado. Durante a validade não há novas cobranças nem renovação automática.</p>
</div>
<?php endif; ?>

<?php if ($planoQuitado): ?>
<a href="parceiros.php" class="mt-4 w-full inline-flex items-center justify-center gap-2 bg-primary text-white font-bold py-3.5 rounded-2xl shadow-premium hover:bg-primary-container active:scale-[0.99] transition-all">
<span class="material-symbols-outlined text-[20px]">storefront</span> Ver lojas parceiras
</a>
<?php endif; ?>
</section>

<?php if ($podePagar && $cobrancaAtual): ?>
<!-- ===================== PAGAMENTO ===================== -->
<section class="reveal reveal-2 rounded-3xl bg-white/95 border border-white/70 premium-shadow overflow-hidden">
<div class="px-5 sm:px-6 pt-5 sm:pt-6 pb-4 border-b border-outline-variant/20">
<div class="flex items-start justify-between gap-3">
<div>
<span class="pill <?php echo $statusAtualVisual === 'atrasada' ? 'bg-red-50 text-error border-error/20' : 'status-waiting'; ?> px-2.5 py-1 text-[9.5px] uppercase tracking-wide">
<span class="material-symbols-outlined text-[13px]"><?php echo $statusAtualVisual === 'atrasada' ? 'error' : 'schedule'; ?></span> <?php echo $statusAtualVisual === 'atrasada' ? 'Cobrança atrasada' : 'Aguardando pagamento'; ?>
</span>
<p class="text-[10px] font-extrabold uppercase tracking-[0.18em] text-on-surface-variant/60 mt-3">Etapa <?php echo $etapaAtual; ?> de <?php echo $totalCobrancas; ?></p>
<h2 class="font-display text-[19px] font-extrabold text-on-surface mt-0.5"><?php echo htmlspecialchars($rotuloAtual); ?></h2>
<p class="font-display text-[34px] sm:text-[38px] font-extrabold text-primary leading-none mt-2">R$ <?php echo $valorFmt; ?></p>
<p class="text-[11px] text-on-surface-variant mt-2"><?php echo $etapaAtual === 1 ? 'Taxa única de adesão ao plano.' : 'Mensalidade do plano · liberada após a etapa anterior.'; ?></p>
</div>
<div class="w-11 h-11 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-[23px]" style="font-variation-settings:'FILL' 1;"><?php echo $etapaAtual === 1 ? 'workspace_premium' : 'calendar_month'; ?></span>
</div>
</div>
</div>

<div class="p-5 sm:p-6">
<div class="flex bg-surface-container rounded-2xl p-1 mb-5">
<button type="button" class="tab-btn tab-active flex-1 py-2.5 rounded-xl font-bold text-[12.5px] flex items-center justify-center gap-1.5 transition-all" id="tabPixBtn" onclick="mostrarAba('pix')">
<span class="material-symbols-outlined text-[18px]">qr_code_2</span> PIX
</button>
<button type="button" class="tab-btn flex-1 py-2.5 rounded-xl font-bold text-[12.5px] flex items-center justify-center gap-1.5 text-on-surface-variant transition-all" id="tabCardBtn" onclick="mostrarAba('card')">
<span class="material-symbols-outlined text-[18px]">credit_card</span> Cartão
</button>
</div>

<div id="painelPix">
<div class="rounded-2xl qr-frame border border-outline-variant/25 p-4 sm:p-5">
<div class="w-44 h-44 sm:w-48 sm:h-48 mx-auto bg-white rounded-2xl border border-outline-variant/25 flex items-center justify-center overflow-hidden" id="qrBox">
<?php if ($pix && $pix['qr_code_base64']): ?>
<img id="qrImg" class="w-full h-full object-contain p-1.5" src="data:image/png;base64,<?php echo $pix['qr_code_base64']; ?>" alt="QR Code PIX"/>
<?php else: ?>
<span class="material-symbols-outlined text-[62px] text-outline/35" id="qrIcon">qr_code_2</span>
<?php endif; ?>
</div>

<div class="mt-4 flex items-center justify-center gap-2 text-[11px] text-on-surface-variant">
<span class="relative flex h-2 w-2 text-amber-500">
<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-60"></span>
<span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
</span>
<?php echo $pix ? 'Aguardando confirmação automática do PIX' : 'Gere o QR Code para pagar com PIX'; ?>
</div>
</div>

<?php if ($pix && $pix['qr_code_copia_cola']): ?>
<div class="w-full mt-4" id="pixCodeBox">
<div class="flex items-center justify-between gap-2 mb-1.5">
<p class="text-[9.5px] font-extrabold uppercase tracking-[0.16em] text-on-surface-variant/70">PIX copia e cola</p>
<span class="inline-flex items-center gap-1 text-[9.5px] font-bold text-secondary"><span class="material-symbols-outlined text-[12px]" style="font-variation-settings:'FILL' 1;">lock</span> Seguro</span>
</div>
<div class="w-full bg-surface-container-low border border-outline-variant/25 rounded-2xl p-3 flex items-center gap-2">
<p class="font-mono text-[10.5px] text-on-surface-variant flex-1 break-all text-left" id="pixCodeText"><?php echo htmlspecialchars($pix['qr_code_copia_cola']); ?></p>
<button type="button" class="copy-btn shrink-0 inline-flex items-center gap-1 bg-primary text-white font-bold text-[10.5px] px-3 py-2 rounded-xl" onclick="copiarPix()">
<span class="material-symbols-outlined text-[15px]">content_copy</span> COPIAR
</button>
</div>
</div>
<div class="w-full mt-4" id="pixWaitBox">
<div class="rounded-2xl border border-outline-variant/20 bg-surface-container-low/60 px-4 py-3 flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-[17px] text-primary animate-spin">progress_activity</span>
<p class="text-[11.5px] font-semibold text-on-surface-variant">Aguardando pagamento...</p>
</div>
</div>
<?php else: ?>
<button type="button" id="btnGerarPix" class="mt-4 w-full bg-primary text-white font-bold py-3.5 rounded-2xl shadow-premium hover:bg-primary-container active:scale-[0.99] transition-all flex items-center justify-center gap-2" onclick="gerarPix()">
<span class="material-symbols-outlined text-[20px]">qr_code_2</span> GERAR QR CODE PIX
</button>
<div class="w-full mt-4 hidden" id="pixWaitBox">
<div class="rounded-2xl border border-outline-variant/20 bg-surface-container-low/60 px-4 py-3 flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-[17px] text-primary animate-spin">progress_activity</span>
<p class="text-[11.5px] font-semibold text-on-surface-variant">Aguardando pagamento...</p>
</div>
</div>
<?php endif; ?>
</div>

<div id="painelCard" class="hidden">
<div class="rounded-2xl border border-outline-variant/25 bg-surface-container-low/40 p-4 sm:p-5">
<p class="text-[11.5px] text-on-surface-variant mb-4 text-center">Pague com <b class="text-on-surface">cartão de crédito</b> o valor de <b class="text-on-surface">R$ <?php echo $valorFmt; ?></b>. A confirmação é imediata.</p>
<?php if ($asaasOk): ?>
<form id="cardForm" class="space-y-3">
<div>
<label class="text-[9.5px] font-extrabold uppercase tracking-[0.14em] text-on-surface-variant/75 mb-1 block" for="cardholderName">Nome no cartão</label>
<input id="cardholderName" name="cardholderName" type="text" autocomplete="off" class="premium-input w-full rounded-xl px-3.5 py-2.5 text-[13.5px]" placeholder="Nome impresso no cartão" required/>
</div>
<div>
<label class="text-[9.5px] font-extrabold uppercase tracking-[0.14em] text-on-surface-variant/75 mb-1 block" for="cardNumber">Número do cartão</label>
<input id="cardNumber" name="cardNumber" type="text" autocomplete="off" inputmode="numeric" class="premium-input w-full rounded-xl px-3.5 py-2.5 text-[13.5px]" placeholder="0000 0000 0000 0000" required/>
</div>
<div class="grid grid-cols-2 gap-3">
<div>
<label class="text-[9.5px] font-extrabold uppercase tracking-[0.14em] text-on-surface-variant/75 mb-1 block" for="cardExpirationDate">Vencimento</label>
<input id="cardExpirationDate" name="cardExpirationDate" type="text" autocomplete="off" inputmode="numeric" class="premium-input w-full rounded-xl px-3.5 py-2.5 text-[13.5px]" placeholder="MM/AA" required/>
</div>
<div>
<label class="text-[9.5px] font-extrabold uppercase tracking-[0.14em] text-on-surface-variant/75 mb-1 block" for="cardSecurityCode">Cód. segurança</label>
<input id="cardSecurityCode" name="cardSecurityCode" type="text" autocomplete="off" inputmode="numeric" maxlength="4" class="premium-input w-full rounded-xl px-3.5 py-2.5 text-[13.5px]" placeholder="123" required/>
</div>
</div>
<div class="grid grid-cols-2 gap-3">
<div>
<label class="text-[9.5px] font-extrabold uppercase tracking-[0.14em] text-on-surface-variant/75 mb-1 block" for="cardPostalCode">CEP do titular</label>
<input id="cardPostalCode" name="cardPostalCode" type="text" autocomplete="off" inputmode="numeric" value="<?php echo htmlspecialchars($cepUsuario); ?>" class="premium-input w-full rounded-xl px-3.5 py-2.5 text-[13.5px]" placeholder="00000-000" required/>
</div>
<div>
<label class="text-[9.5px] font-extrabold uppercase tracking-[0.14em] text-on-surface-variant/75 mb-1 block" for="cardAddressNumber">Número</label>
<input id="cardAddressNumber" name="cardAddressNumber" type="text" autocomplete="off" value="<?php echo htmlspecialchars($numEndereco); ?>" class="premium-input w-full rounded-xl px-3.5 py-2.5 text-[13.5px]" placeholder="Nº" required/>
</div>
</div>
<div id="cardErrorBox" class="hidden">
<div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-3.5 py-2.5 text-[12.5px]" id="cardErrorText"></div>
</div>
<button type="submit" id="cardPayBtn" class="w-full bg-primary text-white font-bold py-3.5 rounded-2xl shadow-premium hover:bg-primary-container active:scale-[0.99] transition-all flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-[19px]">lock</span> PAGAR R$ <?php echo $valorFmt; ?>
</button>
</form>
<?php else: ?>
<div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-3.5 py-2.5 text-[12.5px]">Pagamento por cartão indisponível no momento.</div>
<?php endif; ?>
</div>
</div>
</div>

<div class="px-5 sm:px-6 py-4 bg-surface-container-low/50 border-t border-outline-variant/20 flex items-start gap-2">
<span class="material-symbols-outlined text-secondary text-[18px] mt-0.5" style="font-variation-settings:'FILL' 1;">verified_user</span>
<p class="text-[10.5px] text-on-surface-variant leading-relaxed">Pagamento processado em ambiente seguro. A confirmação é automática e a próxima etapa só é liberada após a aprovação desta cobrança.</p>
</div>
</section>

<?php elseif (!$planoQuitado): ?>
<!-- ===================== AGUARDANDO LIBERAÇÃO ===================== -->
<section class="reveal reveal-2 rounded-3xl bg-white/95 border border-white/70 premium-shadow p-6 sm:p-8 text-center">
<div class="w-20 h-20 mx-auto rounded-full bg-primary/10 flex items-center justify-center mb-5">
<span class="material-symbols-outlined text-primary text-[42px]">schedule</span>
</div>
<span class="pill px-3 py-1.5 text-[10px] uppercase tracking-wide text-primary bg-primary/10 border-primary/10">
<span class="material-symbols-outlined text-[13px]">hourglass_top</span> Aguardando liberação
</span>
<h2 class="font-display text-[22px] font-extrabold text-on-surface mt-4">
<?php echo (($situacao === 'concluido' || $situacao === 'expirado')) ? 'Ciclo concluído' : 'Próxima cobrança em breve'; ?>
</h2>
<p class="text-[13.5px] text-on-surface-variant mt-2 max-w-sm mx-auto">
<?php echo htmlspecialchars($motivo !== '' ? $motivo : 'Nenhuma cobrança disponível no momento.'); ?>
</p>
<?php if (!empty($resumoPlano['cobranca_atual']['vencimento']) && $resumoPlano['cobranca_atual']['vencimento'] > $hoje): ?>
<div class="mt-5 rounded-2xl bg-surface-container-low border border-outline-variant/20 p-4 inline-flex items-center gap-2.5">
<span class="material-symbols-outlined text-primary text-[20px]">event</span>
<div class="text-left">
<p class="text-[9.5px] font-extrabold uppercase tracking-[0.16em] text-on-surface-variant/70">Próxima cobrança</p>
<p class="text-[14px] font-extrabold text-on-surface"><?php echo date('d/m/Y', strtotime($resumoPlano['cobranca_atual']['vencimento'])); ?></p>
</div>
</div>
<?php endif; ?>
<?php if (!empty($resumoPlano['validade_ate']) && $resumoPlano['validade_ate'] < $hoje): ?>
<p class="text-[12.5px] text-on-surface-variant mt-4">Sua validade terminou em <?php echo date('d/m/Y', strtotime($resumoPlano['validade_ate'])); ?>. Fale com o suporte para contratar um novo ciclo.</p>
<?php endif; ?>
<a href="dashboard.php" class="mt-5 w-full inline-flex items-center justify-center gap-2 bg-primary text-white font-bold py-3.5 rounded-2xl shadow-premium hover:bg-primary-container active:scale-[0.99] transition-all">
<span class="material-symbols-outlined text-[19px]">home</span> Voltar ao início
</a>
</section>
<?php endif; ?>

<!-- Selo de confiança -->
<section class="reveal reveal-3 rounded-3xl bg-white/70 border border-white/70 pix-shadow p-4 sm:p-5">
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-2 text-center sm:text-left">
<div class="flex items-center gap-2.5 sm:justify-center">
<span class="material-symbols-outlined text-primary text-[20px]">lock</span>
<div><p class="text-[11px] font-extrabold text-on-surface">Pagamento seguro</p><p class="text-[10px] text-on-surface-variant">Ambiente criptografado</p></div>
</div>
<div class="flex items-center gap-2.5 sm:justify-center">
<span class="material-symbols-outlined text-primary text-[20px]">bolt</span>
<div><p class="text-[11px] font-extrabold text-on-surface">Confirmação automática</p><p class="text-[10px] text-on-surface-variant">Atualização em tempo real</p></div>
</div>
<div class="flex items-center gap-2.5 sm:justify-center">
<span class="material-symbols-outlined text-primary text-[20px]">support_agent</span>
<div><p class="text-[11px] font-extrabold text-on-surface">Suporte disponível</p><p class="text-[10px] text-on-surface-variant">Fale com a gente</p></div>
</div>
</div>
</section>

<a class="text-primary font-bold text-[13px] hover:underline underline-offset-4 inline-flex items-center justify-center gap-1 mx-auto" href="parceiros.php">
Ver lojas parceiras <span class="material-symbols-outlined text-[17px]">arrow_forward</span>
</a>
</div>
</div>
</main>

<?php
require_once __DIR__ . '/bottom_nav.php';
app_bottom_nav([
    ['href' => 'dashboard.php', 'icone' => 'home', 'label' => 'Home'],
    ['href' => 'parceiros.php', 'icone' => 'storefront', 'label' => 'Parceiros'],
    ['href' => 'ativar.php', 'icone' => 'credit_card', 'label' => 'Cartão', 'ativo' => true],
    ['href' => 'perfil.php', 'icone' => 'person', 'label' => 'Perfil'],
]);
?>

<!-- Modal cartão ativado -->
<div id="modalAtivado" class="hidden fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm flex items-center justify-center p-6">
<div class="w-full max-w-sm bg-white rounded-3xl p-8 text-center shadow-2xl reveal">
<div class="w-20 h-20 mx-auto rounded-full bg-secondary-container/45 flex items-center justify-center mb-4">
<span class="material-symbols-outlined text-secondary text-[48px]" style="font-variation-settings:'FILL' 1;">check_circle</span>
</div>
<h2 class="font-display text-[22px] font-extrabold text-on-surface mb-2">Cartão Ativado</h2>
<p class="text-[13.5px] text-on-surface-variant mb-6">Pagamento confirmado! Seu cartão está ativo e as mensalidades já estão disponíveis em Meu Plano.</p>
<button class="block w-full bg-primary text-white font-bold py-3.5 rounded-2xl shadow-premium" onclick="location.reload()">Continuar</button>
</div>
</div>

<!-- Modal plano quitado (última mensalidade paga) -->
<div id="modalQuitado" class="hidden fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm flex items-center justify-center p-6">
<div class="w-full max-w-sm bg-white rounded-3xl p-8 text-center shadow-2xl reveal">
<div class="w-20 h-20 mx-auto rounded-full bg-secondary-container/45 flex items-center justify-center mb-4">
<span class="material-symbols-outlined text-secondary text-[48px]" style="font-variation-settings:'FILL' 1;">verified</span>
</div>
<h2 class="font-display text-[22px] font-extrabold text-on-surface mb-2">Plano quitado</h2>
<p class="text-[13.5px] text-on-surface-variant mb-6">Pagamento confirmado! Seu plano está totalmente quitado e o cartão tem validade de <?php echo $validadeMeses; ?> meses.</p>
<a href="parceiros.php" class="block w-full bg-primary text-white font-bold py-3.5 rounded-2xl shadow-premium">Ver lojas parceiras</a>
</div>
</div>

<!-- Modal pagamento confirmado (etapa intermediária) -->
<div id="modalConfirmado" class="hidden fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm flex items-center justify-center p-6">
<div class="w-full max-w-sm bg-white rounded-3xl p-8 text-center shadow-2xl reveal">
<div class="w-20 h-20 mx-auto rounded-full bg-primary/10 flex items-center justify-center mb-4">
<span class="material-symbols-outlined text-primary text-[48px]" style="font-variation-settings:'FILL' 1;">task_alt</span>
</div>
<h2 class="font-display text-[22px] font-extrabold text-on-surface mb-2">Pagamento confirmado</h2>
<p class="text-[13.5px] text-on-surface-variant mb-6">Sua cobrança foi registrada. A próxima etapa será liberada na data de vencimento.</p>
<button class="block w-full bg-primary text-white font-bold py-3.5 rounded-2xl shadow-premium" onclick="location.reload()">Continuar</button>
</div>
</div>

<script>
let pagamentoLocalId = <?php echo $pix ? (int)$pix['id'] : 0; ?>;
let polling = null;

function mostrarAba(aba) {
    const pixBtn = document.getElementById('tabPixBtn');
    const cardBtn = document.getElementById('tabCardBtn');
    const painelPix = document.getElementById('painelPix');
    const painelCard = document.getElementById('painelCard');
    if (!pixBtn || !cardBtn || !painelPix || !painelCard) return;
    const ativo = 'tab-active flex-1 py-2.5 rounded-xl font-bold text-[12.5px] flex items-center justify-center gap-1.5 transition-all';
    const inativo = 'tab-btn flex-1 py-2.5 rounded-xl font-bold text-[12.5px] flex items-center justify-center gap-1.5 text-on-surface-variant transition-all';
    if (aba === 'pix') {
        pixBtn.className = 'tab-btn ' + ativo;
        cardBtn.className = inativo;
        painelPix.classList.remove('hidden');
        painelCard.classList.add('hidden');
    } else {
        cardBtn.className = 'tab-btn ' + ativo;
        pixBtn.className = inativo;
        painelCard.classList.remove('hidden');
        painelPix.classList.add('hidden');
    }
}
mostrarAba('pix');

function gerarPix() {
    const btn = document.getElementById('btnGerarPix');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-outlined text-[20px] animate-spin">progress_activity</span> GERANDO...'; }
    fetch('criar_pix.php', { method: 'POST', body: new URLSearchParams({ plano_id: <?php echo $planoId; ?> }) })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'pending' && d.pix) {
                const qrBox = document.getElementById('qrBox');
                if (qrBox) qrBox.innerHTML = '<img id="qrImg" class="w-full h-full object-contain p-1.5" src="data:image/png;base64,' + d.pix.qr_code_base64 + '" alt="QR Code PIX"/>';
                const waitBox = document.getElementById('pixWaitBox');
                let codeBox = document.getElementById('pixCodeBox');
                if (!codeBox) {
                    const painel = document.getElementById('painelPix');
                    if (painel) {
                        codeBox = document.createElement('div');
                        codeBox.className = 'w-full mt-4';
                        codeBox.id = 'pixCodeBox';
                        codeBox.innerHTML = '<div class="flex items-center justify-between gap-2 mb-1.5"><p class="text-[9.5px] font-extrabold uppercase tracking-[0.16em] text-on-surface-variant/70">PIX copia e cola</p><span class="inline-flex items-center gap-1 text-[9.5px] font-bold text-secondary"><span class="material-symbols-outlined text-[12px]" style="font-variation-settings:\'FILL\' 1;">lock</span> Seguro</span></div><div class="w-full bg-surface-container-low border border-outline-variant/25 rounded-2xl p-3 flex items-center gap-2"><p class="font-mono text-[10.5px] text-on-surface-variant flex-1 break-all text-left" id="pixCodeText"></p><button type="button" class="copy-btn shrink-0 inline-flex items-center gap-1 bg-primary text-white font-bold text-[10.5px] px-3 py-2 rounded-xl" onclick="copiarPix()"><span class="material-symbols-outlined text-[15px]">content_copy</span> COPIAR</button></div>';
                        painel.insertBefore(codeBox, waitBox);
                    }
                }
                const codeText = document.getElementById('pixCodeText');
                if (codeText) codeText.textContent = d.pix.qr_code_copia_cola;
                if (waitBox) waitBox.classList.remove('hidden');
                if (btn) btn.remove();
                pagamentoLocalId = d.pix.id;
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

function copiarPix() {
    const el = document.getElementById('pixCodeText');
    const valor = el ? el.textContent : '';
    if (!valor) return;
    function sucesso() {
        const btn = document.querySelector('#pixCodeBox button');
        if (btn) {
            const original = btn.innerHTML;
            btn.innerHTML = '<span class="material-symbols-outlined text-[15px]">check</span> COPIADO!';
            setTimeout(() => { btn.innerHTML = original; }, 2000);
        }
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(valor).then(sucesso).catch(() => fallbackCopiar(valor, sucesso));
        return;
    }
    fallbackCopiar(valor, sucesso);
}
function fallbackCopiar(valor, cb) {
    const ta = document.createElement('textarea');
    ta.value = valor; ta.style.position = 'fixed'; ta.style.top = '-9999px';
    document.body.appendChild(ta); ta.focus(); ta.select();
    let ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(ta);
    if (ok) { if (cb) cb(); } else { alert('Selecione o código e copie manualmente.'); }
}

function verificarPagamento() {
    if (!pagamentoLocalId) return;
    fetch('verifica_pagamento.php?id=' + pagamentoLocalId)
        .then(r => r.json())
        .then(d => {
            if (d.status === 'approved') {
                if (polling) clearInterval(polling);
                if (d.concluiu) mostrarQuitado();
                else if (d.ativou) mostrarModalAtivado();
                else { mostrarConfirmado(); }
            } else if (d.status === 'rejected' || d.status === 'cancelled') {
                if (polling) clearInterval(polling);
                location.reload();
            }
        })
        .catch(() => {});
}
function mostrarModalAtivado() {
    document.getElementById('modalAtivado').classList.remove('hidden');
    setTimeout(() => location.reload(), 3000);
}
function mostrarQuitado() {
    document.getElementById('modalQuitado').classList.remove('hidden');
    setTimeout(() => location.reload(), 3500);
}
function mostrarConfirmado() {
    document.getElementById('modalConfirmado').classList.remove('hidden');
    setTimeout(() => location.reload(), 3000);
}
if (pagamentoLocalId) {
    polling = setInterval(verificarPagamento, 5000);
    verificarPagamento();
}

const cardForm = document.getElementById('cardForm');
const payBtn = document.getElementById('cardPayBtn');
const errorBox = document.getElementById('cardErrorBox');
const errorText = document.getElementById('cardErrorText');
function cardErro(msg) {
    if (!errorBox) return;
    errorText.textContent = msg;
    errorBox.classList.remove('hidden');
}
if (cardForm) {
    cardForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const venc = document.getElementById('cardExpirationDate').value.trim();
        const partes = venc.match(/^(\d{1,2})\s*\/?\s*(\d{2}|\d{4})$/);
        if (!partes) { cardErro('Validade inválida. Use o formato MM/AA.'); return; }
        let ano = partes[2];
        if (ano.length === 2) ano = '20' + ano;
        errorBox.classList.add('hidden');
        payBtn.disabled = true;
        payBtn.innerHTML = '<span class="material-symbols-outlined text-[20px] animate-spin">progress_activity</span> PROCESSANDO...';
        const body = new URLSearchParams({
            plano_id: <?php echo $planoId; ?>,
            holder_name: document.getElementById('cardholderName').value.trim(),
            number: document.getElementById('cardNumber').value.replace(/\D/g, ''),
            expiry: partes[1].padStart(2, '0') + '/' + ano,
            ccc: document.getElementById('cardSecurityCode').value.replace(/\D/g, ''),
            postal_code: document.getElementById('cardPostalCode').value.replace(/\D/g, ''),
            address_number: document.getElementById('cardAddressNumber').value.trim()
        });
        fetch('processa_pagamento_cartao.php', { method: 'POST', body: body })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'approved') {
                    if (d.concluiu) mostrarQuitado();
                    else if (d.ativou) mostrarModalAtivado();
                    else mostrarConfirmado();
                } else if (d.status === 'pending') {
                    setTimeout(() => location.reload(), 3000);
                } else {
                    payBtn.disabled = false;
                    payBtn.innerHTML = '<span class="material-symbols-outlined text-[19px]">lock</span> PAGAR R$ <?php echo $valorFmt; ?>';
                    cardErro(d.message || 'Pagamento recusado. Tente novamente.');
                }
            })
            .catch(() => {
                payBtn.disabled = false;
                payBtn.innerHTML = '<span class="material-symbols-outlined text-[19px]">lock</span> PAGAR R$ <?php echo $valorFmt; ?>';
                cardErro('Erro ao processar o pagamento. Tente novamente.');
            });
    });
}
</script>
</body>
</html>
