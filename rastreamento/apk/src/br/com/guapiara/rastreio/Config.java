package br.com.guapiara.rastreio;

final class Config {
    // Realtime Database do projeto rastreioguapiara
    static final String DB_URL = "https://rastreioguapiara-default-rtdb.europe-west1.firebasedatabase.app";
    static final String GEOFIRE = "geofire";        // {g: geohash, l: [lat, lng]}
    static final String META = "motoristas";        // {nome, hora}
    static final long INTERVALO_MS = 10000;         // pede posição a cada 10 s
    static final float DISTANCIA_M = 10;            // ou a cada 10 m

    // Telefone só com dígitos, sem 55 e sem zero na frente: (15) 99999-8888 -> 15999998888
    static String normalizar(String tel) {
        String d = tel == null ? "" : tel.replaceAll("\\D", "");
        if (d.startsWith("55") && d.length() >= 12) d = d.substring(2);
        while (d.startsWith("0")) d = d.substring(1);
        return d;
    }
}
