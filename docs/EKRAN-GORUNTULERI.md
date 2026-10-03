# Ekran görüntüleri

Bu depoda **hazır ekran görüntüsü yok** ve bu bilinçli.

Eski sürümün görüntüleri Valorant temalıydı: Riot Games'in rütbe rozetleri,
ajan portreleri ve silah ikonları görünüyordu; başlık şeridinde ayrıca
eski bir sunucunun **gerçek IP adresi** ve clan adı vardı. İkisi de
yayınlanamaz — biri telif, diğeri veri.

Görüntüleri kendiniz yakalamanız gerekiyor. Eklenti bunun için bir rcon
komutuyla geliyor:

```
kaesra_goster <ad parçası> <komut>
```

Örneğin:

```
kaesra_goster kaptan /top
kaesra_goster kaptan /rank
kaesra_goster kaptan /rutbeler
kaesra_goster kaptan /silahlar
kaesra_goster kaptan /haritalar
kaesra_goster kaptan /karsilastir sessiz_adim
```

Komut, adı eşleşen oyuncunun ekranında MOTD penceresini açıyor — oyuncunun
kendisi yazmış gibi. Tanıtım çekimi sırasında fareye dokunmadan sayfa
açmanın tek yolu bu.

## Yakalama sırası

1. Sunucuyu ve web servisini kaldırın, bir tur oynatın (depo dolsun).
2. `kaesra_goster` ile sayfayı açın.
3. Pencereyi yakalayın (OBS, `Win+Shift+S`, veya `sunucu/pencere-cek.ps1`
   benzeri bir betik).
4. **Yayınlamadan önce başlık şeridini kontrol edin.** `yapilandirma.php`
   içindeki `sunucuAdres` doluysa IP'niz görüntüde görünüyor. Boş bırakın.

## Ölçü

MOTD penceresi 1280×720'de kabaca **860×550** iç alan veriyor. Sayfalar bu
ölçüye sığdırıldı; kaydırma yok (GoldSrc'in HTML denetimi fare tekerini
iletmiyor). Yakaladığınız görüntü pencereyi aşıyorsa bir şey bozulmuş
demektir — `sunucu/olcu-motd.php` gerçek ölçüyü öğrenmek için duruyor.
