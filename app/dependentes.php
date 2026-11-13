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
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if (!$u) { header('Location: logout.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_id'])) {
    $delId = (int)$_POST['excluir_id'];
    $stmtD = $conn->prepare("DELETE FROM dependentes WHERE id = ? AND usuario_id = ?");
    $stmtD->bind_param('ii', $delId, $uid);
    $stmtD->execute();
    header('Location: dependentes.php?removido=1');
    exit;
}
$removido = isset($_GET['removido']);

$salvo = false;
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $whats = trim($_POST['whatsapp'] ?? '');
    $cpf   = preg_replace('/\D/', '', trim($_POST['cpf'] ?? ''));
    $end   = trim($_POST['endereco'] ?? '');

    if ($nome === '') {
        $erro = 'Informe o nome do dependente.';
    } elseif (strlen($nome) > 255) {
        $erro = 'O nome informado é muito longo.';
    } elseif ($cpf !== '' && !cpf_valido($cpf)) {
        $erro = 'CPF inválido. Verifique os números digitados.';
    } else {
        $q = $conn->prepare("SELECT COUNT(*) AS total FROM dependentes WHERE usuario_id = ?");
        $q->bind_param('i', $uid);
        $q->execute();
        $totalDep = (int)$q->get_result()->fetch_assoc()['total'];
        if ($totalDep >= 5) {
            $erro = 'Limite de 5 dependentes atingido. Remova um dependente para cadastrar outro.';
        } else {
            $ins = $conn->prepare("INSERT INTO dependentes (usuario_id, nome, whatsapp, cpf, endereco) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param('issss', $uid, $nome, $whats, $cpf, $end);
            $ins->execute();
            $salvo = true;
        }
    }
}

$dep = $conn->prepare("SELECT * FROM dependentes WHERE usuario_id = ? ORDER BY criado_em DESC, id DESC");
$dep->bind_param('i', $uid);
$dep->execute();
$dependentes = $dep->get_result();
$qtdDep = (int)$dependentes->num_rows;
$depArr = [];
foreach ($dependentes as $dd) {
    $depArr[] = [
        'id' => (int)$dd['id'],
        'nome' => $dd['nome'],
        'whatsapp' => $dd['whatsapp'],
        'cpf' => $dd['cpf'],
        'endereco' => $dd['endereco'],
        'criado_em' => $dd['criado_em'],
    ];
}
if ($dependentes->num_rows > 0) { $dependentes->data_seek(0); }

