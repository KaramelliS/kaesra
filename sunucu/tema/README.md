# Tema sistemi

Rütbe adları, silah adları, renkler ve görsellerin **hepsi** tek klasörde:

```
sunucu/tema/<ad>/katalog.php
```

Sayfalarda ve `kaesra-ortak.php`'de sabit metin **yok**. Etkin tema
`kaesra-ortak.php` içindeki `TEMA` sabitinden okunuyor.

Varsayılan tema **`klasik`**: 25 kademe (Acemi 1 → Zirve), CS 1.6'nın kendi
silah adları, **hiç görsel yok**. Sıfır ek dosya, sıfır telif riski, kurulum
sonrası sayfa hemen dolu görünüyor.

---

## Kendi temanızı yapmak

```bash
cp -r sunucu/tema/klasik sunucu/tema/benimtemam
# düzenleyin
# sonra kaesra-ortak.php içinde:  const TEMA = 'benimtemam';
```

`katalog.php` bir PHP dizisi döndürüyor. Şema:

```php
return [
    'ad'        => 'Benim Temam',
    'ajanVar'   => false,        // karakter/sınıf kavramı var mı
    'bayrakVar' => true,         // bayrak/ klasöründen ülke bayrakları

    // Rütbe merdiveni. Numaralar 1'den başlar, ARTAN ve BOŞLUKSUZ olmalı:
    // KP eşikleri bu sıradan türetiliyor (kaesra-ortak.php → kademeEsikleri).
    // Son kademe "en üst" sayılır; onda RR her zaman 100 görünür.
    'kademe' => [
        1 => ['ad' => 'Acemi 1', 'dosya' => null, 'renk' => '#8C9196'],
        // ...
        25 => ['ad' => 'Zirve', 'dosya' => null, 'renk' => '#FF9F1C'],
    ],

    // CS motor adı -> temanın gösterdiği ad.
    'silahEsleme' => [
        'ak47' => 'AK-47',
        // ...
    ],

    // silahEsleme'nin DEĞERLERİ burada anahtar olarak durmalı.
    'silah' => [
        'AK-47' => ['dosya' => null, 'sinif' => 'Tüfek'],
        // ...
    ],

    // Anahtar, motorun verdiği ham harita adı. Listede olmayan harita
    // gelirse ad aynen gösterilir, görsel basılmaz — sayfa bozulmaz.
    'harita' => [
        'de_dust2' => ['dosya' => null, 'splash' => null],
        // ...
    ],

    // ajanVar true ise doldurun, yoksa boş bırakın.
    'ajan' => [],
];
```

### Görseller

Her `dosya` alanı **null olabilir** ve bu desteklenen bir durum. Sayfalar
`img()` yardımcısını kullanıyor: yol null ise `<img>` etiketi **hiç
basılmıyor**. Yani görselsiz tema "bozuk tema" değil, geçerli bir tema.

Görsel eklemek için null yerine yolu yazın ve dosyayı tema klasörüne koyun:

```php
1 => ['ad' => 'Acemi 1', 'dosya' => 'kademe/acemi-1.png', 'renk' => '#8C9196'],
```

Yol, tema klasörüne görelidir (`tema/benimtemam/kademe/acemi-1.png`).

### Ölçek uyarısı

Merdivenin tavanı **3816 KP** (90'dan başlayıp +6 artan 24 adım). Kendi
merdiveninizi kurarken gerçek sunucunuzdaki KP dağılımının bu tavanın
altında kaldığını kontrol edin — yoksa herkes en üst kademede yığılır ve
merdiven anlamsızlaşır. Tavanı değiştirmek isterseniz
`kaesra-ortak.php → kademeEsikleri()` içindeki `90` ve `+6` değerlerini
düzenleyin.

---

## Telif — önemli

Tema klasörü **telifli sanatın girdiği tek yer**. Bu yüzden:

- Depoya **Riot Games / Valorant** varlığı koymayın: rütbe rozetleri, ajan
  portreleri, silah ikonları, harita splash'leri, kademe adları
  ("Radiant", "Ascendant"). Bunlar Riot'un telifli ve ticari markalı
  işleri; açık bir depoda dağıtımı DMCA takedown sebebidir.
- Başka bir oyunun varlıkları için de aynı kural geçerli. Kendi çiziminiz,
  CC0/CC-BY kaynak veya SIL OFL yazı tipi kullanın.
- Kendi sunucunuzda, **kapalı** bir kurulumda ne çalıştırdığınız sizin
  sorumluluğunuz; ama bu depoya koyduğunuz her şey dünyaya açık.

Kendi temanızı yazıp bu depoya pull request olarak göndermek isterseniz:
yalnızca **sizin telifiniz olan** veya lisansı yeniden dağıtıma izin veren
varlıklar kabul ediliyor. bkz. `docs/KATKI.md`.

---

## Örnek: silah adlarını değiştirmek

Motorun DeathMsg olayı her zaman `ak47` der. Oyuncunun göreceği adı tema
seçer:

```php
'silahEsleme' => ['ak47' => 'Kurt-47', ...],
'silah'       => ['Kurt-47' => ['dosya' => 'silah/kurt47.png', 'sinif' => 'Tüfek'], ...],
```

Tek uyarı: `oyun/kaesra.sma` içindeki `BILINEN_SILAHLAR` listesi **motor
adlarını** (`ak47`) taşır ve API'nin kabul ettiği anahtarlardır. Onu
değiştirmeyin — tema yalnız görünen adı değiştirir, telgrafı değil.
