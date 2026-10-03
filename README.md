# Kaesra

**Counter-Strike 1.6 için rank ve istatistik sistemi.** Oyuncu öldürür,
asist yapar, bomba kurar, tur kazanır; Kaesra bunları toplar, sunucu
tarafında puana çevirir ve oyun içinde MOTD sayfaları olarak gösterir.

ReAPI hook zincirleri + AmxxEasyHttp ile çalışır, `csstats` modülüne
bağımlılığı yoktur. Web tarafı PHP 8 ve tek bir JSON dosyası — MySQL
şeması hazır ama varsayılan kurulumda veritabanı istemez.

---

## Neye benziyor

Oyun içi komutlar GoldSrc'in MOTD penceresinde HTML sayfa açıyor. Altı sayfa var:

| Komut | Sayfa | İçerik |
|---|---|---|
| `/rank`, `/kaesra` | profil | rütbe, KP, K/D, isabet, silah ve harita kırılımı |
| `/top`, `/siralama` | sıralama | sunucunun en iyileri, arama kutusu, sayfalama |
| `/rutbeler`, `/merdiven` | merdiven | 25 kademe, eşikler, bir üste kaç KP kaldığı |
| `/silahlar` | silahlar | silah başına kill, en çok kullanılan |
| `/haritalar` | haritalar | harita başına süre ve performans |
| `/karsilastir <isim>` | karşılaştırma | iki oyuncu yan yana, ölçüt çubukları |
| `/kp`, `/oturum` | sohbet | hızlı bakış / bu oturumun özeti |

Ayrıca `bind F5 rank` gibi konsol bağlamaları çalışıyor — tur ortasında
sohbeti açmak gerekmeyebilsin diye.

> Ekran görüntüleri: Valorant temalı eski sürümden kalan görüntüler Riot
> Games'in sanatını ve eski bir sunucunun gerçek IP'sini içerdiği için
> **yayınlanmadı**. Jenerik temayla yakalanmış görüntüler
> `docs/EKRAN-GORUNTULERI.md` içinde — nasıl alındığı da orada yazıyor.

---

## Gereksinimler

| Bileşen | Sürüm | Not |
|---|---|---|
| HLDS (CS 1.6) | app 90, `steam_legacy` | SteamCMD ile |
| ReHLDS | 3.15.0.896 | motor |
| ReGameDLL_CS | 5.30.0.814 | oyun dll'i |
| Metamod-R | 1.3.0.149 | |
| AMX Mod X | 1.10.0-git5486 | base + cstrike |
| ReAPI | 5.29.0.358 | hook zincirleri |
| AmxxEasyHttp | 1.5.0 | HTTP istemcisi |
| PHP | 8.1+ | web tarafı, `mbstring` gerekmez |

**Bu beşli birbirine bağlı.** ReAPI sessizce yüklenmiyorsa sebebi neredeyse
her zaman ReGameDLL sürüm uyuşmazlığıdır. `kurulum/surumler.ini` bu
sürümleri sabitliyor ve kurulum betiği oradan okuyor.

---

## Hızlı başlangıç

### 1. Web servisi

```bash
cd sunucu
php -S 0.0.0.0:8130           # veya Apache/nginx, bkz. KURULUM.md
```

Kurulumda **anahtar, jeton veya token yok.** Web servisi kalktığı anda
hazır; eklenti ona doğrudan yazar.

### 2. Oyun sunucusu

```powershell
cd kurulum
.\kurulum.ps1 -Hedef "C:\Games\CS 1.6\cstrike" -Botlar
```

Betik yedi paketi resmî kaynaklarından indirir, doğru sırayla kurar,
`kaesra.sma`'yı derler, `plugins.ini` / `modules.ini` / `server.cfg`
satırlarını ekler. Linux için bkz. `KURULUM.md`.

### 3. Doğrulayın

Sunucuyu başlatın, konsolda:

```
kaesra_durum
```

`api : http://...` satırını görmelisiniz.

**Kurulum sıfır veriyle başlar ve bu beklenen durumdur:** depo boşken
sayfalar boş durum gösterir ("Henüz kimse yok"), bot gerekmez. İlk gerçek
tur bittiğinde sıralama dolmaya başlar. Tanıtım görüntüsü çekmek için
sahte veri isterseniz `sunucu/yapilandirma.php` içinde `'tohumVeri' => '1'`
yapın, sonra geri kapatın.

