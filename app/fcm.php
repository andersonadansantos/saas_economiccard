<?php
/**
 * Firebase Cloud Messaging (HTTP v1) — envio server-side.
 *
 * Não depende de Composer/vendor: usa apenas openssl (JWT RS256) e curl, que
 * já estão disponíveis na imagem php:8.2-apache.
 *
 * A credencial da conta de serviço NUNCA fica no APK nem no git. Ordem de
 * leitura:
 *   1. variável de ambiente FCM_CREDENTIALS  (JSON completo)
 *   2. variável de ambiente FCM_CREDENTIALS_FILE (caminho do JSON)
 *   3. constante FCM_CREDENTIALS_JSON (definida em secrets_local.php)
 *   4. app/fcm_service_account.json (arquivo local, ignorado pelo git)
 *
 * Diagnóstico sem enviar de verdade: variável FCM_DRY_RUN=1.
 */

class FcmSender
{
    const SCOPE     = 'https://www.googleapis.com/auth/firebase.messaging';
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const ENDPOINT  = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    private $projectId   = '';
    private $clientEmail = '';
    private $privateKey  = '';
    private $erroConfig  = '';
    private $cacheFile   = '';

    public function __construct($credencialJson = null)
    {
        $this->carregarCredenciais($credencialJson);
    }

    public function configurado()
    {
        return $this->erroConfig === '';
    }

    public function erroConfig()
    {
        return $this->erroConfig;
    }

    public function projeto()
    {
        return $this->projectId;
    }

    public static function dryRun()
    {
        $v = strtolower((string)getenv('FCM_DRY_RUN'));
        return $v === '1' || $v === 'true' || $v === 'sim';
    }

    // ------------------------------------------------------------------ credenciais

    private function carregarCredenciais($credencialJson)
    {
        $json = $credencialJson;

        if (!$json) {
            $json = getenv('FCM_CREDENTIALS');
        }
        if (!$json && defined('FCM_CREDENTIALS_JSON')) {
            $json = FCM_CREDENTIALS_JSON;
        }
        if (!$json) {
            $arquivo = getenv('FCM_CREDENTIALS_FILE');
            if (!$arquivo) {
                $arquivo = __DIR__ . '/fcm_service_account.json';
            }
            if (is_file($arquivo)) {
                $json = @file_get_contents($arquivo);
            }
        }

        if (!$json || trim($json) === '') {
            $this->erroConfig = 'Credencial do Firebase não configurada no servidor.';
            return;
        }

        $dados = json_decode($json, true);
        if (!is_array($dados)) {
            $this->erroConfig = 'Credencial do Firebase inválida (JSON malformado).';
            return;
        }

        $this->projectId   = (string)($dados['project_id'] ?? '');
        $this->clientEmail = (string)($dados['client_email'] ?? '');
        $this->privateKey  = (string)($dados['private_key'] ?? '');

        if ($this->projectId === '' || $this->clientEmail === '' || $this->privateKey === '') {
            $this->erroConfig = 'Credencial do Firebase incompleta (project_id, client_email ou private_key ausente).';
            return;
        }

        $this->cacheFile = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
            . 'ec_fcm_' . substr(sha1($this->clientEmail), 0, 12) . '.json';
    }

    // ------------------------------------------------------------------ envio

