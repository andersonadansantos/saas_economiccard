/**
 * Bridge de registro do token FCM dentro da WebView.
 *
 * O app Android (Kodular/Niotron/WebView) recebe o token pelo Firebase e chama:
 *     window.EconomicCardPush.register('TOKEN_FCM');
 * Opcionalmente com identificadores do aparelho/versão:
 *     window.EconomicCardPush.register('TOKEN_FCM', 'device-id', '1.0.3');
 *
 * A chamada usa a sessão do usuário (cookie) e o token CSRF da <meta>, então o
 * servidor associa o token ao usuário autenticado. Sem login, o token fica
 * registrado sem usuário e é reatribuído no próximo registro após o login.
 */
(function () {
    'use strict';

    var registeredOkToken = null;
    var lastAttemptToken = null;
    var lastAttemptAt = 0;

    function baseUrl() {
        var src = '';
        if (document.currentScript && document.currentScript.src) {
            src = document.currentScript.src;
        }
        if (!src) {
            var s = document.querySelector('script[src*="push_client.js"]');
            if (s) src = s.src;
        }
        if (!src) return '';
        return src.replace(/[?#].*$/, '').replace(/[^\/]*$/, '');
    }

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    /**
     * Aviso discreto quando o usuário nega a permissão de notificações no
     * Android 13+. O app nativo chama EconomicCardPush.permissao(false) e o
     * aviso aparece uma vez por sessão, sem depender de CSS da página.
     */
    function avisoNotificacoes() {
        if (document.getElementById('ec-push-aviso')) return;
        var box = document.createElement('div');
        box.id = 'ec-push-aviso';
        box.setAttribute('role', 'status');
        box.style.cssText = 'position:fixed;left:12px;right:12px;bottom:84px;z-index:2147483647;'
            + 'background:#2b0b3a;color:#fff;font:600 13px/1.45 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;'
            + 'padding:14px 44px 14px 16px;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.35)';
        box.textContent = 'Notificações desativadas. Ative as notificações do Economic Card '
            + 'nas configurações do Android para receber os avisos.';
        var fechar = document.createElement('button');
        fechar.type = 'button';
        fechar.setAttribute('aria-label', 'Fechar aviso');
        fechar.textContent = '\u00d7';
        fechar.style.cssText = 'position:absolute;top:6px;right:8px;background:transparent;border:0;'
            + 'color:#fff;font-size:22px;line-height:1;padding:6px;cursor:pointer';
        fechar.onclick = function () {
            if (box.parentNode) box.parentNode.removeChild(box);
        };
        box.appendChild(fechar);
        (document.body || document.documentElement).appendChild(box);
    }

    function enviar(payload) {
        var url = baseUrl() + 'push_register.php';
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf(),
                'X-Requested-With': 'EconomicCardApp'
            },
            body: JSON.stringify(payload)
        }).then(function (resposta) {
            return resposta.json().catch(function () {
                return { ok: false, erro: 'Resposta inválida do servidor.' };
            });
        });
    }

    function registrarComRetentativa(token, deviceId, appVersion, tentativas) {
        tentativas = typeof tentativas === 'number' ? tentativas : 4;
        return enviar({
            acao: 'registrar',
            token: String(token),
            device_id: deviceId ? String(deviceId) : '',
            app_version: appVersion ? String(appVersion) : '',
            plataforma: 'android'
        }).then(function (res) {
            if (res && res.ok) return res;
            if (tentativas > 1) {
                return new Promise(function (resolve) { setTimeout(resolve, 1500); })
                    .then(function () { return registrarComRetentativa(token, deviceId, appVersion, tentativas - 1); });
            }
            return res;
        }).catch(function (err) {
            if (tentativas > 1) {
                return new Promise(function (resolve) { setTimeout(resolve, 1500); })
                    .then(function () { return registrarComRetentativa(token, deviceId, appVersion, tentativas - 1); });
            }
            throw err;
        });
    }

    function normalizeToken(t) {
        if (t === null || t === undefined) return '';
        try { t = String(t); } catch (e) { return ''; }
        t = t.replace(/^\s+|\s+$/g, '');
        return t;
    }

    function tryRegisterToken(token, deviceId, appVersion, reason) {
        token = normalizeToken(token);
        if (!token || token.length < 20) return Promise.resolve({ ok: false, skipped: true });
        try { window.__ecLastFcmToken = token; } catch (e) {}

        if (registeredOkToken === token) {
            // mesmo token já confirmado nesta página; ainda assim revalida se veio de chamada explícita do app
            if (reason !== 'explicit' && reason !== 'alias' && reason !== 'manual') {
                return Promise.resolve({ ok: true, cached: true });
            }
        }

        var now = (new Date()).getTime();
        if (lastAttemptToken === token && (now - lastAttemptAt) < 4000 && reason !== 'explicit' && reason !== 'alias' && reason !== 'manual') {
            return Promise.resolve({ ok: false, throttled: true });
        }
        lastAttemptToken = token;
        lastAttemptAt = now;

        return registrarComRetentativa(token, deviceId, appVersion, 4).then(function (res) {
            if (res && res.ok) {
                registeredOkToken = token;
                try { sessionStorage.setItem('ec_push_registered_token', token); } catch (e) {}
            }
            return res || { ok: false };
        }).catch(function (err) {
            return { ok: false, erro: String(err) };
        });
    }

    function discoverToken() {
        var names = ['__ecPendingFcmToken', 'EC_FCM_TOKEN', 'fcmToken', 'FCM_TOKEN', '__fcmToken', 'pushToken', 'devicePushToken', '__ecLastFcmToken'];
        for (var i = 0; i < names.length; i++) {
            try {
                var v = window[names[i]];
                var t = normalizeToken(v);
                if (t && t.length >= 20) return t;
            } catch (e) {}
        }
        try {
            var prev = sessionStorage.getItem('ec_push_registered_token');
            prev = normalizeToken(prev);
            if (prev && prev.length >= 20) return prev;
        } catch (e) {}
        return '';
    }

    function autoTry(reason) {
        var t = discoverToken();
        if (t) {
            tryRegisterToken(t, '', '', reason || 'auto');
        }
    }

    window.EconomicCardPush = {
        pronto: true,

        register: function (token, deviceId, appVersion) {
            token = normalizeToken(token);
            if (!token) {
                return Promise.reject(new Error('Token FCM vazio.'));
            }
            try { window.__ecLastFcmToken = token; window.__ecPendingFcmToken = token; } catch (e) {}
            return tryRegisterToken(token, deviceId, appVersion, 'explicit');
        },

        unregister: function (token) {
            token = normalizeToken(token);
            if (!token) {
                return Promise.reject(new Error('Token FCM vazio.'));
            }
            return enviar({ acao: 'remover', token: String(token) });
        },

        /**
         * Chamado pelo app nativo quando a permissão de notificações muda.
         * Em caso de negação mostra um aviso orientando a ativar nas
         * configurações do Android (uma vez por sessão). Em caso de concessão,
         * tenta registrar novamente (o token pode ter sido gerado agora).
         */
        permissao: function (habilitada) {
            var ok = habilitada === true || String(habilitada) === 'true';
            if (ok) {
                try { window.__ecPushPermissionGranted = true; } catch (e) {}
                autoTry('permissao_ok');
                setTimeout(function () { autoTry('permissao_ok_2s'); }, 2000);
                setTimeout(function () { autoTry('permissao_ok_6s'); }, 6000);
                return true;
            }
            try {
                if (sessionStorage.getItem('ec_push_aviso') === '1') return false;
                sessionStorage.setItem('ec_push_aviso', '1');
            } catch (e) { /* modo privado: mostra mesmo assim */ }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', avisoNotificacoes);
            } else {
                avisoNotificacoes();
            }
            return false;
        },

        tryAutoRegister: function () { autoTry('manual'); return discoverToken(); },
        getLastToken: function () { try { return window.__ecLastFcmToken || registeredOkToken || ''; } catch (e) { return registeredOkToken || ''; } }
    };

    // Aliases para integrações nativas que usem nomes alternativos.
    try { window.registerFcmToken = function (t, d, v) { return window.EconomicCardPush.register(t, d, v); }; } catch (e) {}
    try { window.registerPushToken = function (t, d, v) { return window.EconomicCardPush.register(t, d, v); }; } catch (e) {}
    try { window.EC_registerPush = function (t, d, v) { return window.EconomicCardPush.register(t, d, v); }; } catch (e) {}
    try { window.onFcmToken = function (t, d, v) { return window.EconomicCardPush.register(t, d, v); }; } catch (e) {}

    // Tentativas iniciais e tardias (token pode ser injetado após concessão/permissão).
    autoTry('init');
    var delays = [300, 1000, 2000, 4000, 7000, 12000, 20000, 35000, 60000, 90000, 120000];
    for (var i = 0; i < delays.length; i++) {
        (function (d) { setTimeout(function () { autoTry('delay_' + d); }, d); })(delays[i]);
    }
    function onVisible() { if (!document.hidden) autoTry('visible'); }
    try { document.addEventListener('visibilitychange', onVisible); } catch (e) {}
    try { window.addEventListener('pageshow', function () { autoTry('pageshow'); }); } catch (e) {}
    try { window.addEventListener('focus', function () { autoTry('focus'); }); } catch (e) {}
    try { window.addEventListener('load', function () { autoTry('load'); }); } catch (e) {}
})();
