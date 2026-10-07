#!/bin/sh
# Compila o APK sem Gradle. Requer: JDK, aapt, dalvik-exchange (dx), zipalign, apksigner
# e o android.jar da API 30 (ANDROID_JAR).
set -e
cd "$(dirname "$0")"
ANDROID_JAR=${ANDROID_JAR:-$HOME/android/android.jar}
rm -rf build && mkdir -p build/classes build/gen

aapt package -f -m -J build/gen -M AndroidManifest.xml -S res -I "$ANDROID_JAR"
javac -nowarn --release 8 -encoding UTF-8 -classpath "$ANDROID_JAR" -d build/classes \
  $(find src build/gen -name '*.java')
dalvik-exchange --dex --min-sdk-version=23 --output=build/classes.dex build/classes

aapt package -f -M AndroidManifest.xml -S res -I "$ANDROID_JAR" -F build/app.unsigned.apk
(cd build && aapt add -f app.unsigned.apk classes.dex >/dev/null)
zipalign -f 4 build/app.unsigned.apk build/app.aligned.apk

[ -f rastreio.keystore ] || keytool -genkeypair -keystore rastreio.keystore -alias rastreio \
  -storepass rastreio123 -keypass rastreio123 -keyalg RSA -keysize 2048 -validity 10000 \
  -dname "CN=Rastreio Motorista"
apksigner sign --ks rastreio.keystore --ks-pass pass:rastreio123 --key-pass pass:rastreio123 \
  --out ../app-motorista.apk build/app.aligned.apk
echo "APK gerado: rastreamento/app-motorista.apk"
