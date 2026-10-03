# Katkı

Teşekkürler. Üç kural var, üçü de birer saatlik kaybın tekrarı olmasın diye
yazıldı.

## 1. Tahmin ile ölçümü ayırın

Bu projede bir davranışı **deneyerek** bulduysanız yorumda `ÖLÇÜLDÜ` diye
işaretleyin ve **ne denendiğini** yazın:

```pawn
/*
 * ÖLÇÜLDÜ: MOTD penceresi bir kez açıldıktan sonra ikinci show_motd onu
 * GEZDİRMİYOR; eski sayfa kalıyor, log "açıldı" diyor. Tek yol cancelselect
 * ile kapatıp ~1 sn sonra tek seferde açmak. Araya boş sayfa koymak işe
 * yaramıyor, orada takılıyor.
 */
```

Neden: GoldSrc ve AMXX davranışlarının yarısı belgelerde yazmıyor ve
yanlış. Bir sonraki kişi aynı şeyi tekrar denemesin. "Bence şöyle çalışıyor"
yorumu **kabul edilmiyor** — deneyin, sonra yazın.

## 2. Hata dallarını da sınayın

Bir koruma eklediyseniz `test/api-sina.sh` içine **kırılma** sınaması da
ekleyin. Mutlu yolun geçmesi bir şey kanıtlamıyor; asıl kırılan hata
dalları. Betikte hata dalları mutlu yol kadar yer kaplıyor — bilerek.

```bash
php -S 127.0.0.1:8130 &
bash test/api-sina.sh
```

17 sınamanın hepsi yeşil olmalı.

## 3. Commit biçimi

```
<kapsam>: <ne değişti, tek satır>

<neden — bir cümle yeterli ama yazın>
```

Örnek:

```
senkron: auth gövde doğrulamasından önce çalışıyor

Anahtarı olmayan biri 422 yanıtlarından API şemasını yoklayabiliyordu.
```

Kapsamlar: `oyun`, `senkron`, `motd`, `tema`, `kurulum`, `test`, `docs`.

## Kod stili

- **Pawn:** fonksiyon adları `BuyukHarfBaslar`, değişkenler `kucukHarf`.
  Blok yorumları Türkçe ve **neden**'i anlatır, ne yaptığını değil.
- **PHP:** `declare(strict_types=1);` her dosyanın başında. Yardımcı
  fonksiyonlar `kaesra-ortak.php` içinde, sayfalarda tekrar yok.
- **Hiçbir sayfada sabit metin yok.** Görünen her şey ya
  `yapilandirma.php`'den ya temadan gelir. Bir sunucu adı veya IP'yi
  HTML'e elle yazmak, çatallayan herkesin sizin adınızı taşıması demek.

## Neleri kabul etmiyoruz

- Valorant / Riot Games varlıkları (görsel, ad, logo). Tema sistemine
  kendi temanızı ekleyebilirsiniz ama depoya Riot sanatı girmiyor.
  bkz. `sunucu/tema/README.md`
- Gerçek oyuncu verisi. `sunucu/veri/depo.json` gitignore'da; örnek veri
  `veri-tohum.php` içinde ve tamamı üretilmiş.
- `csstats` modülüne bağımlılık. Toplama katmanı ReAPI + core event'lerle
  kendi başına çalışıyor ve öyle kalacak.
