<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../usuario');
    exit;
}
if (ehDependente()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_id'])) {
    $delId = (int)$_POST['excluir_id'];
    $dUid = (int)$_SESSION['usuario_id'];
    $stmtD = $conn->prepare("DELETE FROM dependentes WHERE id = ? AND usuario_id = ?");
    $stmtD->bind_param('ii', $delId, $dUid);
    $stmtD->execute();
    header('Location: dependentes.php?removido=1');
    exit;
}
$removido = isset($_GET['removido']);

$paginaAtiva = 'dependentes';
$tituloPagina = 'Dependentes';
require_once __DIR__ . '/_head.php';

$salvo = false;
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid  = (int)$_SESSION['usuario_id'];
    $nome = trim($_POST['nome'] ?? '');
    $whats = trim($_POST['whatsapp'] ?? '');
    $cpf  = preg_replace('/\D/', '', trim($_POST['cpf'] ?? ''));
    $end  = trim($_POST['endereco'] ?? '');

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
$uidDep = (int)$_SESSION['usuario_id'];
$dep->bind_param('i', $uidDep);
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
<div class="mb-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
<div class="lg:col-span-3 bg-white rounded-2xl border border-outline-variant p-6 card-shadow">
<div class="flex items-center justify-between gap-4 mb-4">
<div>
<p class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">Dependentes cadastrados</p>
<p class="text-xs text-on-surface-variant mt-0.5">Você pode cadastrar até 5 dependentes no seu cartão.</p>
</div>
<p class="text-2xl font-extrabold text-primary"><?php echo $qtdDep; ?><span class="text-base text-on-surface-variant font-semibold">/5</span></p>
</div>
<div class="h-2.5 bg-surface-variant rounded-full overflow-hidden">
<div class="h-2.5 bg-secondary rounded-full transition-all duration-500" style="width:<?php echo max(4, min(100, round($qtdDep / 5 * 100))); ?>%"></div>
</div>
</div>

<?php if ($salvo): ?>
<div class="lg:col-span-3 p-4 rounded-xl bg-[#b6f570]/20 border border-[#3e6a00]/30 text-[#2e5000] text-sm font-semibold">Dependente cadastrado com sucesso!</div>
<?php endif; ?>
<?php if ($removido): ?>
<div class="lg:col-span-3 p-4 rounded-xl bg-[#ffdad6] border border-red-200 text-[#93000a] text-sm font-semibold">Dependente removido.</div>
<?php endif; ?>
<?php if ($erro !== ''): ?>
<div class="lg:col-span-3 p-4 rounded-xl bg-[#ffdad6] border border-red-200 text-[#93000a] text-sm font-semibold"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<div class="bg-white rounded-2xl border border-outline-variant p-6 card-shadow">
<h3 class="text-lg font-extrabold text-on-surface mb-4 flex items-center gap-2">
<span class="material-symbols-outlined text-primary">person_add</span> Cadastrar dependente
</h3>
<?php if ($qtdDep >= 5): ?>
<div class="py-10 text-center">
<span class="material-symbols-outlined text-on-surface-variant text-[44px]">block</span>
<p class="text-sm text-on-surface-variant mt-3">Limite de dependentes atingido. Nenhum novo cadastro é permitido.</p>
</div>
<?php else: ?>
<form method="POST" action="dependentes.php" class="space-y-4">
<div>
<label class="block text-xs font-bold text-on-surface-variant uppercase mb-1.5">Nome *</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">badge</span>
<input name="nome" type="text" maxlength="255" required placeholder="Nome do dependente" class="w-full border border-outline-variant rounded-xl px-4 py-3 pl-12 focus:outline-none focus:ring-2 focus:ring-[#51036d]/30 text-sm"/>
</div>
</div>
<div>
<label class="block text-xs font-bold text-on-surface-variant uppercase mb-1.5">WhatsApp</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">call</span>
<input name="whatsapp" id="web-whatsapp" type="tel" placeholder="(00) 00000-0000" class="w-full border border-outline-variant rounded-xl px-4 py-3 pl-12 focus:outline-none focus:ring-2 focus:ring-[#51036d]/30 text-sm"/>
</div>
</div>
<div>
<label class="block text-xs font-bold text-on-surface-variant uppercase mb-1.5">CPF</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">credit_score</span>
<input name="cpf" id="web-cpf" type="text" inputmode="numeric" placeholder="000.000.000-00" class="w-full border border-outline-variant rounded-xl px-4 py-3 pl-12 focus:outline-none focus:ring-2 focus:ring-[#51036d]/30 text-sm"/>
</div>
</div>
<div>
<label class="block text-xs font-bold text-on-surface-variant uppercase mb-1.5">Endereço completo</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">home_pin</span>
<input name="endereco" type="text" maxlength="500" placeholder="Rua, número, bairro, cidade, estado e CEP" class="w-full border border-outline-variant rounded-xl px-4 py-3 pl-12 focus:outline-none focus:ring-2 focus:ring-[#51036d]/30 text-sm"/>
</div>
</div>
<button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-primary hover:bg-primary-dark text-white font-bold text-sm rounded-xl px-6 py-3.5 transition active:scale-[0.99]">
<span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">person_add</span> Cadastrar dependente
</button>
</form>
<?php endif; ?>
</div>

