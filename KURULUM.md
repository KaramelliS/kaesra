# Kurulum

İki parça var: **web servisi** (PHP) ve **oyun sunucusu** (HLDS + AMX Mod X).
İkisi de aynı makinede olabilir; `kaesra_api` varsayılanı zaten
`http://127.0.0.1:8130`.

Sıra önemli değil, ama **doğrulama** için ikisi de çalışıyor olmalı.

---

## 0. Ön koşul: HLDS

Kurulum betiği HLDS'in kendisini **kurma**z. Önce SteamCMD ile:

```bash
steamcmd +login anonymous +force_install_dir ./hlds \
  +app_set_config 90 mod cstrike \
  +app_update 90 -beta steam_legacy validate +quit
```

`steam_legacy` dalı önemli: ana dal SDL3 bekleyen bir `hlds.exe` ile
geliyor ve bu kurulumda çalışmıyor.

---

## 1. Web servisi

```bash
cd sunucu
php anahtar-uret.php
```

Çıktıyı bir yere yazın — adım 3'te gerekecek. Betik anahtarı
`sunucu/veri/anahtar.txt` dosyasına `0600` izinleriyle koyuyor.

### Geliştirme için

```bash
php -S 127.0.0.1:8130
```

### Üretim için

PHP'nin kendi sunucusu tek iş parçacıklıdır; gerçek sunucuda
**kullanmayın**. Apache veya nginx + PHP-FPM:

```apache
# Apache
DocumentRoot /var/www/kaesra/sunucu
<Directory /var/www/kaesra/sunucu>
    Options -Indexes
    Require all granted
</Directory>

# veri/ klasörüne dışarıdan erişim ENGELLENMELİ — içinde anahtar.txt var.
<Directory /var/www/kaesra/sunucu/veri>
    Require all denied
</Directory>
```

```nginx
# nginx
root /var/www/kaesra/sunucu;
index index.php;
location ~ ^/veri/ { deny all; }
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

> **`veri/` klasörünü web'den kapatmak zorunlu.** İçinde paylaşılan anahtar
> ve tüm oyuncu istatistikleri duruyor. nginx/Apache yapılandırmasında
> `deny all` satırını atlamak, anahtarı dünyaya açmak demek.

---

## 2. Oyun sunucusu

### Windows

```powershell
cd kurulum
.\kurulum.ps1 -Hedef "C:\Games\CS 1.6\cstrike" -Botlar
```

| Parametre | Ne yapar |
|---|---|
| `-Hedef` | **zorunlu.** `cstrike` klasörünün yolu (`liblist.gam` orada olmalı) |
| `-Valve` | `valve` klasörü; verilmezse hedefin üst klasörü sayılır |
| `-Botlar` | `BotProfile.db` + `bot_enable 1` (test sunucusu için) |
| `-YalnizKontrol` | hiçbir dosyaya dokunmadan önbelleği raporlar |
| `-AtlaIndirme` | ağa çıkmadan önbellektekileri kullanır |

Betik şunları yapar:

1. 7 paketi GitHub Releases'tan indirir (`kurulum/onbellek/`, ~12 MB)
2. ReHLDS'ten **yalnız `swds.dll`** kopyalar → `valve/`
3. ReGameDLL'den `mp.dll` / `cs.dll` → `cstrike/dlls/`
4. Metamod-R → `cstrike/addons/metamod/` ve `liblist.gam` içindeki
   `gamedll` satırını çevirir (yedek alır: `liblist.gam.kaesra-yedek`)
5. AMX Mod X base + cstrike, ReAPI, AmxxEasyHttp → `valve/addons/`
6. `kaesra.sma`'yı derler, `kaesra.cfg`'yi koyar, `plugins.ini` /
   `modules.ini` / `server.cfg` satırlarını ekler

`ExecutionPolicy` engeline takılırsanız:

```powershell
powershell -ExecutionPolicy Bypass -File .\kurulum.ps1 -Hedef "..."
```

### Linux

Kurulum betiğinin Linux sürümü henüz yok (yol haritasında). Elle:

```bash
cd kurulum
# sürümler surumler.ini içinde; etiketleri oradan okuyun
BASE=https://github.com
wget $BASE/rehlds/ReHLDS/releases/download/3.15.0.896/rehlds-bin-3.15.0.896.zip
wget $BASE/rehlds/ReGameDLL_CS/releases/download/5.30.0.814/regamedll-bin-5.30.0.814.zip
wget $BASE/rehlds/Metamod-R/releases/download/1.3.0.149/metamod-bin-1.3.0.149.zip
wget $BASE/alliedmodders/amxmodx/releases/download/1.10.0.5486/amxmodx-1.10.0-git5486-base-linux.tar.gz
wget $BASE/alliedmodders/amxmodx/releases/download/1.10.0.5486/amxmodx-1.10.0-git5486-cstrike-linux.tar.gz
wget $BASE/rehlds/ReAPI/releases/download/5.29.0.358/reapi-bin-5.29.0.358.zip
wget $BASE/Next21Team/AmxxEasyHttp/releases/download/1.5.0/AmxxEasyHttp-1.5.0-linux-i386.zip

