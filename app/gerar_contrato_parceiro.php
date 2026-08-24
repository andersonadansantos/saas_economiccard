<?php
require 'config.php';

$preview = (($_GET['preview'] ?? '') === '1');
$download = (($_GET['download'] ?? '') === '1');

if ($preview) {
    $p = [
        'nome' => trim($_GET['nome'] ?? ''),
    ];
    $p['contrato_codigo'] = 'ECP-PREV-' . strtoupper(substr(md5(trim($p['nome'])), 0, 8));
    $p['contrato_aceite'] = date('Y-m-d H:i:s');
    $p['contrato_aceite_ip'] = '';
} else {
    if (empty($_SESSION['admin_logado'])) {
        header('Location: admin_login.php');
        exit;
    }
    $pid = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT id, nome, whatsapp, endereco, categoria FROM parceiros WHERE id = ?");
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    if (!$p) {
        die('Parceiro não encontrado.');
    }
    $contrato = garantir_aceite_contrato($conn, 'parceiros', $pid);
    $p['contrato_codigo'] = $contrato['codigo'];
    $p['contrato_aceite'] = $contrato['aceite'];
    $p['contrato_aceite_ip'] = '';
}

function ec_latin($s) {
    $s = html_entity_decode((string)$s, ENT_QUOTES, 'UTF-8');
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
}

require 'lib/fpdf/fpdf.php';
require 'lib/fpdi/autoload.php';

use setasign\Fpdi\Fpdi;

$pdf = new Fpdi();
$pdf->SetAutoPageBreak(false);
$pdf->SetTextColor(0, 0, 0);

$src = __DIR__ . '/Contrato_de_Parceria_Economic_Card.pdf';
$pageCount = $pdf->setSourceFile($src);
for ($pIdx = 1; $pIdx <= $pageCount; $pIdx++) {
    $tpl = $pdf->importPage($pIdx);
    $size = $pdf->getTemplateSize($tpl);
    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
    $pdf->useTemplate($tpl);
    rodape_contrato($pdf, $p['contrato_codigo']);
}

adicionar_certificado_aceite($pdf, [
    'certificado' => ['titulo' => 'Contrato de Parceria Economic Card'],
    'contrato_codigo' => $p['contrato_codigo'],
    'contrato_aceite' => $p['contrato_aceite'],
    'contrato_aceite_ip' => $p['contrato_aceite_ip'],
    'nome' => $p['nome'],
    'cpf' => '',
    'whatsapp' => $p['whatsapp'] ?? '',
    'endereco' => $p['endereco'] ?? '',
    'cidade' => $p['cidade'] ?? '',
]);

$arq = 'Contrato_de_Parceria_Economic_Card_' . preg_replace('/[^A-Za-z0-9]+/', '_', trim($p['nome'])) . '.pdf';
$pdf->Output($preview && !$download ? 'I' : 'D', $arq);
exit;