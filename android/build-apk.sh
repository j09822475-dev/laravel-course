#!/usr/bin/env bash
#
# Сборка APK без Android Studio и Gradle — только на инструментах из пакетов
# дистрибутива: aapt2, javac, dalvik-exchange (dx), zipalign, apksigner.
#
#   sudo apt-get install -y aapt apksigner zipalign dalvik-exchange \
#        android-sdk-platform-23 android-sdk-build-tools
#   ./build-apk.sh
#
# На выходе: build/trainer.apk — установочный файл для телефона.
set -euo pipefail

cd "$(dirname "$0")"

# JAVA_TOOL_OPTIONS из окружения засоряет вывод и прячет настоящие ошибки.
unset JAVA_TOOL_OPTIONS

ANDROID_JAR=${ANDROID_JAR:-/usr/lib/android-sdk/platforms/android-23/android.jar}
KEYSTORE=${KEYSTORE:-keystore/debug.keystore}
KEY_ALIAS=${KEY_ALIAS:-trainer}
KEY_PASS=${KEY_PASS:-android}
BUILD=build
OUT=$BUILD/trainer.apk

[ -f "$ANDROID_JAR" ] || { echo "Не найден android.jar: $ANDROID_JAR"; exit 1; }

rm -rf "$BUILD"
mkdir -p "$BUILD/res" "$BUILD/classes" "$BUILD/gen" keystore

echo "1/6 Ресурсы"
aapt2 compile --dir app/res -o "$BUILD/res.zip" >/dev/null
aapt2 link \
    -I "$ANDROID_JAR" \
    --manifest app/AndroidManifest.xml \
    --java "$BUILD/gen" \
    -o "$BUILD/base.apk" \
    "$BUILD/res.zip" >/dev/null

echo "2/6 Компиляция Java"
javac -nowarn -source 8 -target 8 \
    -bootclasspath "$ANDROID_JAR" \
    -classpath "$ANDROID_JAR" \
    -d "$BUILD/classes" \
    $(find app/src "$BUILD/gen" -name '*.java')

echo "3/6 Преобразование в dex"
dalvik-exchange --dex --output="$BUILD/classes.dex" "$BUILD/classes"

echo "4/6 Упаковка"
(cd "$BUILD" && aapt add base.apk classes.dex >/dev/null)

echo "5/6 Выравнивание"
zipalign -f -p 4 "$BUILD/base.apk" "$BUILD/aligned.apk"

echo "6/6 Подпись"
if [ ! -f "$KEYSTORE" ]; then
    # Отладочный ключ создаётся локально и не попадает в репозиторий.
    # Для публикации в Google Play нужен собственный ключ, который нельзя терять.
    keytool -genkeypair -v \
        -keystore "$KEYSTORE" -alias "$KEY_ALIAS" \
        -keyalg RSA -keysize 2048 -validity 10000 \
        -storepass "$KEY_PASS" -keypass "$KEY_PASS" \
        -dname "CN=Laravel Interview Trainer, OU=Dev, O=Trainer, L=-, S=-, C=RU" >/dev/null 2>&1
fi

apksigner sign \
    --ks "$KEYSTORE" --ks-key-alias "$KEY_ALIAS" \
    --ks-pass "pass:$KEY_PASS" --key-pass "pass:$KEY_PASS" \
    --out "$OUT" "$BUILD/aligned.apk"

apksigner verify --print-certs "$OUT" | head -3
echo
echo "Готово: $OUT ($(du -h "$OUT" | cut -f1))"
