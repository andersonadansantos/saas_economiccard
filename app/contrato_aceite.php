<?php
// Helper de contratos: código único + certificação de aceite.
// Incluído automaticamente via config.php.

function contrato_prefixo($tabela) {
    return [
        'usuarios'  => 'ECU',
        'afiliados' => 'ECA',
        'parceiros' => 'ECP',
    ][$tabela] ?? 'ECC';
}

function novo_codigo_contrato($prefixo) {
    return $prefixo . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

// Gera um código único (não repetido na tabela).
function gerar_codigo_contrato_unico($conn, $tabela) {
    $prefixo = contrato_prefixo($tabela);
    do {
        $codigo = novo_codigo_contrato($prefixo);
        $st = $conn->prepare("SELECT id FROM `$tabela` WHERE contrato_codigo = ?");
        $st->bind_param('s', $codigo);
        $st->execute();
    } while ($st->get_result()->num_rows > 0);
    return $codigo;
}

// Registra o aceite (código + data/hora + IP) num registro recém-criado ou existente.
// Retorna ['codigo' => string, 'aceite' => string].
function registrar_aceite_contrato($conn, $tabela, $id, $aceiteEm = null) {
    $codigo = gerar_codigo_contrato_unico($conn, $tabela);
    $aceite = $aceiteEm ?: date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $st = $conn->prepare("UPDATE `$tabela` SET contrato_codigo = ?, contrato_aceite = ?, contrato_aceite_ip = ? WHERE id = ?");
    $st->bind_param('sssi', $codigo, $aceite, $ip, $id);
    $st->execute();
    return ['codigo' => $codigo, 'aceite' => $aceite];
}

// Retorna ['codigo', 'aceite'] do registro; gera na hora se ainda não existir.
function garantir_aceite_contrato($conn, $tabela, $id, $aceiteEm = null) {
    $st = $conn->prepare("SELECT contrato_codigo, contrato_aceite FROM `$tabela` WHERE id = ?");
    $st->bind_param('i', $id);
    $st->execute();
    $dados = $st->get_result()->fetch_assoc();
    if ($dados && !empty($dados['contrato_codigo'])) {
        return ['codigo' => $dados['contrato_codigo'], 'aceite' => $dados['contrato_aceite']];
    }
    return registrar_aceite_contrato($conn, $tabela, $id, $aceiteEm);
}

// Backfill: atribui código + aceite (usando a data de criação) a registros antigos.
function backfill_contratos($conn, $tabela) {
    $res = $conn->query("SELECT id, criado_em FROM `$tabela` WHERE contrato_codigo IS NULL OR contrato_codigo = ''");
    $n = 0;
    while ($r = $res->fetch_assoc()) {
        $aceite = $r['criado_em'] ? date('Y-m-d H:i:s', strtotime($r['criado_em'])) : date('Y-m-d H:i:s');
        registrar_aceite_contrato($conn, $tabela, (int)$r['id'], $aceite);
        $n++;
    }
    return $n;
}

// Desenha o código do contrato em letras pequenas no rodapé da página atual.
function rodape_contrato($pdf, $codigo) {
    if ($codigo === '' || $codigo === null) return;
    $latin = function($s) {
        $s = html_entity_decode((string)$s, ENT_QUOTES, 'UTF-8');
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
    };
    $w = $pdf->GetPageWidth();
    $h = $pdf->GetPageHeight();
    $pdf->SetFont('Helvetica', '', 6.5);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->SetXY(0, $h - 8);
    $pdf->Cell($w, 5, $latin('Contrato Nº ' . $codigo), 0, 0, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

// Adiciona uma página final de certificado de aceite ao PDF (exige FPDF/FPDI carregados).
function adicionar_certificado_aceite($pdf, $dados) {
    $latin = function($s) {
        $s = html_entity_decode((string)$s, ENT_QUOTES, 'UTF-8');
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
    };
    $cert = $dados['certificado'] ?? [];
    $titulo        = $cert['titulo'] ?? 'Contrato';
    $codigo        = $dados['contrato_codigo'] ?? '';
    $aceite        = $dados['contrato_aceite'] ?? '';
    $aceiteIp      = $dados['contrato_aceite_ip'] ?? '';
    $nome          = $dados['nome'] ?? '';
    $cpf           = $dados['cpf'] ?? '';
    $rg            = $dados['rg'] ?? '';
    $nascimento    = $dados['nascimento'] ?? '';
    $email         = $dados['email'] ?? '';
    $telefone      = $dados['whatsapp'] ?? $dados['telefone'] ?? '';
    $endereco      = $dados['endereco'] ?? '';
    $cidade        = $dados['cidade'] ?? '';
    $emitidoEm     = date('d/m/Y H:i');

    if ($aceite) {
        $ts = strtotime($aceite);
        $aceiteFmt = date('d/m/Y', $ts) . ' às ' . date('H:i', $ts);
        $aceiteDia = date('d', $ts);
        $aceiteMes = date('m', $ts);
        $aceiteAno = date('Y', $ts);
    } else {
        $aceiteFmt = '-';
        $aceiteDia = '____';
        $aceiteMes = '____';
        $aceiteAno = '____';
    }

    $A4 = [210, 297]; // mm
    $pdf->AddPage('P', $A4);
    $pdf->SetMargins(20, 16, 20);
    $pdf->SetAutoPageBreak(true, 22);

    // Cabeçalho
    $pdf->SetFont('Helvetica', 'B', 15);
    $pdf->SetFillColor(81, 3, 109);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 12, 'CERTIFICADO DE ACEITE DE CONTRATO', 0, 1, 'C', true);
    $pdf->Ln(5);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(0, 8, $latin($titulo), 0, 1, 'C');
    $pdf->Ln(6);

    // Bloco do código
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 245);
    $pdf->Cell(0, 13, 'CODIGO DO CONTRATO: ' . $latin($codigo), 0, 1, 'C', true);
    $pdf->Ln(6);

    // Qualificação completa do contratante
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'QUALIFICACAO DO CONTRATANTE', 0, 1, 'L');
    $pdf->SetDrawColor(81, 3, 109);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(3);

    $pdf->SetFont('Helvetica', '', 10.5);
    $pdf->MultiCell(0, 6, "NOME: " . $latin($nome), 0, 'L');
    if ($cpf !== '') {
        $pdf->MultiCell(0, 6, "CPF: " . $latin($cpf), 0, 'L');
    }
    if ($rg !== '') {
        $pdf->MultiCell(0, 6, "RG: " . $latin($rg), 0, 'L');
    }
    if ($nascimento !== '') {
        $pdf->MultiCell(0, 6, "DATA DE NASCIMENTO: " . $latin(date('d/m/Y', strtotime($nascimento))), 0, 'L');
    }
    if ($telefone !== '') {
        $pdf->MultiCell(0, 6, "TELEFONE: " . $latin($telefone), 0, 'L');
    }
    if ($email !== '') {
        $pdf->MultiCell(0, 6, "E-MAIL: " . $latin($email), 0, 'L');
    }
    $endCompleto = trim($endereco . ($cidade !== '' ? ' - ' . $cidade : ''));
    if ($endCompleto !== '') {
        $pdf->MultiCell(0, 6, "ENDEREÇO: " . $latin($endCompleto), 0, 'L');
    }
    $pdf->Ln(4);

    // Declaração de aceite
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'DECLARACAO DE ACEITE EXPRESSO', 0, 1, 'L');
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(3);

    $pdf->SetFont('Helvetica', '', 10);
    $texto = "O CONTRATANTE acima qualificado declara, de forma livre, expressa e espontanea, que leu, compreendeu e ACEITOU integralmente todas as Clausulas e condicoes do instrumento contratual identificado pelo codigo " . $latin($codigo) . ", manifestando seu aceite definitivo, sem ressalvas, realizado por meio eletronico em " . $latin($aceiteFmt) . ".";
    if ($aceiteIp) {
        $texto .= "\n\nAceite registrado eletronicamente a partir do endereco IP " . $latin($aceiteIp) . ", devidamente identificado e vinculado a este documento.";
    }
    $texto .= "\n\nNos termos do art. 10, § 2º, da Medida Provisoria 2.200-2/2001 e do art. 219 da Lei 13.105/2015, o presente aceite eletronico tem plena validade juridica, fazendo o presente instrumento prova entre as partes. O contratante declara ainda receber e concordar com o tratamento dos seus dados pessoais na forma da Lei 13.709/2018 (LGPD).";
    $pdf->SetTextColor(60, 60, 60);
    $pdf->MultiCell(0, 6, $latin($texto), 0, 'J');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(5);

    // Local e data
    $pdf->SetFont('Helvetica', '', 10.5);
    $local = ($cidade !== '' ? $cidade . ', ' : '__________________, ');
    $pdf->MultiCell(0, 6, "LOCAL E DATA: " . $latin($local . $aceiteDia . ' de ' . $aceiteMes . ' de ' . $aceiteAno), 0, 'L');
    $pdf->Ln(6);

    // Linha de assinatura do contratante
    $y = $pdf->GetY();
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Line(35, $y, 175, $y);
    $pdf->Ln(2);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 5, $latin($nome), 0, 1, 'C');
    $pdf->Cell(0, 5, 'Contratante', 0, 1, 'C');
    $pdf->Ln(8);

    // Testemunhas
    $y = $pdf->GetY();
    $pdf->Line(35, $y, 100, $y);
    $pdf->Line(110, $y, 175, $y);
    $pdf->Ln(2);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(65, 5, 'Testemunha 1', 0, 0, 'C');
    $pdf->Cell(65, 5, 'Testemunha 2', 0, 1, 'C');
    $pdf->Ln(6);

    // Rodapé do documento
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->MultiCell(0, 4, 'Documento gerado eletronicamente em ' . $latin($emitidoEm) . ' pelo sistema Economic Card. Documento de aceite e vinculado ao contrato de codigo ' . $latin($codigo) . '.', 0, 'C');
}