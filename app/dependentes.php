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
</head>
<body class="min-h-screen pb-32">
<!-- TopAppBar -->
<header class="fixed top-0 w-full z-50 bg-surface/80 dark:bg-surface-dim/80 backdrop-blur-xl shadow-sm h-16 flex items-center justify-between px-container-padding">
<a class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-surface-container-high/50 transition-colors active:scale-95 duration-200" href="dashboard.php">
<span class="material-symbols-outlined text-primary">arrow_back</span>
</a>
<h1 class="font-headline-md text-headline-md text-primary">Dependentes</h1>
<span class="w-10 h-10 flex items-center justify-center rounded-full text-primary">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">family_restroom</span>
</span>
</header>
<main class="pt-24 px-container-padding max-w-2xl mx-auto">

<?php if ($salvo): ?>
<div class="mb-md p-md rounded-xl bg-secondary-container/30 border border-secondary/30 text-on-secondary-container font-headline-sm text-center">Dependente cadastrado com sucesso!</div>
<?php endif; ?>
<?php if ($removido): ?>
<div class="mb-md p-md rounded-xl bg-error-container/30 border border-error/30 text-on-error-container font-headline-sm text-center">Dependente removido.</div>
<?php endif; ?>
<?php if ($erro !== ''): ?>
<div class="mb-md p-md rounded-xl bg-error-container/30 border border-error/30 text-on-error-container font-headline-sm text-center"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<!-- Status do limite -->
<div class="mb-lg bg-white rounded-2xl border border-outline-variant/30 p-md card-shadow" style="box-shadow:0 4px 20px rgba(0,0,0,0.05);">
<div class="flex items-center justify-between mb-sm">
<p class="font-label-caps text-label-caps text-on-surface-variant uppercase tracking-wider">Dependentes cadastrados</p>
<p class="font-headline-sm text-headline-sm text-primary"><?php echo $qtdDep; ?><span class="text-on-surface-variant text-[13px] font-normal">/5</span></p>
</div>
<div class="h-2 bg-surface-variant rounded-full overflow-hidden">
<div class="h-2 bg-secondary rounded-full transition-all duration-500" style="width:<?php echo max(4, min(100, round($qtdDep / 5 * 100))); ?>%"></div>
</div>
</div>

<!-- Cadastro -->
<section class="mb-lg bg-white rounded-2xl border border-outline-variant/30 p-lg" style="box-shadow:0 4px 20px rgba(0,0,0,0.05);">
<h2 class="font-headline-md text-headline-md text-on-surface mb-sm">Cadastrar dependente</h2>
<div class="flex items-center gap-base mb-md bg-primary/5 border border-primary/20 rounded-xl px-md py-sm">
<span class="material-symbols-outlined text-primary text-[20px]">info</span>
<p class="font-label-bold text-label-bold text-primary">Você pode cadastrar até 5 dependentes no seu cartão.</p>
</div>
<?php if ($qtdDep >= 5): ?>
<div class="py-md text-center">
<span class="material-symbols-outlined text-on-surface-variant text-[40px]">block</span>
<p class="font-body-md text-on-surface-variant mt-sm">Limite de dependentes atingido. Nenhum novo cadastro é permitido.</p>
</div>
<?php else: ?>
<form class="space-y-md" method="POST" action="dependentes.php">
<div class="input-group relative">
<label class="font-label-bold text-label-bold text-on-surface-variant ml-1 mb-base block">NOME *</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">badge</span>
<input class="w-full bg-[#F1F3F5] border-transparent border-b-2 border-b-transparent focus:border-b-primary focus:ring-0 rounded-xl py-3.5 pl-12 pr-4 font-body-md text-on-surface transition-all" id="nome" name="nome" type="text" maxlength="255" placeholder="Nome do dependente" required/>
</div>
</div>
<div class="input-group relative">
<label class="font-label-bold text-label-bold text-on-surface-variant ml-1 mb-base block">WHATSAPP</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">call</span>
<input class="w-full bg-[#F1F3F5] border-transparent border-b-2 border-b-transparent focus:border-b-primary focus:ring-0 rounded-xl py-3.5 pl-12 pr-4 font-body-md text-on-surface transition-all" id="whatsapp" name="whatsapp" type="tel" placeholder="(00) 00000-0000"/>
</div>
</div>
<div class="input-group relative">
<label class="font-label-bold text-label-bold text-on-surface-variant ml-1 mb-base block">CPF</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">credit_score</span>
<input class="w-full bg-[#F1F3F5] border-transparent border-b-2 border-b-transparent focus:border-b-primary focus:ring-0 rounded-xl py-3.5 pl-12 pr-4 font-body-md text-on-surface transition-all" id="cpf" name="cpf" type="text" inputmode="numeric" placeholder="000.000.000-00"/>
</div>
</div>
<div class="input-group relative">
<label class="font-label-bold text-label-bold text-on-surface-variant ml-1 mb-base block">ENDEREÇO COMPLETO</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">home_pin</span>
<input class="w-full bg-[#F1F3F5] border-transparent border-b-2 border-b-transparent focus:border-b-primary focus:ring-0 rounded-xl py-3.5 pl-12 pr-4 font-body-md text-on-surface transition-all" id="endereco" name="endereco" type="text" maxlength="500" placeholder="Rua, número, bairro, cidade, estado e CEP"/>
</div>
</div>
<div class="pt-md pb-md">
<button class="w-full bg-primary hover:bg-primary-container active:scale-95 text-on-primary rounded-full py-4 font-headline-sm text-headline-sm flex items-center justify-center gap-sm transition-all select-none" type="submit">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">person_add</span>
                    CADASTRAR DEPENDENTE
                </button>
