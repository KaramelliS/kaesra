#!/usr/bin/env bash
# Test sunucusunu başlatır ve gerçekten ayağa kalktığını doğrular.
#
# Neden döngü: Steam istemcisi açıkken HLDS'in Steam ilklendirmesi bazen
# "FATAL ERROR: Unable to initialize Steam" ile düşüyor, bazen geçiyor. Dört
# denemenin biri geçti. Kapanan süreç UDP 27015'i bir süre bırakmıyor, o
# yüzden her denemeden önce kalıntı temizleniyor.
#
#   ./sunucu-baslat.sh            # varsayılan 5 deneme
#   DENEME=2 ./sunucu-baslat.sh
set -u

KOK="$(cd "$(dirname "$0")" && pwd)"
DENEME="${DENEME:-5}"

kalintiyiTemizle() {
    powershell -NoProfile -Command \
        "Get-CimInstance Win32_Process -Filter \"Name='hlds.exe'\" | ForEach-Object { Invoke-CimMethod -InputObject \$_ -MethodName Terminate | Out-Null }" \
        >/dev/null 2>&1
    sleep 4
}

for ((i = 1; i <= DENEME; i++)); do
    kalintiyiTemizle

    if netstat -ano | grep -q ':27015 '; then
        echo "[$i/$DENEME] port 27015 hala dolu, bekleniyor"
        sleep 8
    fi

    # "+exec server.cfg" açıkça veriliyor: bu kurulumda HLDS onu kendiliğinden
    # çalıştırmıyor (konsol günlüğünde yalnız ReGameDLL'in dosyası görünüyor),
    # dolayısıyla rcon_password ayarsız kalıyor ve rcon challenge dönmüyor.
    ( cd "$KOK/hlds" && ./hlds.exe -console -condebug -game cstrike -insecure \
        -port 27015 +maxplayers 16 +map de_dust2 +exec server.cfg >/dev/null 2>&1 ) &

    for ((s = 0; s < 12; s++)); do
        sleep 4
        if php "$KOK/rcon.php" "status" 2>/dev/null | grep -q "hostname"; then
            echo "[$i/$DENEME] sunucu ayakta"
            exit 0
        fi
    done

    echo "[$i/$DENEME] kalkmadi, tekrar deneniyor"
done

echo "sunucu $DENEME denemede kalkmadi; Steam istemcisi acikken HLDS'in"
echo "Steam ilklendirmesi tutmuyor olabilir."
exit 1