    /**
     * Envia para um token. Retorna:
     *   ['ok' => bool, 'http' => int, 'erro' => string, 'token_invalido' => bool]
     */
    public function enviar($token, $titulo, $corpo, $link = '', array $dados = [])
    {
        if (!$this->configurado()) {
            return ['ok' => false, 'http' => 0, 'erro' => $this->erroConfig, 'token_invalido' => false];
        }

        $payload = $this->montarPayload($token, $titulo, $corpo, $link, $dados);
        if ($payload === '') {
            return ['ok' => false, 'http' => 0, 'erro' => 'Payload inválido ou acima de 4 KB.', 'token_invalido' => false];
        }

        if (self::dryRun()) {
            self::log('DRY-RUN token=' . self::mascarar($token) . ' titulo="' . $titulo . '" link="' . $link . '"');
            return ['ok' => true, 'http' => 200, 'erro' => '', 'token_invalido' => false];
        }

        $access = $this->tokenAcesso();
        if ($access === '') {
            return ['ok' => false, 'http' => 0, 'erro' => 'Falha ao obter access token do Google.', 'token_invalido' => false];
        }

        $ch = $this->handle($token, $payload, $access);
        $resposta = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false) {
            self::log('ERRO curl token=' . self::mascarar($token) . ' ' . $erroCurl);
            return ['ok' => false, 'http' => 0, 'erro' => 'Falha de conexão: ' . $erroCurl, 'token_invalido' => false];
        }

        $ok = $http >= 200 && $http < 300;
        $invalido = !$ok && self::tokenInvalido($http, $resposta);
        if (!$ok) {
            self::log('FALHA http=' . $http . ' token=' . self::mascarar($token) . ' resp=' . substr($resposta, 0, 400));
        }

