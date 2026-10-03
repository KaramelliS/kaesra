#!/usr/bin/env bash
# API uclarinin sinamasi. Her satir bir beklenti; sapan kirmizi doner.
#
#   ./api-sina.sh                 # varsayilan http://127.0.0.1:8130
#   KOK=http://sunucun:8130 ./api-sina.sh
#
# Neden ayri bir betik: bu kontrolleri elle curl'lemek her degisiklikten
# sonra on dakika suruyordu ve yanlislikla yalnizca mutlu yolu deniyordum.
# Hata dallari burada mutlu yol kadar yer kapliyor cunku asil kiriliyor
# olan onlar.
set -u

KOK="${KOK:-http://127.0.0.1:8130}"

gecen=0
kalan=0

# Nonce her cagride yeni olmali.
#
# Ilk hali printf '%032x' ile bash aritmetigi kullaniyordu ve sayi 64 biti
# asinca "Numerical result out of range" verip AYNI degeri donduruyordu —
# yani tekrar korumasini sinamaya calisirken tekrar uretiyordum. API dogru
# davranip 409 dondu; hatali olan sinamaydi. /dev/urandom tasmiyor.
nonce() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 16
    else
        od -An -N16 -tx1 /dev/urandom | tr -d ' \n'
    fi
}

# bekle <aciklama> <beklenen kod> <curl argumanlari...>
bekle() {
    local aciklama="$1" beklenen="$2"; shift 2
    local kod
    kod=$(curl -s -o /tmp/kaesra-yanit.json -w '%{http_code}' "$@")

    if [ "$kod" = "$beklenen" ]; then
        printf '  \033[32mTAMAM\033[0m %-3s %s\n' "$kod" "$aciklama"
        gecen=$((gecen + 1))
    else
        printf '  \033[31mSAPMA\033[0m %-3s (beklenen %s) %s\n' "$kod" "$beklenen" "$aciklama"
        head -c 220 /tmp/kaesra-yanit.json 2>/dev/null | sed 's/^/        /'
        echo
        kalan=$((kalan + 1))
    fi
}

ZAMAN=$(date +%s)

echo "== kimlik ve tekrar korumasi =="
#
# Bu bolum eskiden api-lisans.php'yi siniyordu. Satis/lisans sistemi bu
# surumde kaldirildi; ama ayni guvenlik dallari (paylasilan anahtar, zaman
# penceresi, nonce tekrari) artik api-senkron.php uzerinde yasiyor ve
# SINANMALARI GEREKIYOR: bir korumanin varligi ancak kirilmayi deneyerek
# gosterilebilir. Mutlu yolun gecmesi tek basina bir sey kanitlamiyor.

# Alan dogrulamalarindan gecen, yazmaya uygun en kucuk govde.
GECERLI='{"port":27015,"oturum":"sinama1","parti":500,"gecen":60,"harita":"de_dust2","oyuncular":[{"kimlik":"STEAM_0:1:492817364","ad":"Sinama","kill":1,"death":1,"atis":10,"isabet":3}]}'

bekle "GET reddediliyor (405)" 405 -X GET "$KOK/api-senkron.php"




bekle "tekrar basliklari eksik (400)" 400 -X POST "$KOK/api-senkron.php"         -H 'Content-Type: application/json' -d "$GECERLI"

bekle "zaman damgasi kaymis (400)" 400 -X POST "$KOK/api-senkron.php"         -H "X-Kaesra-Zaman: $((ZAMAN - 4000))" -H "X-Kaesra-Tek: $(nonce)"     -H 'Content-Type: application/json' -d "$GECERLI"

bekle "gecersiz port (422)" 422 -X POST "$KOK/api-senkron.php"         -H "X-Kaesra-Zaman: $ZAMAN" -H "X-Kaesra-Tek: $(nonce)"     -H 'Content-Type: application/json' -d '{"port":99999,"oturum":"sinama1","parti":501,"gecen":60,"oyuncular":[]}'

# Nonce tekrari: ayni degerle iki istek. Ikincisi 409 almali.
TEKRAR=$(nonce)
bekle "nonce ilk kullanim (200)" 200 -X POST "$KOK/api-senkron.php"         -H "X-Kaesra-Zaman: $ZAMAN" -H "X-Kaesra-Tek: $TEKRAR"     -H 'Content-Type: application/json' -d "$GECERLI"

bekle "ayni nonce tekrar (409)" 409 -X POST "$KOK/api-senkron.php"         -H "X-Kaesra-Zaman: $ZAMAN" -H "X-Kaesra-Tek: $TEKRAR"     -H 'Content-Type: application/json' -d "$GECERLI"


echo "== senkron ucu =="

gonder() {   # gonder <parti> <govde-json>
    curl -s -o /tmp/kaesra-yanit.json -w '%{http_code}' -X POST "$KOK/api-senkron.php" \
        \
        -H "X-Kaesra-Zaman: $(date +%s)" -H "X-Kaesra-Tek: $(nonce)" \
        -H 'Content-Type: application/json' -d "$2"
}

