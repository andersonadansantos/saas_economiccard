<?php
require_once __DIR__ . '/lib/fpdf/fpdf.php';

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 25);

$corPrincipal = [81, 3, 109];
$corSecundaria = [62, 106, 0];
$corTexto = [40, 40, 40];
$corLeve = [100, 100, 100];

function capa(&$pdf) {
    $pdf->AddPage();
    $pdf->SetFillColor(81, 3, 109);
    $pdf->Rect(0, 0, 210, 95, 'F');
    $pdf->SetY(35);
    $pdf->SetFont('Helvetica', 'B', 28);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 12, 'Politica de Privacidade', 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 14);
    $pdf->SetTextColor(200, 180, 220);
    $pdf->Cell(0, 8, 'Economic Card', 0, 1, 'C');
    $pdf->SetY(72);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(230, 210, 245);
    $pdf->Cell(0, 6, 'Conforme a Lei Geral de Protecao de Dados (LGPD - Lei 13.709/2018)', 0, 1, 'C');
    $pdf->SetY(105);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->MultiCell(0, 6, 'Ultima atualizacao: ' . date('d/m/Y') . "\n"
        . "Versao: 1.0\n"
        . "Responsavel: Economic Card LTDA (ou pessoa fisica responsavel)");
    $pdf->Ln(4);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(81, 3, 109);
    $pdf->Cell(0, 7, 'Indice', 0, 1);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(40, 40, 40);
    $indices = [
        '1. Introducao e Escopo',
        '2. Controlador dos Dados',
        '3. Dados Pessoais Coletados',
        '4. Finalidades e Bases Legais',
        '5. Compartilhamento com Terceiros',
        '6. Retencao e Eliminacao de Dados',
        '7. Direitos dos Titulares',
        '8. Seguranca dos Dados',
        '9. Cookies e Sessoes',
        '10. Menores de Idade',
        '11. Alteracoes nesta Politica',
        '12. Contato e Encarregado',
    ];
    foreach ($indices as $i => $item) {
        $pdf->Cell(0, 6, '  ' . $item, 0, 1);
    }
}

function titulo(&$pdf, $texto) {
    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->SetTextColor(81, 3, 109);
    $pdf->Ln(3);
    $pdf->Cell(0, 8, $texto, 0, 1);
    $pdf->SetDrawColor(81, 3, 109);
    $pdf->SetLineWidth(0.4);
    $pdf->Line(10, $pdf->GetY(), 80, $pdf->GetY());
    $pdf->Ln(3);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetFont('Helvetica', '', 10);
}

function subtitulo(&$pdf, $texto) {
    $pdf->SetFont('Helvetica', 'B', 10.5);
    $pdf->SetTextColor(62, 106, 0);
    $pdf->Cell(0, 6, $texto, 0, 1);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(40, 40, 40);
}

function texto(&$pdf, $texto) {
    $pdf->MultiCell(0, 5.5, $texto);
    $pdf->Ln(2);
}

function bullet(&$pdf, $texto) {
    $x = $pdf->GetX();
    $pdf->Cell(6, 5.5, chr(149), 0, 0);
    $pdf->MultiCell(0, 5.5, $texto);
    $pdf->Ln(0.5);
}