        return [
            'ok' => $ok,
            'http' => $http,
            'erro' => $ok ? '' : substr($resposta, 0, 400),
            'token_invalido' => $invalido,
        ];
    }

    /**
     * Envia para vários tokens com concorrência limitada (curl_multi).
     * Retorna contadores + lista de tokens que devem ser desativados.
     *   ['ok' => int, 'falhas' => int, 'invalidos' => string[], 'erros' => string[]]
     */
    public function enviarMuitos(array $tokens, $titulo, $corpo, $link = '', array $dados = [], $concorrencia = 10)
    {
        $resultado = ['ok' => 0, 'falhas' => 0, 'invalidos' => [], 'erros' => []];

        if (!$this->configurado()) {
            $resultado['falhas'] = count($tokens);
            $resultado['erros'][] = $this->erroConfig;
            return $resultado;
        }

        $tokens = array_values(array_unique(array_filter(array_map('strval', $tokens))));
        if (!$tokens) return $resultado;

        if (self::dryRun()) {
            foreach ($tokens as $t) {
                self::log('DRY-RUN lote token=' . self::mascarar($t) . ' titulo="' . $titulo . '" link="' . $link . '"');
            }
            $resultado['ok'] = count($tokens);
            return $resultado;
        }

        $access = $this->tokenAcesso();
        if ($access === '') {
            $resultado['falhas'] = count($tokens);
            $resultado['erros'][] = 'Falha ao obter access token do Google.';
            return $resultado;
        }

        $concorrencia = max(1, min(20, (int)$concorrencia));

        foreach (array_chunk($tokens, $concorrencia) as $lote) {
            $mh = curl_multi_init();
            $handles = [];

            foreach ($lote as $i => $token) {
                $payload = $this->montarPayload($token, $titulo, $corpo, $link, $dados);
                if ($payload === '') {
                    $resultado['falhas']++;
                    $resultado['erros'][] = 'Payload inválido para ' . self::mascarar($token);
                    continue;
                }
                $ch = $this->handle($token, $payload, $access);
                curl_multi_add_handle($mh, $ch);
                $handles[$i] = ['ch' => $ch, 'token' => $token];
            }

            $ativo = null;
            do {
                $status = curl_multi_exec($mh, $ativo);
                if ($ativo) curl_multi_select($mh, 1.0);
            } while ($ativo > 0 && $status === CURLM_OK);

            foreach ($handles as $item) {
                $ch = $item['ch'];
                $token = $item['token'];
                $resposta = (string)curl_multi_getcontent($ch);
                $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($http >= 200 && $http < 300) {
                    $resultado['ok']++;
                } else {
                    $resultado['falhas']++;
                    if (self::tokenInvalido($http, $resposta)) {
                        $resultado['invalidos'][] = $token;
                    }
                    if (count($resultado['erros']) < 10) {
                        $resultado['erros'][] = 'http=' . $http . ' ' . substr($resposta, 0, 200);
                    }
                    self::log('FALHA lote http=' . $http . ' token=' . self::mascarar($token) . ' resp=' . substr($resposta, 0, 300));
                }

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }

            curl_multi_close($mh);
        }

        return $resultado;
    }

    // ------------------------------------------------------------------ internos

    private function montarPayload($token, $titulo, $corpo, $link, array $dados)
    {
        $token = trim((string)$token);
        if ($token === '') return '';

        $data = ['tipo' => 'mensagem'];
        if ($link !== '') $data['link'] = (string)$link;
        foreach ($dados as $k => $v) {
            if (is_scalar($v)) $data[(string)$k] = (string)$v;
        }

        $mensagem = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => (string)$titulo,
                    'body'  => (string)$corpo,
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'channel_id'                => 'economiccard_default',
                        'icon'                      => 'ic_stat_economiccard',
                        'color'                     => '#51036D',
                        'sound'                     => 'default',
                        'default_vibrate_timings'   => true,
                    ],
                ],
            ],
        ];

        $json = json_encode($mensagem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return '';
        if (strlen($json) > 4096) return '';
        return $json;
    }

    private function handle($token, $payload, $access)
    {
        $ch = curl_init(sprintf(self::ENDPOINT, rawurlencode($this->projectId)));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $access,
                'Content-Type: application/json; charset=UTF-8',
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 25,
        ]);
        return $ch;
    }

    private function tokenAcesso($forcar = false)
    {
        if (!$forcar && $this->cacheFile && is_file($this->cacheFile)) {
            $cache = json_decode((string)@file_get_contents($this->cacheFile), true);
            if (is_array($cache) && !empty($cache['access_token']) && (int)($cache['expires_at'] ?? 0) > time() + 60) {
                return (string)$cache['access_token'];
            }
        }

        $jwt = $this->jwt();
        if ($jwt === '') return '';

        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $resposta = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false || $http < 200 || $http >= 300) {
            self::log('ERRO oauth http=' . $http . ' ' . $erroCurl . ' resp=' . substr((string)$resposta, 0, 300));
            return '';
        }

        $dados = json_decode((string)$resposta, true);
        $access = (string)($dados['access_token'] ?? '');
        if ($access === '') return '';

        if ($this->cacheFile) {
            @file_put_contents($this->cacheFile, json_encode([
                'access_token' => $access,
                'expires_at'   => time() + max(60, (int)($dados['expires_in'] ?? 3600)),
            ]), LOCK_EX);
            @chmod($this->cacheFile, 0600);
        }

        return $access;
    }

    private function jwt()
    {
        $agora = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $this->clientEmail,
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_URL,
            'iat'   => $agora,
            'exp'   => $agora + 3600,
        ];

        $entrada = self::base64url(json_encode($header))
            . '.' . self::base64url(json_encode($claims));

        $assinatura = '';
        if (!openssl_sign($entrada, $assinatura, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            self::log('ERRO ao assinar JWT — verifique a private_key da conta de serviço.');
            return '';
        }

        return $entrada . '.' . self::base64url($assinatura);
    }

    private static function base64url($dados)
    {
        return rtrim(strtr(base64_encode($dados), '+/', '-_'), '=');
    }

    private static function tokenInvalido($http, $corpo)
    {
        $c = strtoupper((string)$corpo);
        if (strpos($c, 'UNREGISTERED') !== false) return true;
        if (strpos($c, 'SENDER_ID_MISMATCH') !== false) return true;
        if (strpos($c, 'REGISTRATION_TOKENS_ARE_NOT_VALID') !== false) return true;
        if ($http === 400 && strpos($c, 'INVALID_ARGUMENT') !== false && strpos($c, 'TOKEN') !== false) return true;
        return false;
    }

    private static function mascarar($token)
    {
        $token = (string)$token;
        if (strlen($token) <= 12) return $token;
        return substr($token, 0, 6) . '...' . substr($token, -4);
    }

    private static function log($mensagem)
    {
        $dir = __DIR__ . '/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents(
            $dir . '/push.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
