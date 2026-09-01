<?php
$paginaAtiva = 'ativar';
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../usuario');
    exit;
}
$bloqueado = isset($_GET['bloqueado']);
$rowStatus = $conn->query("SELECT cartao_ativo FROM usuarios WHERE id = " . (int)$_SESSION['usuario_id'])->fetch_assoc();
$tituloPagina = ($rowStatus['cartao_ativo'] ?? 0) ? 'Cartão Ativado' : 'Ativar Cartão';
require_once __DIR__ . '/_head.php';

$final = $u['final_cartao'] ?: '8829';
$cartaoAtivado = $pers['cartao_ativado'] ?? '';
$cartaoDesativado = $pers['cartao_desativado'] ?? '';
$cartaoImg = $u['cartao_ativo'] ? $cartaoAtivado : $cartaoDesativado;

$planos = [];
$pr = $conn->query("SELECT * FROM planos WHERE ativo = 1 ORDER BY ordem, id");
while ($p = $pr->fetch_assoc()) { $planos[] = $p; }
require_once __DIR__ . '/../asaas_pix.php';
$asaasOk = asaas_config($conn) !== null;
$cepUsuario = preg_replace('/\D/', '', (string)($u['cep'] ?? ''));
$numEndereco = '';
if (!empty($u['endereco']) && preg_match('/(\d+)/', (string)$u['endereco'], $mNum)) {
    $numEndereco = $mNum[1];
}
$pix = null;
if (!$u['cartao_ativo']) {
    $stmt = $conn->prepare("SELECT * FROM pagamentos_pix WHERE usuario_id = ? AND status = 'pending' AND pix_validade > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $pix = $stmt->get_result()->fetch_assoc();
}
$valAdesaoGlobal = 0.0;
$pPers = $conn->query("SELECT valor_adesao FROM personalizacao WHERE id = 1")->fetch_assoc();
if ($pPers) $valAdesaoGlobal = (float)($pPers['valor_adesao'] ?? 0);
$adesaoPaga = (int)($u['adesao_paga'] ?? 0) === 1;
$planoMensal = null;
foreach ($planos as $pl) {
    if (strtolower(trim((string)$pl['nome'])) === 'mensal') { $planoMensal = $pl; break; }
}
?>
<section class="grid grid-cols-1 lg:grid-cols-2 gap-8">
<!-- Card -->
<div>
<div class="bg-white rounded-2xl card-shadow overflow-hidden">
<div class="px-6 py-4 border-b border-outline-variant">
<h3 class="font-extrabold text-on-surface">Seu Cartão</h3>
</div>
<div class="p-6">
<?php if ($cartaoImg): ?>
<img class="w-full h-auto rounded-xl object-contain" src="<?php echo webUrl($cartaoImg); ?>" alt="Cartão Economic Card"/>
<?php else: ?>
<div class="premium-gradient rounded-xl p-6 shadow-lg relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between">
<div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
<div class="flex justify-between items-start z-10">
<div class="space-y-1"><span class="text-[10px] text-white/60 tracking-widest font-semibold">MEMBRO PLATINUM</span><div class="h-[2px] w-8 bg-secondary-container"></div></div>
<?php if ($u['cartao_ativo']): ?>
<span class="px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 bg-secondary text-white"><span class="w-1.5 h-1.5 rounded-full bg-white/80"></span> ATIVADO</span>
<?php else: ?>
<span class="px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 bg-[#ba1a1a] text-white"><span class="w-1.5 h-1.5 rounded-full bg-white/80"></span> EXPIRADO</span>
<?php endif; ?>
</div>
<div class="z-10 mt-auto">
<p class="text-white text-lg font-extrabold mb-4"><?php echo htmlspecialchars(strtoupper($u['nome'])); ?></p>
<div class="flex justify-between items-end">
<div class="space-y-1"><p class="text-[13px] text-white/70 font-bold tracking-widest">•••• •••• •••• <?php echo htmlspecialchars($final); ?></p><p class="text-[11px] text-white/50 font-semibold">VALIDADE 08/25</p></div>
<p class="text-white/20 text-3xl font-extrabold italic">Economic</p>
</div>
</div>
</div>
<?php endif; ?>
<div class="mt-6 text-center">
<?php if ($u['cartao_ativo']): ?>
<h4 class="text-xl font-extrabold text-secondary flex items-center justify-center gap-2"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">check_circle</span> Cartão já ativado</h4>
<p class="text-sm text-on-surface-variant mt-1">Você já está liberado para usar seus descontos.</p>
<?php else: ?>
<h4 class="text-xl font-extrabold text-on-surface">Você precisa ativar seu cartão</h4>
<p class="text-sm text-on-surface-variant mt-1">Escolha um plano abaixo para ativar seus descontos.</p>
<?php endif; ?>
</div>
</div>
</div>
</div>

<!-- PIX -->
<div>
<?php if ($u['cartao_ativo']): ?>
<div class="bg-white rounded-2xl card-shadow p-10 text-center">
<div class="w-20 h-20 mx-auto rounded-full bg-secondary/10 flex items-center justify-center mb-4">
<span class="material-symbols-outlined text-secondary text-[44px]" style="font-variation-settings:'FILL' 1;">check_circle</span>
</div>
<h3 class="text-xl font-extrabold text-on-surface uppercase mb-2">Cartão Ativado</h3>
<p class="text-sm text-on-surface-variant mb-6">Seu cartão foi ativado! Aproveite os descontos nas lojas parceiras!</p>
<a href="parceiros.php" class="inline-block bg-primary hover:bg-primary-dark text-white font-bold px-8 py-3 rounded-full shadow-lg transition">Ver lojas parceiras</a>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl card-shadow p-8">
<div class="mb-6">
<p class="text-[10px] font-bold text-on-surface-variant uppercase mb-2 text-center">1. Escolha seu plano</p>
<div class="flex flex-col gap-2">
<?php foreach ($planos as $i => $pl): $plValor = number_format((float)($pl['valor_mensal'] ?: $pl['valor_adesao'] ?: $pl['valor']), 2, ',', '.'); ?>
<?php $ehMensal = $planoMensal && (int)$pl['id'] === (int)$planoMensal['id']; ?>
<div class="flex items-center gap-3 border border-outline-variant rounded-xl px-4 py-3 cursor-pointer transition-all plan-card <?php echo ($i === 0) ? 'border-primary bg-primary/5' : ''; ?> <?php echo $ehMensal ? 'border-amber-300 bg-amber-50/60' : ''; ?>" data-id="<?php echo (int)$pl['id']; ?>" data-valor="<?php echo htmlspecialchars($plValor); ?>" data-dias="<?php echo (int)$pl['dias']; ?>" data-nome="<?php echo htmlspecialchars($pl['nome']); ?>" onclick="selecionarPlano(this)">
<?php if ($ehMensal): ?>
<span class="text-xl shrink-0 leading-none" title="Coroa - plano mais adquirido">👑</span>
<?php else: ?>
<div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 plan-radio <?php echo ($i === 0) ? 'border-primary' : 'border-outline'; ?>">
<?php if ($i === 0): ?><span class="w-2.5 h-2.5 rounded-full bg-primary"></span><?php endif; ?>
</div>
<?php endif; ?>
<div class="flex-1">
<p class="text-sm font-bold text-on-surface flex items-center gap-1.5">
<?php echo htmlspecialchars($pl['nome']); ?>
<?php if ($ehMensal): ?>
<span class="inline-flex items-center gap-1 text-[9px] font-extrabold uppercase tracking-wide text-amber-600 bg-amber-100 border border-amber-300 rounded-full px-2 py-0.5">O MAIS ADQUIRIDO</span>
<?php endif; ?>
</p>
<p class="text-[11px] text-on-surface-variant"><?php echo htmlspecialchars($pl['descricao'] ?: $pl['dias'] . ' dias de desconto'); ?></p>
</div>
<div class="text-right">
<p class="text-[16px] font-extrabold text-primary">R$ <?php echo $plValor; ?></p>
<p class="text-[10px] text-on-surface-variant font-bold uppercase"><?php echo (int)$pl['dias']; ?> dias</p>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
<div class="mb-6">
<p class="text-[10px] font-bold text-on-surface-variant uppercase mb-2 text-center">2. Forma de pagamento</p>
<div class="flex bg-surface-variant/50 rounded-xl p-1">
<button type="button" class="flex-1 py-2.5 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all" id="tabPixBtn" onclick="mostrarAba('pix')">
<span class="material-symbols-outlined text-[18px]">qr_code_2</span> PIX
</button>
<button type="button" class="flex-1 py-2.5 rounded-lg text-sm font-bold text-on-surface-variant flex items-center justify-center gap-1.5 transition-all" id="tabCardBtn" onclick="mostrarAba('card')">
<span class="material-symbols-outlined text-[18px]">credit_card</span> Cartão de crédito
</button>
</div>
</div>

<!-- PIX Panel -->
<div id="painelPix">
<img class="w-32 h-12 object-contain mb-6 mx-auto" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAT-tQ9XWL3_Pfxc9QMZ9W3An6rHLy83qfPjEyHfiwJuTXGHQ-sFBk6Xxa9go9W3YTlN2NQQVcTLVAIFQmHLBrCusUqbMWihhDAAtkPl3vwp6JXtKyAXFxcd6qNX16tGYeuy3JkWUniaakOVsE1QudDk1p-gocxFfLVk8CKHUU8zBvM79CfF9OBCVROQYB06u7UBACiFfgmptO8oQ8WZT5ogcNtfMGQMQRuP7MvEzeBBlRD69Ngfs-0xg" alt="Logo PIX"/>
<div class="w-48 h-48 bg-surface-variant/50 rounded-lg flex items-center justify-center mb-6 border-2 border-dashed border-outline-variant mx-auto" id="qrBox">
<?php if ($pix && $pix['qr_code_base64']): ?>
<img id="qrImg" class="w-full h-full object-contain" src="data:image/png;base64,<?php echo $pix['qr_code_base64']; ?>" alt="QR Code PIX"/>
<?php else: ?>
<span class="material-symbols-outlined text-[64px] text-on-surface-variant opacity-40" id="qrIcon">qr_code_2</span>
<?php endif; ?>
</div>
<p class="text-sm text-on-surface-variant text-center mb-6">
Escaneie o QR Code ou use o código PIX abaixo para ativar seu cartão. Valor: <b id="pixValorText"><?php echo isset($planos[0]) ? 'R$ ' . number_format((float)($planos[0]['valor_mensal'] ?: $planos[0]['valor_adesao'] ?: $planos[0]['valor']), 2, ',', '.') : ''; ?></b>. A ativação é automática após a confirmação do pagamento.
</p>
<?php if ($pix && $pix['qr_code_copia_cola']): ?>
<div class="mt-6" id="pixCodeBox">
<p class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 text-left">Código PIX (copia e cola)</p>
<div class="w-full bg-surface-variant/50 rounded-lg p-3 flex items-center gap-2">
<p class="text-[11px] text-on-surface-variant flex-1 break-all text-left" id="pixCodeText"><?php echo htmlspecialchars($pix['qr_code_copia_cola']); ?></p>
<button class="text-primary font-bold text-[12px] whitespace-nowrap" onclick="copiarPix()">COPIAR</button>
</div>
</div>
<div class="w-full mt-6" id="pixWaitBox">
<div class="bg-secondary-container/20 border border-secondary/20 rounded-xl px-4 py-3 text-center">
<p class="text-sm font-semibold text-secondary flex items-center justify-center gap-2"><span class="material-symbols-outlined">hourglass_top</span> Aguardando pagamento...</p>
</div>
</div>
<?php else: ?>
<button type="button" id="btnGerarPix" class="w-full bg-secondary hover:bg-on-secondary-fixed-variant text-white font-bold py-3 rounded-full shadow-lg transition flex items-center justify-center gap-2" onclick="gerarPix()">
<span class="material-symbols-outlined text-[20px]">qr_code_2</span> GERAR QR CODE PIX
</button>
<div class="w-full mt-6 hidden" id="pixWaitBox">
<div class="bg-secondary-container/20 border border-secondary/20 rounded-xl px-4 py-3 text-center">
<p class="text-sm font-semibold text-secondary flex items-center justify-center gap-2"><span class="material-symbols-outlined">hourglass_top</span> Aguardando pagamento...</p>
</div>
</div>
<?php endif; ?>
</div>

<!-- Card Panel -->
<div id="painelCard" class="hidden">
<p class="text-sm text-on-surface-variant text-center mb-6">Pague com <b>cartão de crédito</b> para ativar seu cartão por <b id="cardDiasText"><?php echo isset($planos[0]) ? (int)$planos[0]['dias'] : '60'; ?></b> dias. Valor: <b id="cardValorText"><?php echo isset($planos[0]) ? 'R$ ' . number_format((float)($planos[0]['valor_mensal'] ?: $planos[0]['valor_adesao'] ?: $planos[0]['valor']), 2, ',', '.') : ''; ?></b>.</p>
<?php if ($asaasOk): ?>
<form id="cardForm" class="space-y-4">
<div>
<label class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 block" for="cardholderName">Nome no cartão</label>
<input id="cardholderName" name="cardholderName" type="text" autocomplete="off" class="w-full bg-surface-variant/50 rounded-lg px-3.5 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="Nome impresso no cartão" required/>
</div>
<div>
<label class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 block" for="cardNumber">Número do cartão</label>
<input id="cardNumber" name="cardNumber" type="text" autocomplete="off" inputmode="numeric" class="w-full bg-surface-variant/50 rounded-lg px-3.5 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="0000 0000 0000 0000" required/>
</div>
<div class="grid grid-cols-2 gap-3">
<div>
<label class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 block" for="cardExpirationDate">Vencimento</label>
<input id="cardExpirationDate" name="cardExpirationDate" type="text" autocomplete="off" inputmode="numeric" class="w-full bg-surface-variant/50 rounded-lg px-3.5 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="MM/AA" required/>
</div>
<div>
<label class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 block" for="cardSecurityCode">Código de segurança</label>
<input id="cardSecurityCode" name="cardSecurityCode" type="text" autocomplete="off" inputmode="numeric" maxlength="4" class="w-full bg-surface-variant/50 rounded-lg px-3.5 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="123" required/>
</div>
</div>
<div class="grid grid-cols-2 gap-3">
<div>
<label class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 block" for="cardPostalCode">CEP (endereço do titular)</label>
<input id="cardPostalCode" name="cardPostalCode" type="text" autocomplete="off" inputmode="numeric" value="<?php echo htmlspecialchars($cepUsuario); ?>" class="w-full bg-surface-variant/50 rounded-lg px-3.5 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="00000-000" required/>
</div>
<div>
<label class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 block" for="cardAddressNumber">Número</label>
<input id="cardAddressNumber" name="cardAddressNumber" type="text" autocomplete="off" value="<?php echo htmlspecialchars($numEndereco); ?>" class="w-full bg-surface-variant/50 rounded-lg px-3.5 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="Nº" required/>
</div>
</div>
<div id="cardErrorBox" class="hidden">
<div class="bg-error-container/60 border border-error/30 text-[#93000a] rounded-lg px-3.5 py-2.5 text-[13px]" id="cardErrorText"></div>
</div>
<button type="submit" id="cardPayBtn" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3.5 rounded-full shadow-lg transition flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-[20px]">lock</span> PAGAR R$ <?php echo isset($planos[0]) ? number_format((float)($planos[0]['valor_mensal'] ?: $planos[0]['valor_adesao'] ?: $planos[0]['valor']), 2, ',', '.') : '5,00'; ?>
</button>
</form>
<?php else: ?>
<div class="bg-error-container/60 border border-error/30 text-[#93000a] rounded-lg px-3.5 py-2.5 text-[13px]">Pagamento por cartão indisponível no momento.</div>
<?php endif; ?>
</div>

<div class="text-center mt-6">
<a class="text-primary font-bold text-sm hover:underline underline-offset-4 inline-flex items-center gap-1" href="parceiros.php">Ver lojas parceiras <span class="material-symbols-outlined">arrow_forward</span></a>
</div>
</div>
<?php endif; ?>
</div>
</section>

<script>
let planoAtual = <?php echo isset($planos[0]) ? json_encode(['id' => (int)$planos[0]['id'], 'valor' => number_format((float)($planos[0]['valor_mensal'] ?: $planos[0]['valor_adesao'] ?: $planos[0]['valor']), 2, ',', '.'), 'dias' => (int)$planos[0]['dias'], 'nome' => $planos[0]['nome']]) : 'null'; ?>;
let pixAtivo = <?php echo $pix ? json_encode(['id' => (int)$pix['id'], 'gateway_payment_id' => ($pix['provedor'] ?? 'mp') === 'asaas' ? (string)$pix['asaas_payment_id'] : (string)$pix['mp_payment_id'], 'copia_cola' => $pix['qr_code_copia_cola'], 'plano_id' => (int)($pix['plano_id'] ?? 0)]) : 'null'; ?>;
let pagamentoLocalId = pixAtivo ? (pixAtivo.id || 0) : 0;
const pixCodeText = pixAtivo ? pixAtivo.copia_cola : '';
const planoMensalId = <?php echo $planoMensal ? (int)$planoMensal['id'] : 0; ?>;
const adesaoPaga = <?php echo $adesaoPaga ? 'true' : 'false'; ?>;
const valAdesaoGlobal = <?php echo json_encode('R$ ' . number_format($valAdesaoGlobal, 2, ',', '.')); ?>;
const valAdesaoGlobalNum = <?php echo number_format($valAdesaoGlobal, 2, '.', ''); ?>;
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
    if (planoMensalId && +el.dataset.id === planoMensalId && !adesaoPaga) {
        abrirModalMensal();
        return;
    }
    const brl = 'R$ ' + planoAtual.valor;
    if (document.getElementById('pixValorText')) document.getElementById('pixValorText').textContent = brl;
    if (document.getElementById('cardValorText')) document.getElementById('cardValorText').textContent = brl;
    if (document.getElementById('cardDiasText')) document.getElementById('cardDiasText').textContent = planoAtual.dias;
    if (document.getElementById('cardPayBtn')) document.getElementById('cardPayBtn').innerHTML = '<span class="material-symbols-outlined text-[20px]">lock</span> PAGAR ' + brl;
}
function gerarPix() {
    if (!planoAtual) return;
    const btn = document.getElementById('btnGerarPix');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-outlined text-[20px]">hourglass_top</span> GERANDO...'; }
    const body = new URLSearchParams({ plano_id: planoAtual.id });
    fetch('../criar_pix.php', { method: 'POST', body: body })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'pending' && d.pix) {
                pixAtivo = { id: d.pix.id, gateway_payment_id: d.pix.gateway_payment_id || '', copia_cola: d.pix.qr_code_copia_cola, plano_id: planoAtual.id };
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
                        div.className = 'mt-6';
                        div.id = 'pixCodeBox';
                        div.innerHTML = '<p class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 text-left">Código PIX (copia e cola)</p><div class="w-full bg-surface-variant/50 rounded-lg p-3 flex items-center gap-2"><p class="text-[11px] text-on-surface-variant flex-1 break-all text-left" id="pixCodeText"></p><button class="text-primary font-bold text-[12px] whitespace-nowrap" onclick="copiarPix()">COPIAR</button></div>';
                        painel.insertBefore(div, waitBox);
                    }
                }
                const codeText = document.getElementById('pixCodeText');
                if (codeText) codeText.textContent = d.pix.qr_code_copia_cola;
                if (waitBox) waitBox.classList.remove('hidden');
                if (btn) { btn.remove(); }
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
function mostrarModalAtivado() {
    document.getElementById('modalAtivado').classList.remove('hidden');
    setTimeout(() => location.reload(), 2500);
}
function verificarPagamento() {
    if (!pagamentoLocalId) return;
    fetch('../verifica_pagamento.php?id=' + pagamentoLocalId)
        .then(r => r.json())
        .then(d => {
            if (d.status === 'approved') {
                if (document.getElementById('pixWaitBox')) document.getElementById('pixWaitBox').innerHTML = '<div class="bg-secondary-container/20 border border-secondary/20 rounded-xl px-4 py-3 text-center"><p class="text-sm font-bold text-secondary">Pagamento confirmado!</p></div>';
                clearInterval(polling);
                mostrarModalAtivado();
            } else if (d.status === 'rejected' || d.status === 'cancelled') {
                clearInterval(polling);
                location.reload();
            }
        })
        .catch(() => {});
}
let polling = pagamentoLocalId ? setInterval(verificarPagamento, 5000) : null;
if (pagamentoLocalId) verificarPagamento();

