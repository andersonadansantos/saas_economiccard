<?php
// ============================================================
// MODELO DE SEGREDOS - copie para secrets_local.php e preencha
// (o secrets_local.php fica fora do git; em produção/Docker use
//  as mesmas chaves como variáveis de ambiente)
// ============================================================
//
// Variáveis de ambiente equivalentes:
//   GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET,
//   FB_APP_ID, FB_APP_SECRET,
//   TURNSTILE_SITE_KEY, TURNSTILE_SECRET,
//   ADMIN_API_SENHA,
//   DB_HOST, DB_USER, DB_PASS, DB_NAME,
//   FCM_CREDENTIALS (JSON da conta de serviço) ou
//   FCM_CREDENTIALS_FILE (caminho do JSON) — usado pelo envio de push.

define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');

define('FB_APP_ID', '');
define('FB_APP_SECRET', '');

define('TURNSTILE_SITE_KEY', '');
define('TURNSTILE_SECRET', '');

define('ADMIN_API_SENHA', '');

// ====== FIREBASE CLOUD MESSAGING (PUSH) ======
// Credencial da conta de serviço (JSON). NUNCA vai para o APK nem para o git.
// Prefira manter o JSON fora do webroot e apontar o caminho:
//   define('FCM_CREDENTIALS_FILE', '/caminho/fora/do/webroot/fcm.json');
// Ou cole o JSON inteiro (menos recomendado):
//   define('FCM_CREDENTIALS_JSON', '{"type":"service_account", ...}');
// Sem credencial o painel continua funcionando, mas não envia push.