**Detaylı kurulum, tuzaklar ve sorun giderme:** [`KURULUM.md`](KURULUM.md)

---

## Mimari

```
oyun/kaesra.sma        eklenti — toplar ve gösterir, HESAPLAMAZ
sunucu/api-senkron.php   delta partilerini alır, KP'yi hesaplar, rütbeyi döner
sunucu/api-oyuncu.php    bağlantı anında rütbe özetini verir
sunucu/*-motd.php        altı MOTD sayfası
sunucu/kaesra-ortak.php   tema, rütbe merdiveni, KP formülü
sunucu/kaesra-depo.php    JSON depo (flock ile), MySQL'e geçiş noktası
sunucu/tema/klasik/      görsel kimlik — kataloğun kendisi
```

### KP ve rütbe oyun sunucusunda hesaplanmıyor

Bu projenin imzası. Eklenti yalnız **ham sayaç** gönderiyor (`kill`, `hs`,
`asist`, `hasar`, `atis`, `isabet`, `kurma`, `cozme`, `mvp`, ...); puan
değeri tek bir yerde, `kaesra-ortak.php → kazanilanPuan()` içinde duruyor.
Üç sonucu var:

- `.sma`'yı düzenleyerek rütbe şişirilemiyor
- Formül değişince kimseye yeni `.amxx` dağıtmak gerekmiyor
- Formül düzeltilince **bütün sıralama geriye dönük yeniden hesaplanıyor**,
  çünkü saklanan şey puan değil ham sayaç

### Delta gönderimi

Tur sonunda sayaçlar **fark** olarak gidiyor, toplam olarak değil. Ağ
koptuğunda eklenti partiyi kuyrukta tutuyor ve tekrar gönderiyor; API
`(sunucu, oturum, parti)` üçlüsüyle idempotency sağlıyor, yani aynı parti
iki kez yazılmıyor. Nonce + 300 saniyelik zaman penceresi tekrar
saldırısını kesiyor.

### Ölçülmüş kurallar

Kodda `ÖLÇÜLDÜ` diye işaretlenmiş yorumlar tahmin değil, denemeyle
bulunmuş GoldSrc davranışları. En önemlileri:

1. MOTD penceresi bir kez açıldıktan sonra ikinci `show_motd` onu
   **gezdirmiyor**; eski sayfa kalıyor. Tek yol `cancelselect` ile kapatıp
   ~1 sn sonra tek seferde açmak.
2. Sayfa agresif önbellekleniyor; adrese zaman damgası şart.
3. `RG_CBasePlayer_Killed`'ın `pevAttacker` parametresi pratikte **entity
   indeksi** olarak geliyor, pev değil.
4. Cvar'lar `plugin_init`'te değil `plugin_cfg`'de okunmalı — ama
   `plugin_cfg` de `server.cfg`'den **önce** çalışıyor, o yüzden ayarlar
   üç saniye sonra bir kez daha okunuyor.
5. `ezjson`'da geçersiz tanıtıcı `EzInvalid_JSON` yani **-1**. Sıfır
   **geçerli** bir tanıtıcı; `== 0` ile hata arayan kod her başarılı
   çözümlemeyi hata sanıyor.
6. `amxxpc` kaynaktaki UTF-8 baytlarını olduğu gibi bırakıyor. Sohbet tek
   baytlık çizildiği için çıkışta çevriliyor; bkz. `SohbetKodla` ve
   `kaesra_sohbet_kodlama`.

---

## Tema sistemi

Rütbe adları, silah adları, renkler ve görsellerin **hepsi** tek klasörde:
`sunucu/tema/<ad>/katalog.php`. Sayfalarda sabit metin yok.

Varsayılan tema **klasik**: 25 kademe (Acemi 1 → Zirve), CS 1.6'nın kendi
silah adları, **hiç görsel yok**. Sıfır ek dosya, sıfır telif riski.

Kendi temanızı yapmak: [`sunucu/tema/README.md`](sunucu/tema/README.md)

---

## Ayarlar

`cstrike/addons/amxmodx/configs/kaesra.cfg`:

