<?php
require_once __DIR__ . '/config.php';
$pers = (function () use ($conn) {
    try { return $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc(); }
    catch (Throwable $e) { return null; }
})();
$logoApp = $pers['logo_login_user'] ?? '';
function polUrl($src) {
    if (!$src) return '';
    if (preg_match('#^https?://#i', $src) || strpos($src, 'data:') === 0) return $src;
    return asset_url($src);
}
$tituloPagina = 'Política de Privacidade';
?>
<!DOCTYPE html>
<html class="light" lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title><?php echo htmlspecialchars($tituloPagina); ?> - Economic Card</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Hanken+Grotesk:wght@600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#51036d", "primary-dark": "#3a024d", "primary-container": "#6a2585",
                    secondary: "#3e6a00", "secondary-container": "#b6f570",
                    surface: "#f4f5f7", "on-surface": "#191c1d", "surface-variant": "#e1e3e4",
                    "on-surface-variant": "#4e434f", "outline-variant": "#d1c2d1",
                    "surface-container-lowest": "#ffffff", "surface-container-high": "#e7e8e9"
                },
                borderRadius: { lg: "0.5rem", xl: "0.75rem", "2xl": "1rem", full: "9999px" },
                fontFamily: { sans: ["Manrope", "sans-serif"], display: ["Hanken Grotesk", "sans-serif"] }
            }
        }
    };