</div>
</form>
<?php endif; ?>
</section>

<!-- Lista de dependentes -->
<section>
<h2 class="font-headline-md text-headline-md text-on-surface mb-sm">Seus dependentes</h2>
<?php if ($qtdDep === 0): ?>
<div class="bg-white rounded-2xl border border-outline-variant/30 p-lg text-center" style="box-shadow:0 4px 20px rgba(0,0,0,0.05);">
<span class="material-symbols-outlined text-on-surface-variant text-[44px]">groups</span>
<p class="font-body-md text-on-surface-variant mt-sm">Nenhum dependente cadastrado ainda.</p>
</div>
<?php else: ?>
<div class="space-y-sm">
<?php while ($dd = $dependentes->fetch_assoc()): ?>
<div class="flex items-center gap-md bg-white rounded-2xl border border-outline-variant/30 p-2 pl-md" style="box-shadow:0 4px 20px rgba(0,0,0,0.05);">
<button type="button" class="flex-1 flex items-center gap-md min-w-0 text-left" onclick="abrirDetalhe(<?php echo (int)$dd['id']; ?>)">
<div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">person</span>
</div>
<div class="flex-1 min-w-0">
<p class="font-headline-sm text-headline-sm text-on-surface truncate"><?php echo htmlspecialchars($dd['nome']); ?></p>
<p class="font-label-caps text-label-caps text-on-surface-variant mt-0.5"><?php echo htmlspecialchars(fmtCpf($dd['cpf']) ?: 'CPF não informado'); ?></p>
</div>
<span class="material-symbols-outlined text-on-surface-variant shrink-0">chevron_right</span>
</button>
<form method="POST" action="dependentes.php" class="shrink-0 pr-1" data-nome="<?php echo htmlspecialchars($dd['nome'], ENT_QUOTES); ?>" onsubmit="return confirmarExclusaoDep(event, this)">
<input type="hidden" name="excluir_id" value="<?php echo (int)$dd['id']; ?>"/>
<button type="submit" class="w-11 h-11 flex items-center justify-center rounded-full text-error hover:bg-error-container/40 transition active:scale-95 duration-200" title="Excluir dependente" aria-label="Excluir dependente">
<span class="material-symbols-outlined">delete</span>
</button>
</form>
</div>
<?php endwhile; ?>
</div>
<?php endif; ?>
</section>
</main>

