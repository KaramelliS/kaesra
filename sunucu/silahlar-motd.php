<?php
declare(strict_types=1);

/**
 * Silah sıralaması — hangi silahla kim en iyi.
 *
 * Sıralama sayfası "kim en iyi" sorusunu cevaplıyor; bu sayfa "hangi silahta
 * kim" sorusunu. AWP'ci ile tüfekçi aynı tabloda yarışmıyor, ikisi de kendi
 * sütununda birinci olabiliyor — küçük bir sunucuda bu, sıralamanın en
 * altındaki oyuncuya da görünecek bir yer açıyor.
 *
 * Silahlar toplam kill'e göre sıralı: sunucuda ne oynandığını da gösteriyor.
 */

require __DIR__ . '/kaesra-ortak.php';
motdBasliklari();

$oyuncular = oyuncuListesi();

/**
 * Silah -> [toplam, en iyi oyuncu, onun kill'i, kullanan sayısı].
 *
 * Tek geçişte toplanıyor. Her silah için oyuncu listesini yeniden dolaşmak
 * 20 silah × 16 oyuncu = 320 gereksiz karşılaştırma demekti.
 */
$silahlar = [];

foreach ($oyuncular as $sira => $o) {
    foreach ($o['silahlar'] as $csAdi => $adet) {
        if (!isset($silahlar[$csAdi])) {
            $silahlar[$csAdi] = ['toplam' => 0, 'kullanan' => 0, 'enIyi' => null, 'enIyiAdet' => 0, 'enIyiSira' => 0];
        }

        $silahlar[$csAdi]['toplam'] += $adet;
        $silahlar[$csAdi]['kullanan']++;

        if ($adet > $silahlar[$csAdi]['enIyiAdet']) {
            $silahlar[$csAdi]['enIyiAdet'] = $adet;
            $silahlar[$csAdi]['enIyi'] = $o;
            $silahlar[$csAdi]['enIyiSira'] = $sira + 1;
        }
    }
}

uasort($silahlar, static fn(array $a, array $b): int => $b['toplam'] <=> $a['toplam']);

$enCok = $silahlar === [] ? 1 : reset($silahlar)['toplam'];
$toplamKill = array_sum(array_column($silahlar, 'toplam'));
$silahSayisi = count($silahlar);

/*
 * Tema silahları YENİDEN ADLANDIRIYOR mu? Klasik temada motor adı ile
 * görünen ad aynı ("ak47" -> "AK-47", büyük/küçük harf dışında), yani
 * başlıktaki "küçük yazı motorun gördüğü ad" açıklaması anlamsız kalıyor.
 * Yeniden adlandıran bir temada (kendi silah dilinizi kurduğunuzda) ise
 * o açıklama değerli. Başlık buna göre değişiyor.
 */
$temaAdlariFarkli = false;
foreach (katalog()['silahEsleme'] ?? [] as $ham => $gorunen) {
    if (strcasecmp((string) $ham, (string) $gorunen) !== 0) { $temaAdlariFarkli = true; break; }
}

/* Başlıktaki "en çok kullanılan" sayfalamadan ÖNCE alınmalı: sonra alınınca
   2. sayfada o sayfanın ilk silahını gösteriyordu — yanlış bilgi. Ham CS
   adı yerine temanın verdiği ad basılıyor; oyuncunun gördüğü dil o. */
$enCokAdi = $silahlar === [] ? '—' : silah((string) array_key_first($silahlar))['ad'];

// MOTD kaydirilamiyor; liste sayfalara bolunuyor.
[$silahlar, $kisaltSerit] = sayfala($silahlar, 7, 'silahlar-motd.php');
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<style><?= tema() ?>

/* --- yalniz bu sayfaya ozgu --- */
<?= kisaltStili() ?>

.satir {
  display:flex; align-items:center; padding:4px 22px;
  border-bottom:1px solid rgba(236,232,225,.05);
}
.satir:hover { background:rgba(236,232,225,.04); }

.satir .gorsel { width:86px; }
.satir .gorsel img { width:78px; height:27px; }

