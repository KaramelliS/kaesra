<?php
declare(strict_types=1);

/**
 * İki oyuncuyu yan yana koyar. Oyun içinden /karsilastir <isim> ile açılıyor.
 *
 *   karsilastir-motd.php?sol=STEAM_0:1:...&sag=VALVE_0:0:...
 *
 * Her satırda iki değer ve aralarında oranı gösteren bir çubuk var. Üstün
 * olan taraf kırmızı; sayıları yan yana yazıp okuyucuya karşılaştırmayı
 * bırakmak, karşılaştırma sayfasının işini yapmamak olurdu.
 *
 * "Üstün" her ölçütte büyük olan değil: ölüm sayısında az olan iyi. Ölçüt
 * tablosunda her satır kendi yönünü taşıyor.
 */

require __DIR__ . '/kaesra-ortak.php';
motdBasliklari();

$oyuncular = oyuncuListesi();

$dizin = [];
foreach ($oyuncular as $i => $o) {
    $dizin[$o['kimlik']] = ['sira' => $i + 1] + $o;
}

$sol = $dizin[(string) ($_GET['sol'] ?? '')] ?? null;
$sag = $dizin[(string) ($_GET['sag'] ?? '')] ?? null;

/** [etiket, deger(sol), deger(sag), biçim, büyük mü iyi] */
function olcutler(array $a, array $b): array
{
    return [
        ['Kazanılan puan', $a['kp'], $b['kp'], 'sayi', true],
        ['Sıralama', $a['sira'], $b['sira'], 'sira', false],
        ['Öldürme', $a['kill'], $b['kill'], 'sayi', true],
        ['Ölüm', $a['death'], $b['death'], 'sayi', false],
        ['K / D', $a['kill'] / max(1, $a['death']), $b['kill'] / max(1, $b['death']), 'ondalik', true],
        ['Headshot', hsYuzde($a['hs'], $a['kill']), hsYuzde($b['hs'], $b['kill']), 'yuzde', true],
        ['Tur MVP', $a['mvp'], $b['mvp'], 'sayi', true],
        ['Asist', $a['asist'], $b['asist'], 'sayi', true],
        ['Toplam hasar', $a['hasar'], $b['hasar'], 'sayi', true],
        /* atis=0 yeni oyuncuda gerçek bir durum; sıfıra bölme sayfayı düşürüyordu. */
        ['İsabet oranı',
            $a['atis'] > 0 ? $a['isabet'] / $a['atis'] * 100 : 0.0,
            $b['atis'] > 0 ? $b['isabet'] / $b['atis'] * 100 : 0.0,
            'ondalik_yuzde', true],
        ['Spike kurma', $a['kurma'], $b['kurma'], 'sayi', true],
        ['Spike çözme', $a['cozme'], $b['cozme'], 'sayi', true],
        ['Kazanılan tur', $a['kazanilan'], $b['kazanilan'], 'sayi', true],
        ['Sunucuda', $a['saniye'], $b['saniye'], 'sure', true],
    ];
}

function bicimle(string $tur, float $deger): string
{
    return match ($tur) {
        'ondalik'       => sayi($deger, 2),
        'yuzde'         => '%' . sayi((int) $deger),
        'ondalik_yuzde' => '%' . sayi($deger, 1),
        'sure'          => sure((int) $deger),
        'sira'          => sayi((int) $deger) . '.',
        default         => sayi((int) $deger),
    };
}
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<style><?= tema() ?>

