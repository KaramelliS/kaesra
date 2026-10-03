<?php
declare(strict_types=1);

/**
 * Harita sıralaması — sunucuda ne oynanıyor, hangi haritada kim yaşıyor.
 *
 * Silah sayfası "hangi silahta kim" diyor, bu sayfa "hangi haritada kim".
 * İkisi kasten farklı duruyor: silah bir liste satırı, harita bir şerit.
 *
 * Görsel seçimi ölçüldü, tercih değil. Tema splash'leri 1920x1080 ve
 * tanesi 2-3 MB; dokuz harita 22 MB eder ve MOTD paneli o yükü kaldırmıyor.
 * Liste ikonu 456x100 ve 65 KB, üstelik zaten banner biçiminde — doğal
 * boyutunda kullanılıyor, hiç ölçeklenmiyor. Sayfa toplamı ~600 KB.
 */

require __DIR__ . '/kaesra-ortak.php';
motdBasliklari();

$oyuncular = oyuncuListesi();

/**
 * Harita -> [toplam saniye, oynayan sayısı, en çok oynayan, onun saniyesi].
 *
 * Tohum veride harita başına kill tutulmuyor, yalnız süre. "En iyi oyuncu"
 * yerine "en çok oynayan" yazmasının sebebi bu — elimizde olmayan bir ölçütü
 * varmış gibi göstermek sayfanın tamamını şüpheli yapardı.
 */
$haritalar = [];

foreach ($oyuncular as $o) {
    foreach ($o['haritalar'] as $ad => $saniye) {
        if (!isset($haritalar[$ad])) {
            $haritalar[$ad] = ['toplam' => 0, 'oynayan' => 0, 'kral' => null, 'kralSaniye' => 0];
        }

        $haritalar[$ad]['toplam'] += $saniye;
        $haritalar[$ad]['oynayan']++;

        if ($saniye > $haritalar[$ad]['kralSaniye']) {
            $haritalar[$ad]['kralSaniye'] = $saniye;
            $haritalar[$ad]['kral'] = $o;
        }
    }
}

uasort($haritalar, static fn(array $a, array $b): int => $b['toplam'] <=> $a['toplam']);

$enCok = $haritalar === [] ? 1 : reset($haritalar)['toplam'];
$toplamSaniye = max(1, array_sum(array_column($haritalar, 'toplam')));
$haritaSayisi = count($haritalar);

/* Sayfalamadan ÖNCE: sonra alınınca 2. sayfada o sayfanın ilkini
   "en çok oynanan" diye gösteriyordu. */
$enCokAdi = $haritalar === [] ? '—' : (string) array_key_first($haritalar);

// MOTD kaydirilamiyor; serit basina 64px, sayfa basina bes serit.
[$haritalar, $kisaltSerit] = sayfala($haritalar, 5, 'haritalar-motd.php');
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<style><?= tema() ?>

/* --- yalniz bu sayfaya ozgu --- */
<?= kisaltStili() ?>

.serit {
  position:relative; overflow:hidden; height:64px;
  border-bottom:1px solid rgba(236,232,225,.06);
}
.serit .banner { position:absolute; left:0; top:0; width:292px; height:64px; }

/* Banner'in sag kenarini yazinin altina karistiran perde. Gorselin kendisi
   456px'te bitiyor; perde 200px once basliyor ki kesik gorunmesin. */
