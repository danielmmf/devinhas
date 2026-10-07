package br.com.guapiara.rastreio;

import android.Manifest;
import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.PowerManager;
import android.provider.Settings;
import android.telephony.TelephonyManager;
import android.text.InputType;
import android.view.Gravity;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

public class MainActivity extends Activity {
    private EditText campoTel, campoNome;
    private Button botao;
    private TextView status;
    private SharedPreferences prefs;

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        prefs = getSharedPreferences("rastreio", MODE_PRIVATE);

        LinearLayout tela = new LinearLayout(this);
        tela.setOrientation(LinearLayout.VERTICAL);
        tela.setGravity(Gravity.CENTER_HORIZONTAL);
        int p = (int) (24 * getResources().getDisplayMetrics().density);
        tela.setPadding(p, p, p, p);

        TextView titulo = new TextView(this);
        titulo.setText("Rastreio Motorista");
        titulo.setTextSize(24);
        tela.addView(titulo);

        campoTel = new EditText(this);
        campoTel.setHint("Seu telefone com DDD");
        campoTel.setInputType(InputType.TYPE_CLASS_PHONE);
        campoTel.setText(prefs.getString("tel", ""));
        tela.addView(campoTel);

        campoNome = new EditText(this);
        campoNome.setHint("Seu nome");
        campoNome.setText(prefs.getString("nome", ""));
        tela.addView(campoNome);

        botao = new Button(this);
        botao.setOnClickListener(new android.view.View.OnClickListener() {
            @Override public void onClick(android.view.View v) { alternar(); }
        });
        tela.addView(botao);

        status = new TextView(this);
        status.setPadding(0, p, 0, 0);
        tela.addView(status);

        setContentView(tela);

        // Pede localização e tenta ler o número do chip
        requestPermissions(new String[]{
                Manifest.permission.ACCESS_FINE_LOCATION,
                Manifest.permission.ACCESS_COARSE_LOCATION,
                Manifest.permission.READ_PHONE_STATE,
                Manifest.permission.READ_PHONE_NUMBERS}, 1);
        if (prefs.getBoolean("ativo", false)) iniciarServico();   // ex.: depois de reiniciar o celular
        atualizarTela();
    }

    @Override
    public void onRequestPermissionsResult(int req, String[] perms, int[] res) {
        if (campoTel.getText().toString().trim().isEmpty()) {
            String doChip = numeroDoChip();
            if (!doChip.isEmpty()) campoTel.setText(doChip);
        }
    }

    @SuppressWarnings({"MissingPermission", "HardwareIds"})
    private String numeroDoChip() {
        try {
            TelephonyManager tm = (TelephonyManager) getSystemService(Context.TELEPHONY_SERVICE);
            String n = tm.getLine1Number();   // muitas operadoras não gravam no chip: pode vir vazio
            return Config.normalizar(n);
        } catch (Exception e) {
            return "";
        }
    }

    private void alternar() {
        if (prefs.getBoolean("ativo", false)) {
            Intent i = new Intent(this, RastreioService.class).setAction(RastreioService.PARAR);
            startService(i);
            prefs.edit().putBoolean("ativo", false).apply();
            atualizarTela();
            return;
        }

        String tel = Config.normalizar(campoTel.getText().toString());
        if (tel.length() < 10) {
            Toast.makeText(this, "Digite o telefone com DDD", Toast.LENGTH_LONG).show();
            return;
        }
        if (checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            Toast.makeText(this, "Permita o acesso à localização", Toast.LENGTH_LONG).show();
            requestPermissions(new String[]{Manifest.permission.ACCESS_FINE_LOCATION}, 1);
            return;
        }

        prefs.edit()
                .putString("tel", tel)
                .putString("nome", campoNome.getText().toString().trim())
                .putBoolean("ativo", true)
                .apply();
        campoTel.setText(tel);

        pedirSemOtimizacaoDeBateria();
        iniciarServico();
        atualizarTela();
    }

    private void iniciarServico() {
        Intent i = new Intent(this, RastreioService.class);
        if (Build.VERSION.SDK_INT >= 26) startForegroundService(i); else startService(i);
    }

    // Sem isso, vários aparelhos matam o serviço depois de alguns minutos com a tela apagada
    private void pedirSemOtimizacaoDeBateria() {
        PowerManager pm = (PowerManager) getSystemService(POWER_SERVICE);
        if (!pm.isIgnoringBatteryOptimizations(getPackageName())) {
            try {
                startActivity(new Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS,
                        Uri.parse("package:" + getPackageName())));
            } catch (Exception ignored) { }
        }
    }

    private void atualizarTela() {
        boolean ativo = prefs.getBoolean("ativo", false);
        botao.setText(ativo ? "Parar rastreamento" : "Iniciar rastreamento");
        campoTel.setEnabled(!ativo);
        campoNome.setEnabled(!ativo);
        status.setText(ativo
                ? "Rastreamento ativo. Pode minimizar o app."
                : "Rastreamento parado.");
    }
}
