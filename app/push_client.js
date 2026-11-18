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

    window.EconomicCardPush = {
        pronto: true,

        register: function (token, deviceId, appVersion) {
            if (!token) {
                return Promise.reject(new Error('Token FCM vazio.'));
            }
            return enviar({
                acao: 'registrar',
                token: String(token),
                device_id: deviceId ? String(deviceId) : '',
                app_version: appVersion ? String(appVersion) : '',
                plataforma: 'android'
            });
        },

        unregister: function (token) {
            if (!token) {
                return Promise.reject(new Error('Token FCM vazio.'));
            }
            return enviar({ acao: 'remover', token: String(token) });
        }
    };
})();