/* --- yalniz bu sayfaya ozgu --- */
.kafa { display:flex; border-bottom:2px solid #FF4655; }
.kafa > div { flex:1; position:relative; overflow:hidden; min-height:68px; padding:5px 18px; }
.kafa > div.sagKafa { text-align:right; }
.kafa .zemin { position:absolute; left:0; top:0; right:0; bottom:0; }
.kafa .bust { position:absolute; bottom:-24px; height:134px; opacity:.30; }
.kafa .solKafa .bust { left:-16px; }
.kafa .sagKafa .bust { right:-16px; }
.kafa .perde4 { position:absolute; left:0; top:0; right:0; bottom:0; }
.kafa .solKafa .perde4 { background:linear-gradient(90deg, rgba(11,18,25,.55) 0%, rgba(11,18,25,.90) 55%, #0B1219 100%); }
.kafa .sagKafa .perde4 { background:linear-gradient(270deg, rgba(11,18,25,.55) 0%, rgba(11,18,25,.90) 55%, #0B1219 100%); }
.kafa .ic { position:relative; }
.kafa .ad { font-family:'Kaesra Display',sans-serif; font-size:19px; line-height:.98; text-transform:uppercase; margin-top:4px; }
.kafa .rutbe { font-family:'Kaesra Dar',sans-serif; font-weight:600; font-size:13px; letter-spacing:.18em; text-transform:uppercase; margin-top:5px; }
.kafa img.rozet { width:30px; height:30px; vertical-align:middle; }
.kafa .ajanSat { color:#93A2AE; font-size:11px; margin-top:2px; }
.kafa .ajanSat img { width:18px; height:18px; vertical-align:middle; margin:0 6px; }

/* ".kafa > div" kuralı flex:1 dağıtıyor ve ayraç da bir div: genişliği
   ezilip ekranın üçte biri kadar kırmızı bir blok oluyordu. flex:0 0 2px
   hem büyümeyi kapatıyor hem tabanı sabitliyor; seçici de ".kafa > div"i
   yenecek kadar özgül. */
.kafa > div.ortaAyrac { flex:0 0 2px; padding:0; min-height:0; background:#FF4655; }

.olcut { border-bottom:1px solid rgba(236,232,225,.05); padding:0 22px 1px; }
.olcut .ust { display:flex; align-items:baseline; }
/* Kazanan taraf parlak beyaz, kaybeden soluk. Eskiden kazanan kırmızıydı;
   kırmızı bu sayfada SOL oyuncunun çubuk rengi, sağdaki kazanınca değeri
   soldakinin rengiyle yanıyordu — yanlış tarafı işaret eden bir vurgu. */
.olcut .deg { width:92px; font-family:'Kaesra Display',sans-serif; font-size:13px; line-height:1.25; color:#5E7080; }
.olcut .deg.sagD { text-align:right; }
.olcut .deg.kazanan { color:#ECE8E1; font-size:15px; }
.olcut .ad2 { flex:1; text-align:center; font-family:'Kaesra Dar',sans-serif; font-weight:600;
              font-size:11px; letter-spacing:.16em; text-transform:uppercase; color:#5E7080; }
.olcut .bar { display:flex; height:3px; margin-top:2px; background:rgba(236,232,225,.07); }
.olcut .bar i { display:block; height:3px; }
.olcut .bar .a { background:#FF4655; }
.olcut .bar .b { background:#4FC9D6; }

.yok { padding:48px 26px; }
</style>
</head>
<body style="background:#0B1219;color:#ECE8E1">

<div class="gezinme" style="display:flex;justify-content:space-between;padding:9px 22px;background:#0E161F;border-bottom:1px solid rgba(236,232,225,.10)">
  <a href="siralama-motd.php" style="color:#93A2AE;text-decoration:none;font-family:'Kaesra Dar',sans-serif;font-weight:600;font-size:13px;letter-spacing:.18em;text-transform:uppercase">&larr; Sıralamaya dön</a>
  <span class="etiket">Karşılaştırma</span>
</div>

<?php if ($sol === null || $sag === null): ?>
  <div class="yok">
    <span class="etiket">Eksik kimlik</span>
    <h1 style="font-family:'Kaesra Display',sans-serif;font-size:38px;text-transform:uppercase;margin:6px 0 0">Karşılaştırılacak iki oyuncu gerek</h1>
    <p style="color:#6E7F8C;max-width:600px">
      Oyun içinde <b style="color:#ECE8E1">/karsilastir &lt;isim&gt;</b> yazarsan seninle o oyuncu
      yan yana gelir. İkisinden biri kaesrade yoksa bu sayfa açılmıyor.
    </p>
  </div>
<?php else: ?>

<?php
$solK = kademe($sol['kp']);
$sagK = kademe($sag['kp']);
$solA = ajan($sol['ajan']);
$sagA = ajan($sag['ajan']);
?>

<div class="kafa">
  <div class="solKafa">
    <div class="zemin" style="background:linear-gradient(90deg, <?= esc($solA['renk']) ?> 0%, #0B1219 78%)"></div>
    <?= img($solA['bust'], 'bust') ?>
    <div class="perde4"></div>
    <div class="ic">
      <span class="etiket"><?= $sol['sira'] ?>. sıra</span>
      <div class="ad"><?= esc($sol['ad']) ?></div>
      <div class="rutbe" style="color:<?= esc($solK['renk']) ?>">
        <?= img(kademeIkonu($solK), 'rozet') ?> <?= esc($solK['ad']) ?>
      </div>
      <?php if ($solA['ad'] !== null): ?><div class="ajanSat"><?= img($solA['dosya']) ?><?= esc($solA['ad']) ?></div><?php endif; ?>
    </div>
  </div>

  <div class="ortaAyrac"></div>

  <div class="sagKafa">
    <div class="zemin" style="background:linear-gradient(270deg, <?= esc($sagA['renk']) ?> 0%, #0B1219 78%)"></div>
    <?= img($sagA['bust'], 'bust') ?>
    <div class="perde4"></div>
    <div class="ic">
      <span class="etiket"><?= $sag['sira'] ?>. sıra</span>
      <div class="ad"><?= esc($sag['ad']) ?></div>
      <div class="rutbe" style="color:<?= esc($sagK['renk']) ?>">
        <?= esc($sagK['ad']) ?> <?= img(kademeIkonu($sagK), 'rozet') ?>
      </div>
      <?php if ($sagA['ad'] !== null): ?><div class="ajanSat"><?= esc($sagA['ad']) ?><?= img($sagA['dosya']) ?></div><?php endif; ?>
    </div>
  </div>
</div>

<?php foreach (olcutler($sol, $sag) as [$etiket, $a, $b, $tur, $buyukIyi]): ?>
  <?php
  $solKazandi = $buyukIyi ? $a > $b : $a < $b;
  $sagKazandi = $buyukIyi ? $b > $a : $b < $a;

  // Çubuk oranı her zaman büyüklükten: yön bilgisi rengi değil kazananı
  // belirliyor, çubuk sadece farkın büyüklüğünü gösteriyor.
  $toplam = abs($a) + abs($b);
  $solPay = $toplam > 0 ? (int) round(abs($a) / $toplam * 100) : 50;
  ?>
  <div class="olcut">
    <div class="ust">
      <span class="deg<?= $solKazandi ? ' kazanan' : '' ?>"><?= bicimle($tur, (float) $a) ?></span>
      <span class="ad2"><?= esc($etiket) ?></span>
      <span class="deg sagD<?= $sagKazandi ? ' kazanan' : '' ?>"><?= bicimle($tur, (float) $b) ?></span>
    </div>
    <div class="bar">
      <i class="a" style="width:<?= $solPay ?>%"></i>
      <i class="b" style="width:<?= 100 - $solPay ?>%"></i>
    </div>
  </div>
<?php endforeach; ?>

<?php endif; ?>
</body>
</html>
