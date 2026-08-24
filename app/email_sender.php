<?php
// Funções de envio de e-mail via SMTP (sockets nativos, sem dependências externas)

function getSmtpConfig() {
    global $conn;
    $cfg = $conn->query("SELECT * FROM config_smtp WHERE id = 1")->fetch_assoc();
    return $cfg ?: [];
}

function smtpLeitura($fp, $prefixoEsperado = '250') {
    $respostas = [];
    $linha = '';
    while (!feof($fp)) {
        $linha = fgets($fp, 515);
        if ($linha === false) break;
        $respostas[] = trim($linha);
        if (strlen($linha) >= 4 && $linha[3] === ' ') break;
    }
    $ultimo = $respostas ? (string)$respostas[count($respostas) - 1] : '';
    $codigo = $ultimo !== '' ? (int)$ultimo : 0;
    $ok = $ultimo !== '' && $ultimo[0] === (string)$prefixoEsperado[0];
    return ['ok' => $ok, 'codigo' => $codigo, 'respostas' => $respostas];
}

function smtpComando($fp, $comando, $prefixoEsperado = '250') {
    fwrite($fp, $comando . "\r\n");
    return smtpLeitura($fp, $prefixoEsperado);
}

/**
 * Envia e-mail via SMTP.
 * @param string $para     destinatário
 * @param string $assunto  assunto
 * @param string $corpoHtml corpo em HTML
 * @param array|null $smtpConfig configuração (se null, busca no banco)
 * @return array ['ok' => bool, 'msg' => string]
 */
