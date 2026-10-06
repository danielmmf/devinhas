<?php
session_start();
require_once 'conexao.php';

$viagem_id =$_GET['id'] ?? null;
$viagem = null;

// Verifica se o arquivo do APK existe fisicamente no servidor
$apk_existe = file_exists('app-motorista.apk');

if ($viagem_id) {
    try {
        $stmt =$pdo->prepare("
            SELECT vi.id, v.modelo, v.placa, m.nome AS motorista, vi.destino, vi.data_viagem 
            FROM viagens vi
            LEFT JOIN veiculos v ON vi.veiculo_id = v.id
            LEFT JOIN motoristas m ON vi.motorista_id = m.id
            WHERE vi.id = ?
        ");
        $stmt->execute([$viagem_id]);
        $viagem =$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {$viagem = null;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Motorista - Rastreamento</title>
    <style>
        :root {
            --primary: #007BFF;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f3f5;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 400px;
            text-align: center;
            border-top: 4px solid var(--primary);
        }
        h2 { color: var(--primary); margin-top: 0; font-size: 1.3rem; }
        .info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0;
            text-align: left;
            font-size: 0.9rem;
            color: #495057;
        }
        .info-box p { margin: 6px 0; }
        .btn-ativar {
            background: var(--success);
            color: #fff;
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2);
        }
        .btn-ativar:hover { background: #218838; }
        .btn-cancelar {
            background: var(--danger);
            color: #fff;
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 4px 10px rgba(220, 53, 69, 0.2);
            margin-top: 10px;
            display: none;
        }
        .btn-cancelar:hover { background: #c82333; }
        .bloco-instalacao {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 15px;
            text-align: center;
            display: none; /* Oculto por padrão, gerido pelo JS */
        }
        .btn-baixar-apk {
            display: inline-block;
            background: #ffc107;
            color: #333;
            font-weight: bold;
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .btn-baixar-apk:hover { background: #e0a800; }
        .status-msg {
            margin-top: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #6c757d;
        }
        .erro-msg {
            color: var(--danger);
            font-weight: 600;
            font-size: 0.9rem;
        }
        .app-info {
            font-size: 0.8rem;
            color: #28a745;
            font-weight: bold;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px dashed #dee2e6;
            display: none; /* Só aparece se estiver no app nativo */
        }
    </style>
</head>
<body>

<div class="card">
    <h2>1Painel do Motorista</h2>
    
    <?php if ($viagem): ?>
        <!-- Bloco de aviso de instalação (aparece apenas no navegador comum) -->
        <div id="painelInstalacao" class="bloco-instalacao">
            ⚠️ <strong>Modo Navegador Detetado!</strong><br>
            Para que o rastreamento não pare ao minimizar o telemóvel, instale o aplicativo oficial:<br>
            <?php if ($apk_existe): ?>
                <a href="app-motorista.apk" class="btn-baixar-apk" download>📥 Baixar app-motorista.apk</a>
            <?php else: ?>
                <span style="color: red; font-size: 0.8rem;"><br>⚠️ Arquivo <code>app-motorista.apk</code> não encontrado na raiz do servidor.</span>
            <?php endif; ?>
        </div>

        <!-- Conteúdo principal do painel -->
        <div id="painelPrincipal">
            <div class="info-box">
                <p><strong>Motorista:</strong> <?= htmlspecialchars($viagem['motorista'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Veículo:</strong> <?= htmlspecialchars($viagem['modelo'] . ' / ' .$viagem['placa'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Destino:</strong> <?= htmlspecialchars($viagem['destino'], ENT_QUOTES, 'UTF-8') ?></p>
                <div id="statusAppInstalado" class="app-info">📱 App Oficial Detetado: <strong>app-motorista.apk</strong></div>
            </div>

            <p style="font-size: 0.85rem; color: #666; margin-bottom: 20px;">
                Toque no botão abaixo para ativar o rastreamento GPS da viagem.
            </p>

            <button id="btnIniciar" class="btn-ativar" onclick="iniciarRastreamento(<?= $viagem['id'] ?>)">
                📍 Ativar Localização
            </button>

            <button id="btnCancelar" class="btn-cancelar" onclick="cancelarRastreamento()">
                ❌ Cancelar Rastreamento
            </button>

            <div id="status" class="status-msg">Aguardando autorização...</div>
        </div>

    <?php else: ?>
        <p class="erro-msg">Viagem não encontrada ou link inválido.</p>
    <?php endif; ?>
</div>

<script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
<script src="https://cdn.firebase.com/libs/geofire/5.0.1/geofire.min.js"></script>
<script>
const firebaseConfig = {
    apiKey: "AIzaSyC4rG9xevBnnnD2C8yartqn1Jj80tK8HBM",
    authDomain: "rastreioguapiara.firebaseapp.com",
    databaseURL: "https://rastreioguapiara-default-rtdb.europe-west1.firebasedatabase.app",
    projectId: "rastreioguapiara",
    storageBucket: "rastreioguapiara.firebasestorage.app",
    messagingSenderId: "633809880923",
    appId: "1:633809880923:web:d4ee01c480ea60d93c3c7e"
};
firebase.initializeApp(firebaseConfig);
const geoFire = new GeoFire(firebase.database().ref('geofire'));
const GEOFIRE_KEY = 'viagem_<?= (int)$viagem_id ?>';

window.addEventListener('DOMContentLoaded', () => {
    // Validação estrita do ambiente nativo
    const isNativeApp = (window.Capacitor && window.Capacitor.isNativePlatform()) || 
                        navigator.userAgent.includes('wv') || 
                        window.location.search.includes('app=1');

    const painelInstalacao = document.getElementById('painelInstalacao');
    const statusAppInstalado = document.getElementById('statusAppInstalado');

    if (isNativeApp) {
        // Se estiver dentro do aplicativo nativo
        if (painelInstalacao) painelInstalacao.style.display = 'none';
        if (statusAppInstalado) statusAppInstalado.style.display = 'block';
    } else {
        // Se estiver num navegador comum (Chrome, Safari, etc.)
        if (painelInstalacao) painelInstalacao.style.display = 'block';
        if (statusAppInstalado) statusAppInstalado.style.display = 'none';
    }
});

let wakeLock = null;
let ultimaLat = null;
let ultimaLng = null;
let watchId = null;
let backgroundWatcherId = null;
let intervaloEnvio = null;
let pluginBackgroundGeo = null;
let envioAtivo = false;

async function ativarWakeLock() {
    try {
        if ('wakeLock' in navigator) {
            wakeLock = await navigator.wakeLock.request('screen');
            document.addEventListener('visibilitychange', async () => {
                if (wakeLock !== null && document.visibilityState === 'visible') {
                    wakeLock = await navigator.wakeLock.request('screen');
                }
            });
        }
    } catch (err) {
        console.error(`Erro ao ativar Wake Lock: ${err.name}, ${err.message}`);
    }
}

function iniciarRastreamento(viagemId) {
    const statusDiv = document.getElementById('status');
    const btnIniciar = document.getElementById('btnIniciar');
    const btnCancelar = document.getElementById('btnCancelar');

    <?php if ($viagem): ?>
    localStorage.setItem('jg_motorista_nome', "<?= addslashes($viagem['motorista']) ?>");
    <?php endif; ?>

    function enviarCoordenadas(lat, lng) {
        geoFire.set(GEOFIRE_KEY, [lat, lng]).catch(e => console.error('GeoFire:', e));
        fetch('atualiza_posicao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ viagem_id: viagemId, latitude: lat, longitude: lng })
        })
        .then(response => response.json())
        .then(data => {
            btnIniciar.style.background = "#28a745";
            btnIniciar.textContent = "Rastreamento Ativo 🟢";
            btnIniciar.disabled = true;
            btnCancelar.style.display = "block";
            
            const agora = new Date().toLocaleTimeString();
            statusDiv.textContent = "Transmitido às " + agora + " (Ativo)";
            statusDiv.style.color = "#28a745";
        })
        .catch(error => {
            console.error("Erro ao enviar coordenadas:", error);
        });
    }

    const isNativeApp = (window.Capacitor && window.Capacitor.isNativePlatform()) || 
                        navigator.userAgent.includes('wv') || 
                        window.location.search.includes('app=1');

    if (isNativeApp) {
        statusDiv.textContent = "Iniciando GPS Nativo em Segundo Plano...";
        ativarWakeLock();

        const bgGeo = window.BackgroundGeolocation || 
                      (window.Capacitor.Plugins && window.Capacitor.Plugins.BackgroundGeolocation);

        if (bgGeo) {
            executarBackgroundGeo(bgGeo);
        } else {
            try {
                import('@capacitor-community/background-geolocation').then(({ BackgroundGeolocation }) => {
                    executarBackgroundGeo(BackgroundGeolocation);
                }).catch(err => {
                    fallbackWebGeolocation();
                });
            } catch (e) {
                fallbackWebGeolocation();
            }
        }

        function executarBackgroundGeo(BackgroundGeolocation) {
            pluginBackgroundGeo = BackgroundGeolocation;
            BackgroundGeolocation.requestPermissions().then(permission => {
                if (permission.location !== 'granted' && permission.location !== 'always') {
                    statusDiv.textContent = "Permissão de localização em segundo plano negada.";
                    statusDiv.style.color = "#dc3545";
                    return;
                }

                BackgroundGeolocation.addWatcher(
                    {
                        backgroundMessage: "A viagem está em andamento. O rastreamento continua ativo em segundo plano.",
                        backgroundTitle: "Rastreamento JG Soft 🟢",
                        requestPermissions: true,
                        stale: false,
                        accuracy: 5,
                        distanceFilter: 5,
                        notificationsEnabled: true
                    },
                    function (location, error) {
                        if (error) {
                            console.warn("Erro no Background Watcher:", error);
                            return;
                        }
                        if (location) {
                            enviarCoordenadas(location.latitude, location.longitude);
                        }
                    }
                ).then(watcherId => {
                    backgroundWatcherId = watcherId;
                    statusDiv.textContent = "Rastreamento em Segundo Plano Ativo 🟢";
                }).catch(err => {
                    console.error("Erro ao adicionar watcher:", err);
                });
            }).catch(e => {
                console.error("Erro nas permissões:", e);
            });
        }

    } else {
        fallbackWebGeolocation();
    }

    function fallbackWebGeolocation() {
        if (!navigator.geolocation) {
            statusDiv.textContent = "Seu navegador não suporta geolocalização.";
            statusDiv.style.color = "#dc3545";
            return;
        }

        statusDiv.textContent = "Iniciando GPS (Web)...";
        ativarWakeLock();

        watchId = navigator.geolocation.watchPosition(
            function(position) {
                ultimaLat = position.coords.latitude;
                ultimaLng = position.coords.longitude;
                enviarCoordenadas(ultimaLat, ultimaLng);
            },
            function(error) {
                console.warn("Erro no watchPosition:", error.code);
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 }
        );

        if (!envioAtivo) {
            envioAtivo = true;
            intervaloEnvio = setInterval(() => {
                if (ultimaLat !== null && ultimaLng !== null) {
                    enviarCoordenadas(ultimaLat, ultimaLng);
                }
            }, 10000);
        }
    }
}

function cancelarRastreamento() {
    const statusDiv = document.getElementById('status');
    const btnIniciar = document.getElementById('btnIniciar');
    const btnCancelar = document.getElementById('btnCancelar');

    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }

    if (intervaloEnvio !== null) {
        clearInterval(intervaloEnvio);
        intervaloEnvio = null;
    }

    if (pluginBackgroundGeo && backgroundWatcherId !== null) {
        pluginBackgroundGeo.removeWatcher({ id: backgroundWatcherId }).catch(e => console.log(e));
        backgroundWatcherId = null;
    }

    if (wakeLock !== null) {
        wakeLock.release().catch(() => {});
        wakeLock = null;
    }

    geoFire.remove(GEOFIRE_KEY);

    envioAtivo = false;
    ultimaLat = null;
    ultimaLng = null;

    btnIniciar.style.background = "var(--success)";
    btnIniciar.textContent = "📍 Ativar Localização";
    btnIniciar.disabled = false;
    btnCancelar.style.display = "none";

    statusDiv.textContent = "Rastreamento cancelado.";
    statusDiv.style.color = "#dc3545";
}
</script>

</body>
</html>