if (!function_exists('fmtCpf')) {
    function fmtCpf($cpf) {
        $digits = preg_replace('/\D/', '', (string)$cpf);
        if (strlen($digits) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
        return $cpf;
    }
}
if (!function_exists('fmtWhats')) {
    function fmtWhats($whats) {
        $digits = preg_replace('/\D/', '', (string)$whats);
        if (strlen($digits) === 11) return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits);
        if (strlen($digits) === 10) return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits);
        return $whats;
    }
}
?>
<!DOCTYPE html><html class="light" lang="pt-BR"><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport">
<title>Economic Card - Dependentes</title>
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
        body { background-color: #f8f9fa; color: #191c1d; font-family: 'Inter', 'Manrope', sans-serif; -webkit-tap-highlight-color: transparent; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .fab-shadow { box-shadow: 0 8px 30px rgba(106, 37, 133, 0.15); }
    </style>
<link rel="stylesheet" href="ui.css"/>
</head>
<body class="min-h-screen pb-32 ec-body">
<!-- TopAppBar -->
<header class="ec-appbar" id="appBar">
<div class="ec-appbar__inner">
<a class="ec-iconbtn" href="dashboard.php" aria-label="Voltar">
<span class="material-symbols-outlined">arrow_back</span>
</a>
<span class="text-[15px] font-extrabold text-on-surface tracking-tight">Dependentes</span>
<div class="ec-appbar__spacer"></div>
<span class="ec-chip ec-chip--neutral"><?php echo $qtdDep; ?>/5</span>
</div>
</header>
<main class="ec-shell">

<?php if ($salvo): ?>
<div class="ec-alert ec-alert--success"><span class="material-symbols-outlined text-[19px]">check_circle</span><span>Dependente cadastrado com sucesso!</span></div>
<?php endif; ?>
<?php if ($removido): ?>
<div class="ec-alert ec-alert--info"><span class="material-symbols-outlined text-[19px]">person_remove</span><span>Dependente removido.</span></div>
<?php endif; ?>
<?php if ($erro !== ''): ?>
<div class="ec-alert ec-alert--error"><span class="material-symbols-outlined text-[19px]">error</span><span><?php echo htmlspecialchars($erro); ?></span></div>
<?php endif; ?>

<!-- Status do limite -->
<section>
<div class="ec-card">
<div class="ec-card__body" style="padding:16px 18px">
<div class="flex items-center justify-between gap-3">
<div class="flex items-center gap-2.5 min-w-0">
<span class="ec-tile ec-tile--soft"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">groups</span></span>
<div class="min-w-0">
<p class="text-[13px] font-extrabold text-on-surface leading-tight">Dependentes cadastrados</p>
<p class="ec-meta mt-0.5">Limite de 5 por cartão</p>
</div>
</div>
<p class="text-[19px] font-extrabold text-primary leading-none ec-num"><?php echo $qtdDep; ?><span class="text-[12px] font-bold text-on-surface-variant">/5</span></p>
</div>
<div class="ec-progress mt-3.5">
<div class="ec-progress__bar" style="width:<?php echo max(4, min(100, (int)round($qtdDep / 5 * 100))); ?>%"></div>
</div>
</div>
</div>
</section>

<!-- Cadastro -->
<section class="ec-section">
<div class="ec-section__head">
<span class="ec-eyebrow">Cadastrar dependente</span>
</div>
<div class="ec-card">
<div class="ec-card__body">
<?php if ($qtdDep >= 5): ?>
<div class="text-center" style="padding:10px 4px">
<span class="ec-tile" style="margin:0 auto;width:52px;height:52px;border-radius:16px"><span class="material-symbols-outlined">block</span></span>
<p class="text-[13.5px] font-bold text-on-surface mt-3">Limite atingido</p>
<p class="ec-meta mt-1">Você já possui 5 dependentes. Remova um para cadastrar outro.</p>
</div>
<?php else: ?>
<div class="flex items-start gap-2" style="background:rgba(81,3,109,.05);border:1px solid rgba(81,3,109,.14);border-radius:14px;padding:11px 13px;margin-bottom:16px">
<span class="material-symbols-outlined" style="font-size:18px;color:var(--ec-primary);flex:0 0 auto">info</span>
<p class="text-[12px] leading-snug" style="color:var(--ec-primary)">Você pode cadastrar até 5 dependentes no seu cartão.</p>
</div>
<form method="POST" action="dependentes.php">
<div class="flex flex-col gap-3.5">
<div class="ec-field">
<label class="ec-field__label" for="nome">Nome *</label>
<div class="relative">
<span class="material-symbols-outlined ec-field__icon">badge</span>
<input class="ec-input ec-input--icon" id="nome" name="nome" type="text" maxlength="255" placeholder="Nome do dependente" required/>
</div>
</div>
<div class="ec-field">
<label class="ec-field__label" for="whatsapp">WhatsApp</label>
<div class="relative">
<span class="material-symbols-outlined ec-field__icon">call</span>
<input class="ec-input ec-input--icon" id="whatsapp" name="whatsapp" type="tel" placeholder="(00) 00000-0000"/>
</div>
</div>
<div class="ec-field">
<label class="ec-field__label" for="cpf">CPF</label>
<div class="relative">
<span class="material-symbols-outlined ec-field__icon">credit_score</span>
<input class="ec-input ec-input--icon" id="cpf" name="cpf" type="text" inputmode="numeric" placeholder="000.000.000-00"/>
</div>
</div>
<div class="ec-field">
<label class="ec-field__label" for="endereco">Endereço completo</label>
<div class="relative">
<span class="material-symbols-outlined ec-field__icon">home_pin</span>
<input class="ec-input ec-input--icon" id="endereco" name="endereco" type="text" maxlength="500" placeholder="Rua, número, bairro, cidade, estado e CEP"/>
</div>
</div>
<button class="ec-btn ec-btn--primary ec-btn--block" type="submit">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">person_add</span>
Cadastrar dependente
</button>
</div>
</form>
<?php endif; ?>
</div>
</div>
</section>

<!-- Lista de dependentes -->
<section class="ec-section">
<div class="ec-section__head">
<span class="ec-eyebrow">Seus dependentes</span>
<span class="ec-meta ec-num"><?php echo $qtdDep; ?> cadastrado<?php echo $qtdDep === 1 ? '' : 's'; ?></span>
</div>
<?php if ($qtdDep === 0): ?>
<div class="ec-card">
<div class="ec-card__body" style="text-align:center;padding:34px 20px">
<span class="ec-tile" style="margin:0 auto;width:52px;height:52px;border-radius:16px"><span class="material-symbols-outlined">groups</span></span>
<p class="text-[13.5px] font-bold text-on-surface mt-3">Nenhum dependente cadastrado</p>
<p class="ec-meta mt-1">Preencha o formulário acima para adicionar.</p>
</div>
</div>
<?php else: ?>
<div class="ec-stack ec-stack--sm">
<?php while ($dd = $dependentes->fetch_assoc()): ?>
<div class="ec-card">
<div class="flex items-center gap-1" style="padding:8px 8px 8px 12px">
<button type="button" class="flex-1 flex items-center gap-3 min-w-0 text-left" style="background:none;border:0;padding:0;cursor:pointer" onclick="abrirDetalhe(<?php echo (int)$dd['id']; ?>)">
<span class="ec-avatar-sm" style="width:42px;height:42px"><span class="material-symbols-outlined" style="font-size:22px;font-variation-settings:'FILL' 1;">person</span></span>
<span class="flex-1 min-w-0">
<span class="block text-[13.5px] font-extrabold text-on-surface truncate"><?php echo htmlspecialchars($dd['nome']); ?></span>
<span class="ec-meta block mt-0.5 ec-num"><?php echo htmlspecialchars(fmtCpf($dd['cpf']) ?: 'CPF não informado'); ?></span>
</span>
<span class="material-symbols-outlined" style="color:#b3aab8;font-size:20px;flex:0 0 auto">chevron_right</span>
</button>
<form method="POST" action="dependentes.php" class="shrink-0" data-nome="<?php echo htmlspecialchars($dd['nome'], ENT_QUOTES); ?>" onsubmit="return confirmarExclusaoDep(event, this)">
<input type="hidden" name="excluir_id" value="<?php echo (int)$dd['id']; ?>"/>
<button type="submit" class="ec-iconbtn" style="width:38px;height:38px;color:#93000a;background:rgba(186,26,26,.06)" title="Excluir dependente" aria-label="Excluir dependente">
<span class="material-symbols-outlined" style="font-size:19px">delete</span>
</button>
</form>
</div>
</div>
<?php endwhile; ?>
</div>
<?php endif; ?>
</section>
</main>

<!-- Modal de detalhes -->
<div id="detalheModal" class="hidden fixed inset-0 z-[90] bg-black/60 backdrop-blur-sm items-center justify-center p-5">
<div class="ec-sheet">
<div class="ec-sheet__head">
<div class="flex items-center gap-2 min-w-0">
<span class="ec-tile" style="width:34px;height:34px;border-radius:11px"><span class="material-symbols-outlined" style="font-size:18px;font-variation-settings:'FILL' 1;">family_restroom</span></span>
<span class="text-[12.5px] font-extrabold text-on-surface uppercase tracking-wide truncate">Dados do dependente</span>
</div>
<button class="ec-iconbtn ec-iconbtn--muted" style="width:34px;height:34px" onclick="fecharDetalhe()" aria-label="Fechar"><span class="material-symbols-outlined" style="font-size:20px">close</span></button>
</div>
<div class="ec-sheet__body">
<div class="flex items-center gap-3 mb-4">
<span class="ec-avatar-sm" style="width:48px;height:48px"><span class="material-symbols-outlined" style="font-size:24px;font-variation-settings:'FILL' 1;">person</span></span>
<div class="min-w-0">
<p class="text-[14px] font-extrabold text-on-surface truncate" id="det-nome"></p>
<p class="ec-meta" id="det-criado"></p>
</div>
</div>
<div class="flex flex-col gap-2">
<div class="flex items-start gap-3" style="background:#f4f5f6;border-radius:14px;padding:12px 14px">
<span class="material-symbols-outlined" style="font-size:19px;color:var(--ec-primary);flex:0 0 auto">credit_score</span>
<div class="min-w-0"><p class="ec-field__label">CPF</p><p class="text-[13px] font-bold text-on-surface mt-0.5 ec-num" id="det-cpf"></p></div>
</div>
<div class="flex items-start gap-3" style="background:#f4f5f6;border-radius:14px;padding:12px 14px">
<span class="material-symbols-outlined" style="font-size:19px;color:var(--ec-primary);flex:0 0 auto">call</span>
<div class="min-w-0"><p class="ec-field__label">WhatsApp</p><p class="text-[13px] font-bold text-on-surface mt-0.5" id="det-whats"></p></div>
</div>
<div class="flex items-start gap-3" style="background:#f4f5f6;border-radius:14px;padding:12px 14px">
<span class="material-symbols-outlined" style="font-size:19px;color:var(--ec-primary);flex:0 0 auto">home_pin</span>
<div class="min-w-0"><p class="ec-field__label">Endereço</p><p class="text-[13px] font-bold text-on-surface mt-0.5" id="det-end"></p></div>
</div>
</div>
</div>
<div class="ec-sheet__foot" style="justify-content:space-between">
<button class="ec-btn ec-btn--ghost ec-btn--sm" onclick="fecharDetalhe()">Fechar</button>
<a class="ec-btn ec-btn--sm" style="background:rgba(37,211,102,.12);color:#128C7E;border:1px solid rgba(37,211,102,.35)" id="det-whats-link" target="_blank" rel="noopener">
<span class="material-symbols-outlined">chat</span> WhatsApp
</a>
</div>
</div>
</div>

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
        const deps = <?php echo json_encode($depArr, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        function confirmarExclusaoDep(e, form) {
            const nome = form.dataset.nome || 'este dependente';
            if (!window.confirm('Excluir o dependente "' + nome + '"? Esta ação não pode ser desfeita.')) {
                e.preventDefault();
                return false;
            }
            return true;
        }

        function abrirDetalhe(id) {
            const d = deps.find(x => x.id === id);
            if (!d) return;
            document.getElementById('det-nome').textContent = d.nome || '-';
            document.getElementById('det-cpf').textContent = d.cpf ? fmtCpfJs(d.cpf) : 'Não informado';
            document.getElementById('det-whats').textContent = d.whatsapp ? fmtWhatsJs(d.whatsapp) : 'Não informado';
            document.getElementById('det-end').textContent = d.endereco || 'Não informado';
            document.getElementById('det-criado').textContent = d.criado_em ? 'Cadastrado em ' + new Date(d.criado_em).toLocaleDateString('pt-BR') : '';
            const wa = d.whatsapp ? d.whatsapp.replace(/\D/g, '') : '';
            const link = document.getElementById('det-whats-link');
            if (wa) { link.href = 'https://wa.me/' + wa; link.style.display = 'inline-flex'; }
            else { link.style.display = 'none'; }
            const modal = document.getElementById('detalheModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        function fecharDetalhe() {
            const modal = document.getElementById('detalheModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        document.getElementById('detalheModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('detalheModal')) fecharDetalhe();
        });
        function fmtCpfJs(v) {
            const d = v.replace(/\D/g, '');
            if (d.length === 11) return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            return v;
        }
        function fmtWhatsJs(v) {
            const d = v.replace(/\D/g, '');
            if (d.length === 11) return '(' + d.slice(0,2) + ') ' + d.slice(2,7) + '-' + d.slice(7);
            if (d.length === 10) return '(' + d.slice(0,2) + ') ' + d.slice(2,6) + '-' + d.slice(6);
            return v;
        }
        const cpf2 = document.getElementById('cpf');
        if (cpf2) cpf2.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            if (value.length > 9) value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            else if (value.length > 6) value = value.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
            else if (value.length > 3) value = value.replace(/(\d{3})(\d{3})/, '$1.$2');
            e.target.value = value;
        });
        const whats = document.getElementById('whatsapp');
        if (whats) whats.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            if (value.length > 6) value = value.replace(/(\d{0,2})(\d{0,5})(\d{0,4})/, (m, a, b, c) => [a, b, c].filter(Boolean).join(' '));
            e.target.value = value;
        });
    </script>
</body>
</html>