function enviar_email_smtp($para, $assunto, $corpoHtml, $smtpConfig = null) {
    if ($smtpConfig === null) {
        $smtpConfig = getSmtpConfig();
    }
    $host    = trim($smtpConfig['host'] ?? '');
    $porta   = (int)($smtpConfig['porta'] ?? 587);
    $usuario = trim($smtpConfig['usuario'] ?? '');
    $senha   = $smtpConfig['senha'] ?? '';
    $remNome = trim($smtpConfig['remetente_nome'] ?? '');
    $remMail = trim($smtpConfig['remetente_email'] ?? '');
    $cripto  = strtolower(trim($smtpConfig['criptografia'] ?? 'tls'));

    if ($host === '' || $remMail === '') {
        return ['ok' => false, 'msg' => 'Configure o servidor SMTP (host e e-mail do remetente) antes de enviar.'];
    }
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'msg' => 'E-mail do destinatário inválido: ' . $para];
    }
    if ($remNome === '') { $remNome = $remMail; }

    $prefixoSsl = ($cripto === 'ssl') ? 'ssl://' : '';

    $erroNum = 0;
    $erroStr = '';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $fp = @stream_socket_client(
        $prefixoSsl . $host . ':' . $porta,
        $erroNum,
        $erroStr,
        20,
        STREAM_CLIENT_CONNECT,
        $ctx
    );
    if (!$fp) {
        return ['ok' => false, 'msg' => 'Não foi possível conectar ao servidor SMTP (' . $host . ':' . $porta . '): ' . $erroStr];
    }
    stream_set_timeout($fp, 20);

    $r = smtpLeitura($fp, '220');
    if (!$r['ok']) { fclose($fp); return ['ok' => false, 'msg' => 'Resposta inesperada ao conectar: ' . $r['codigo']]; }

    // STARTTLS
    if ($cripto === 'tls') {
        $r = smtpComando($fp, 'EHLO ' . ($host ?: 'localhost'));
        if ($r['ok']) {
            $r = smtpComando($fp, 'STARTTLS', '220');
            if (!$r['ok']) { fclose($fp); return ['ok' => false, 'msg' => 'Servidor não aceitou STARTTLS.']; }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp); return ['ok' => false, 'msg' => 'Falha na criptografia TLS.'];
            }
            $r = smtpComando($fp, 'EHLO ' . ($host ?: 'localhost'));
        } else {
            // Pode ser conexão já criptografada (implicit TLS na porta 465 sem cripto ssl)
            fclose($fp);
            return ['ok' => false, 'msg' => 'Falha no EHLO: ' . $r['codigo']];
        }
    } else {
        $r = smtpComando($fp, 'EHLO ' . ($host ?: 'localhost'));
        if (!$r['ok']) { fclose($fp); return ['ok' => false, 'msg' => 'Falha no EHLO: ' . $r['codigo']]; }
    }

    // Autenticação
    if ($usuario !== '') {
        $r = smtpComando($fp, 'AUTH LOGIN', '334');
        if ($r['ok']) {
            $r = smtpComando($fp, base64_encode($usuario), '334');
            if (!$r['ok']) { fclose($fp); return ['ok' => false, 'msg' => 'Falha na autenticação SMTP (usuário).']; }
            $r = smtpComando($fp, base64_encode($senha), '235');
            if (!$r['ok']) { fclose($fp); return ['ok' => false, 'msg' => 'Falha na autenticação SMTP (senha).']; }
        } else {
            // Tenta AUTH PLAIN
            $r = smtpComando($fp, 'AUTH PLAIN ' . base64_encode("\0" . $usuario . "\0" . $senha), '235');
            if (!$r['ok']) { fclose($fp); return ['ok' => false, 'msg' => 'Falha na autenticação SMTP.']; }
        }
    }

    $r = smtpComando($fp, 'MAIL FROM: <' . $remMail . '>', '250');
    if (!$r['ok']) { fclose($fp); return ['ok' => false, 'msg' => 'Falha no MAIL FROM.']; }
    $r = smtpComando($fp, 'RCPT TO: <' . $para . '>', '250');
    if (!$r['ok']) { fclose($fp); return ['ok' => false, 'msg' => 'Falha no RCPT TO (destinatário recusado).']; }

    $r = smtpComando($fp, 'DATA', '354');
    if (!$r['ok']) { fclose($fp); return ['ok' => false, 'msg' => 'Falha ao iniciar a mensagem (DATA).']; }

    $bodyPlain = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $corpoHtml)));

    $mensagem = "From: " . mb_encode_mimeheader($remNome, 'UTF-8') . " <" . $remMail . ">\r\n"
        . "To: <" . $para . ">\r\n"
        . "Subject: " . mb_encode_mimeheader($assunto, 'UTF-8') . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n"
        . "X-Mailer: EconomicCard\r\n"
        . "\r\n"
        . $corpoHtml . "\r\n";

    fwrite($fp, $mensagem);
    fwrite($fp, "\r\n.\r\n");
    $r = smtpLeitura($fp, '250');
    if (!$r['ok']) { fclose($fp); return ['ok' => false, 'msg' => 'Falha ao enviar a mensagem: ' . $r['codigo']]; }

    smtpComando($fp, 'QUIT');
    fclose($fp);
    return ['ok' => true, 'msg' => 'E-mail enviado para ' . $para];
}

/**
 * Aplica placeholders {nome}, {email}, {ano} ao corpo/assunto do template.
 */
function renderTemplateEmail($corpo, $assunto, $dados = []) {
    $vars = [
        '{nome}'  => $dados['nome'] ?? '',
        '{email}' => $dados['email'] ?? '',
        '{ano}'   => date('Y'),
    ];
    $corpo   = str_replace(array_keys($vars), array_values($vars), $corpo);
    $assunto = str_replace(array_keys($vars), array_values($vars), $assunto);
    return [$corpo, $assunto];
}

/**
 * Extrai apenas o conteúdo editável de um template.
 * Se o corpo salvo for um documento HTML completo (shell), remove a estrutura
 * externa e devolve somente a região da mensagem.
 */
