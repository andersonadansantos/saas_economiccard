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
        tentativas = typeof tentativas === 'number' ? tentativas : 3;
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

    window.EconomicCardPush = {
        pronto: true,

        register: function (token, deviceId, appVersion) {
            if (!token) {
                return Promise.reject(new Error('Token FCM vazio.'));
            }
            try { window.__ecLastFcmToken = String(token); } catch (e) {}
            return registrarComRetentativa(token, deviceId, appVersion, 3);
        },

        unregister: function (token) {
            if (!token) {
                return Promise.reject(new Error('Token FCM vazio.'));
            }
            return enviar({ acao: 'remover', token: String(token) });
        },

        /**
         * Chamado pelo app nativo quando a permissão de notificações muda.
         * Em caso de negação mostra um aviso orientando a ativar nas
         * configurações do Android (uma vez por sessão).
         */
        permissao: function (habilitada) {
            var ok = habilitada === true || String(habilitada) === 'true';
            if (ok) return true;
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
        }
    };

    // Se o app nativo injetou o token antes do carregamento do script,
    // tenta registrar automaticamente.
    try {
        var pendente = window.__ecPendingFcmToken || window.EC_FCM_TOKEN || window.fcmToken;
        if (pendente) {
            setTimeout(function () {
                try { window.EconomicCardPush.register(pendente); } catch (e) {}
            }, 300);
        }
    } catch (e) {}
})();