function mostrarAba(aba) {
    const pixBtn = document.getElementById('tabPixBtn');
    const cardBtn = document.getElementById('tabCardBtn');
    const painelPix = document.getElementById('painelPix');
    const painelCard = document.getElementById('painelCard');
    if (aba === 'pix') {
        pixBtn.className = 'flex-1 py-2.5 rounded-lg text-sm font-bold bg-white shadow-sm text-primary flex items-center justify-center gap-1.5 transition-all';
        cardBtn.className = 'flex-1 py-2.5 rounded-lg text-sm font-bold text-on-surface-variant flex items-center justify-center gap-1.5 transition-all';
        painelPix.classList.remove('hidden');
        painelCard.classList.add('hidden');
    } else {
        cardBtn.className = 'flex-1 py-2.5 rounded-lg text-sm font-bold bg-white shadow-sm text-primary flex items-center justify-center gap-1.5 transition-all';
        pixBtn.className = 'flex-1 py-2.5 rounded-lg text-sm font-bold text-on-surface-variant flex items-center justify-center gap-1.5 transition-all';
        painelCard.classList.remove('hidden');
        painelPix.classList.add('hidden');
    }
}
</script>
<script>
const cardForm = document.getElementById('cardForm');
const payBtn = document.getElementById('cardPayBtn');
const errorBox = document.getElementById('cardErrorBox');
const errorText = document.getElementById('cardErrorText');
function cardErro(msg) {
    if (!errorBox) return;
    errorText.textContent = msg;
    errorBox.classList.remove('hidden');
}
function pagarBtnReset() {
    payBtn.disabled = false;
    payBtn.innerHTML = '<span class="material-symbols-outlined text-[20px]">lock</span> PAGAR R$ ' + (planoAtual ? planoAtual.valor : '5,00');
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
        payBtn.innerHTML = '<span class="material-symbols-outlined text-[20px]">hourglass_top</span> PROCESSANDO...';
        const body = new URLSearchParams({
            plano_id: planoAtual ? planoAtual.id : 0,
            holder_name: document.getElementById('cardholderName').value.trim(),
            number: document.getElementById('cardNumber').value.replace(/\D/g, ''),
            expiry: partes[1].padStart(2, '0') + '/' + ano,
            ccc: document.getElementById('cardSecurityCode').value.replace(/\D/g, ''),
            postal_code: document.getElementById('cardPostalCode').value.replace(/\D/g, ''),
            address_number: document.getElementById('cardAddressNumber').value.trim()
        });
        fetch('../processa_pagamento_cartao.php', { method: 'POST', body: body })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'approved') {
                    mostrarModalAtivado();
                } else if (d.status === 'pending') {
                    setTimeout(() => location.reload(), 3000);
                } else {
                    pagarBtnReset();
                    cardErro(d.message || 'Pagamento recusado. Tente novamente.');
                }
            })
            .catch(() => {
                pagarBtnReset();
                cardErro('Erro ao processar o pagamento. Tente novamente.');
            });
    });
}
</script>
<!-- Modal CARTÃO ATIVADO -->
<div class="hidden fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm flex items-center justify-center p-6" id="modalAtivado">
<div class="w-full max-w-sm bg-white rounded-2xl p-8 text-center shadow-2xl">
<div class="w-20 h-20 mx-auto rounded-full bg-secondary/10 flex items-center justify-center mb-4">
<span class="material-symbols-outlined text-secondary text-[48px]" style="font-variation-settings:'FILL' 1;">check_circle</span>
</div>
<h2 class="text-xl font-extrabold text-on-surface uppercase tracking-tight mb-2">Cartão Ativado</h2>
<p class="text-sm text-on-surface-variant mb-6">Seu cartão foi ativado! Aproveite os descontos nas lojas parceiras!</p>
<a href="parceiros.php" class="block w-full bg-primary hover:bg-primary-dark text-white font-bold py-4 rounded-full shadow-lg transition">Ver lojas parceiras</a>
</div>
</div>
<!-- Modal ACESSO BLOQUEADO -->
<div class="hidden fixed inset-0 z-[110] bg-black/60 backdrop-blur-sm flex items-center justify-center p-6" id="modalBloqueado">
<div class="w-full max-w-sm bg-white rounded-2xl p-8 text-center shadow-2xl">
<div class="w-20 h-20 mx-auto rounded-full bg-primary/15 flex items-center justify-center mb-4">
<span class="material-symbols-outlined text-primary text-[48px]" style="font-variation-settings:'FILL' 1;">sentiment_very_satisfied</span>
</div>
<h2 class="text-xl font-extrabold text-on-surface uppercase tracking-tight mb-2">Ative seu cartão</h2>
<p class="text-sm text-on-surface-variant mb-6">Para acessar seus benefícios, primeiro ative seu cartão escolhendo um de nossos planos :)</p>
    <button class="block w-full bg-primary hover:bg-primary-dark text-white font-bold py-4 rounded-full shadow-lg transition" onclick="document.getElementById('modalBloqueado').classList.add('hidden')">Entendi</button>
    </div>
    </div>
    <!-- Modal PLANO MENSAL - Taxa de Adesão -->
    <div class="hidden fixed inset-0 z-[120] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto" id="modalMensal">
    <div class="w-full max-w-md bg-white rounded-2xl overflow-hidden shadow-2xl my-auto">
    <div class="px-6 py-4 border-b border-outline-variant flex items-center justify-between bg-primary/5">
    <h3 class="text-lg font-extrabold text-on-surface">Plano Mensal</h3>
    <button type="button" onclick="fecharModalMensal()" class="text-on-surface-variant p-1 rounded-full hover:bg-surface-variant transition-colors"><span class="material-symbols-outlined">close</span></button>
    </div>
    <div class="p-6">
    <div class="bg-secondary-container/10 border border-secondary/20 rounded-xl p-4 mb-5">
    <p class="text-sm text-on-surface-variant leading-relaxed">
    Na contratação do plano mensal, será cobrada uma taxa de adesão de <b class="text-secondary">R$ <span id="mTaxaValor"><?php echo number_format($valAdesaoGlobal, 2, ',', '.'); ?></span></b>, paga uma única vez. A partir do segundo mês, você pagará apenas o valor da mensalidade do plano escolhido.
    </p>
    </div>
    <div class="flex bg-surface-variant/50 rounded-xl p-1 mb-5">
    <button type="button" class="flex-1 py-2.5 rounded-lg text-sm font-bold text-primary bg-white shadow-sm flex items-center justify-center gap-1.5 transition-all" id="mTabPixBtn" onclick="mMostrarAba('pix')">
    <span class="material-symbols-outlined text-[18px]">qr_code_2</span> PIX
    </button>
    <?php if ($asaasOk): ?>
    <button type="button" class="flex-1 py-2.5 rounded-lg text-sm font-bold text-on-surface-variant flex items-center justify-center gap-1.5 transition-all" id="mTabCardBtn" onclick="mMostrarAba('card')">
    <span class="material-symbols-outlined text-[18px]">credit_card</span> Cartão de crédito
    </button>
    <?php endif; ?>
    </div>
    <!-- Modal: PIX Panel -->
    <div id="mPainelPix">
    <div class="w-40 h-40 bg-surface-variant/50 rounded-xl flex items-center justify-center mb-5 border-2 border-dashed border-outline-variant mx-auto" id="mqrBox">
    <span class="material-symbols-outlined text-[56px] text-on-surface-variant opacity-40">qr_code_2</span>
    </div>
    <p class="text-sm text-on-surface-variant text-center mb-5">Valor a pagar: <b class="text-secondary" id="mPixValorText">R$ <?php echo number_format($valAdesaoGlobal, 2, ',', '.'); ?></b> (taxa de adesão)</p>
    <button type="button" id="mBtnGerarPix" class="w-full bg-secondary hover:bg-on-secondary-fixed-variant text-white font-bold py-3 rounded-full shadow-lg transition flex items-center justify-center gap-2" onclick="mGerarPix()">
    <span class="material-symbols-outlined text-[20px]">qr_code_2</span> GERAR QR CODE PIX
    </button>
    <div class="w-full mt-4 hidden" id="mPixCodeBox">
    <p class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 text-left">Código PIX (copia e cola)</p>
    <div class="w-full bg-surface-variant/50 rounded-lg p-3 flex items-center gap-2">
    <p class="text-[11px] text-on-surface-variant flex-1 break-all text-left" id="mPixCodeText"></p>
    <button type="button" class="text-primary font-bold text-[12px] whitespace-nowrap" onclick="mCopiarPix()">COPIAR</button>
    </div>
    </div>
    <div class="w-full mt-4 hidden" id="mPixWaitBox">
    <div class="bg-secondary-container/20 border border-secondary/20 rounded-xl px-4 py-3 text-center">
    <p class="text-sm font-semibold text-secondary flex items-center justify-center gap-2"><span class="material-symbols-outlined">hourglass_top</span> Aguardando pagamento...</p>
    </div>
    </div>
    </div>
    <!-- Modal: Cartão Panel -->
    <div id="mPainelCard" class="hidden">
    <p class="text-sm text-on-surface-variant text-center mb-5">Pague com <b>cartão de crédito</b> para ativar seu cartão por <b id="mCardDiasText"><?php echo $planoMensal ? (int)$planoMensal['dias'] : '30'; ?></b> dias. Valor: <b id="mCardValorText">R$ <?php echo number_format($valAdesaoGlobal, 2, ',', '.'); ?></b>.</p>
    <?php if ($asaasOk): ?>
    <form id="mCardForm" class="space-y-4">
    <div>
    <label class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 block" for="mCardholderName">Nome no cartão</label>
    <input id="mCardholderName" name="cardholderName" type="text" autocomplete="off" class="w-full bg-surface-variant/50 rounded-lg px-3.5 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="Nome impresso no cartão" required/>
    </div>
    <div>
    <label class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 block" for="mCardNumber">Número do cartão</label>
    <input id="mCardNumber" name="number" type="text" autocomplete="off" inputmode="numeric" class="w-full bg-surface-variant/50 rounded-lg px-3.5 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="0000 0000 0000 0000" required/>
    </div>
    <div class="grid grid-cols-2 gap-3">
    <div>
    <label class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 block" for="mCardExpirationDate">Vencimento</label>
    <input id="mCardExpirationDate" name="expiry" type="text" autocomplete="off" inputmode="numeric" class="w-full bg-surface-variant/50 rounded-lg px-3.5 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="MM/AA" required/>
    </div>
    <div>
    <label class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 block" for="mCardSecurityCode">Código de segurança</label>
    <input id="mCardSecurityCode" name="ccc" type="text" autocomplete="off" inputmode="numeric" maxlength="4" class="w-full bg-surface-variant/50 rounded-lg px-3.5 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="123" required/>
    </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
    <div>
    <label class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 block" for="mCardPostalCode">CEP (endereço do titular)</label>
    <input id="mCardPostalCode" name="postal_code" type="text" autocomplete="off" inputmode="numeric" value="<?php echo htmlspecialchars($cepUsuario); ?>" class="w-full bg-surface-variant/50 rounded-lg px-3.5 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="00000-000" required/>
    </div>
    <div>
    <label class="text-[10px] font-bold text-on-surface-variant uppercase mb-1 block" for="mCardAddressNumber">Número</label>
    <input id="mCardAddressNumber" name="address_number" type="text" autocomplete="off" value="<?php echo htmlspecialchars($numEndereco); ?>" class="w-full bg-surface-variant/50 rounded-lg px-3.5 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary border border-outline-variant/40" placeholder="Nº" required/>
    </div>
    </div>
    <div id="mCardErrorBox" class="hidden">
    <div class="bg-error-container/60 border border-error/30 text-[#93000a] rounded-lg px-3.5 py-2.5 text-[13px]" id="mCardErrorText"></div>
    </div>
    <button type="submit" id="mCardPayBtn" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3.5 rounded-full shadow-lg transition flex items-center justify-center gap-2">
    <span class="material-symbols-outlined text-[20px]">lock</span> PAGAR R$ <?php echo number_format($valAdesaoGlobal, 2, ',', '.'); ?>
    </button>
    </form>
    <?php else: ?>
    <div class="bg-error-container/60 border border-error/30 text-[#93000a] rounded-lg px-3.5 py-2.5 text-[13px]">Pagamento por cartão indisponível no momento.</div>
    <?php endif; ?>
    </div>
    </div>
    </div>
    </div>
    <script>
        // --- Modal Plano Mensal (taxa de adesão) ---
        let mPolling = null;
        let mPagamentoLocalId = 0;
        function abrirModalMensal() {
            document.getElementById('modalMensal').classList.remove('hidden');
            mMostrarAba('pix');
            const qr = document.getElementById('mqrBox');
            if (qr) qr.innerHTML = '<span class="material-symbols-outlined text-[56px] text-on-surface-variant opacity-40">qr_code_2</span>';
            const cb = document.getElementById('mPixCodeBox'); if (cb) cb.classList.add('hidden');
            const wb = document.getElementById('mPixWaitBox'); if (wb) wb.classList.add('hidden');
            const btn = document.getElementById('mBtnGerarPix'); if (btn) btn.style.display = '';
            mPagamentoLocalId = 0;
            if (mPolling) { clearInterval(mPolling); mPolling = null; }
        }
        function fecharModalMensal() {
            document.getElementById('modalMensal').classList.add('hidden');
            if (mPolling) { clearInterval(mPolling); mPolling = null; }
        }
        function mMostrarAba(aba) {
            const pp = document.getElementById('mPainelPix');
            const pc = document.getElementById('mPainelCard');
            const tb = document.getElementById('mTabPixBtn');
            const tcb = document.getElementById('mTabCardBtn');
            const on  = 'flex-1 py-2.5 rounded-lg text-sm font-bold text-primary bg-white shadow-sm flex items-center justify-center gap-1.5 transition-all';
            const off = 'flex-1 py-2.5 rounded-lg text-sm font-bold text-on-surface-variant flex items-center justify-center gap-1.5 transition-all';
            if (aba === 'pix') { tb.className = on; tcb.className = off; pp.classList.remove('hidden'); pc.classList.add('hidden'); }
            else { tcb.className = on; tb.className = off; pc.classList.remove('hidden'); pp.classList.add('hidden'); }
        }
        function mGerarPix() {
            if (!planoAtual) return;
            const btn = document.getElementById('mBtnGerarPix');
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-outlined text-[20px]">hourglass_top</span> GERANDO...'; }
            const body = new URLSearchParams({ plano_id: planoAtual.id });
            fetch('../criar_pix.php', { method: 'POST', body: body })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'pending' && d.pix) {
                        const qr = document.getElementById('mqrBox');
                        if (qr) qr.innerHTML = '<img class="w-full h-full object-contain" src="data:image/png;base64,' + d.pix.qr_code_base64 + '" alt="QR Code PIX"/>';
                        const ct = document.getElementById('mPixCodeText');
                        if (ct) ct.textContent = d.pix.qr_code_copia_cola;
                        const cb = document.getElementById('mPixCodeBox'); if (cb) cb.classList.remove('hidden');
                        const wb = document.getElementById('mPixWaitBox'); if (wb) wb.classList.remove('hidden');
                        if (btn) btn.style.display = 'none';
                        mPagamentoLocalId = d.pix.id || 0;
                        if (mPolling) clearInterval(mPolling);
                        mPolling = setInterval(mVerificarPagamento, 5000);
                        mVerificarPagamento();
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
        function mCopiarPix() {
            const el = document.getElementById('mPixCodeText');
            const valor = el ? el.textContent : '';
            if (!valor) return;
            function sucesso() {
                const b = document.querySelector('#mPixCodeBox button');
                if (b) { const o = b.textContent; b.textContent = 'COPIADO!'; setTimeout(() => b.textContent = o, 2000); }
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(valor).then(sucesso).catch(() => mFallbackCopiar(valor, sucesso));
                return;
            }
            mFallbackCopiar(valor, sucesso);
        }
        function mFallbackCopiar(valor, cb) {
            const ta = document.createElement('textarea');
            ta.value = valor; ta.style.position = 'fixed'; ta.style.top = '-9999px'; ta.style.left = '-9999px';
            ta.setAttribute('readonly', ''); document.body.appendChild(ta); ta.focus(); ta.select(); ta.setSelectionRange(0, ta.value.length);
            let ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            document.body.removeChild(ta);
            if (ok) { if (cb) cb(); } else { alert('Não foi possível copiar automaticamente. Selecione o código e copie manualmente.'); }
        }
        function mVerificarPagamento() {
            if (!mPagamentoLocalId) return;
            fetch('../verifica_pagamento.php?id=' + mPagamentoLocalId)
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'approved') {
                        const wb = document.getElementById('mPixWaitBox');
                        if (wb) wb.innerHTML = '<div class="bg-secondary-container/20 border border-secondary/20 rounded-xl px-4 py-3 text-center"><p class="text-sm font-bold text-secondary">Pagamento confirmado!</p></div>';
                        if (mPolling) clearInterval(mPolling);
                        mostrarModalAtivado();
                    } else if (d.status === 'rejected' || d.status === 'cancelled') {
                        if (mPolling) clearInterval(mPolling);
                        fecharModalMensal();
                        location.reload();
                    }
                })
                .catch(() => {});
        }
        function mCardErro(msg) {
            const eb = document.getElementById('mCardErrorBox');
            const et = document.getElementById('mCardErrorText');
            if (eb && et) { et.textContent = msg; eb.classList.remove('hidden'); }
        }
        function mPagarBtnReset() {
            const b = document.getElementById('mCardPayBtn');
            b.disabled = false;
            b.innerHTML = '<span class="material-symbols-outlined text-[20px]">lock</span> PAGAR R$ ' + valAdesaoGlobalNum.toFixed(2).replace('.', ',');
        }
        const mCardFormEl = document.getElementById('mCardForm');
        if (mCardFormEl) {
            mCardFormEl.addEventListener('submit', (event) => {
                event.preventDefault();
                const venc = document.getElementById('mCardExpirationDate').value.trim();
                const partes = venc.match(/^(\d{1,2})\s*\/?\s*(\d{2}|\d{4})$/);
                if (!partes) { mCardErro('Validade inválida. Use o formato MM/AA.'); return; }
                let ano = partes[2];
                if (ano.length === 2) ano = '20' + ano;
                const eb = document.getElementById('mCardErrorBox'); if (eb) eb.classList.add('hidden');
                const payBtn = document.getElementById('mCardPayBtn');
                payBtn.disabled = true;
                payBtn.innerHTML = '<span class="material-symbols-outlined text-[20px]">hourglass_top</span> PROCESSANDO...';
                const body = new URLSearchParams({
                    plano_id: planoAtual ? planoAtual.id : 0,
                    holder_name: document.getElementById('mCardholderName').value.trim(),
                    number: document.getElementById('mCardNumber').value.replace(/\D/g, ''),
                    expiry: partes[1].padStart(2, '0') + '/' + ano,
                    ccc: document.getElementById('mCardSecurityCode').value.replace(/\D/g, ''),
                    postal_code: document.getElementById('mCardPostalCode').value.replace(/\D/g, ''),
                    address_number: document.getElementById('mCardAddressNumber').value.trim()
                });
                fetch('../processa_pagamento_cartao.php', { method: 'POST', body: body })
                    .then(r => r.json())
                    .then(d => {
                        if (d.status === 'approved') {
                            mostrarModalAtivado();
                        } else if (d.status === 'pending') {
                            setTimeout(() => location.reload(), 3000);
                        } else {
                            mPagarBtnReset();
                            mCardErro(d.message || 'Pagamento recusado. Tente novamente.');
                        }
                    })
                    .catch(() => {
                        mPagarBtnReset();
                        mCardErro('Erro ao processar o pagamento. Tente novamente.');
                    });
            });
        }
    </script>
    <script>
    if (<?php echo $bloqueado ? 'true' : 'false'; ?>) {
        document.getElementById('modalBloqueado').classList.remove('hidden');
    }
    </script>
    <?php require_once __DIR__ . '/_foot.php'; ?>
