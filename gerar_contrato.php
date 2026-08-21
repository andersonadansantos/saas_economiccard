<?php
require 'config.php';

$preview = (($_GET['preview'] ?? '') === '1');
$download = (($_GET['download'] ?? '') === '1');

if ($preview) {
    $u = [
        'nome'      => trim($_GET['nome'] ?? ''),
        'cpf'       => trim($_GET['cpf'] ?? ''),
        'email'     => trim($_GET['email'] ?? ''),
        'whatsapp'  => trim($_GET['whatsapp'] ?? ''),
        'endereco'  => trim($_GET['endereco'] ?? ''),
        'cidade'    => trim($_GET['cidade'] ?? ''),
        'cep'       => trim($_GET['cep'] ?? ''),
    ];
    if (!empty($_GET['uid']) && !empty($_SESSION['admin_logado'])) {
        $uid = (int)$_GET['uid'];
        $contr = garantir_aceite_contrato($conn, 'usuarios', $uid);
        $u['contrato_codigo'] = $contr['codigo'];
        $u['contrato_aceite'] = $contr['aceite'];
        $u['contrato_aceite_ip'] = '';
    } else {
        $u['contrato_codigo'] = 'ECU-PREV-' . strtoupper(substr(md5(trim($u['cpf'])), 0, 8));
        $u['contrato_aceite'] = date('Y-m-d H:i:s');
        $u['contrato_aceite_ip'] = '';
    }
} else {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }

    $uid = (int)$_SESSION['usuario_id'];
    $stmt = $conn->prepare("SELECT nome, cpf, rg, nascimento, email, whatsapp, endereco, cidade, cep FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    if (!$u) {
        die('Usuário não encontrado.');
    }
    $u['contrato_codigo'] = garantir_aceite_contrato($conn, 'usuarios', $uid)['codigo'];
    $u['contrato_aceite'] = garantir_aceite_contrato($conn, 'usuarios', $uid)['aceite'];
    $u['contrato_aceite_ip'] = '';
}

function ec_latin($s) {
    $s = html_entity_decode((string)$s, ENT_QUOTES, 'UTF-8');
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
}

function ec_cpf($cpf) {
    $d = preg_replace('/\D/', '', (string)$cpf);
    if (strlen($d) === 11) {
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }
    return $cpf;
}

function ec_fit($pdf, $s, $maxW) {
    $s = (string)$s;
    if ($pdf->GetStringWidth($s) <= $maxW) return $s;
    $out = '';
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        if ($pdf->GetStringWidth($out . $ch) > $maxW) break;
        $out .= $ch;
    }
    return rtrim($out);
}

require 'lib/fpdf/fpdf.php';
require 'lib/fpdi/autoload.php';

use setasign\Fpdi\Fpdi;

$pdf = new Fpdi();
$pdf->SetAutoPageBreak(false);
$pdf->SetTextColor(0, 0, 0);

$src = __DIR__ . '/Contrato_de_Adesao_Economic_Card.pdf';
$data = date('d/m/Y');
$pageCount = $pdf->setSourceFile($src);
for ($p = 1; $p <= $pageCount; $p++) {
    $tpl = $pdf->importPage($p);
    $size = $pdf->getTemplateSize($tpl);
    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
    $pdf->useTemplate($tpl);
    if ($p === 2) {
        gerar_dados_pag2($pdf, $u, $data);
    }
    rodape_contrato($pdf, $u['contrato_codigo']);
}

adicionar_certificado_aceite($pdf, [
    'contrato_codigo' => $u['contrato_codigo'] ?? '',
    'contrato_aceite' => $u['contrato_aceite'] ?? '',
    'contrato_aceite_ip' => $u['contrato_aceite_ip'] ?? '',
    'nome' => $u['nome'],
    'cpf' => $u['cpf'],
    'rg' => $u['rg'] ?? '',
    'nascimento' => $u['nascimento'] ?? '',
    'email' => $u['email'] ?? '',
    'whatsapp' => $u['whatsapp'] ?? '',
    'endereco' => $u['endereco'] ?? '',
    'cidade' => $u['cidade'] ?? '',
]);

