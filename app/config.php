<?php
// Detecta HTTPS mesmo atrás de proxy reverso (Traefik/Nginx terminam o TLS e
// repassam HTTP puro ao container; o esquema real vem no X-Forwarded-Proto).
// Necessário para o OAuth do Google, que exige https no redirect_uri registrado.
function request_is_https() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $partes = array_map('trim', explode(',', strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'])));
        return in_array('https', $partes, true);
    }
    return (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
}

// Sessão persistente (7 dias): mantém o usuário logado ao fechar/reabrir o app
// ou navegador. SameSite=Lax é obrigatório para o OAuth (retorno do Google é
// navegação top-level GET) e httponly/secure protegem o cookie de sessão.
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params([
    'lifetime' => 604800,
    'path'     => '/',
    'secure'   => request_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Politica zero cache: garante que nenhum navegador armazene o sistema do cartao,
// mesmo que mod_headers nao esteja disponivel no servidor.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Segredos locais (fora do git) — veja secrets_exemplo.php.
// Em produção/Docker use variáveis de ambiente; localmente o XAMPP usa secrets_local.php.
if (file_exists(__DIR__ . '/secrets_local.php')) {
    require_once __DIR__ . '/secrets_local.php';
}

$host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
$port = getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : 3306);
$user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (defined('DB_PASS') ? DB_PASS : '');
$db   = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'economicacard');

try {
    $conn = new mysqli($host, $user, $pass, $db, (int)$port);
} catch (mysqli_sql_exception $e) {
    http_response_code(503);
    die('Falha na conexao com o banco de dados. Verifique se o MySQL esta em execucao no XAMPP.');
}
$conn->set_charset('utf8mb4');

// URL absoluta para assets em app/uploads (funciona mesmo quando a página é
// acessada via URL reescrita, ex.: /card/admin_banners.php em vez de /card/app/...)
function asset_url($src) {
    if (!$src) return '';
    if (preg_match('#^https?://#i', $src) || strpos($src, 'data:') === 0 || strpos($src, '//') === 0) {
        return $src;
    }
    if (strpos($src, '/') === 0) return $src;
    static $appBase = null;
    if ($appBase === null) {
        $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $appAbs = str_replace('\\', '/', __DIR__);
        $appBase = $docRoot && strpos($appAbs, $docRoot) === 0
            ? '/' . ltrim(substr($appAbs, strlen($docRoot)), '/')
            : dirname($_SERVER['SCRIPT_NAME'] ?? '');
    }
    return $appBase . '/' . ltrim($src, '/');
}

// Caminho público base do site (ex.: "/card" no XAMPP local, "" quando a raiz é o
// documento). Usado para montar links amigáveis, ex.: => /card/indicacao/TOKEN.
function site_base() {
    static $siteBase = null;
    if ($siteBase === null) {
        $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $appAbs = str_replace('\\', '/', __DIR__);
        if ($docRoot && strpos($appAbs, $docRoot) === 0) {
            $appBase = '/' . ltrim(substr($appAbs, strlen($docRoot)), '/'); // e.g. /card/app ou /app
            $siteBase = preg_replace('#/app$#', '', $appBase);              // -> /card ou ''
        } else {
            $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
            $siteBase = preg_replace('#/app$#', '', $scriptDir);
        }
    }
    return rtrim($siteBase, '/');
}

// Cloudflare Turnstile (proteção das páginas de login) — helpers centralizados em turnstile.php
require_once __DIR__ . '/turnstile.php';

// Contratos: código único + certificação de aceite
require_once __DIR__ . '/contrato_aceite.php';

// Desativa cartões com validade expirada (60 dias)
$conn->query("UPDATE usuarios SET cartao_ativo = 0 WHERE cartao_ativo = 1 AND cartao_validade IS NOT NULL AND cartao_validade < CURDATE()");

// Garante que a tabela banners_topo existe (slide topo do dashboard).
// Se a tabela existir mas estiver com colunas faltando (ex.: deploy antigo),
// recria ela para manter o schema correto.
$colsTopo = $conn->query("SHOW COLUMNS FROM banners_topo")->fetch_all(MYSQLI_ASSOC);
$colNames = array_column($colsTopo, 'Field');
if (!$colsTopo || !in_array('titulo', $colNames) || !in_array('link_externo', $colNames)) {
    $conn->query("DROP TABLE IF EXISTS banners_topo");
}
$conn->query("CREATE TABLE IF NOT EXISTS banners_topo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL DEFAULT '',
    imagem VARCHAR(500) NOT NULL DEFAULT '',
    link_externo VARCHAR(500) NOT NULL DEFAULT '',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Tabela de dependentes (até 5 por usuário).
$conn->query("CREATE TABLE IF NOT EXISTS dependentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL DEFAULT '',
    whatsapp VARCHAR(20) NOT NULL DEFAULT '',
    cpf VARCHAR(20) NOT NULL DEFAULT '',
    endereco VARCHAR(500) NOT NULL DEFAULT '',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_usuario (usuario_id),
    CONSTRAINT fk_dependentes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ====== REMUNERAÇÃO DE AFILIADOS: VALOR FIXO POR MENSALIDADE PAGA ======
// Auto-migração idempotente (mesmo estilo das tabelas acima), sem perda de
// dados — as colunas antigas (afiliados.comissao, api_pagamento.split_afiliado_*)
// são preservadas, apenas deixam de ser usadas:
//  - afiliados.valor_fixo_mensalidade: valor em R$ que o afiliado recebe por
//    mensalidade efetivamente paga por usuário indicado (configurável por afiliado).
//  - pagamentos_pix.status: acrescenta 'refunded' para registrar reembolsos/
//    estornos de mensalidades (necessário para desfazer o crédito do afiliado).
//  - creditos_afiliado: livro-caixa dos créditos — uma linha por mensalidade
//    paga, com usuário pagador, afiliado, valor, data e a mensalidade
//    correspondente (UNIQUE por pagamento: nunca duplica crédito).
$fixo = @$conn->query("SELECT
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'afiliados' AND COLUMN_NAME = 'valor_fixo_mensalidade') AS tem_coluna,
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagamentos_pix' AND COLUMN_NAME = 'status' AND COLUMN_TYPE LIKE '%refunded%') AS tem_refunded")->fetch_assoc();
if ($fixo && (int)$fixo['tem_coluna'] === 0) {
    @$conn->query("ALTER TABLE afiliados ADD COLUMN valor_fixo_mensalidade DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER comissao");
}
if ($fixo && (int)$fixo['tem_refunded'] === 0) {
    @$conn->query("ALTER TABLE pagamentos_pix MODIFY status ENUM('pending','approved','rejected','cancelled','refunded') NOT NULL DEFAULT 'pending'");
}
$conn->query("CREATE TABLE IF NOT EXISTS creditos_afiliado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afiliado_id INT NOT NULL,
    usuario_id INT NOT NULL,
    pagamento_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tipo_cobranca VARCHAR(20) NOT NULL DEFAULT '',
    status ENUM('ativo','estornado') NOT NULL DEFAULT 'ativo',
    estornado_em DATETIME NULL DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pagamento (pagamento_id),
    KEY idx_afiliado (afiliado_id),
    KEY idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ====== REMUNERAÇÃO DO PARCEIRO DA EMPRESA: VALOR FIXO POR TRANSAÇÃO PAGA ======
// Auto-migração idempotente, sem perda de dados:
//  - api_pagamento.valor_fixo_parceiro: coluna legada que passa a ser o ÚNICO
//    parâmetro do parceiro — valor em R$ por pagamento confirmado (qualquer
//    usuário, tipo de cobrança e método). As colunas antigas
//    split_parceiro_adesao/split_parceiro_recorrencia são preservadas por
//    histórico e sincronizadas com o valor fixo ao salvar no painel.
//    Em instalações novas o padrão é R$ 6,00.
//  - creditos_parceiro: livro-caixa do parceiro — uma linha por transação
//    confirmada, com valor, usuário pagador e estorno (UNIQUE por pagamento:
//    nunca duplica crédito em notificações repetidas dos gateways).
$parc = @$conn->query("SELECT
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'api_pagamento' AND COLUMN_NAME = 'valor_fixo_parceiro') AS tem_coluna")->fetch_assoc();
if ($parc && (int)$parc['tem_coluna'] === 0) {
    @$conn->query("ALTER TABLE api_pagamento ADD COLUMN valor_fixo_parceiro DECIMAL(10,2) NOT NULL DEFAULT 6.00 AFTER asaas_wallet_parceiro");
    @$conn->query("UPDATE api_pagamento SET valor_fixo_parceiro = 6.00 WHERE valor_fixo_parceiro <= 0");
}
$conn->query("CREATE TABLE IF NOT EXISTS creditos_parceiro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pagamento_id INT NOT NULL,
    usuario_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('ativo','estornado') NOT NULL DEFAULT 'ativo',
    estornado_em DATETIME NULL DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pagamento (pagamento_id),
    KEY idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");


// Sessão é de um dependente (login pelo CPF do dependente).
function ehDependente() {
    return !empty($_SESSION['tipo_conta']) && $_SESSION['tipo_conta'] === 'dependente';
}

// Bloqueia o acesso enquanto o usuário não ativar o cartão.
// Redireciona para ativar.php (usado na versão app / raiz).
function exigirCartaoAtivo() {
    if (!isset($_SESSION['usuario_id'])) return;
    global $conn;
    $uid = (int)$_SESSION['usuario_id'];
    $stmt = $conn->prepare("SELECT cartao_ativo, status FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r && ($r['status'] ?? 'ativo') === 'desativado') {
        session_unset();
        session_destroy();
        header('Location: login.php?conta_encerrada=1');
        exit;
    }
    if ($r && !$r['cartao_ativo']) {
        if (ehDependente()) {
            session_unset();
            session_destroy();
            header('Location: login.php?acesso_bloqueado=1');
        } else {
            header('Location: ativar.php?bloqueado=1');
        }
        exit;
    }
}

// Resolve o plano de uma cobrança. Se o plano original foi excluído,
// cai no plano "Mensal" padrão e passa a cobrar o valor do 2º mês em diante (valor_mensal).
// Retorna o plano + campo 'cobrar' (valor a ser cobrado) e 'fallback' (bool).
function obter_plano_seguro($planoId) {
    global $conn;
    $fallback = false;
    $pl = null;
    $id = (int)$planoId;
    if ($id > 0) {
        $r = $conn->query("SELECT * FROM planos WHERE id = " . $id);
        if ($r) $pl = $r->fetch_assoc();
    }
    if (!$pl) {
        $fallback = true;
        $r = $conn->query("SELECT * FROM planos WHERE nome = 'Mensal' AND ativo = 1 ORDER BY ordem, id LIMIT 1");
        if (!$r || !($pl = $r->fetch_assoc())) {
            $r = $conn->query("SELECT * FROM planos WHERE ativo = 1 ORDER BY ordem, id LIMIT 1");
            $pl = $r ? $r->fetch_assoc() : null;
        }
    }
    if (!$pl) return null;
    if ($fallback) {
        // Plano excluído -> vira "Mensal" e cobra o valor do 2º mês em diante (recorrência)
        $pl['cobrar'] = ($pl['valor_mensal'] > 0) ? (float)$pl['valor_mensal'] : (float)($pl['valor_adesao'] ?: $pl['valor']);
    } else {
        // Plano normal -> primeira cobrança usa o valor de adesão (1º mês)
        $pl['cobrar'] = (float)($pl['valor_adesao'] ?: $pl['valor']);
    }
    $pl['fallback'] = $fallback;
    return $pl;
}

// Valor a cobrar na ativação/renovação de um plano (somente o valor monetário).
// Plano "Mensal" (nome exato): na primeira contratação (usuário sem adesao_paga)
// cobra a taxa de adesão global (personalizacao.valor_adesao), única vez; a partir
// do segundo mês (adesao_paga = 1) cobra apenas a mensalidade (valor_mensal).
// Demais planos: cobram o valor normal do plano (valor_mensal ?: valor_adesao ?: valor).
function valor_cobranca_plano_ativacao($conn, $plano, $u) {
    $nome = strtolower(trim((string)($plano['nome'] ?? '')));
    $ehMensal = ($nome === 'mensal');
    if ($ehMensal && (int)($u['adesao_paga'] ?? 0) === 0) {
        $p = $conn->query("SELECT valor_adesao FROM personalizacao WHERE id = 1")->fetch_assoc();
        $taxa = (float)($p['valor_adesao'] ?? 0);
        if ($taxa > 0) return $taxa;
    }
    $valor = (float)($plano['valor_mensal'] ?? 0);
    if ($valor <= 0) $valor = (float)($plano['valor_adesao'] ?? 0);
    if ($valor <= 0) $valor = (float)($plano['valor'] ?? 0);
    return $valor;
}

// Valida CPF brasileiro (algoritmo oficial dos dígitos verificadores).
function cpf_valido($cpf) {
    $cpf = preg_replace('/\D/', '', (string)$cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ((int)$cpf[$c] !== $d) {
            return false;
        }
    }
    return true;
}

// ====== LOGIN COM GOOGLE (OAuth 2.0) =====
// Credenciais: secrets_local.php (fora do git) ou variáveis de ambiente.
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
}

// URI de retorno canônica do OAuth. Deve coincidir, byte a byte, com uma das
// "Authorized redirect URIs" do OAuth Client no Google Cloud Console.
// Override manual: define('GOOGLE_REDIRECT_URI', 'https://host/app/google_callback.php');
function google_redirect_uri() {
    if (defined('GOOGLE_REDIRECT_URI') && GOOGLE_REDIRECT_URI !== '') {
        return GOOGLE_REDIRECT_URI;
    }
    $esquema = request_is_https() ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir     = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $esquema . '://' . $host . $dir . '/google_callback.php';
}

// Log de eventos da aplicação (app/logs/<canal>.log) — usado para diagnosticar
// falhas de OAuth/integrações. Os arquivos são bloqueados pelo .htaccess (*.log).
function app_log($canal, $mensagem) {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $arquivo = $dir . '/' . preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$canal)) . '.log';
    $linha   = '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL;
    @file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX);
}
