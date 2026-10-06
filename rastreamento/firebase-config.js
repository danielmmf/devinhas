// Configuração compartilhada (página do motorista, service worker e mapa).
// Preencha com os dados do seu projeto: Console Firebase > Configurações > Seus apps.
self.RASTREAMENTO_CONFIG = {
  firebase: {
    apiKey: 'SUA_API_KEY',
    authDomain: 'SEU_PROJETO.firebaseapp.com',
    databaseURL: 'https://SEU_PROJETO-default-rtdb.firebaseio.com',
    projectId: 'SEU_PROJETO'
  },
  geofirePath: 'geofire',        // nós {g, l:[lat,lng]} gravados no formato GeoFire
  metaPath: 'motoristas_meta'    // dados extras: nome, placa, destino, hora
};