# HLDS'in kökünde (valve/ ve cstrike/ kardeş olmalı):
unzip -o rehlds-bin-*.zip      'bin/linux32/engine_i486.so' -d tmp/ && cp tmp/bin/linux32/engine_i486.so .
unzip -o regamedll-bin-*.zip   'bin/linux32/cstrike/cs.so'    -d tmp/ && cp tmp/bin/linux32/cstrike/cs.so cstrike/dlls/
unzip -o metamod-bin-*.zip     'addons/metamod/dlls/metamod_i386.so' -d cstrike/
tar xzf amxmodx-*-base-linux.tar.gz   -C .
tar xzf amxmodx-*-cstrike-linux.tar.gz -C cstrike/
unzip -o reapi-bin-*.zip       -d .
unzip -o AmxxEasyHttp-*-linux-i386.zip -d .

# liblist.gam
sed -i 's|^gamedll_linux.*|gamedll_linux "addons/metamod/metamod_i386.so"|' cstrike/liblist.gam

# Kaesra
cp ../oyun/kaesra.sma  cstrike/addons/amxmodx/scripting/
cp ../oyun/kaesra.cfg  cstrike/addons/amxmodx/configs/
cd cstrike/addons/amxmodx/scripting && ./amxxpc kaesra.sma -o../plugins/kaesra.amxx
echo kaesra.amxx >> ../configs/plugins.ini
```

---

## 3. Anahtarı iki tarafa yazın

`php anahtar-uret.php` çıktısını şuraya da yazın:

```
cstrike/addons/amxmodx/configs/kaesra.cfg
  kaesra_anahtar "<64 haneli değer>"
