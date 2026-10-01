const MRBAR_PWA_CACHE='mrbar-time-static-v14905';
const MRBAR_PWA_STATIC=[
  './assets/pwa-time.css?v=1.27.21',
  './assets/pwa-time.js?v=1.48.52',
  './assets/staff-auth-v12721.css?v=12721',
  './assets/staff-auth-v12721.js?v=12721',
  './assets/line-login-v14901.css?v=14901',
  './assets/line-login-v14901.js?v=14901',
  './assets/line-flow-v14901.css?v=14901',
  './assets/line-flow-v14901.js?v=14901',
  './assets/line-auth-theme-v14901.css?v=14901',
  './assets/login-experience-v14904.css?v=14904',
  './assets/login-pin-v14905.css?v=14905',
  './assets/login-pin-v14905.js?v=14905',
  './assets/typography.css?v=1.27.21',
  './assets/fonts/mrbar-thai-variable.ttf?v=1224',
  './assets/icons/mrbar-time-180.png',
  './assets/icons/mrbar-time-192.png',
  './assets/icons/mrbar-time-512.png',
  './assets/icons/mrbar-time-maskable-512.png'
];
self.addEventListener('install',event=>{
  event.waitUntil(caches.open(MRBAR_PWA_CACHE).then(cache=>cache.addAll(MRBAR_PWA_STATIC)).then(()=>self.skipWaiting()));
});
self.addEventListener('activate',event=>{
  event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key.startsWith('mrbar-time-static-')&&key!==MRBAR_PWA_CACHE).map(key=>caches.delete(key)))).then(()=>self.clients.claim()));
});
self.addEventListener('push',event=>{
  let payload={};
  try{payload=event.data?event.data.json():{}}catch(_){payload={body:event.data?event.data.text():''};}
  const title=String(payload.title||'MR BAR · แจ้งเตือน');
  const body=String(payload.body||payload.message||'มีรายการใหม่ที่ต้องตรวจสอบ');
  const options={
    body,
    icon:payload.icon||'assets/icons/mrbar-time-192.png',
    badge:payload.badge||'assets/icons/mrbar-time-192.png',
    tag:String(payload.tag||payload.event_id||'mrbar-notification'),
    data:{url:String(payload.url||'dashboard.php')},
    renotify:false,
  };
  event.waitUntil(self.registration.showNotification(title,options));
});
self.addEventListener('notificationclick',event=>{
  event.notification.close();
  const target=new URL(String(event.notification.data&&event.notification.data.url||'dashboard.php'),self.registration.scope);
  if(target.origin!==self.location.origin)return;
  event.waitUntil(self.clients.matchAll({type:'window',includeUncontrolled:true}).then(clients=>{
    for(const client of clients)if(new URL(client.url).origin===target.origin){client.navigate(target.href);return client.focus();}
    return self.clients.openWindow(target.href);
  }));
});
function mrbarSafeStatic(url){
  const scopePath=new URL(self.registration.scope).pathname.replace(/\/$/,'');
  const p=url.pathname;
  if(p.startsWith(scopePath+'/assets/icons/'))return true;
  return [
    scopePath+'/assets/pwa-time.css',
    scopePath+'/assets/pwa-time.js',
    scopePath+'/assets/staff-auth-v12721.css',
    scopePath+'/assets/staff-auth-v12721.js',
    scopePath+'/assets/line-login-v14901.css',
    scopePath+'/assets/line-login-v14901.js',
    scopePath+'/assets/line-flow-v14901.css',
    scopePath+'/assets/line-flow-v14901.js',
    scopePath+'/assets/line-auth-theme-v14901.css',
    scopePath+'/assets/login-experience-v14904.css',
    scopePath+'/assets/login-pin-v14905.css',
    scopePath+'/assets/login-pin-v14905.js',
    scopePath+'/assets/typography.css',
    scopePath+'/assets/fonts/mrbar-thai-variable.ttf'
  ].includes(p);
}
self.addEventListener('fetch',event=>{
  const req=event.request;
  if(req.method!=='GET'||req.mode==='navigate')return;
  const url=new URL(req.url);
  if(url.origin!==self.location.origin||!mrbarSafeStatic(url))return;
  event.respondWith(caches.match(req).then(hit=>hit||fetch(req).then(res=>{
    if(res&&res.ok){const copy=res.clone();caches.open(MRBAR_PWA_CACHE).then(cache=>cache.put(req,copy));}
    return res;
  })));
});
