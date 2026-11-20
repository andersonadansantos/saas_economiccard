<?php
require_once __DIR__ . '/config.php';
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$uid = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;
?>
<!DOCTYPE html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?php echo $csrf; ?>">
<title>Diagnóstico Push</title>
<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#f5f5f7;color:#111} .w{max-width:760px;margin:0 auto;padding:16px} .c{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:14px;margin:12px 0} h1{font-size:20px;margin:0 0 4px} .m{font-size:13px;color:#555;word-break:break-all} .ok{color:#166534;font-weight:700} .bad{color:#991b1b;font-weight:700} button{background:#51036d;color:#fff;border:0;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer;margin:4px 4px 0 0} pre{white-space:pre-wrap;background:#111827;color:#e5e7eb;padding:10px;border-radius:10px;font-size:12px;max-height:260px;overflow:auto}</style>
<script src="push_client.js?v=<?php echo time(); ?>"></script>
</head><body><div class="w">
<h1>Diagnóstico de Push</h1>
<div class="m">Abra esta página no mesmo dispositivo/app onde a notificação foi permitida. Usuário sessão: <b><?php echo $uid ?: 'não logado'; ?></b></div>
<div class="c"><b>Estado</b><div id="estado" class="m">Carregando...</div></div>
<div class="c"><b>Token detectado</b><div id="token" class="m"></div></div>
<div class="c"><button id="btn" type="button">Tentar registrar token agora</button> <button id="btn2" type="button" style="background:#374151">Atualizar status</button></div>
<div class="c"><b>Resultado</b><pre id="out">(aguardando)</pre></div>
<div class="c"><b>Interpretação</b><div class="m" id="hint"></div></div>
</div>
<script>
(function(){
  var out=document.getElementById('out'), tok=document.getElementById('token'), est=document.getElementById('estado'), hint=document.getElementById('hint');
  function log(x){ try{ out.textContent = (typeof x==='string'?x:JSON.stringify(x,null,2)); }catch(e){ out.textContent=String(x);} }
  function status(){
    var t=''; try{ t=(window.EconomicCardPush&&EconomicCardPush.getLastToken&&EconomicCardPush.getLastToken())||window.__ecLastFcmToken||window.__ecPendingFcmToken||window.EC_FCM_TOKEN||window.fcmToken||''; }catch(e){}
    var has = !!t;
    tok.textContent = has ? (String(t).slice(0,24)+'... ('+String(t).length+' chars)') : 'NENHUM token exposto ao WebView';
    var parts=[];
    parts.push('EconomicCardPush: ' + (!!(window.EconomicCardPush&&window.EconomicCardPush.pronto)));
    parts.push('csrf-meta: ' + (!!document.querySelector('meta[name="csrf-token"]')));
    parts.push('online: ' + navigator.onLine);
    parts.push('visibility: ' + document.visibilityState);
    try{ parts.push('notif-perm(api): ' + (('Notification' in window)?Notification.permission:'n/d')); }catch(e){}
    est.innerHTML = parts.map(function(p){return '<div>'+p+'</div>';}).join('');
    if(!has){
      hint.innerHTML = '<span class="bad">Token FCM não encontrado no WebView.</span> Isso indica que o app nativo não entregou o token ao JavaScript (evento TokenReceived/bridge). Conceder permissão de notificação, por si só, não registra o dispositivo — o app precisa obter o token FCM e chamar <code>window.EconomicCardPush.register(token)</code> (ou expor o token em variável compatível). Verifique a integração FCM/TokenReceived na build do app.';
    } else {
      hint.innerHTML = '<span class="ok">Token detectado.</span> Clique em "Tentar registrar token agora" para enviar ao servidor. Se retornar <code>ok:true</code>, o dispositivo deve aparecer em "Dispositivos ativos com push" no admin.';
    }
    return t;
  }
  document.getElementById('btn2').onclick=function(){ status(); };
  document.getElementById('btn').onclick=function(){
    var t=status();
    if(!t){ log({ok:false, erro:'Token FCM não encontrado no WebView. O app nativo precisa chamar window.EconomicCardPush.register(token) após o TokenReceived.'}); return; }
    log('Registrando...');
    Promise.resolve().then(function(){ return (window.EconomicCardPush&&EconomicCardPush.register)?EconomicCardPush.register(t):{ok:false,erro:'Bridge ausente'}; })
      .then(function(r){ log(r); status(); })
      .catch(function(e){ log({ok:false, erro:String(e)}); status(); });
  };
  status();
  setTimeout(status, 800); setTimeout(status, 2500); setTimeout(status, 6000);
  try{ window.addEventListener('focus', status); document.addEventListener('visibilitychange', status); }catch(e){}
})();
</script></body></html>