```

Sonra haritayı yenileyin (`changelevel de_dust2`) ve konsolda:

```
kaesra_durum
```

`paylasilan anahtar: ayarli` görmelisiniz.

> Anahtarı yenilemek: `php anahtar-uret.php --zorla` — sonra `kaesra.cfg`'yi
> de güncellemeyi unutmayın, yoksa API 401 döner.

---

## Kurulum tuzakları

Hepsi denendi ve ölçüldü. Kurulum betiği ilk dördünü otomatik uyguluyor;
beşincisi sıra ile ilgili, betik uygulayamaz.

### 1. ReHLDS'in `hlds.exe`'sini kopyalamayın

Pakette `bin/win32/` altında tam bir ikili takım var ama **yalnız `swds.dll`
gerekli**. `hlds.exe`'yi de kopyalarsanız sunucu

```
Assertion Failed: Failed to load "SDL3.dll"
```

deyip donuyor — Steam'in `steam_legacy` dalı SDL2 ile geliyor.

### 2. `steamcmd validate` her şeyi geri alır

Doğrulama, değiştirilmiş dosyaları varsayılana çeviriyor. Validate'ten sonra
**hem** `liblist.gam`'daki `gamedll` satırını **hem** ReGameDLL'i yeniden
uygulayın. En kolay yol: kurulum betiğini tekrar çalıştırmak.

### 3. ZBot varsayılan kapalı

`game_init.cfg` içinde `bot_enable "0"` duruyor. 1 yapılmadan `bot_quota`
cvar'ı bile kaydolmuyor ve rcon komuta hiç yanıt vermiyor — bilinmeyen komut
hatası da basmıyor, sadece sessizlik. `-Botlar` bunu açıyor.

### 4. `BotProfile.db` ayracı BOŞLUK

Profil satırı `Şablon+Şablon isim` biçiminde ve aradaki ayraç **boşluk**
olmalı. Sekme kullanınca:

```
Error parsing BotProfile.db - expected '='
```

ve tek bir bot bile girmiyor. Ayrıca `AimFocusInitial` ve kardeşleri bu
yapıda **yok** (CS:CZ'den geliyorlar) ve `bot_difficulty` hangi kademeyi
istiyorsa profil o kademede olmak zorunda.

`kurulum/BotProfile.db` Valve'ın dosyası değil, elle yazıldı: 14 bot,
becerileri 25–100 arasında dağıtıldı ki K/D oranları birebir aynı çıkmasın.

### 5. Sıra: önce HLDS, sonra istemci

Revolution emülatörü kullanan bir CS 1.6 istemcisini (ör. `csduragics16`)
**önce** açmak, `HKCU\Software\Valve\Steam\ActiveProcess` altındaki
`SteamClientDll` değerini kendi `steamclient.dll`'ine çeviriyor. HLDS bu
anahtarı **yalnız açılışta** okuyor ve ya

```
FATAL ERROR: Unable to initialize Steam
```

veriyor ya da sessizce asılı kalıyor — port'a bağlanıyor ama A2S sorgusuna
bile cevap vermiyor, "Server IP address" satırını hiç basmıyor.

Anahtar bozulduysa Steam istemcisini bir kez açmak onarıyor (açılışta kendi
pid'ini ve kendi dll'ini yazıyor).

---

## Sorun giderme

### API her isteği 401 ile çeviriyor

İki taraf farklı anahtara bakıyor. Kontrol:

```bash
cat sunucu/veri/anahtar.txt
grep kaesra_anahtar cstrike/addons/amxmodx/configs/kaesra.cfg
```

İkisi **birebir** aynı olmalı. Baştaki/sondaki boşluk en sık sebep —
`hash_equals` karşılaştırması kırpma yapmıyor.

### 503 `Anahtar uretilmemis`

`php anahtar-uret.php` çalıştırılmamış. Dosya `sunucu/veri/anahtar.txt`.

### 400 `Zaman damgasi pencere disinda`

Oyun sunucusunun saati 300 saniyeden fazla kaymış. `kaesra_anahtar` doğru
bile olsa istek reddedilir. NTP'yi düzeltin.

### 422 `Alanlar gecersiz` — `bilinmeyen_silah`

Eklentinin gönderdiği silah adı temanın `silahEsleme` tablosunda yok.
Bomba/el bombası/duman öldürmeleri **bilerek** listede değil ve eklenti
bunları süzüyor; süzgeç ile tema ayrışırsa bütün parti düşer. Tema
değiştirdiyseniz `oyun/kaesra.sma` içindeki `BILINEN_SILAHLAR` listesini de
güncelleyin.

### Veri gidiyor ama MOTD boş

`kaesra_api` yanlış ya da MOTD penceresi erişemiyor. MOTD **istemcinin**
açtığı bir sayfadır: `127.0.0.1` yalnız sunucu ile istemci aynı makinedeyken
çalışır. Gerçek oyuncular için dışarıdan erişilebilir bir adres yazın.

### Sohbet Türkçe karakterleri bozuk

`kaesra_sohbet_kodlama` yanlış. Üç kip var:

| Değer | Ne zaman |
|---|---|
| `0` UTF-8 | yeni istemciler (GoldSrc UTF-8 yaması, NextClient) |
| `1` CP1254 | eski/vanilla istemci — Türkçe tek bayt çizilir |
| `2` ASCII | Türkçe karakterleri tamamen atar, en güvenli |

`kaesra_durum` hangi kipin etkin olduğunu söylüyor.

### ReAPI yüklenmiyor

Neredeyse her zaman ReGameDLL sürüm uyuşmazlığı. `kurulum/surumler.ini`
içindeki ikiliyi kullanın: ReAPI **5.29.0.358** ↔ ReGameDLL_CS **5.30.0.814**.

---

## Test

```bash
cd sunucu
php -S 127.0.0.1:8130 &

# sayfalar
for s in siralama rutbeler silahlar haritalar oyuncu karsilastir; do
  printf "%-14s %s\n" "$s" "$(curl -s -o /dev/null -w '%{http_code}' \
    "http://127.0.0.1:8130/$s-motd.php")"
done

# API uçtan uca
bash test/api-sina.sh
```

`test/api-sina.sh` mutlu yolu **ve** hata dallarını sınar: yanlış anahtar
401, kaymış saat 400, tekrarlanan nonce 409, bilinmeyen silah 422,
`isabet > atis` kırpması.