function gerar_dados_pag2($pdf, $u, $data) {
    $nome     = $u['nome'];
    $cpf      = ec_cpf($u['cpf']);
    $whats    = (string)($u['whatsapp'] ?? '');
    $email    = (string)($u['email'] ?? '');
    $endereco = trim((string)($u['endereco'] ?? ''));
    $cidade   = trim((string)($u['cidade'] ?? ''));
    $cep      = trim((string)($u['cep'] ?? ''));

    $PTH   = 25.4 / 72;
    $PGHPT = 841.8898;
    $mmTop = function($ptBottom) use ($PGHPT, $PTH) { return ($PGHPT - $ptBottom) * $PTH; };

    // Cobre a área dos underscores do campo com branco e escreve o valor por cima.
    $pdf_fill = function($x, $baseTop, $fontPt, $w) use ($pdf) {
        $h = $fontPt * 0.3528 + 1.7; // altura do campo
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $baseTop - $h / 2, $w, $h, 'F');
    };

    // 1) Linha "Local: ___ Data: ___" — baseline 218.2 pt do fundo
    $bedY = $mmTop(218.2);
    $lovX = 62.69291 * $PTH;
    $pdf->SetFont('Helvetica', '', 10.5);
    if ($cidade !== '') {
        $locLabelW = $pdf->GetStringWidth('Local: ');
        $locFieldW = $pdf->GetStringWidth(str_repeat('_', 38));
        $pdf_fill($lovX + $locLabelW, $bedY, 10.5, $locFieldW);
        $pdf->Text($lovX + $locLabelW, $bedY, ec_latin(ec_fit($pdf, $cidade, $locFieldW)));
    }
    $dataStart = $lovX + $pdf->GetStringWidth('Local: ' . str_repeat('_', 38) . ' Data: ');
    $dataFieldW = $pdf->GetStringWidth('____/____/________');
    $pdf_fill($dataStart, $bedY, 10.5, $dataFieldW);
    $pdf->Text($dataStart, $bedY, ec_latin($data));

    // 2) Assinatura — coluna CONTRATANTE / TITULAR
    $sigX = 70.86614 * $PTH;
    $pdf->SetFont('Helvetica', '', 9);
    $nomeStart = $sigX + 28.82633 * $PTH + $pdf->GetStringWidth('Nome: ');
    $nomeFieldW = $pdf->GetStringWidth(str_repeat('_', 28));
    $pdf_fill($nomeStart, $mmTop(128.7), 9, $nomeFieldW);
    $pdf->Text($nomeStart, $mmTop(128.7), ec_latin(ec_fit($pdf, $nome, $nomeFieldW)));

    $cpfStart = $sigX + 29.32583 * $PTH + $pdf->GetStringWidth('CPF: ');
    $cpfFieldW = $pdf->GetStringWidth(str_repeat('_', 29));
    $pdf_fill($cpfStart, $mmTop(110.7), 9, $cpfFieldW);
    $pdf->Text($cpfStart, $mmTop(110.7), ec_latin(ec_fit($pdf, $cpf, $cpfFieldW)));

    // 3) Bloco Telefone/WhatsApp, E-mail, Endereço (entre "Local:" e a assinatura)
    $bX = 22.1;
    $end = $endereco . ($cidade !== '' ? ' — ' . $cidade : '') . ($cep !== '' ? ' — CEP ' . $cep : '');
    $lines = [
        ($whats !== ''    ? 'Telefone/WhatsApp: ' . ec_fit($pdf, $whats, 95) : ''),
        ($email !== ''    ? 'E-mail: ' . ec_fit($pdf, $email, 95) : ''),
        ($end !== ''      ? 'Endereço: ' . ec_fit($pdf, $end, 105) : ''),
    ];
    $bases = [224.5, 228.5, 232.5];
    $idx = 0;
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $pdf->Text($bX, $bases[$idx], ec_latin($line));
        $idx++;
    }
}

// ---------- Saída ----------
$arq = 'Contrato_de_Adesao_' . preg_replace('/[^A-Za-z0-9]+/', '_', trim($u['nome'])) . '.pdf';
$pdf->Output($preview && !$download ? 'I' : 'D', $arq);
exit;