# Tohum veri

`sunucu/veri-tohum.php`, depo boşken sayfaların dolu görünmesini sağlayan
**üretilmiş** veridir. Gerçek bir sunucudan gelmiyor; içindeki hiçbir nick
gerçek bir oyuncuya ait değil.

İlk gerçek senkron geldiğinde depo dolu olduğu için bu dosya artık
okunmuyor. Yani yalnızca ilk izlenim için var — ama o ilk izlenim, bir
sunucu sahibinin "kurayım mı" kararını verdiği an.

## Yeniden üretmek

```bash
python docs/tohum-uret.py            # sunucu/veri-tohum.php'yi yazar
python docs/tohum-uret.py --yazma    # yalnız özet basar
```

Windows'ta konsol Türkçe karakterleri bozuyorsa:

```powershell
$env:PYTHONIOENCODING="utf-8"; python docs/tohum-uret.py
```

Betik **sabit tohumla** (`random.seed(20260831)`) çalışıyor: aynı girdiyle
birebir aynı dosyayı üretir. Doğrulandı — üretilen dosya ile depodaki
dosya `==` karşılaştırmasında eşit.

## Kurallar

Sayılar rastgele değil, dengeli:

| Ölçüt | Aralık |
|---|---|
| K/D | 0,59 – 2,58 |
| Kafadan vuruş oranı | %34 – %61 |
| Isabet oranı | %12 – %23 |
| Silah kırılımı | favorilere ağırlıklı, tek silahlı değil |
| Harita süreleri | tek haritaya yığılmaz, 30 dk dilimlerine yuvarlanır |

İki kural özellikle önemli:

1. **`kp` elle yazılmaz.** `kazanilanPuan()` formülünden hesaplanır
   (`kill*1 + hs*1 + asist*1 + kurma*2 + cozme*3 + mvp*5`). Formülü
   değiştirirseniz bu dosyayı da yeniden üretin, yoksa tohum veri ile
   gerçek veri farklı kurala uyar.

2. **En iyi oyuncu merdiven tavanının altında kalır.** Tavan 3816 KP
   (`kademeEsikleri()`: 90'dan başlayıp +6 artan 24 adım). Şu an en yüksek
   KP 2950 (oran 0,77). Tavan aşılırsa ilk sekiz oyuncunun hepsi "Zirve"
   görünür ve merdiven anlamsızlaşır — betik bunu `assert` ile engelliyor.

## Neden gerçek veri değil

Eski sürümün tohum verisi gerçek bir sunucudan kopyalanmıştı: gerçek
SteamID'ler ve gerçek nick'ler içeriyordu. Açık bir depoda oyuncu verisi
dağıtmak hem KVKK/GDPR açısından sorunlu hem de gereksiz. Bu sürümdeki
veri tamamen sentetik.
