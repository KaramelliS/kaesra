# Ekran görüntüleri

Bu klasördeki görseller **klasik temayla** ve `tohumVeri => '1'` (sahte veri)
açıkken, MOTD penceresinin gerçek ölçüsü olan **860×550**'de headless
Chrome ile üretildi:

```bash
chrome --headless=new --window-size=860,550 --hide-scrollbars \
       --screenshot=docs/motd-siralama.png \
       "http://127.0.0.1:8130/siralama-motd.php"
```

| Dosya | Sayfa |
|---|---|
| `motd-siralama.png` | `/top` — sıralama, arama, sayfalama, sezon birincisi |
| `motd-profil.png` | `/rank` — profil, rütbe, silah/harita kırılımı |
| `motd-rutbeler.png` | `/rutbeler` — 25 kademeli merdiven |
| `motd-silahlar.png` | `/silahlar` — silah başına kill |
| `motd-haritalar.png` | `/haritalar` — harita başına süre |
| `motd-karsilastir.png` | `/karsilastir` — iki oyuncu yan yana |

Görsellerde **hiçbir üçüncü taraf telifli varlık yok**: klasik tema
görselsizdir, rütbe ve silah adları özgündür. Eski Valorant temalı
görüntüler Riot Games sanatı ve gerçek bir sunucu IP'si içerdiği için
**yayınlanmadı ve silindi**.

## Oyun içinde görmek

MOTD penceresi bu HTML'i birebir gösterir. Oyun içinde doğrulamak için
bir istemciyle bağlanıp `/top` yazın; ya da sunucudan bir oyuncuya
açtırın:

```
kaesra_goster <ad parçası> /top
```

## Neden 860×550

GoldSrc'in MOTD penceresi 1280×720'de kabaca bu iç alanı veriyor ve
**kaydırılamıyor** (HTML denetimi fare tekerini iletmiyor). Sayfalar bu
ölçüye sığdırıldı; gezinme şeritleri listenin üstünde. Ürettiğiniz görsel
pencereyi aşıyorsa bir şey bozulmuş demektir.