.serit .perde5 {
  position:absolute; left:160px; top:0; right:0; bottom:0;
  background:linear-gradient(90deg, rgba(11,18,25,0) 0%, #0B1219 44%, #0B1219 100%);
}

.serit .ad {
  position:absolute; left:16px; top:11px;
  font-family:'Kaesra Display',sans-serif; font-size:22px; line-height:1;
  text-transform:uppercase; color:#ECE8E1;
}
.serit .adGolge {
  position:absolute; left:16px; top:11px;
  font-family:'Kaesra Display',sans-serif; font-size:22px; line-height:1;
  text-transform:uppercase; color:#0B1219;
}
.serit .pay { position:absolute; left:17px; top:38px; color:#C6CED4; font-size:11px; }

.serit .orta { position:absolute; left:320px; top:11px; }
.serit .orta .sure { font-family:'Kaesra Display',sans-serif; font-size:21px; line-height:1; }
.serit .orta .kim { color:#5E7080; font-size:11px; margin-top:3px; }

/* Üç satır 64 piksele sığmıyordu; sürenin ayrı satırı kesiliyordu. Süre
   artık adın yanında, blok iki satır ve top:13 ile dikeyde ortalı. */
.serit .sagBlok { position:absolute; right:22px; top:13px; text-align:right; }
.serit .sagBlok .rol { color:#5E7080; font-size:11px; letter-spacing:.14em; }
.serit .sagBlok a { color:#ECE8E1; text-decoration:none; font-size:15px; }
.serit .sagBlok a:hover { color:#FF4655; }
.serit .sagBlok img { width:22px; height:22px; vertical-align:middle; margin-left:7px; }
.serit .sagBlok .kadar { color:#93A2AE; font-size:12px; margin-left:9px; }

/* Oran çizgisi nötr; yalnız en çok oynanan kırmızı — silah sayfasıyla
   aynı kural, vurgu tek şeye harcanıyor. */
.serit .cizgi2 { position:absolute; left:0; bottom:0; height:3px; background:#3D4E5C; }
.serit.birinci .cizgi2 { background:#FF4655; }

.yokSerit { padding:44px 26px; color:#6E7F8C; }
</style>
</head>
<body style="background:#0B1219;color:#ECE8E1">

<div class="gezinme" style="display:flex;justify-content:space-between;padding:9px 26px;background:#0E161F;border-bottom:1px solid rgba(236,232,225,.10)">
  <a href="siralama-motd.php" style="color:#93A2AE;text-decoration:none;font-family:'Kaesra Dar',sans-serif;font-weight:600;font-size:13px;letter-spacing:.18em;text-transform:uppercase">&larr; Sıralamaya dön</a>
  <span class="etiket">Harita sıralaması</span>
</div>

<div class="tepe">
  <div class="perde"></div>
  <div class="ic">
    <div>
      <h1 class="marka">Harita<b>l</b>ar</h1>
      <span class="etiket"><?= $haritaSayisi ?> harita<span class="tik"><i></i><i class="b"></i><i class="c"></i></span><?= sure($toplamSaniye) ?> toplam oyun</span>
    </div>
    <div class="sag">
      <div class="n"><?= esc($enCokAdi) ?></div>
      <span class="etiket">En çok oynanan</span>
    </div>
  </div>
</div>

<?php if ($haritalar === []): ?>
  <div class="yokSerit">Henüz harita kaydı yok.</div>
<?php endif; ?>

<?= $kisaltSerit ?>

<?php foreach ($haritalar as $ad => $bilgi): ?>
  <?php
  $h = harita((string) $ad);
  $kral = $bilgi['kral'];
  $kralKademe = $kral === null ? null : kademe($kral['kp']);
  $yuzde = (int) round($bilgi['toplam'] / $toplamSaniye * 100);
  ?>
  <div class="serit<?= $bilgi['toplam'] === $enCok ? ' birinci' : '' ?>">
    <?= img($h['dosya'], 'banner') ?>
    <div class="perde5"></div>

    <?php /* Gölge bir piksel kaydırılmış kopya: banner'in açık yerlerinde
             de ad okunsun diye. text-shadow bu motorda denenmedi. */ ?>
    <div class="adGolge" style="left:17px;top:12px"><?= esc((string) $ad) ?></div>
    <div class="ad"><?= esc((string) $ad) ?></div>
    <?php /* "%26'i" gibi bozuk ek üretmemek için ek gerektirmeyen kalıp. */ ?>
    <div class="pay">oyun süresinde payı %<?= $yuzde ?></div>

    <div class="orta">
      <div class="sure"><?= sure($bilgi['toplam']) ?></div>
      <div class="kim"><?= $bilgi['oynayan'] ?> oyuncu bu haritada kayıtlı</div>
    </div>

    <?php if ($kral !== null): ?>
      <div class="sagBlok">
        <div class="rol">EN ÇOK OYNAYAN <span class="kadar"><?= sure($bilgi['kralSaniye']) ?></span></div>
        <a href="oyuncu-motd.php?kimlik=<?= urlencode($kral['kimlik']) ?>"><?= esc($kral['ad']) ?></a>
        <?= img(kademeIkonu($kralKademe)) ?>
      </div>
    <?php endif; ?>

    <i class="cizgi2" style="width:<?= (int) round($bilgi['toplam'] / $enCok * 100) ?>%"></i>
  </div>
<?php endforeach; ?>

<div class="dip">
  <span class="etiket">Süreler sunucuda geçirilen zaman<span class="tik"><i></i><i class="b"></i><i class="c"></i></span>Alt çizgi en çok oynanana göre oran</span>
  <span class="etiket">30 saniyede bir güncelleniyor</span>
</div>

</body>
</html>