function extrairConteudoTemplate($html) {
    $html = (string)$html;
    if ($html === '') return '';
    if (stripos($html, '<html') === false && stripos($html, '<body') === false && stripos($html, '<!DOCTYPE') === false) {
        return $html;
    }
    if (preg_match('/<tr><td style="padding:32px 24px;font-family:Arial,Helvetica,sans-serif;color:#191c1d;font-size:15px;line-height:1\.6;">([\s\S]*?)<\/td><\/tr>/i', $html, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/<body[^>]*>([\s\S]*?)<\/body>/i', $html, $m)) {
        return trim($m[1]);
    }
    return $html;
}

/**
 * Converte classes CSS geradas pelo Quill em estilos inline,
 * garantindo compatibilidade com clientes e servidores de e-mail.
 */
function quillParaInline($html) {
    if (trim($html) === '') return $html;
    $html = preg_replace_callback('/<([a-zA-Z0-9]+)([^>]*class="([^"]*)"[^>]*)>/i', function ($m) {
        $tag   = $m[1];
        $rest  = $m[2];
        $class = $m[3];
        $style = '';
        if (preg_match('/\bql-align-(left|center|right|justify)\b/', $class, $a)) {
            $style .= 'text-align:' . $a[1] . ';';
        }
        if (preg_match('/\bql-indent-([0-9]+)\b/', $class, $a)) {
            $style .= 'padding-left:' . ((int)$a[1] * 2) . 'em;';
        }
        if (preg_match('/\bql-size-(small|large|huge)\b/', $class, $a)) {
            $sizes = ['small' => '12px', 'large' => '18px', 'huge' => '24px'];
            $style .= 'font-size:' . $sizes[$a[1]] . ';';
        }
        if ($style === '') { return $m[0]; }
        if (preg_match('/style="([^"]*)"/i', $rest, $s)) {
            $rest = str_replace($s[0], 'style="' . $s[1] . $style . '"', $rest);
        } else {
            $rest .= ' style="' . $style . '"';
        }
        $classNovo = trim(preg_replace('/\bql-[a-z]+-[a-z0-9]+\b/', '', $class));
        $rest = preg_replace('/class="[^"]*"/i', $classNovo === '' ? '' : 'class="' . $classNovo . '"', $rest);
        return '<' . $tag . $rest . '>';
    }, $html);
    $html = preg_replace('/ class=""/', '', $html);
    return $html;
}

/**
 * Monta o HTML completo do e-mail (shell com cabeçalho e rodapé),
 * envolvendo o conteúdo editável no editor visual.
 */
function templateShell($conteudo) {
    $ano = date('Y');
    return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{assunto}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f5f7;">
<tr><td align="center" style="padding:32px 16px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(81,3,109,0.08);">
<tr><td align="center" style="background-color:#51036d;padding:32px 24px;">
<div style="font-size:24px;font-weight:800;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">ECONOMIC CARD</div>
</td></tr>
<tr><td style="padding:32px 24px;font-family:Arial,Helvetica,sans-serif;color:#191c1d;font-size:15px;line-height:1.6;">
' . $conteudo . '
</td></tr>
<tr><td align="center" style="padding:24px;background-color:#f4f5f7;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#7f7381;">
&copy; ' . $ano . ' Economic Card. Todos os direitos reservados.
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
}

/**
 * Carrega um template da tabela template_email_geral pela chave e envia
 * o e-mail renderizado para o destinatário. Se o SMTP não estiver configurado,
 * falha silenciosamente (retorna ok=false sem quebrar o fluxo).
 */
function enviar_template_geral($chave, $dados = []) {
    global $conn;
    $st = $conn->prepare("SELECT * FROM template_email_geral WHERE chave = ?");
    $st->bind_param('s', $chave);
    $st->execute();
    $t = $st->get_result()->fetch_assoc();
    if (!$t || trim($t['assunto'] ?? '') === '' || trim($t['corpo'] ?? '') === '') {
        return ['ok' => false, 'msg' => 'Template "' . $chave . '" não configurado.'];
    }
    $corpoCompleto = templateShell(quillParaInline(extrairConteudoTemplate($t['corpo'])));
    $render = renderTemplateEmail($corpoCompleto, $t['assunto'], $dados);
    return enviar_email_smtp($dados['email'] ?? '', $render[1], $render[0]);
}