<div class="bg-white rounded-2xl border border-outline-variant p-6 card-shadow">
<h3 class="text-lg font-extrabold text-on-surface mb-4 flex items-center gap-2">
<span class="material-symbols-outlined text-primary">groups</span> Seus dependentes
</h3>
<?php if ($qtdDep === 0): ?>
<div class="py-12 text-center">
<span class="material-symbols-outlined text-on-surface-variant text-[48px]">groups</span>
<p class="text-sm text-on-surface-variant mt-3">Nenhum dependente cadastrado ainda.</p>
</div>
<?php else: ?>
<div class="space-y-3">
<?php while ($dd = $dependentes->fetch_assoc()): ?>
<div class="flex items-center gap-2 bg-surface-container-low rounded-xl border border-outline-variant/40 p-2 pl-4">
<button type="button" onclick="abrirDetalhe(<?php echo (int)$dd['id']; ?>)" class="flex-1 flex items-center gap-4 min-w-0 text-left hover:border-primary/40 transition active:scale-[0.99]">
<div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">person</span>
</div>
<div class="flex-1 min-w-0">
<p class="font-bold text-sm text-on-surface truncate"><?php echo htmlspecialchars($dd['nome']); ?></p>
<p class="text-[13px] text-on-surface-variant mt-0.5"><?php echo htmlspecialchars(fmtCpf($dd['cpf']) ?: 'CPF não informado'); ?></p>
</div>
<span class="material-symbols-outlined text-on-surface-variant shrink-0">chevron_right</span>
</button>
<form method="POST" action="dependentes.php" class="shrink-0 pr-1" data-nome="<?php echo htmlspecialchars($dd['nome'], ENT_QUOTES); ?>" onsubmit="return confirmarExclusaoDep(event, this)">
<input type="hidden" name="excluir_id" value="<?php echo (int)$dd['id']; ?>"/>
<button type="submit" class="w-10 h-10 flex items-center justify-center rounded-full text-[#ba1a1a] hover:bg-[#ffdad6]/60 transition active:scale-95 duration-200" title="Excluir dependente" aria-label="Excluir dependente">
<span class="material-symbols-outlined">delete</span>
</button>
</form>
</div>
<?php endwhile; ?>
</div>
<?php endif; ?>
</div>
</div>

<!-- Detalhes -->
<div class="hidden fixed inset-0 z-[90] bg-black/60 backdrop-blur-sm items-center justify-center p-6" id="detalheModal">
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
<p class="font-bold text-on-surface truncate" id="det-nome"></p>
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
        const webCpf = document.getElementById('web-cpf');
        if (webCpf) webCpf.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            if (value.length > 9) value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            else if (value.length > 6) value = value.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
            else if (value.length > 3) value = value.replace(/(\d{3})(\d{3})/, '$1.$2');
            e.target.value = value;
        });
        const webWhats = document.getElementById('web-whatsapp');
        if (webWhats) webWhats.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            if (value.length > 2) value = value.replace(/(\d{2})(\d{0,5})(\d{0,4})/, (m, a, b, c) => '(' + a + ')' + (b ? ' ' + b : '') + (c ? '-' + c : ''));
            e.target.value = value;
        });
    </script>
<?php require_once __DIR__ . '/_foot.php'; ?>