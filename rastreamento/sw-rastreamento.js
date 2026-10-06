/* Service worker de rastreamento.
 *
 * IMPORTANTE: service workers NÃO têm acesso à Geolocation API. Quem lê o GPS é a
 * página (watchPosition) e repassa cada posição para cá via postMessage. O SW
 * grava no GeoFire (Realtime Database, formato {g: geohash, l: [lat, lng]}) via
 * REST, mantém a última posição no IndexedDB e reenvia com Background Sync se
 * a rede cair.
 */
importScripts('firebase-config.js');

const CFG = self.RASTREAMENTO_CONFIG;
const DB_URL = CFG.firebase.databaseURL.replace(/\/$/, '');
const BASE32 = '0123456789bcdefghjkmnpqrstuvwxyz';

// --- geohash (mesmo algoritmo e precisão 10 do GeoFire) ---------------------
function geohash(lat, lng, precision = 10) {
  let latR = [-90, 90], lngR = [-180, 180];
  let hash = '', bits = 0, bit = 0, ch = 0, even = true;
  while (hash.length < precision) {
    const r = even ? lngR : latR;
    const v = even ? lng : lat;
    const mid = (r[0] + r[1]) / 2;
    if (v > mid) { ch = (ch << 1) | 1; r[0] = mid; } else { ch = ch << 1; r[1] = mid; }
    even = !even;
    if (++bits === 5) { hash += BASE32[ch]; bits = 0; ch = 0; }
  }
  return hash;
}

// --- IndexedDB: guarda a última posição pendente ----------------------------
function abrirDb() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open('rastreamento', 1);
    req.onupgradeneeded = () => req.result.createObjectStore('pendente');
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}
async function dbOp(modo, fn) {
  const db = await abrirDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction('pendente', modo);
    const r = fn(tx.objectStore('pendente'));
    tx.oncomplete = () => resolve(r && r.result);
    tx.onerror = () => reject(tx.error);
  });
}
const salvarPendente = (p) => dbOp('readwrite', (s) => s.put(p, 'ultima'));
const limparPendente = () => dbOp('readwrite', (s) => s.delete('ultima'));
const lerPendente = () => dbOp('readonly', (s) => s.get('ultima'));

// --- envio ao Firebase (REST) -----------------------------------------------
function url(path, token) {
  return `${DB_URL}/${path}.json` + (token ? `?auth=${encodeURIComponent(token)}` : '');
}
async function enviar(p) {
  const { key, lat, lng, token, meta } = p;
  const geo = fetch(url(`${CFG.geofirePath}/${key}`, token), {
    method: 'PUT',
    body: JSON.stringify({ g: geohash(lat, lng), l: [lat, lng] }),
    keepalive: true
  });
  const info = fetch(url(`${CFG.metaPath}/${key}`, token), {
    method: 'PATCH',
    body: JSON.stringify({ ...meta, ts: p.ts }),
    keepalive: true
  });
  const [a, b] = await Promise.all([geo, info]);
  if (!a.ok || !b.ok) throw new Error(`Firebase respondeu ${a.status}/${b.status}`);
}

async function remover({ key, token }) {
  await Promise.all([
    fetch(url(`${CFG.geofirePath}/${key}`, token), { method: 'DELETE' }),
    fetch(url(`${CFG.metaPath}/${key}`, token), { method: 'DELETE' })
  ]);
}

async function avisar(msg) {
  const clientes = await self.clients.matchAll({ includeUncontrolled: true });
  clientes.forEach((c) => c.postMessage(msg));
}

async function processar(p) {
  try {
    await enviar(p);
    await limparPendente();
    await avisar({ tipo: 'enviado', ts: p.ts });
  } catch (e) {
    await salvarPendente(p);
    if (self.registration.sync) {
      try { await self.registration.sync.register('enviar-posicao'); } catch (_) {}
    }
    await avisar({ tipo: 'erro', erro: String(e) });
  }
}

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

self.addEventListener('message', (event) => {
  const d = event.data || {};
  if (d.tipo === 'posicao') {
    event.waitUntil(processar({ ...d, ts: d.ts || Date.now() }));
  } else if (d.tipo === 'parar') {
    event.waitUntil(limparPendente().then(() => remover(d)));
  }
});

self.addEventListener('sync', (event) => {
  if (event.tag === 'enviar-posicao') {
    event.waitUntil(lerPendente().then((p) => p && processar(p)));
  }
});
