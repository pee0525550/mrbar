const MRBAR_PWA_CACHE='mrbar-time-static-v14852';
const MRBAR_PWA_STATIC=[
  './assets/pwa-time.css?v=1.27.21',
  './assets/pwa-time.js?v=1.48.52',
  './assets/staff-auth-v12721.css?v=12721',
  './assets/staff-auth-v12721.js?v=12721',
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
function mrbarSafeStatic(url){
  const scopePath=new URL(self.registration.scope).pathname.replace(/\/$/,'');
  const p=url.pathname;
  if(p.startsWith(scopePath+'/assets/icons/'))return true;
  return [
    scopePath+'/assets/pwa-time.css',
    scopePath+'/assets/pwa-time.js',
    scopePath+'/assets/staff-auth-v12721.css',
    scopePath+'/assets/staff-auth-v12721.js',
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