</script>
<style>
    body { background-color: #ffffff; color: #191c1d; font-family: 'Manrope', sans-serif; -webkit-tap-highlight-color: transparent; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .card-shadow { box-shadow: 0 8px 30px rgba(81, 3, 109, 0.08); }
    .legal-section h2 { color: #51036d; font-weight: 800; font-family: 'Hanken Grotesk', sans-serif; margin-top: 2.5rem; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 2px solid #51036d; }
    .legal-section h3 { color: #51036d; font-weight: 700; margin-top: 1.5rem; margin-bottom: 0.5rem; }
    .legal-section p { line-height: 1.75; color: #000000; margin-bottom: 1rem; }
    .legal-section ul { padding-left: 1.5rem; list-style: disc; color: #000000; }
    .legal-section ul li { margin-bottom: 0.5rem; line-height: 1.6; }
    .legal-section li b { color: #000000; }
    .legal-section table { width: 100%; border-collapse: collapse; margin: 1rem 0 1.5rem; font-size: 0.875rem; }
    .legal-section table th { background-color: #51036d; color: #fff; padding: 0.6rem; text-align: left; font-weight: 600; }
    .legal-section table td { padding: 0.6rem; border: 1px solid #d1c2d1; vertical-align: top; }
    .legal-section table tr:nth-child(even) td { background-color: #f5f2f8; }
</style>
</head>
<body class="min-h-screen font-sans antialiased">
<div class="min-h-screen flex flex-col bg-white">
<header class="sticky top-0 z-40 bg-white border-b border-gray-200">
<div class="max-w-3xl mx-auto px-4 md:px-8 h-16 flex items-center justify-between">
<div class="flex items-center gap-3">
<a href="login.php"><button class="p-2 -ml-2 rounded-lg hover:bg-gray-100"><span class="material-symbols-outlined text-gray-700">arrow_back</span></button></a>
<?php if ($logoApp): ?>
<img class="w-8 h-8 rounded-lg object-contain bg-gray-100 p-0.5" src="<?php echo polUrl($logoApp); ?>" alt="Logo Economic Card"/>
<?php else: ?>
<span class="material-symbols-outlined text-primary">credit_card</span>
<?php endif; ?>
<span class="text-sm font-bold text-black">Política de Privacidade</span>
</div>
</div>
</header>

<main class="max-w-3xl mx-auto w-full px-4 md:px-8 py-10">
<section class="bg-white card-shadow rounded-2xl p-6 md:p-10 legal-section">

<div class="-m-6 md:-m-10 mb-8 p-6 md:p-10 rounded-t-2xl border-b border-gray-200">
<div class="text-3xl md:text-4xl font-extrabold font-display mb-1 text-primary">Política de Privacidade</div>
<p class="text-black text-lg mb-1 font-semibold">Economic Card</p>
<p class="text-black text-sm">Conforme a Lei Geral de Proteção de Dados (LGPD - Lei 13.709/2018)</p>
<div class="mt-5 text-sm text-black leading-relaxed">
<p>Última atualização: <?php echo date('d/m/Y'); ?></p>
<p>Versão: 1.0</p>
<p>Responsável: Economic Card LTDA (ou pessoa física responsável)</p>
</div>
</div>

<h2 class="!mt-0">Índice</h2>
<ol class="list-decimal list-inside text-black space-y-1 mb-6">
<?php
$indices = [
    'Introdução e Escopo',
    'Controlador dos Dados',
    'Dados Pessoais Coletados',
    'Finalidades e Bases Legais',
    'Compartilhamento com Terceiros',
    'Retenção e Eliminação de Dados',
    'Direitos dos Titulares',
    'Segurança dos Dados',
    'Cookies e Sessões',
    'Menores de Idade',
    'Alterações nesta Política',
    'Contato e Encarregado',
];
foreach ($indices as $i => $item) {
    echo '<li><a class="text-primary font-semibold hover:underline" href="#sec' . ($i + 1) . '">' . $i + 1 . '. ' . htmlspecialchars($item) . '</a></li>';
}
?>
</ol>

<h2 id="sec1">1. Introdução e Escopo</h2>
<p>Esta Política de Privacidade descreve como o <b>Economic Card</b> ("Plataforma"), disponível em economiccard.com.br, coleta, utiliza, armazena e protege os dados pessoais dos usuários que acessam ou utilizam nossos serviços.</p>
<p>Ao se cadastrar, acessar ou utilizar a Plataforma, o usuário concorda com as práticas descritas nesta Política. Recomendamos a leitura atenta de todos os termos.</p>
<p>Esta Política está em conformidade com a Lei Geral de Proteção de Dados (LGPD - Lei 13.709/2018), o Código de Defesa do Consumidor (CDC) e demais normas aplicáveis da República Federativa do Brasil.</p>

<h2 id="sec2">2. Controlador dos Dados</h2>
<p>O controlador dos dados pessoais tratados pela Plataforma é o <b>Economic Card</b>, pessoa jurídica de direito privado, com endereço em Rua Moura Carvalho 136 - Agulha - Belém/PA.</p>
<p>Qualquer dúvida ou solicitação relativa aos dados pessoais pode ser encaminhada ao Encarregado de Proteção de Dados (DPO) pelo e-mail: <b>negocio@economiccard.com.br</b>.</p>

<h2 id="sec3">3. Dados Pessoais Coletados</h2>
<h3>3.1 Cadastro de Usuários (Titulares do Cartão)</h3>
<p>No momento do cadastro, são coletados os seguintes dados:</p>
<ul>
<li>Nome completo</li>
<li>Endereço de e-mail</li>
<li>CPF (Cadastro de Pessoa Física) - utilizado como credencial de login e para processamento de pagamentos</li>
<li>Número de WhatsApp</li>
<li>RG (Registro Geral)</li>
<li>Data de nascimento</li>
<li>CEP, cidade e endereço completo</li>
<li>Foto/avatar de perfil (quando enviada pelo usuário)</li>
<li>Dados de autenticação via Google ou Facebook (quando o usuário opta pelo login social: ID da conta, nome, foto de perfil)</li>
</ul>

<h3>3.2 Cadastro de Afiliados</h3>
<p>Para afiliados, são coletados:</p>
<ul>
<li>Nome completo, e-mail, WhatsApp, CPF, data de nascimento e senha (em hash)</li>
<li>Código de indicação único e token de rastreamento</li>
<li>Dados bancários via integração Asaas (wallet ID) para repasse de comissões</li>
</ul>

<h3>3.3 Cadastro de Parceiros</h3>
<p>Para lojas parceiras, são coletados:</p>
<ul>
<li>Nome da empresa, categoria, endereço, WhatsApp, Instagram, Facebook, site e logo (imagem)</li>
</ul>

<h3>3.4 Dados de Pagamento</h3>
<p>Para processamento financeiro, os seguintes dados são transmitidos ao gateway de pagamento Asaas:</p>
<ul>
<li>CPF/CNPJ, nome, e-mail, telefone e endereço do titular (para criação de customer no Asaas)</li>
<li>Dados de cartão de crédito (número, validade, CVV, nome no cartão) - processados e tokenizados pelo Asaas. O Economic Card <b>NÃO armazena números de cartão</b> em seu banco de dados</li>
<li>Chave PIX (payload copia-e-cola e QR Code em imagem) - gerada pelo Asaas e armazenada localmente até a confirmação do pagamento</li>
<li>ID da transação no Asaas (asaas_payment_id) e status do pagamento</li>
</ul>

<h3>3.5 Dados de Contrato</h3>
<p>A Plataforma registra:</p>
<ul>
<li>Aceite do Contrato de Adesão com data/hora, endereço IP e identificação do usuário</li>
<li>Contrato gerado em PDF com nome, CPF, WhatsApp, e-mail e endereço</li>
</ul>

<h3>3.6 Dados de Comunicação</h3>
<p>São mantidos registros de:</p>
<ul>
<li>Mensagens in-app enviadas pelo administrador</li>
<li>E-mails transacionais e de marketing enviados (título, destinatário, status de envio)</li>
<li>Mensagens de WhatsApp enviadas (notificações de vencimento, lembretes)</li>
</ul>

<h3>3.7 Logs de Sistema</h3>
<p>Para fins de diagnóstico e segurança, são registrados logs que podem conter:</p>
<ul>
<li>Endereço IP, horário de acesso e dados de navegação</li>
<li>Registros de transações com dados parciais (nome, parte do CPF)</li>
</ul>

<h2 id="sec4">4. Finalidades e Bases Legais</h2>
<p>Os dados pessoais são tratados para as seguintes finalidades, conforme a base legal da LGPD:</p>
<p><b>Art. 7, I - Consentimento:</b> comunicações de marketing, uso de cookies não essenciais e dados de redes sociais.</p>
<p><b>Art. 7, II - Obrigação legal:</b> emissão de notas fiscais, cumprimento de obrigatoriedades regulatórias.</p>
<p><b>Art. 7, V - Execução de contrato:</b> cadastro, autenticação, ativação do cartão, processamento de pagamentos, geração de contratos, acesso aos descontos e benefícios.</p>
<p><b>Art. 7, IX - Legítimo interesse:</b> prevenção à fraude, melhoria dos serviços, comunicações transacionais (vencimento, lembretes de pagamento).</p>

<table>
<thead>
<tr><th>Dado Coletado</th><th>Finalidade</th><th>Base Legal</th></tr>
</thead>
<tbody>
<tr><td>Nome, e-mail, CPF, senha</td><td>Cadastro, login e autenticação</td><td>Art. 7, V (contrato)</td></tr>
<tr><td>CPF, dados de pagamento</td><td>Processamento financeiro via Asaas</td><td>Art. 7, V (contrato)</td></tr>
<tr><td>WhatsApp</td><td>Notificações transacionais</td><td>Art. 7, IX (interesse legítimo)</td></tr>
<tr><td>RG, nascimento, endereço</td><td>Geração de contrato de adesão</td><td>Art. 7, V (contrato)</td></tr>
<tr><td>Foto/avatar</td><td>Exibição no perfil do usuário</td><td>Art. 7, I (consentimento)</td></tr>
<tr><td>Dados de login social</td><td>Autenticação via Google/Facebook</td><td>Art. 7, I (consentimento)</td></tr>
<tr><td>IP e data/hora do aceite</td><td>Prova legal de aceite do contrato</td><td>Art. 7, II (obrigação)</td></tr>
<tr><td>E-mails e WhatsApp enviados</td><td>Comunicação e suporte ao cliente</td><td>Art. 7, IX (interesse legítimo)</td></tr>
</tbody>
</table>

<h2 id="sec5">5. Compartilhamento com Terceiros</h2>
<p>Os dados pessoais dos usuários podem ser compartilhados exclusivamente para cumprir as finalidades descritas neste documento, com os seguintes terceiros:</p>

<h3>5.1 Asaas (Gateway de Pagamento)</h3>
<p>O Asaas (asaas.com) é o operador de pagamentos utilizado pela Plataforma. São enviados: nome, CPF/CNPJ, e-mail, telefone, endereço e, quando aplicável, dados de cartão de crédito (tokenizados) para processamento de cobranças PIX e cartão de crédito com split de pagamentos.</p>
<p>Política de Privacidade: https://www.asaas.com/politica-de-privacidade</p>

<h3>5.2 Evolution API (WhatsApp)</h3>
<p>Plataforma de automação de mensagens utilizada para envio de notificações transacionais via WhatsApp (lembretes de vencimento, avisos de ativação). São enviados: nome do usuário e número de WhatsApp.</p>
<p>Política de Privacidade: https://docs.evolution-api.com/</p>

<h3>5.3 Cloudflare Turnstile</h3>
<p>Serviço de proteção contra bots e automações (captcha), utilizado nas telas de login. O Turnstile pode coletar endereço IP e dados de navegação para verificação. Nenhum dado pessoal adicional é transmitido.</p>
<p>Política de Privacidade: https://www.cloudflare.com/privacypolicy/</p>

<h3>5.4 Google (OAuth)</h3>
<p>Quando o usuário opta pelo login via Google, são recebidos da conta Google: ID da conta, nome e foto de perfil. Nenhum dado adicional é transmitido ao Google pelo Economic Card.</p>
<p>Política de Privacidade: https://policies.google.com/privacy</p>

<h3>5.5 Meta / Facebook (OAuth)</h3>
<p>Quando o usuário opta pelo login via Facebook, são recebidos: ID da conta, nome e e-mail. Nenhum dado adicional é transmitido ao Facebook pelo Economic Card.</p>
<p>Política de Privacidade: https://www.facebook.com/privacy/policy/</p>

<h3>5.6 Serviço de E-mail (SMTP)</h3>
<p>Os dados de destinatário (e-mail) e conteúdo das mensagens são transmitidos ao servidor SMTP configurado para envio de e-mails transacionais e de marketing. O Economic Card utiliza servidor de e-mail próprio ou de terceiros contratado.</p>

<h3>5.7 ViaCEP</h3>
<p>O CEP digitado pelo usuário é consultado na API pública viacep.com.br para auto-completar endereço. O CEP é o único dado transmitido. Não há registro persistente desta consulta.</p>
<p>Política de Privacidade: https://viacep.com.br/</p>

<p><b>O Economic Card NÃO vende, aluga ou compartilha dados pessoais com terceiros para fins de marketing ou fins não descritos nesta Política.</b></p>

<h2 id="sec6">6. Retenção e Eliminação de Dados</h2>
<p>Os dados pessoais são armazenados pelo tempo necessário para cumprir as finalidades para as quais foram coletados:</p>
<ul>
<li><b>Dados de cadastro:</b> mantidos enquanto a conta do usuário estiver ativa, e por até 5 (cinco) anos após o último acesso, conforme obrigação legal e prescrição de direitos.</li>
<li><b>Dados de pagamento:</b> mantidos por 5 (cinco) anos após a transação, em conformidade com obrigatoriedades fiscais e contábeis.</li>
<li><b>Registros de aceite de contrato:</b> mantidos por 5 (cinco) anos como prova legal.</li>
<li><b>Logs de sistema:</b> mantidos por 90 (noventa) dias para fins de diagnóstico e segurança, sendo automaticamente eliminados após este período.</li>
<li><b>E-mails e WhatsApp enviados:</b> registros de envio mantidos por 12 (doze) meses.</li>
<li>Apurado o período de retenção, os dados são eliminados de forma segura e irrecuperável.</li>
</ul>
<p>O usuário pode solicitar a eliminação de sua conta e dados a qualquer tempo (ver Seção 7).</p>

<h2 id="sec7">7. Direitos dos Titulares</h2>
<p>Nos termos da LGPD (Art. 18), o usuário titular dos dados pessoais tem direito a:</p>
<ul>
<li>Confirmação da existência de tratamento de dados (Art. 18, I)</li>
<li>Acesso aos dados pessoais tratados (Art. 18, II)</li>
<li>Correção de dados incompletos, inexatos ou desatualizados (Art. 18, III)</li>
<li>Anonimização, bloqueio ou eliminação de dados desnecessários, excessivos ou tratados em desconformidade com a LGPD (Art. 18, IV)</li>
<li>Portabilidade dos dados a outro fornecedor de serviço (Art. 18, V)</li>
<li>Eliminação dos dados pessoais tratados com consentimento (Art. 18, VI)</li>
<li>Informação sobre entidades públicas e privadas com as quais houve uso compartilhado (Art. 18, VII)</li>
<li>Informação sobre a possibilidade de não fornecer consentimento e sobre as consequências (Art. 18, VIII)</li>
<li>Revogação do consentimento (Art. 18, IX)</li>
</ul>
<p>Para exercer qualquer um desses direitos, o usuário deve entrar em contato pelo e-mail: <b>negocio@economiccard.com.br</b>. A solicitação será atendida em até 15 (quinze) dias úteis, conforme LGPD.</p>
<p>O usuário também pode solicitar a exclusão de sua conta diretamente pela tela de Perfil na Plataforma, ou mediante solicitação por e-mail. Após a exclusão, os dados serão eliminados conforme os prazos de retenção descritos na Seção 6.</p>

<h2 id="sec8">8. Segurança dos Dados</h2>
<p>O Economic Card adota medidas técnicas e organizacionais para proteger os dados pessoais contra acessos não autorizados, alterações, divulgação ou destruição indevida, incluindo:</p>
<ul>
<li>Senhas armazenadas em formato irrecuperável (hash com password_hash/bcrypt) - nunca em texto plano</li>
<li>Comunicação com a API de pagamentos Asaas realizada exclusivamente via HTTPS/TLS</li>
<li>Chaves de API armazenadas em variáveis de ambiente ou em arquivos protegidos por .gitignore (fora do repositório de código)</li>
<li>Controle de acesso ao painel administrativo com autenticação por senha</li>
<li>Proteção contra injeção SQL via prepared statements (mysqli) em todas as consultas ao banco de dados</li>
<li>Proteção contra ataques de scripts entre sites (XSS) via sanitização com htmlspecialchars em todas as saídas HTML</li>
<li>Proteção CSRF em operações críticas</li>
<li>Logs de acesso protegidos com restrição via Apache (.htaccess)</li>
<li>Backup regular do banco de dados</li>
</ul>
<p>Embora adotemos as melhores práticas de segurança, nenhum método de transmissão ou armazenamento eletrônico é 100% seguro. Em caso de incidente de segurança que possa acarretar risco ao usuário, seremos notificados conforme exigido pela LGPD.</p>

<h2 id="sec9">9. Cookies e Sessões</h2>
<p>A Plataforma utiliza apenas sessões HTTP (PHP Sessions) para manter a autenticação do usuário durante a navegação. Não são utilizados cookies de rastreamento, cookies de terceiros ou tecnologias de fingerprinting.</p>
<ul>
<li><b>Sessão PHP:</b> utilizada exclusivamente para identificar o usuário autenticado e manter seu acesso durante a navegação na Plataforma</li>
<li><b>Não utilizamos</b> Google Analytics, Meta Pixel, cookies de publicidade ou qualquer ferramenta de rastreamento comportamental</li>
<li>O Cloudflare Turnstile pode utilizar cookies técnicos para funcionalidade de proteção contra bots</li>
</ul>

<h2 id="sec10">10. Menores de Idade</h2>
<p>O serviço do Economic Card é destinado a maiores de 18 (dezoito) anos de idade. O cadastro de menores de 18 anos só é permitido mediante consentimento específico e destacado de pelo menos um dos pais ou responsável legal, nos termos da LGPD (Art. 14).</p>
<p>Cientes de que dados de menores recebem tratamento prioritário de proteção, o Economic Card se reserva o direito de cancelar contas de menores não autorizadas por responsável legal.</p>

<h2 id="sec11">11. Alterações nesta Política</h2>
<p>Esta Política de Privacidade pode ser atualizada periodicamente para refletir mudanças em nossas práticas, serviços ou obrigatoriedades legais. As alterações serão disponibilizadas nesta página com a data da última atualização.</p>
<p>Recomendamos que os usuários consultem esta Política regularmente. O uso continuado da Plataforma após eventuais alterações constitui aceite das novas condições.</p>

<h2 id="sec12">12. Contato e Encarregado</h2>
<p>Para exercer seus direitos como titular de dados, esclarecer dúvidas ou apresentar reclamações relativas a esta Política de Privacidade ou ao tratamento de seus dados pessoais:</p>
<p>
Economic Card<br/>
E-mail: <b>negocio@economiccard.com.br</b><br/>
WhatsApp: 5591980881718<br/>
Endereço: Rua Moura Carvalho 136 - Agulha - Belém/PA<br/>
Encarregado de Proteção de Dados (DPO): EMERSON RONALDO DA SILVA OLIVEIRA<br/>
E-mail do DPO: negocio@economiccard.com.br
</p>
<p>Caso não receba resposta satisfatória, o usuário pode encaminhar reclamação à Autoridade Nacional de Proteção de Dados (ANPD): https://www.gov.br/anpd/</p>

</section>

<footer class="mt-8 text-center pb-8 text-black text-xs">
<div class="flex justify-center items-center gap-4">
<a class="hover:text-primary transition-colors text-black" href="login.php">Entrar</a>
<span class="text-gray-400">•</span>
<a class="hover:text-primary transition-colors text-black" href="../index.php">Home</a>
</div>
<p class="mt-3">© <?php echo date('Y'); ?> ECONOMIC CARD. TODOS OS DIREITOS RESERVADOS.</p>
</footer>
</main>
</div>
</body>
</html>