function tabela(&$pdf, $cabecalho, $linhas, $larguras) {
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetFillColor(81, 3, 109);
    $pdf->SetTextColor(255, 255, 255);
    for ($i = 0; $i < count($cabecalho); $i++) {
        $pdf->Cell($larguras[$i], 7, $cabecalho[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(40, 40, 40);
    $fill = false;
    foreach ($linhas as $linha) {
        $maxH = 0;
        foreach ($linha as $i => $celula) {
            $nLines = max(1, ceil($pdf->GetStringWidth($celula) / ($larguras[$i] - 2)));
            $maxH = max($maxH, $nLines * 5);
        }
        $maxH = max($maxH, 7);
        if ($fill) {
            $pdf->SetFillColor(245, 242, 248);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $y0 = $pdf->GetY();
        $x0 = $pdf->GetX();
        foreach ($linha as $i => $celula) {
            $pdf->SetXY($x0 + array_sum(array_slice($larguras, 0, $i)), $y0);
            $pdf->Cell($larguras[$i], $maxH, '', 1, 0, '', $fill);
            $pdf->SetXY($x0 + array_sum(array_slice($larguras, 0, $i)) + 1, $y0 + 1);
            $pdf->MultiCell($larguras[$i] - 2, 4.5, $celula, 0);
        }
        $pdf->SetXY($x0, $y0 + $maxH);
        $pdf->Ln(0);
        $fill = !$fill;
    }
    $pdf->Ln(4);
}

capa($pdf);

$pdf->AddPage();
titulo($pdf, '1. Introducao e Escopo');
texto($pdf, 'Esta Politica de Privacidade descreve como o Economic Card ("Plataforma"), disponivel em economiccard.com.br, coleta, utiliza, armazena e protege os dados pessoais dos usuarios que acessam ou utilizam nossos servicos.');
texto($pdf, 'Ao se cadastrar, acessar ou utilizar a Plataforma, o usuario concorda com as práticas descritas nesta Politica. Recomendamos a leitura atenta de todos os termos.');
texto($pdf, 'Esta Politica esta em conformidade com a Lei Geral de Protecao de Dados (LGPD - Lei 13.709/2018), o Codigo de Defesa do Consumidor (CDC) e demais normas aplicaveis da Republica Federativa do Brasil.');

titulo($pdf, '2. Controlador dos Dados');
texto($pdf, 'O controlador dos dados pessoais tratados pela Plataforma e o Economic Card, pessoa juridica de direito privado, inscrita no CNPJ sob o numero [INSERIR CNPJ], com endereco em [INSERIR ENDERECO COMPLETO].');
texto($pdf, 'Qualquer duvida ou solicitacao relativa aos dados pessoais pode ser encaminhada ao Encarregado de Protecao de Dados (DPO) pelo e-mail: [INSERIR E-MAIL DE CONTATO].');

titulo($pdf, '3. Dados Pessoais Coletados');
subtitulo($pdf, '3.1 Cadastro de Usuarios (Titulares do Cartao)');
texto($pdf, 'No momento do cadastro, sao coletados os seguintes dados:');
bullet($pdf, 'Nome completo');
bullet($pdf, 'Endereco de e-mail');
bullet($pdf, 'CPF (Cadastro de Pessoa Fisica) - utilizado como credencial de login e para processamento de pagamentos');
bullet($pdf, 'Numero de WhatsApp');
bullet($pdf, 'RG (Registro Geral)');
bullet($pdf, 'Data de nascimento');
bullet($pdf, 'CEP, cidade e endereco completo');
bullet($pdf, 'Foto/avatar de perfil (quando enviada pelo usuario)');
bullet($pdf, 'Dados de autenticacao via Google ou Facebook (quando o usuario opta pelo login social: ID da conta, nome, foto de perfil)');

subtitulo($pdf, '3.2 Cadastro de Afiliados');
texto($pdf, 'Para afiliados, sao coletados:');
bullet($pdf, 'Nome completo, e-mail, WhatsApp, CPF, data de nascimento e senha (em hash)');
bullet($pdf, 'Codigo de indicacao unico e token de rastreamento');
bullet($pdf, 'Dados bancarios via integracao Asaas (wallet ID) para repasse de comissoes');

subtitulo($pdf, '3.3 Cadastro de Parceiros');
texto($pdf, 'Para lojas parceiras, sao coletados:');
bullet($pdf, 'Nome da empresa, categoria, endereco, WhatsApp, Instagram, Facebook, site e logo (imagem)');

subtitulo($pdf, '3.4 Dados de Pagamento');
texto($pdf, 'Para processamento financeiro, os seguintes dados sao transmitidos ao gateway de pagamento Asaas:');
bullet($pdf, 'CPF/CNPJ, nome, e-mail, telefone e endereco do titular (para criacao de customer no Asaas)');
bullet($pdf, 'Dados de cartao de credito (numero, validade, CVV, nome no cartao) - processados e tokenizados pelo Asaas. O Economic Card NAO armazena numeros de cartao em seu banco de dados');
bullet($pdf, 'Chave PIX (payload copia-e-cola e QR Code em imagem) - gerada pelo Asaas e armazenada localmente ate a confirmacao do pagamento');
bullet($pdf, 'ID da transacao no Asaas (asaas_payment_id) e status do pagamento');

subtitulo($pdf, '3.5 Dados de Contrato');
texto($pdf, 'A Plataforma registra:');
bullet($pdf, 'Aceite do Contrato de Adesao com data/hora, endereco IP e identificacao do usuario');
bullet($pdf, 'Contrato gerado em PDF com nome, CPF, WhatsApp, e-mail e endereco');

subtitulo($pdf, '3.6 Dados de Comunicacao');
texto($pdf, 'Sao mantidos registros de:');
bullet($pdf, 'Mensagens in-app enviadas pelo administrador');
bullet($pdf, 'E-mails transacionais e de marketing enviados (titulo, destinatario, status de envio)');
bullet($pdf, 'Mensagens de WhatsApp enviadas (notificacoes de vencimento, lembretes)');

subtitulo($pdf, '3.7 Logs de Sistema');
texto($pdf, 'Para fins de diagnostico e seguranca, sao registrados logs que podem conter:');
bullet($pdf, 'Endereco IP, horario de acesso e dados de navegacao');
bullet($pdf, 'Registros de transacoes com dados parciais (nome, parte do CPF)');

titulo($pdf, '4. Finalidades e Bases Legais');
texto($pdf, 'Os dados pessoais sao tratados para as seguintes finalidades, conforme a base legal da LGPD:');
texto($pdf, 'Art. 7, I - Consentimento: comunicacoes de marketing, uso de cookies nao essenciais e dados de redes sociais.');
texto($pdf, 'Art. 7, II - Obrigacao legal: emissao de notas fiscais, cumprimento de obrigatoriedades regulatorias.');
texto($pdf, 'Art. 7, V - Execucao de contrato ou politicas publicas: cadastro, autenticacao, ativacao do cartao, processamento de pagamentos, geracao de contratos, acesso aos descontos e beneficios.');
texto($pdf, 'Art. 7, IX - Legitimo interesse: prevencao a fraude, melhoria dos servicos, comunicacoes transacionais (vencimento, lembretes de pagamento).');

tabela($pdf,
    ['Dado Coletado', 'Finalidade', 'Base Legal'],
    [
        ['Nome, e-mail, CPF, senha', 'Cadastro, login e autenticacao', 'Art. 7, V (contrato)'],
        ['CPF, dados de pagamento', 'Processamento financeiro via Asaas', 'Art. 7, V (contrato)'],
        ['WhatsApp', 'Notificacoes transacionais', 'Art. 7, IX (interesse legitimo)'],
        ['RG, nascimento, endereco', 'Geracao de contrato de adesao', 'Art. 7, V (contrato)'],
        ['Foto/avatar', 'Exibicao no perfil do usuario', 'Art. 7, I (consentimento)'],
        ['Dados de login social', 'Autenticacao via Google/Facebook', 'Art. 7, I (consentimento)'],
        ['IP e data/hora do aceite', 'Prova legal de aceite do contrato', 'Art. 7, II (obrigacao)'],
        ['E-mails e WhatsApp enviados', 'Comunicacao e suporte ao cliente', 'Art. 7, IX (interesse legitimo)'],
    ],
    [55, 70, 55]
);

titulo($pdf, '5. Compartilhamento com Terceiros');
texto($pdf, 'Os dados pessoais dos usuarios podem ser compartilhados exclusivamente para cumprir as finalidades descritas neste documento, com os seguintes terceiros:');

subtitulo($pdf, '5.1 Asaas (Gateway de Pagamento)');
texto($pdf, 'O Asaas (asaas.com) e o operador de pagamentos utilizado pela Plataforma. Sao enviados: nome, CPF/CNPJ, e-mail, telefone, endereco e, quando aplicavel, dados de cartao de credito (tokenizados) para processamento de cobrancas PIX e cartao de credito com split de pagamentos.');
texto($pdf, 'Politica de Privacidade: https://www.asaas.com/politica-de-privacidade');

subtitulo($pdf, '5.2 Evolution API (WhatsApp)');
texto($pdf, 'Plataforma de automacao de mensagens utilizada para envio de notificacoes transacionais via WhatsApp (lembretes de vencimento, avisos de ativacao). Sao enviados: nome do usuario e numero de WhatsApp.');
texto($pdf, 'Politica de Privacidade: https://docs.evolution-api.com/');

subtitulo($pdf, '5.3 Cloudflare Turnstile');
texto($pdf, 'Servico de protecao contra bots e automacoes (captcha), utilizado nas telas de login. O Turnstile pode coletar endereco IP e dados de navegacao para verificacao. Nenhum dado pessoal adicional e transmitido.');
texto($pdf, 'Politica de Privacidade: https://www.cloudflare.com/privacypolicy/');

subtitulo($pdf, '5.4 Google (OAuth)');
texto($pdf, 'Quando o usuario opta pelo login via Google, sao recebidos da conta Google: ID da conta, nome e foto de perfil. Nenhum dado adicional e transmitido ao Google pelo Economic Card.');
texto($pdf, 'Politica de Privacidade: https://policies.google.com/privacy');

subtitulo($pdf, '5.5 Meta / Facebook (OAuth)');
texto($pdf, 'Quando o usuario opta pelo login via Facebook, sao recebidos: ID da conta, nome e e-mail. Nenhum dado adicional e transmitido ao Facebook pelo Economic Card.');
texto($pdf, 'Politica de Privacidade: https://www.facebook.com/privacy/policy/');

subtitulo($pdf, '5.6 Servico de E-mail (SMTP)');
texto($pdf, 'Os dados de destinatario (e-mail) e conteudo das mensagens sao transmitidos ao servidor SMTP configurado para envio de e-mails transacionais e de marketing. O Economic Card utiliza servidor de e-mail proprio ou de terceiros contratado.');

subtitulo($pdf, '5.7 ViaCEP');
texto($pdf, 'O CEP digitado pelo usuario e consultado na API publica viacep.com.br para auto-completar endereco. O CEP e o unico dado transmitido. Nao ha registro persistente desta consulta.');
texto($pdf, 'Politica de Privacidade: https://viacep.com.br/');

texto($pdf, 'O Economic Card NAO vende, aluga ou compartilha dados pessoais com terceiros para fins de marketing ou fins nao descritos nesta Politica.');

titulo($pdf, '6. Retencao e Eliminacao de Dados');
texto($pdf, 'Os dados pessoais sao armazenados pelo tempo necessario para cumprir as finalidades para as quais foram coletados:');
bullet($pdf, 'Dados de cadastro: mantidos enquanto a conta do usuario estiver ativa, e por ate 5 (cinco) anos apos o ultimo acesso, conforme obrigacao legal e prescricao de direitos.');
bullet($pdf, 'Dados de pagamento: mantidos por 5 (cinco) anos apos a transacao, em conformidade com obrigatoriedades fiscais e contabeis.');
bullet($pdf, 'Registros de aceite de contrato: mantidos por 5 (cinco) anos como prova legal.');
bullet($pdf, 'Logs de sistema: mantidos por 90 (noventa) dias para fins de diagnostico e seguranca, sendo automaticamente eliminados apos este periodo.');
bullet($pdf, 'E-mails e WhatsApp enviados: registros de envio mantidos por 12 (doze) meses.');
bullet($pdf, 'Apos o periodo de retencao, os dados sao eliminados de forma segura e irrecuperavel.');
texto($pdf, 'O usuario pode solicitar a eliminacao de sua conta e dados a qualquer tempo (ver Secao 7).');

titulo($pdf, '7. Direitos dos Titulares');
texto($pdf, 'Nos termos da LGPD (Art. 18), o usuario titular dos dados pessoais tem direito a:');
bullet($pdf, 'Confirmacao da existencia de tratamento de dados (Art. 18, I)');
bullet($pdf, 'Acesso aos dados pessoais tratados (Art. 18, II)');
bullet($pdf, 'Correcao de dados incompletos, inexatos ou desatualizados (Art. 18, III)');
bullet($pdf, 'Anonimizacao, bloqueio ou eliminacao de dados desnecessarios, excessivos ou tratados em desconformidade com a LGPD (Art. 18, IV)');
bullet($pdf, 'Portabilidade dos dados a outro fornecedor de servico (Art. 18, V)');
bullet($pdf, 'Eliminacao dos dados pessoais tratados com consentimento (Art. 18, VI)');
bullet($pdf, 'Informacao sobre entidades publicas e privadas com as quais houve uso compartilhado (Art. 18, VII)');
bullet($pdf, 'Informacao sobre a possibilidade de nao fornecer consentimento e sobre as consequencias (Art. 18, VIII)');
bullet($pdf, 'Revogacao do consentimento (Art. 18, IX)');
texto($pdf, 'Para exercer qualquer um desses direitos, o usuario deve entrar em contato pelo e-mail: [INSERIR E-MAIL DE CONTATO]. A solicitacao sera atendida em ate 15 (quinze) dias uteis, conforme LGPD.');
texto($pdf, 'O usuario tambem pode solicitar a exclusao de sua conta diretamente pela tela de Perfil na Plataforma, ou mediante solicitacao por e-mail. Apos a exclusao, os dados serao eliminados conforme os prazos de retencao descritos na Secao 6.');

titulo($pdf, '8. Seguranca dos Dados');
texto($pdf, 'O Economic Card adota medidas tecnicas e organizacionais para proteger os dados pessoais contra acessos nao autorizados, alteracoes, divulgacao ou destruicao indevida, incluindo:');
bullet($pdf, 'Senhas armazenadas em formato irrecuperavel (hash com password_hash/bcrypt) - nunca em texto plano');
bullet($pdf, 'Comunicacao com a API de pagamentos Asaas realizada exclusivamente via HTTPS/TLS');
bullet($pdf, 'Chaves de API armazenadas em variaveis de ambiente ou em arquivos protegidos por .gitignore (fora do repositorio de codigo)');
bullet($pdf, 'Controle de acesso ao painel administrativo com autenticacao por senha');
bullet($pdf, 'Protecao contra injecao SQL via prepared statements (mysqli) em todas as consultas ao banco de dados');
bullet($pdf, 'Protecao contra ataques de scripts entre sites (XSS) via sanitizacao com htmlspecialchars em todas as saidas HTML');
bullet($pdf, 'Protecao CSRF em operacoes criticas');
bullet($pdf, 'Logs de acesso protegidos com restricao via Apache (.htaccess)');
bullet($pdf, 'Backup regular do banco de dados');
texto($pdf, 'Embora adotemos as melhores praticas de seguranca, nenhum metodo de transmissao ou armazenamento eletronico e 100% seguro. Em caso de incidente de seguranca que possa acarretar risco ao usuario, seremos notificados conforme exigido pela LGPD.');

titulo($pdf, '9. Cookies e Sessoes');
texto($pdf, 'A Plataforma utiliza apenas sessoes HTTP (PHP Sessions) para manter a autenticacao do usuario durante a navegacao. Nao sao utilizados cookies de rastreamento, cookies de terceiros ou tecnologias de fingerprinting.');
bullet($pdf, 'Sessao PHP: utilizada exclusivamente para identificar o usuario autenticado e manter seu acesso durante a navegacao na Plataforma');
bullet($pdf, 'Nao utilizamos Google Analytics, Meta Pixel, cookies de publicidade ou qualquer ferramenta de rastreamento comportamental');
bullet($pdf, 'O Cloudflare Turnstile pode utilizar cookies tecnicos para funcionalidade de protecao contra bots');

titulo($pdf, '10. Menores de Idade');
texto($pdf, 'O servico do Economic Card e destinado a maiores de 18 (dezoito) anos de idade. O cadastro de menores de 18 anos so e permitido mediante consentimento especifico e destacado de pelo menos um dos pais ou responsavel legal, nos termos da LGPD (Art. 14).');
texto($pdf, 'Cientes de que dados de menores recebem tratamento prioritario de protecao, o Economic Card se reserva o direito de cancelar contas de menores nao autorizadas por responsavel legal.');

titulo($pdf, '11. Alteracoes nesta Politica');
texto($pdf, 'Esta Politica de Privacidade pode ser atualizada periodicamente para refletir mudancas em nossas praticas, servicos ou obrigatoriedades legais. As alteracoes serao disponibilizadas nesta pagina com a data da ultima atualizacao.');
texto($pdf, 'Recomendamos que os usuarios consultem esta Politica regularmente. O uso continuado da Plataforma apos eventuais alteracoes constitui aceite das novas condicoes.');

titulo($pdf, '12. Contato e Encarregado');
texto($pdf, 'Para exercer seus direitos como titular de dados, esclarecer duvidas ou apresentar reclamacoes relativas a esta Politica de Privacidade ou ao tratamento de seus dados pessoais:');
texto($pdf, "Economic Card\n"
    . "E-mail: [INSERIR E-MAIL]\n"
    . "WhatsApp: [INSERIR TELEFONE]\n"
    . "Endereco: [INSERIR ENDERECO COMPLETO]\n"
    . "Encarregado de Protecao de Dados (DPO): [INSERIR NOME]\n"
    . "E-mail do DPO: [INSERIR E-MAIL DO DPO]");
texto($pdf, 'Caso nao receba resposta satisfatoria, o usuario pode encaminhar reclamacao a Autoridade Nacional de Protecao de Dados (ANPD): https://www.gov.br/anpd/');

$pdf->Output('F', __DIR__ . '/Politica_de_Privacidade.pdf');
echo 'PDF gerado com sucesso: ' . __DIR__ . '/Politica_de_Privacidade.pdf' . "\n";
echo 'Paginas: ' . $pdf->PageNo() . "\n";
