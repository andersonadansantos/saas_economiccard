<?php
require 'config.php';

$preview = (($_GET['preview'] ?? '') === '1');
$download = (($_GET['download'] ?? '') === '1');

if ($preview) {
    $a = [
        'nome' => trim($_GET['nome'] ?? ''),
        'cpf'  => trim($_GET['cpf'] ?? ''),
    ];
    $a['contrato_codigo'] = 'ECA-PREV-' . strtoupper(substr(md5(trim($a['cpf'])), 0, 8));
    $a['contrato_aceite'] = date('Y-m-d H:i:s');
    $a['contrato_aceite_ip'] = '';
} else {
    if (!isset($_SESSION['afiliado_id'])) {
        header('Location: afiliado.php');
        exit;
    }
    $aid = (int)$_SESSION['afiliado_id'];
    $stmt = $conn->prepare("SELECT nome, cpf, nascimento, email, telefone FROM afiliados WHERE id = ?");
    $stmt->bind_param('i', $aid);
    $stmt->execute();
    $a = $stmt->get_result()->fetch_assoc();
    if (!$a) {
        die('Afiliado não encontrado.');
    }
    $contrato = garantir_aceite_contrato($conn, 'afiliados', $aid);
    $a['contrato_codigo'] = $contrato['codigo'];
    $a['contrato_aceite'] = $contrato['aceite'];
    $a['contrato_aceite_ip'] = '';
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

$src = __DIR__ . '/Contrato_Economic_Card_Vendas_por_Comissao.pdf';
$pageCount = $pdf->setSourceFile($src);
for ($p = 1; $p <= $pageCount; $p++) {
    $tpl = $pdf->importPage($p);
    $size = $pdf->getTemplateSize($tpl);
    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
    $pdf->useTemplate($tpl);
    rodape_contrato($pdf, $a['contrato_codigo']);
}

adicionar_certificado_aceite($pdf, [
    'certificado' => ['titulo' => 'Contrato Economic Card Vendas por Comissao'],
    'contrato_codigo' => $a['contrato_codigo'],
    'contrato_aceite' => $a['contrato_aceite'],
    'contrato_aceite_ip' => $a['contrato_aceite_ip'],
    'nome' => $a['nome'],
    'cpf' => $a['cpf'],
    'nascimento' => $a['nascimento'] ?? '',
    'email' => $a['email'] ?? '',
    'whatsapp' => $a['telefone'] ?? '',
]);

$arq = 'Contrato_Economic_Card_Vendas_por_Comissao_' . preg_replace('/[^A-Za-z0-9]+/', '_', trim($a['nome'])) . '.pdf';
$pdf->Output($preview && !$download ? 'I' : 'D', $arq);
exit;