| Cvar | Varsayılan | Açıklama |
|---|---|---|
| `kaesra_api` | `http://127.0.0.1:8130` | web servisi kök adresi, sonda `/` olmadan |
| `kaesra_gunluk` | `0` | 1 ise her komut ve parti gövdesi konsola yazılır |
| `kaesra_sohbet_etiketi` | `1` | sohbette oyuncu adının önünde rütbe |
| `kaesra_sohbet_kodlama` | `0` | 0 UTF-8 · 1 CP1254 (eski istemci) · 2 ASCII |
| `kaesra_botlari_say` | `0` | bot istatistiği tutulsun mu (yalnız test) |
| `kaesra_uzatma` | `1` | beraberlikte maç uzasın mı |

Sunucu komutları (rcon): `kaesra_durum`, `kaesra_yenile`,
`kaesra_goster <ad> <komut>`, `kaesra_istemci <ad> <komut>`.

---

## Bilinen sınırlar

Dürüst liste — bunlar gizlenmiş değil:

- **Depo tek bir JSON dosyası.** `flock` ile doğru çalışıyor ama her yazımda
  dosyanın tamamı yeniden yazılıyor. ~30 sunucu üstünde tıkanır. MySQL şeması
  `kaesra-depo.php` başında belgelendi; geçişte yalnız o dosyanın gövdesi
  değişecek, API uçlarına dokunulmayacak.
- **Sıralama indekssiz.** Her çağrıda tüm oyuncular geziliyor. 16 oyuncuda
  bedava, 20.000'de değil.
- **MOTD penceresi kaydırılamıyor.** GoldSrc'in HTML denetimi fare tekerini
  iletmiyor. Bütün sayfalar bu yüzden 860×550 hedef ölçüye **sığdırıldı** ve
  gezinme şeritleri listenin üstünde.
- **Bot kimliği `BOT`.** `get_user_authid()` botlar için `STEAM_0:...`
  döndürmüyor, yani botlarla gerçek kimlik ayrıştırması test edilemiyor.
- **Ülke bayrağı IP'den değil.** `geoip_code2_ex()` yolu kodda hazır ama
  bağlantı kurulmadı; şu an tohum veriden geliyor.

---

## Lisans

**CC BY-NC-SA 4.0** — Telif (c) 2026 Berkay. Tüm hakları saklıdır.

| | |
|---|---|
| ✅ **Serbest** | Kullanmak, değiştirmek, paylaşmak. **Para kazanan bir sunucuda çalıştırmak da serbest** — VIP/admin satan sunucu dahil. |
| ❌ **Yasak** | Satmak, ücretli pakete koymak, kendi eserin gibi göstermek, atıfı silmek, "Kaesra" adını/logosunu kullanmak. |
| 🔁 **ShareAlike** | Türevler de aynı lisansla yayınlanmak zorunda. |

Ayrıntı, atıf biçimi ve marka koşulları: **[`NOTICE`](NOTICE)**
Tam lisans metni: **[`LICENSE`](LICENSE)**

Ticari dağıtım (ücretli paket, kapalı kaynak türev) için ayrı lisans
gerekir — bkz. `NOTICE` §7.

Üçüncü taraf bileşenler (AMX Mod X, ReHLDS, ReGameDLL, ReAPI, Metamod-R,
AmxxEasyHttp) **bu depoda değildir**; kurulum betiği onları resmî
kaynaklarından indirir ve kendi GPL lisansları geçerlidir. `sunucu/font/`
içindeki Anton ve Barlow yazı tipleri SIL OFL 1.1 ile dahildir, lisans
metinleri aynı klasördedir.

---

## Katkı

`docs/KATKI.md` — kod stili, commit biçimi ve "ölçülmüş kural" yorumlarının
neden zorunlu olduğu.

Kısa özet: bu projede tahmin ile ölçüm birbirinden ayrılıyor. Bir davranışı
deneyerek bulduysanız yorumda `ÖLÇÜLDÜ` diye işaretleyin ve **ne denendiğini**
yazın; sonraki kişi aynı bir saati harcamasın.

---

## Yol haritası

- [ ] MySQL deposu (`kaesra-depo.php` sözleşmesi zaten buna göre)
- [ ] Sezon sıfırlama ve arşiv
- [ ] `geoip` ile gerçek ülke bayrağı
- [ ] Linux kurulum betiği (`kurulum.sh`)
- [ ] Admin paneli (clan/lisans yönetimi olmadan, yalnız istatistik görüntüleme)
- [ ] Botlar için nav dosyaları — bomba kurma/çözme senaryosu test edilebilsin