bekle "bozuk JSON (400)" 400 -X POST "$KOK/api-senkron.php" \
    \
    -H "X-Kaesra-Zaman: $ZAMAN" -H "X-Kaesra-Tek: $(nonce)" \
    -H 'Content-Type: application/json' -d '{"port":27015,'

bekle "negatif sayac (422)" 422 -X POST "$KOK/api-senkron.php" \
    \
    -H "X-Kaesra-Zaman: $ZAMAN" -H "X-Kaesra-Tek: $(nonce)" \
    -H 'Content-Type: application/json' \
    -d '{"port":27015,"oturum":"sinama1","parti":90,"gecen":60,"harita":"de_dust2","oyuncular":[{"kimlik":"STEAM_0:1:11","kill":-3}]}'

bekle "bilinmeyen silah (422)" 422 -X POST "$KOK/api-senkron.php" \
    \
    -H "X-Kaesra-Zaman: $ZAMAN" -H "X-Kaesra-Tek: $(nonce)" \
    -H 'Content-Type: application/json' \
    -d '{"port":27015,"oturum":"sinama1","parti":91,"gecen":60,"harita":"de_dust2","oyuncular":[{"kimlik":"STEAM_0:1:11","kill":1,"silahlar":{"lazer_topu":1}}]}'

bekle "akil disi kill (422)" 422 -X POST "$KOK/api-senkron.php" \
    \
    -H "X-Kaesra-Zaman: $ZAMAN" -H "X-Kaesra-Tek: $(nonce)" \
    -H 'Content-Type: application/json' \
    -d '{"port":27015,"oturum":"sinama1","parti":92,"gecen":45,"harita":"de_dust2","oyuncular":[{"kimlik":"STEAM_0:1:11","kill":9999}]}'

bekle "hs > kill (422)" 422 -X POST "$KOK/api-senkron.php" \
    \
    -H "X-Kaesra-Zaman: $ZAMAN" -H "X-Kaesra-Tek: $(nonce)" \
    -H 'Content-Type: application/json' \
    -d '{"port":27015,"oturum":"sinama1","parti":93,"gecen":60,"harita":"de_dust2","oyuncular":[{"kimlik":"STEAM_0:1:11","kill":2,"hs":5}]}'

bekle "bozuk kimlik (422)" 422 -X POST "$KOK/api-senkron.php" \
    \
    -H "X-Kaesra-Zaman: $ZAMAN" -H "X-Kaesra-Tek: $(nonce)" \
    -H 'Content-Type: application/json' \
    -d '{"port":27015,"oturum":"sinama1","parti":94,"gecen":60,"harita":"de_dust2","oyuncular":[{"kimlik":"benim adim ahmet","kill":1}]}'

PARTI='{"port":27015,"oturum":"sinama1","parti":1,"gecen":120,"harita":"de_dust2","oyuncular":[
  {"kimlik":"STEAM_0:1:492817364","ad":"efeliman","ulke":"tr","ajan":"Jett",
   "kill":8,"death":4,"asist":2,"hs":5,"hasar":1240,"atis":210,"isabet":64,
   "kurma":1,"cozme":0,"mvp":2,"kazanilan":7,"kaybedilen":3,"saniye":120,
   "silahlar":{"ak47":6,"deagle":2}},
  {"kimlik":"VALVE_0:0:77123","ad":"nonsteam kanka","ulke":"de",
   "kill":3,"death":9,"asist":1,"hs":1,"hasar":480,"atis":150,"isabet":22,
   "kurma":0,"cozme":1,"mvp":0,"kazanilan":3,"kaybedilen":7,"saniye":120,
   "silahlar":{"m4a1":3}}]}'

kod=$(gonder 1 "$PARTI")
if [ "$kod" = "200" ]; then
    printf '  \033[32mTAMAM\033[0m 200 gecerli parti islendi\n'; gecen=$((gecen + 1))
    cat /tmp/kaesra-yanit.json | sed 's/^/        /' | head -c 400; echo
else
    printf '  \033[31mSAPMA\033[0m %s gecerli parti\n' "$kod"; head -c 300 /tmp/kaesra-yanit.json; kalan=$((kalan + 1))
fi

kod=$(gonder 1 "$PARTI")
if [ "$kod" = "200" ] && grep -q "zaten_islendi" /tmp/kaesra-yanit.json; then
    printf '  \033[32mTAMAM\033[0m 200 ayni parti ikinci kez -> zaten_islendi (idempotent)\n'; gecen=$((gecen + 1))
else
    printf '  \033[31mSAPMA\033[0m %s idempotency calismadi\n' "$kod"; head -c 300 /tmp/kaesra-yanit.json; kalan=$((kalan + 1))
fi

echo
printf 'gecen %d, sapan %d\n' "$gecen" "$kalan"
[ "$kalan" -eq 0 ]