<!-- Modal de detalhes -->
<div id="detalheModal" class="hidden fixed inset-0 z-[90] bg-black/60 backdrop-blur-sm items-center justify-center p-6">
<div class="w-full max-w-md bg-white rounded-2xl shadow-2xl border border-outline-variant overflow-hidden">
<div class="px-5 py-4 border-b border-outline-variant flex items-center justify-between gap-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-primary text-[20px] shrink-0" style="font-variation-settings: 'FILL' 1;">family_restroom</span>
<span class="font-bold text-sm text-on-surface uppercase truncate">DADOS DO DEPENDENTE</span>
</div>
<button class="material-symbols-outlined text-on-surface-variant hover:bg-surface-variant/50 p-1.5 rounded-full shrink-0" onclick="fecharDetalhe()">close</button>
</div>
<div class="px-5 py-5">
<div class="flex items-center gap-3 mb-4">
<div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">person</span>
</div>
<div class="min-w-0">
<p class="font-headline-sm text-headline-sm text-on-surface truncate" id="det-nome"></p>
<p class="text-[11px] text-on-surface-variant" id="det-criado"></p>
</div>
</div>
<div class="space-y-2">
<div class="flex items-start gap-3 bg-surface-container-low rounded-xl px-4 py-3">
<span class="material-symbols-outlined text-primary text-[20px] shrink-0">credit_score</span>
<div><p class="text-[11px] font-bold uppercase text-on-surface-variant">CPF</p><p class="text-sm font-semibold text-on-surface mt-0.5" id="det-cpf"></p></div>
</div>
<div class="flex items-start gap-3 bg-surface-container-low rounded-xl px-4 py-3">
<span class="material-symbols-outlined text-primary text-[20px] shrink-0">call</span>
<div><p class="text-[11px] font-bold uppercase text-on-surface-variant">WhatsApp</p><p class="text-sm font-semibold text-on-surface mt-0.5" id="det-whats"></p></div>
</div>
<div class="flex items-start gap-3 bg-surface-container-low rounded-xl px-4 py-3">
<span class="material-symbols-outlined text-primary text-[20px] shrink-0">home_pin</span>
<div><p class="text-[11px] font-bold uppercase text-on-surface-variant">Endereço</p><p class="text-sm font-semibold text-on-surface mt-0.5" id="det-end"></p></div>
</div>
</div>
</div>
<div class="px-5 py-4 border-t border-outline-variant flex items-center justify-end">
<a class="inline-flex items-center gap-2 text-sm font-bold text-[#128C7E] hover:opacity-80 transition" id="det-whats-link" target="_blank" rel="noopener">
<span class="material-symbols-outlined text-[18px]">chat</span> Falar no WhatsApp
</a>
</div>
</div>
</div>

<!-- BottomNavBar -->
<nav class="fixed bottom-0 w-full z-50 rounded-t-xl bg-surface/80 dark:bg-surface-dim/80 backdrop-blur-md border-t border-outline-variant/30 shadow-[0_-4px_20px_rgba(0,0,0,0.05)]">
<div class="flex justify-around items-center w-full py-2 pb-safe px-2">
<a class="flex flex-col items-center justify-center text-on-surface-variant dark:text-outline px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="dashboard.php">
<span class="material-symbols-outlined">home</span>
<span class="font-label-bold text-label-bold mt-1">Home</span>
</a>
<a class="flex flex-col items-center justify-center text-on-surface-variant dark:text-outline px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="parceiros.php">
<span class="material-symbols-outlined">storefront</span>
<span class="font-label-bold text-label-bold mt-1">Parceiros</span>
</a>
<a class="flex flex-col items-center justify-center text-on-surface-variant dark:text-outline px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="ativar.php">
<span class="material-symbols-outlined">credit_card</span>
<span class="font-label-bold text-label-bold mt-1">Cartão</span>
</a>
<a class="flex flex-col items-center justify-center text-on-surface-variant dark:text-outline px-4 py-1.5 hover:opacity-80 transition-opacity active:scale-90 duration-200" href="perfil.php">
<span class="material-symbols-outlined">person</span>
<span class="font-label-bold text-label-bold mt-1">Perfil</span>
</a>
</div>
</nav>
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