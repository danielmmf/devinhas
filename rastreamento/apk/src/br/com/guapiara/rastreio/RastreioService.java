package br.com.guapiara.rastreio;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Intent;
import android.content.SharedPreferences;
import android.location.Location;
import android.location.LocationListener;
import android.location.LocationManager;
import android.os.Build;
import android.os.Bundle;
import android.os.IBinder;
import android.os.PowerManager;
import android.util.Log;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/** Serviço em primeiro plano: lê o GPS e grava no GeoFire mesmo com o app minimizado. */
public class RastreioService extends Service implements LocationListener {
    static final String PARAR = "parar";
    private static final String CANAL = "rastreio";
    private static final String BASE32 = "0123456789bcdefghjkmnpqrstuvwxyz";

    private final ExecutorService rede = Executors.newSingleThreadExecutor();
    private LocationManager lm;
    private PowerManager.WakeLock wakeLock;
    private String tel, nome;

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        SharedPreferences prefs = getSharedPreferences("rastreio", MODE_PRIVATE);
        tel = prefs.getString("tel", "");
        nome = prefs.getString("nome", "");

        if ((intent != null && PARAR.equals(intent.getAction())) || tel.isEmpty()) {
            final String t = tel;
            if (!t.isEmpty()) rede.execute(new Runnable() {
                @Override public void run() { enviar("DELETE", Config.GEOFIRE + "/" + t, null); }
            });
            pararTudo();
            return START_NOT_STICKY;
        }

        startForeground(1, notificacao());
        if (wakeLock == null) {
            PowerManager pm = (PowerManager) getSystemService(POWER_SERVICE);
            wakeLock = pm.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "rastreio:gps");
            wakeLock.acquire();
        }
        if (lm == null) {
            lm = (LocationManager) getSystemService(LOCATION_SERVICE);
            try {
                lm.requestLocationUpdates(LocationManager.GPS_PROVIDER, Config.INTERVALO_MS, Config.DISTANCIA_M, this);
                if (lm.isProviderEnabled(LocationManager.NETWORK_PROVIDER))
                    lm.requestLocationUpdates(LocationManager.NETWORK_PROVIDER, Config.INTERVALO_MS, Config.DISTANCIA_M, this);
            } catch (SecurityException e) {
                Log.e("rastreio", "sem permissão de localização", e);
                pararTudo();
                return START_NOT_STICKY;
            }
        }
        return START_STICKY;   // se o Android matar o serviço, ele é recriado
    }

    @Override
    public void onLocationChanged(Location loc) {
        final double lat = loc.getLatitude(), lng = loc.getLongitude();
        rede.execute(new Runnable() { @Override public void run() {
            enviar("PUT", Config.GEOFIRE + "/" + tel, String.format(Locale.US,
                    "{\"g\":\"%s\",\"l\":[%.7f,%.7f]}", geohash(lat, lng), lat, lng));
            enviar("PUT", Config.META + "/" + tel, "{\"nome\":" + json(nome)
                    + ",\"hora\":{\".sv\":\"timestamp\"}}");
        }});
    }

    private void enviar(String metodo, String caminho, String corpo) {
        HttpURLConnection c = null;
        try {
            c = (HttpURLConnection) new URL(Config.DB_URL + "/" + caminho + ".json").openConnection();
            c.setRequestMethod(metodo);
            c.setConnectTimeout(15000);
            c.setReadTimeout(15000);
            if (corpo != null) {
                c.setDoOutput(true);
                c.setRequestProperty("Content-Type", "application/json");
                try (OutputStream os = c.getOutputStream()) { os.write(corpo.getBytes("UTF-8")); }
            }
            int code = c.getResponseCode();
            if (code >= 300) Log.w("rastreio", metodo + " " + caminho + " -> HTTP " + code);
        } catch (Exception e) {
            Log.w("rastreio", "falha ao enviar (sem internet?)", e);   // a próxima posição tenta de novo
        } finally {
            if (c != null) c.disconnect();
        }
    }

    // Mesmo geohash (precisão 10) que a biblioteca GeoFire usa
    static String geohash(double lat, double lng) {
        double[] la = {-90, 90}, lo = {-180, 180};
        StringBuilder h = new StringBuilder();
        boolean par = true;
        int bit = 0, ch = 0;
        while (h.length() < 10) {
            double[] r = par ? lo : la;
            double v = par ? lng : lat, meio = (r[0] + r[1]) / 2;
            if (v > meio) { ch = (ch << 1) | 1; r[0] = meio; } else { ch <<= 1; r[1] = meio; }
            par = !par;
            if (++bit == 5) { h.append(BASE32.charAt(ch)); bit = 0; ch = 0; }
        }
        return h.toString();
    }

    private static String json(String s) {
        return "\"" + s.replace("\\", "\\\\").replace("\"", "\\\"") + "\"";
    }

    private Notification notificacao() {
        PendingIntent abrir = PendingIntent.getActivity(this, 0,
                new Intent(this, MainActivity.class), PendingIntent.FLAG_IMMUTABLE);
        Notification.Builder b;
        if (Build.VERSION.SDK_INT >= 26) {
            NotificationManager nm = getSystemService(NotificationManager.class);
            nm.createNotificationChannel(new NotificationChannel(CANAL, "Rastreamento", NotificationManager.IMPORTANCE_LOW));
            b = new Notification.Builder(this, CANAL);
        } else {
            b = new Notification.Builder(this);
        }
        return b.setContentTitle("Rastreamento ativo 🟢")
                .setContentText("Sua localização está sendo enviada.")
                .setSmallIcon(android.R.drawable.ic_menu_mylocation)
                .setContentIntent(abrir)
                .setOngoing(true)
                .build();
    }

    private void pararTudo() {
        if (lm != null) { lm.removeUpdates(this); lm = null; }
        if (wakeLock != null && wakeLock.isHeld()) wakeLock.release();
        wakeLock = null;
        stopForeground(true);
        stopSelf();
    }

    @Override public void onDestroy() { pararTudo(); rede.shutdown(); super.onDestroy(); }
    @Override public IBinder onBind(Intent i) { return null; }
    @Override public void onStatusChanged(String p, int s, Bundle e) { }
    @Override public void onProviderEnabled(String p) { }
    @Override public void onProviderDisabled(String p) { }
}
