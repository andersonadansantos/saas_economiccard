<?php
require_once 'config.php';
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
exigirCartaoAtivo();
if (ehDependente()) {
    header('Location: dashboard.php');
    exit;
}
$uid = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'encerrar_conta') {
    $stmt = $conn->prepare("UPDATE usuarios SET status = 'desativado', cartao_ativo = 0 WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    session_unset();
    session_destroy();
    header('Location: login.php?conta_encerrada=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cpf  = trim($_POST['cpf'] ?? '');
    $rg   = trim($_POST['rg'] ?? '');
    $nasc = trim($_POST['nascimento'] ?? '');
    $end  = trim($_POST['endereco'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $cep     = trim($_POST['cep'] ?? '');
    $whats   = trim($_POST['whatsapp'] ?? '');
    $avatar  = trim($_POST['avatar_atual'] ?? '');
    $salvo = false;
    $avErro = '';
    if (isset($_FILES['avatar']) && is_array($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['avatar']['tmp_name'];
        $info = @getimagesize($tmp);
        $exts = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if ($info !== false && isset($exts[$info['mime']])) {
            $dirUpload = __DIR__ . '/uploads';
            if (!is_dir($dirUpload)) { @mkdir($dirUpload, 0777, true); }
            $nomeArq = 'avatar_' . $uid . '_' . time() . '.' . $exts[$info['mime']];
            if (move_uploaded_file($tmp, $dirUpload . '/' . $nomeArq)) {
                $avatar = 'uploads/' . $nomeArq;
            } else {
                $avErro = 'Não foi possível salvar a foto. Verifique a permissão da pasta uploads.';
            }
        } else {
            $avErro = 'Formato de imagem inválido. Envie uma foto PNG, JPG, WEBP ou GIF.';
        }
    } elseif (isset($_FILES['avatar']) && is_array($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $avErro = 'Falha no envio da foto (código ' . (int)$_FILES['avatar']['error'] . '). Tente novamente com uma imagem menor.';
    }
    if (isset($_POST['remover_avatar'])) {
        $avatar = '';
    }
    if ($nome !== '') {
        $stmt = $conn->prepare("UPDATE usuarios SET nome=?, email=?, cpf=?, rg=?, nascimento=?, endereco=?, cidade=?, cep=?, whatsapp=?, avatar=? WHERE id=?");
        $stmt->bind_param('ssssssssssi', $nome, $email, $cpf, $rg, $nasc, $end, $cidade, $cep, $whats, $avatar, $uid);
        $stmt->execute();
        $salvo = true;
    }
}

$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if (!$u) { header('Location: logout.php'); exit; }
$resumoPlano = plano_resumo_usuario($conn, $uid);
?>
<!DOCTYPE html><html class="light" lang="pt-BR"><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport">
<title>Economic Card - Perfil</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "primary-container": "#6a2585", "surface-dim": "#d9dadb", "outline-variant": "#d1c2d1", "error": "#ba1a1a", "tertiary-fixed-dim": "#ffb877", "tertiary": "#4a2700", "on-secondary-fixed": "#0f2000", "on-tertiary": "#ffffff", "on-background": "#191c1d", "tertiary-container": "#6a3a00", "surface": "#f8f9fa", "tertiary-fixed": "#ffdcc0", "on-primary-container": "#e397fd", "on-secondary-container": "#427000", "surface-container": "#edeeef", "on-primary-fixed": "#320046", "error-container": "#ffdad6", "surface-tint": "#8540a0", "on-secondary": "#ffffff", "on-surface-variant": "#4e434f", "on-error": "#ffffff", "surface-container-low": "#f3f4f5", "surface-container-lowest": "#ffffff", "secondary-fixed-dim": "#9cd857", "on-tertiary-fixed": "#2e1600", "inverse-on-surface": "#f0f1f2", "primary-fixed": "#f9d8ff", "surface-variant": "#e1e3e4", "on-secondary-fixed-variant": "#2e5000", "secondary-fixed": "#b6f570", "on-error-container": "#93000a", "on-primary": "#ffffff", "surface-container-high": "#e7e8e9", "on-primary-fixed-variant": "#6b2686", "on-surface": "#191c1d", "secondary-container": "#b6f570", "inverse-surface": "#2e3132", "on-tertiary-fixed-variant": "#6c3b00", "surface-bright": "#f8f9fa", "background": "#f8f9fa", "surface-container-highest": "#e1e3e4", "primary-fixed-dim": "#edb1ff", "on-tertiary-container": "#ff9d35", "outline": "#7f7381", "secondary": "#3e6a00", "primary": "#51036d", "inverse-primary": "#edb1ff"
                    },
                    "borderRadius": { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
                    "spacing": { "container-padding": "20px", "xl": "48px", "xs": "4px", "card-gutter": "16px", "md": "24px", "lg": "32px", "sm": "12px", "base": "8px" },
                    "fontFamily": { "display-lg": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "body-md": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "label-caps": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "label-bold": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "body-lg": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "headline-sm": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"], "headline-md": ["Inter", "ui-sans-serif", "system-ui", "sans-serif"] },
                    "fontSize": {
                        "display-lg": ["30px", { "lineHeight": "1.15", "letterSpacing": "-0.03em", "fontWeight": "700" }], "body-md": ["15px", { "lineHeight": "1.45", "fontWeight": "400" }], "label-caps": ["11px", { "lineHeight": "1", "letterSpacing": "0.06em", "fontWeight": "600" }], "label-bold": ["13px", { "lineHeight": "1", "letterSpacing": "0.01em", "fontWeight": "700" }], "body-lg": ["17px", { "lineHeight": "1.5", "fontWeight": "400" }], "headline-sm": ["18px", { "lineHeight": "1.3", "letterSpacing": "-0.01em", "fontWeight": "600" }], "headline-md": ["22px", { "lineHeight": "1.25", "letterSpacing": "-0.02em", "fontWeight": "700" }]
                    }
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
<link rel="stylesheet" href="ui.css?v=<?php echo (int)@filemtime(__DIR__ . '/ui.css'); ?>"/>
</head>
<body class="min-h-screen pb-32 ec-body">
<!-- TopAppBar -->
<header class="ec-appbar" id="appBar">
<div class="ec-appbar__inner">
<a class="ec-iconbtn" href="dashboard.php" aria-label="Voltar">
<span class="material-symbols-outlined">arrow_back</span>
</a>
<span class="text-[15px] font-extrabold text-on-surface tracking-tight">Perfil</span>
<div class="ec-appbar__spacer"></div>
<a class="ec-iconbtn ec-iconbtn--muted" href="dashboard.php" aria-label="Notificações">
<span class="material-symbols-outlined">notifications</span>
</a>
</div>
</header>
<main class="ec-shell">
<!-- Identificação -->
<section class="ec-card ec-fade-in">
<div class="ec-card__body" style="display:flex;flex-direction:column;align-items:center;text-align:center;padding-top:24px">
<div class="relative">
<div class="w-32 h-32 rounded-full overflow-hidden bg-surface-container-high flex items-center justify-center" style="box-shadow:0 0 0 3px #fff,0 0 0 6px rgba(182,245,112,.85)">
<?php if ($u['avatar']): ?>
<img class="w-full h-full object-cover" src="<?php echo htmlspecialchars(asset_url($u['avatar'])); ?>" alt="Foto de perfil" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"/>
<span class="material-symbols-outlined text-primary text-[44px] hidden" style="font-variation-settings: 'FILL' 1;">person</span>
<?php else: ?>
<span class="material-symbols-outlined text-primary text-[44px]" style="font-variation-settings: 'FILL' 1;">person</span>
<?php endif; ?>
</div>
<button type="button" class="absolute -bottom-1 -right-1 w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center" style="border:2px solid #fff;box-shadow:0 10px 22px -12px rgba(81,3,109,.9)" onclick="document.getElementById('avatarInput').click()" aria-label="Alterar foto">
<span class="material-symbols-outlined text-[19px]" style="font-variation-settings: 'FILL' 1;">photo_camera</span>
</button>
</div>
<h2 class="ec-h2 mt-4"><?php echo htmlspecialchars($u['nome']); ?></h2>
<span class="ec-chip ec-chip--primary mt-2">Usuário do cartão</span>
<p class="ec-meta mt-2"><?php echo htmlspecialchars($u['email'] ?? ''); ?><?php echo !empty($u['cpf']) ? ' · CPF ' . htmlspecialchars($u['cpf']) : ''; ?></p>
</div>
</section>
<?php if (isset($salvo) && $salvo): ?>
<div class="ec-alert ec-alert--success"><span class="material-symbols-outlined text-[19px]">check_circle</span><span>Dados salvos com sucesso!</span></div>
<?php endif; ?>
<?php if (isset($avErro) && $avErro !== ''): ?>
<div class="ec-alert ec-alert--error"><span class="material-symbols-outlined text-[19px]">error</span><span><?php echo htmlspecialchars($avErro); ?></span></div>
<?php endif; ?>
<?php
$totalCobrancasPlano = max(1, (int)($resumoPlano['total_cobrancas'] ?? 1));
$qtdMensalidadesPlano = max(1, (int)($resumoPlano['qtd_mensalidades'] ?? 1));
$planoQuitado = ((int)$resumoPlano['pagas'] >= $totalCobrancasPlano);
?>
<section class="ec-section">
<div class="ec-section__head">
<span class="ec-eyebrow">Meu plano</span>
<?php if ($planoQuitado): ?>
<span class="ec-chip ec-chip--success">Quitado</span>
<?php else: ?>
<span class="ec-chip ec-chip--primary"><?php echo (int)$resumoPlano['pagas']; ?>/<?php echo $totalCobrancasPlano; ?> pagas</span>
<?php endif; ?>
</div>
<div class="ec-card">
<div class="ec-card__body">
<div class="flex items-center gap-3">
<span class="ec-tile ec-tile--soft"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">credit_score</span></span>
<div class="min-w-0">
<p class="text-[13px] font-extrabold text-on-surface leading-tight">Assinatura Economic Card</p>
<p class="ec-meta mt-0.5">Adesão R$ <?php echo number_format((float)$resumoPlano['valor_adesao'], 2, ',', '.'); ?> · <?php echo $qtdMensalidadesPlano; ?>x R$ <?php echo number_format((float)$resumoPlano['valor_mensal'], 2, ',', '.'); ?></p>
</div>
</div>
<div class="ec-progress mt-4">
<div class="ec-progress__bar" style="width:<?php echo max(2, min(100, (int)round(((int)$resumoPlano['pagas'] / $totalCobrancasPlano) * 100))); ?>%"></div>
</div>
<div class="mt-2">
<?php foreach ($resumoPlano['cobrancas'] as $seq => $cob): $statusVisualCob = $cob['status_visual'] ?? plano_status_visual_cobranca($cob); ?>
<div class="flex items-center justify-between gap-3 py-2.5 border-b border-outline-variant/20 last:border-b-0">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-[17px] <?php echo $cob['status'] === 'pago' ? 'text-secondary' : ($statusVisualCob === 'atrasada' ? 'text-error' : 'text-outline'); ?>" style="font-variation-settings:'FILL' 1;"><?php echo $cob['status'] === 'pago' ? 'check_circle' : 'schedule'; ?></span>
<span class="text-[12.5px] text-on-surface truncate"><?php echo htmlspecialchars(plano_rotulo_cobranca($seq, $cob['tipo'] ?? '')); ?></span>
</div>
<div class="text-right shrink-0">
<span class="text-[12.5px] font-bold text-on-surface">R$ <?php echo number_format((float)$cob['valor'], 2, ',', '.'); ?></span>
<p class="text-[10.5px] text-on-surface-variant leading-tight"><?php echo htmlspecialchars(plano_rotulo_status_cobranca($statusVisualCob)); ?><?php echo !empty($cob['pago_em']) ? ' · ' . date('d/m/Y', strtotime($cob['pago_em'])) : ''; ?></p>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
<div class="ec-card__foot" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 16px;align-items:start">
<div><p class="ec-meta">Ativação</p><p class="text-[12px] font-bold text-on-surface mt-0.5"><?php echo !empty($resumoPlano['ativado_em']) ? date('d/m/Y', strtotime($resumoPlano['ativado_em'])) : '-'; ?></p></div>
<div><p class="ec-meta">Vencimento</p><p class="text-[12px] font-bold text-on-surface mt-0.5"><?php echo !empty($resumoPlano['validade_ate']) ? date('d/m/Y', strtotime($resumoPlano['validade_ate'])) : '-'; ?></p></div>
<div><p class="ec-meta">Status do cartão</p><p class="text-[12px] font-bold mt-0.5 <?php echo $u['cartao_ativo'] ? 'text-secondary' : 'text-error'; ?>"><?php echo $u['cartao_ativo'] ? 'Ativo' : 'Inativo'; ?></p></div>
<div><p class="ec-meta">Tempo restante</p><p class="text-[12px] font-bold text-on-surface mt-0.5"><?php echo $resumoPlano['dias_restantes'] !== null ? (int)$resumoPlano['dias_restantes'] . ' dias' : '-'; ?></p></div>
<div class="col-span-2"><p class="ec-meta">Total (<?php echo $qtdMensalidadesPlano + 1; ?> cobranças)</p><p class="text-[12px] font-bold text-on-surface mt-0.5">R$ <?php echo number_format((float)$resumoPlano['valor_total'], 2, ',', '.'); ?></p></div>
</div>
</div>
</section>
<form class="ec-section" method="POST" action="perfil.php" enctype="multipart/form-data">
<input type="hidden" name="avatar_atual" value="<?php echo htmlspecialchars($u['avatar'] ?? ''); ?>"/>
<input type="file" id="avatarInput" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden"/>
<div class="ec-section__head">
<span class="ec-eyebrow">Dados pessoais</span>
</div>
<div class="ec-card">
<div class="ec-card__body">
<div class="flex flex-col gap-3.5">
<?php if ($u['avatar']): ?>
<label class="flex items-center gap-2 text-[12.5px] font-semibold text-error cursor-pointer">
<input type="checkbox" name="remover_avatar" class="w-4 h-4 accent-error"> Remover foto de perfil
</label>
<?php endif; ?>
<div class="ec-field">
<label class="ec-field__label" for="nome">Nome completo</label>
<div class="relative">
<span class="material-symbols-outlined ec-field__icon">person</span>
<input class="ec-input ec-input--icon" id="nome" name="nome" type="text" value="<?php echo htmlspecialchars($u['nome']); ?>" required/>
</div>
</div>
<div class="ec-field">
<label class="ec-field__label" for="email">E-mail</label>
<div class="relative">
<span class="material-symbols-outlined ec-field__icon">mail</span>
<input class="ec-input ec-input--icon" id="email" name="email" type="email" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>"/>
</div>
</div>
<div class="grid grid-cols-2 gap-3">
<div class="ec-field">
<label class="ec-field__label" for="cpf">CPF</label>
<input class="ec-input" id="cpf" name="cpf" type="text" inputmode="numeric" value="<?php echo htmlspecialchars($u['cpf']); ?>" required/>
</div>
<div class="ec-field">
<label class="ec-field__label" for="rg">RG</label>
<input class="ec-input" id="rg" name="rg" type="text" value="<?php echo htmlspecialchars($u['rg'] ?? ''); ?>"/>
</div>
</div>
<div class="ec-field">
<label class="ec-field__label" for="nascimento">Data de nascimento</label>
<div class="relative">
<span class="material-symbols-outlined ec-field__icon">calendar_month</span>
<input class="ec-input ec-input--icon" id="nascimento" name="nascimento" type="date" value="<?php echo htmlspecialchars($u['nascimento'] ?? ''); ?>"/>
</div>
</div>
<div class="ec-field">
<label class="ec-field__label" for="endereco">Endereço completo</label>
<div class="relative">
<span class="material-symbols-outlined ec-field__icon">location_on</span>
<input class="ec-input ec-input--icon" id="endereco" name="endereco" type="text" value="<?php echo htmlspecialchars($u['endereco'] ?? ''); ?>"/>
</div>
</div>
<div class="grid grid-cols-2 gap-3">
<div class="ec-field">
<label class="ec-field__label" for="cidade">Cidade</label>
<input class="ec-input" id="cidade" name="cidade" type="text" value="<?php echo htmlspecialchars($u['cidade'] ?? ''); ?>"/>
</div>
<div class="ec-field">
<label class="ec-field__label" for="cep">CEP</label>
<input class="ec-input" id="cep" name="cep" type="text" inputmode="numeric" value="<?php echo htmlspecialchars($u['cep'] ?? ''); ?>"/>
</div>
</div>
<div class="ec-field">
<label class="ec-field__label" for="whatsapp">WhatsApp</label>
<div class="relative">
<span class="material-symbols-outlined ec-field__icon">call</span>
<input class="ec-input ec-input--icon" id="whatsapp" name="whatsapp" type="tel" value="<?php echo htmlspecialchars($u['whatsapp'] ?? ''); ?>"/>
</div>
</div>
</div>
</div>
<div class="ec-card__foot" style="padding:14px 18px">
<button class="ec-btn ec-btn--secondary ec-btn--block" type="submit">
Salvar alterações
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">save</span>
</button>
</div>
</div>
<div class="ec-card ec-card--flush" style="margin-top:12px">
<div style="padding:4px 10px">
<a class="ec-row" href="gerar_contrato.php">
<span class="ec-tile ec-tile--soft"><span class="material-symbols-outlined">description</span></span>
<span class="ec-row__grow">
<span class="block text-[13px] font-bold text-on-surface leading-tight">Contrato de uso do cartão</span>
<span class="ec-meta">Baixar o documento em PDF</span>
</span>
<span class="material-symbols-outlined ec-row__chev">download</span>
</a>
</div>
</div>
<div class="ec-card ec-card--flush" style="margin-top:12px;border-color:rgba(186,26,26,.18)">
<div style="padding:4px 10px">
<button class="ec-row w-full text-left" type="button" onclick="abrirEncerrar()">
<span class="ec-tile" style="background:rgba(186,26,26,.08);color:#93000a"><span class="material-symbols-outlined">delete</span></span>
<span class="ec-row__grow">
<span class="block text-[13px] font-bold text-error leading-tight">Excluir conta</span>
<span class="ec-meta">Desativar o acesso ao cartão e benefícios</span>
</span>
<span class="material-symbols-outlined ec-row__chev">chevron_right</span>
</button>
</div>
</div>
</form>
</main>
<!-- BottomNavBar -->
<?php
require_once __DIR__ . '/bottom_nav.php';
app_bottom_nav([
    ['href' => 'dashboard.php', 'icone' => 'home', 'label' => 'Home'],
    ['href' => 'parceiros.php', 'icone' => 'storefront', 'label' => 'Parceiros'],
    ['href' => 'ativar.php', 'icone' => 'credit_card', 'label' => 'Cartão'],
    ['href' => 'perfil.php', 'icone' => 'person', 'label' => 'Perfil', 'ativo' => true],
]);
?>
<script>
        const cpfInput = document.getElementById('cpf');
        if (cpfInput) cpfInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            if (value.length > 9) value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            else if (value.length > 6) value = value.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
            else if (value.length > 3) value = value.replace(/(\d{3})(\d{3})/, '$1.$2');
            e.target.value = value;
        });
        const cepInput = document.getElementById('cep');
        if (cepInput) cepInput.addEventListener('input', (e) => { let value = e.target.value.replace(/\D/g, ''); if (value.length > 8) value = value.slice(0,8); if (value.length > 5) value = value.replace(/(\d{5})(\d{3})/, '$1-$2'); e.target.value = value; });
        const avatarInput = document.getElementById('avatarInput');
        if (avatarInput) avatarInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const img = new Image();
            img.onload = function() { img.classList.add('w-full', 'h-full', 'object-cover'); };
            img.src = URL.createObjectURL(file);
            const wrapper = document.querySelector('.w-32.h-32');
            if (wrapper) {
                wrapper.innerHTML = '';
                wrapper.appendChild(img);
            }
        });
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => { input.addEventListener('focus', () => input.parentElement.parentElement.classList.add('scale-[1.01]')); input.addEventListener('blur', () => input.parentElement.parentElement.classList.remove('scale-[1.01]')); });
        function abrirEncerrar() {
            const el = document.getElementById('encerrarModal');
            el.classList.remove('hidden'); el.classList.add('flex');
        }
        function fecharEncerrar() {
            const el = document.getElementById('encerrarModal');
            el.classList.add('hidden'); el.classList.remove('flex');
        }
    </script>
    <!-- Encerrar conta modal -->
    <div class="hidden fixed inset-0 z-[90] bg-black/70 backdrop-blur-sm items-center justify-center p-5" id="encerrarModal">
    <div class="ec-sheet">
    <div class="ec-sheet__head">
    <div class="flex items-center gap-2 min-w-0">
    <span class="ec-tile" style="width:34px;height:34px;border-radius:11px;background:rgba(186,26,26,.08);color:#93000a"><span class="material-symbols-outlined" style="font-size:18px;font-variation-settings:'FILL' 1;">delete_forever</span></span>
    <span class="text-[12.5px] font-extrabold text-on-surface uppercase tracking-wide truncate">Encerrar conta</span>
    </div>
    <button class="ec-iconbtn ec-iconbtn--muted" style="width:34px;height:34px" onclick="fecharEncerrar()" aria-label="Fechar"><span class="material-symbols-outlined" style="font-size:20px">close</span></button>
    </div>
    <div class="ec-sheet__body">
    <p class="text-[13.5px] leading-relaxed text-on-surface-variant">Tem certeza que deseja <b class="text-error">encerrar sua conta</b> no Economic Card?</p>
    <p class="text-[13.5px] leading-relaxed text-on-surface-variant mt-2">Seu acesso ao cartão e aos benefícios será <b>desativado</b>. Esta ação pode ser revertida pela administração.</p>
    </div>
    <div class="ec-sheet__foot" style="justify-content:flex-end;gap:10px">
    <button class="ec-btn ec-btn--ghost ec-btn--sm" type="button" onclick="fecharEncerrar()">Cancelar</button>
    <form method="POST" action="perfil.php" style="margin:0">
    <input type="hidden" name="acao" value="encerrar_conta"/>
    <button class="ec-btn ec-btn--sm" type="submit" style="background:#93000a;color:#fff">
    <span class="material-symbols-outlined" style="font-size:18px">logout</span> Sim, encerrar
    </button>
    </form>
    </div>
    </div>
    </div>
</body></html>