.satir .isim { width:160px; }
.satir .isim .temaAdi { font-family:'Kaesra Display',sans-serif; font-size:17px; line-height:1; }
.satir .isim .hamAd { font-family:'Kaesra Dar',sans-serif; font-weight:600; font-size:11px;
                   letter-spacing:.16em; text-transform:uppercase; color:#4F6376; margin-top:2px; }

/* Çubuklar nötr; yalnız listenin birincisi kırmızı yanıyor. Yedi kırmızı
   çubuk birbirini söndürüyordu — vurgu, vurgulanacak tek şeye harcanıyor
   ve başlıktaki "en çok kullanılan" ile aynı şeyi işaret ediyor. */
.satir .orta { flex:1; padding:0 22px; }
.satir .orta .cizgi { height:4px; background:rgba(236,232,225,.07); }
.satir .orta .cizgi i { display:block; height:4px; background:#5E7080; }
.satir.birinci .orta .cizgi i { background:#FF4655; }
.satir .orta .altYazi { color:#5E7080; font-size:12px; margin-top:5px; }

.satir .toplam { width:90px; text-align:right; font-family:'Kaesra Display',sans-serif; font-size:19px; }

.satir .usta { width:230px; text-align:right; }
.satir .usta a { color:#ECE8E1; text-decoration:none; }
.satir .usta a:hover { color:#FF4655; }
.satir .usta img.rozet { width:24px; height:24px; vertical-align:middle; margin-left:8px; }
.satir .usta .adet { color:#93A2AE; font-size:12px; margin-top:2px; }
</style>
</head>
<body style="background:#0B1219;color:#ECE8E1">

<div class="gezinme" style="display:flex;justify-content:space-between;padding:9px 26px;background:#0E161F;border-bottom:1px solid rgba(236,232,225,.10)">
  <a href="siralama-motd.php" style="color:#93A2AE;text-decoration:none;font-family:'Kaesra Dar',sans-serif;font-weight:600;font-size:13px;letter-spacing:.18em;text-transform:uppercase">&larr; Sıralamaya dön</a>
  <span class="etiket">Silah sıralaması</span>
</div>

<div class="tepe">
  <div class="perde"></div>
  <div class="ic">
    <div>
      <h1 class="marka">Sila<b>h</b>lar</h1>
      <span class="etiket"><?= esc(ayar('sezon', 'Sezon 1')) ?><span class="tik"><i></i><i class="b"></i><i class="c"></i></span><?= $silahSayisi ?> silah<span class="tik"><i></i><i class="b"></i><i class="c"></i></span><?= sayi($toplamKill) ?> kill</span>
    </div>
    <div class="sag">
      <div class="n"><?= esc($enCokAdi) ?></div>
      <span class="etiket">En çok kullanılan</span>
    </div>
  </div>
</div>

<?= $kisaltSerit ?>

<?php foreach ($silahlar as $csAdi => $bilgi): ?>
  <?php
  $s = silah($csAdi);
  $usta = $bilgi['enIyi'];
  $ustaKademe = $usta === null ? null : kademe($usta['kp']);
  ?>
  <div class="satir<?= $bilgi['toplam'] === $enCok ? ' birinci' : '' ?>">
    <div class="gorsel">
      <?= img($s['dosya']) ?>
    </div>

    <div class="isim">
      <div class="temaAdi"><?= esc($s['ad']) ?></div>
      <?php /* Ham motor adı yalnız temanın adından FARKLIYSA basılıyor.
             Klasik temada ikisi de "AK-47" ve aynı satırı iki kez görmek
             gürültü; silahları yeniden adlandıran bir temada ise
             "Vandal / ak47" bilgisi değerli. */ ?>
      <?php if (strcasecmp((string) $s['ad'], (string) $s['ham']) !== 0): ?>
        <div class="hamAd"><?= esc($s['ham']) ?></div>
      <?php endif; ?>
    </div>

    <div class="orta">
      <div class="cizgi"><i style="width:<?= (int) round($bilgi['toplam'] / $enCok * 100) ?>%"></i></div>
      <div class="altYazi"><?= $bilgi['kullanan'] ?> oyuncu kullanıyor</div>
    </div>

    <div class="toplam"><?= sayi($bilgi['toplam']) ?></div>

    <div class="usta">
      <?php if ($usta !== null): ?>
        <a href="oyuncu-motd.php?kimlik=<?= urlencode($usta['kimlik']) ?>"><?= esc($usta['ad']) ?></a>
        <?= img(kademeIkonu($ustaKademe), 'rozet') ?>
        <div class="adet"><?= sayi($bilgi['enIyiAdet']) ?> kill &middot; bu silahın birincisi</div>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<div class="dip">
  <span class="etiket"><?= $temaAdlariFarkli ? 'Silah adları tema karşılıkları<span class="tik"><i></i><i class="b"></i><i class="c"></i></span>Küçük yazı motorun gördüğü ad' : 'Silahlar' ?></span>
  <span class="etiket">30 saniyede bir güncelleniyor</span>
</div>

</body>
</html>
