<?php
declare(strict_types=1);

/**
 * Rütbe merdiveni — yirmi beş kademe, eşikleri ve kimin nerede olduğu.
 *
 * Sunucuda en çok sorulan iki soru: "bir üste kaç KP kaldı" ve "Radyant kaç
 * ediyor". İkisinin de cevabı buradaydı ama hiçbir sayfada yazmıyordu;
 * oyuncu sıralamaya bakıp kendi kafasından çıkarmaya çalışıyordu.
 *
 * Tablo statik değil: her kademede şu an kaç oyuncu olduğu tohum veriden
 * sayılıyor. Boş kademeler soluk duruyor. Ölçüsüz bir eşik listesi her
 * sunucuda aynı olurdu; dolu kademeler bu sunucunun kendi resmi.
 *
 * ?kimlik=... verilirse o oyuncunun satırı işaretleniyor ve üstte "şu kadar
 * kaldı" şeridi çıkıyor — /rutbeler komutu bunu kendiliğinden ekliyor.
 */

require __DIR__ . '/kaesra-ortak.php';
motdBasliklari();

$oyuncular = oyuncuListesi();

/*
 * kademeEsikleri() 0'dan başlayan bir liste değil: anahtarlar temanın
 * kendi kademe numaraları, yani 3 (Iron 1) ile 27 (Radiant) arası. Sıra
 * numarası ayrıca üretiliyor, oyuncuya "3. kademe Iron 1" demek anlamsız.
 */
$esikler = kademeEsikleri();
$numaralar = array_keys($esikler);
$sonNo = $numaralar[count($numaralar) - 1];
$tepeEsik = $esikler[$sonNo];

/** Kademe numarası -> o kademedeki oyuncu sayısı. */
$doluluk = [];
foreach ($oyuncular as $o) {
    $no = kademe($o['kp'])['no'];
    $doluluk[$no] = ($doluluk[$no] ?? 0) + 1;
}

$ben = null;
$benimKimlik = (string) ($_GET['kimlik'] ?? '');
if ($benimKimlik !== '') {
    foreach ($oyuncular as $o) {
        if ($o['kimlik'] === $benimKimlik) {
            $ben = $o;
            break;
        }
    }
}

$benimKademe = $ben === null ? null : kademe($ben['kp']);
$enKalabalik = $doluluk === [] ? 0 : max($doluluk);

/*
 * Görünecek basamaklar.
 *
 * Yirmi beş satır MOTD penceresine sığmıyor ve pencere kaydırılamıyor
 * (GoldSrc'in HTML denetimi fare tekerini iletmiyor). Kısaltmanın doğru yeri
 * baştan kesmek değil: oyuncuyu ilgilendiren kendi basamağının çevresi.
 * Radiant'ın eşiğini merak eden "tümünü göster"e basıyor.
 *
 * Kimlik yoksa tepeden on bir basamak gösteriliyor — merdivenin hedefi orası.
 */
/*
 * Merdiven sayfalara bölünüyor: yirmi beş satır MOTD penceresine sığmıyor ve
 * pencere kaydırılamıyor. Oyuncunun kimliği verilmişse varsayılan sayfa
 * ONUN basamağının olduğu sayfa — merdiveni açan oyuncu önce kendini arıyor.
 */
$benimYer = $benimKademe === null
    ? 0
    : (int) array_search($benimKademe['no'], array_reverse($numaralar), true);

/*
 * Sayfa boyu düz 8 değil: 25 basamak 8'erli bölününce son sayfa TEK satır
 * kalıyordu ve Iron oyuncusu /rutbeler yazınca yüzde yetmişi boş bir ekran
 * görüyordu — en kalabalık kademe orası, en çok o sayfa açılıyor. Satırlar
 * aynı sayfa sayısına eşit dağıtılıyor: 25 -> 7/7/7/4.
 */
$sayfaAdedi = max(1, (int) ceil(count($numaralar) / 8));
$sayfaBoyu = (int) ceil(count($numaralar) / $sayfaAdedi);

// Radiant üstte: liste ters çevrilip öyle sayfalanıyor.
[$gorunen, $kisaltSerit] = sayfala(
    array_reverse($numaralar),
    $sayfaBoyu,
    'rutbeler-motd.php',
    $benimKimlik === '' ? [] : ['kimlik' => $benimKimlik],
    $benimYer
);
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<style><?= tema() ?>

/* --- yalniz bu sayfaya ozgu --- */
<?= kisaltStili() ?>

.durumSerit {
  display:flex; align-items:center; padding:8px 22px;
  background:#101A24; border-bottom:2px solid #FF4655;
}
.durumSerit img { width:44px; height:44px; margin-right:14px; }
.durumSerit .buyuk { font-family:'Kaesra Display',sans-serif; font-size:20px; line-height:1; text-transform:uppercase; }
.durumSerit .kucukAlt { color:#93A2AE; font-size:13px; margin-top:5px; }
.durumSerit .kucukAlt b { color:#ECE8E1; font-weight:500; }
.durumSerit .saga { margin-left:auto; text-align:right; }
.durumSerit .saga .kp { font-family:'Kaesra Display',sans-serif; font-size:26px; line-height:1; color:#FF4655; }

.basamak {
  display:flex; align-items:center; padding:3px 22px;
  border-bottom:1px solid rgba(236,232,225,.045);
}
.basamak.bos { opacity:.42; }
.basamak.benim { background:rgba(255,70,85,.10); border-left:3px solid #FF4655; padding-left:23px; }

.basamak img { width:26px; height:26px; margin-right:11px; }
.basamak .sayi {
  width:34px; font-family:'Kaesra Dar',sans-serif; font-weight:600;
  font-size:13px; color:#4F6376;
}
.basamak .isim2 {
  width:150px; font-family:'Kaesra Dar',sans-serif; font-weight:600;
  font-size:16px; letter-spacing:.10em; text-transform:uppercase;
}
.basamak .esik { width:100px; font-family:'Kaesra Display',sans-serif; font-size:16px; }
.basamak .esik s { color:#4F6376; font-family:'Kaesra Govde',sans-serif; font-size:12px; text-decoration:none; }
.basamak .aralik { flex:1; color:#5E7080; font-size:12px; }
/* Doluluk çubuğu kademenin KENDİ renginde — kırmızı değil. Kırmızı bu
   sayfada yalnız tıklanabilir şeylerde (sayfa düğmeleri, "benim" şeridi);
   çubuğun rengi satırdaki rozet ve adla aynı diziden okunuyor. */
.basamak .kisi { width:150px; text-align:right; color:#93A2AE; font-size:12px; }
.basamak .kisi i { display:inline-block; height:6px; vertical-align:middle; margin-right:9px; }
</style>
</head>
<body style="background:#0B1219;color:#ECE8E1">

<div class="gezinme" style="display:flex;justify-content:space-between;padding:9px 26px;background:#0E161F;border-bottom:1px solid rgba(236,232,225,.10)">
  <a href="siralama-motd.php" style="color:#93A2AE;text-decoration:none;font-family:'Kaesra Dar',sans-serif;font-weight:600;font-size:13px;letter-spacing:.18em;text-transform:uppercase">&larr; Sıralamaya dön</a>
  <span class="etiket">Rütbe merdiveni</span>
</div>

<div class="tepe">
  <div class="perde"></div>
  <div class="ic">
    <div>
      <h1 class="marka">Rütbe<b>l</b>er</h1>
      <span class="etiket">25 kademe<span class="tik"><i></i><i class="b"></i><i class="c"></i></span>Iron 1'den Radiant'a<span class="tik"><i></i><i class="b"></i><i class="c"></i></span><?= sayi($tepeEsik) ?> KP tepe</span>
    </div>
    <div class="sag">
      <div class="n"><?= count($doluluk) ?></div>
      <span class="etiket">Dolu kademe</span>
    </div>
  </div>
</div>

<?php if ($ben !== null && $benimKademe !== null): ?>
  <?php /* Şeridin çizgisi ve KP sayısı oyuncunun KENDİ kademe renginde —
           profil ve sıralama sayfasındaki kuralın aynısı. */ ?>
  <div class="durumSerit" style="border-bottom-color:<?= esc($benimKademe['renk']) ?>">
    <?= img(kademeIkonu($benimKademe)) ?>
    <div>
      <div class="buyuk" style="color:<?= esc($benimKademe['renk']) ?>"><?= esc($benimKademe['ad']) ?></div>
      <div class="kucukAlt">
        <?php if ($benimKademe['kalan'] > 0 && $benimKademe['ustAdi'] !== ''): ?>
          <b><?= esc($benimKademe['ustAdi']) ?></b> için <b><?= sayi($benimKademe['kalan']) ?> KP</b> kaldı
        <?php else: ?>
          Merdivenin tepesindesin, üstünde kademe yok
        <?php endif; ?>
      </div>
    </div>
    <div class="saga">
      <div class="kp" style="color:<?= esc($benimKademe['renk']) ?>"><?= sayi($ben['kp']) ?></div>
      <span class="etiket">Kazanılan puan</span>
    </div>
  </div>
<?php endif; ?>

<?= $kisaltSerit ?>

<?php
// $gorunen zaten ters sırada (Radiant başta); düz ilerliyoruz.
foreach ($gorunen as $no):
    $i = (int) array_search($no, $numaralar, true);
    $altEsik = $esikler[$no];
    $k = kademe($altEsik);
    $kisi = $doluluk[$no] ?? 0;
    $ustEsik = $i + 1 < count($numaralar) ? $esikler[$numaralar[$i + 1]] : null;
    $benimMi = $benimKademe !== null && $benimKademe['no'] === $no;
?>
  <div class="basamak<?= $kisi === 0 ? ' bos' : '' ?><?= $benimMi ? ' benim' : '' ?>">
    <span class="sayi"><?= $i + 1 ?></span>
    <?= img(kademeIkonu($k)) ?>
    <span class="isim2" style="color:<?= esc($k['renk']) ?>"><?= esc($k['ad']) ?></span>
    <span class="esik"><?= sayi($altEsik) ?> <s>KP</s></span>
    <span class="aralik">
      <?php if ($ustEsik === null): ?>
        <?= sayi($altEsik) ?> ve üstü
      <?php else: ?>
        <?= sayi($altEsik) ?> &ndash; <?= sayi($ustEsik - 1) ?> arası &middot; <?= sayi($ustEsik - $altEsik) ?> KP genişlik
      <?php endif; ?>
    </span>
    <span class="kisi">
      <?php if ($kisi > 0): ?>
        <i style="width:<?= max(6, (int) round($kisi / max(1, $enKalabalik) * 54)) ?>px;background:<?= esc($k['renk']) ?>"></i><?= $kisi ?> oyuncu
      <?php else: ?>
        boş
      <?php endif; ?>
    </span>
  </div>
<?php endforeach; ?>

<div class="dip">
  <span class="etiket">Eşikler her kademede genişliyor<span class="tik"><i></i><i class="b"></i><i class="c"></i></span>Iron 1&rarr;2 arası 90 KP, tepede <?= sayi($tepeEsik - $esikler[$numaralar[count($numaralar) - 2]]) ?> KP</span>
  <span class="etiket">Puan değerleri sunucuda değil web'de hesaplanıyor</span>
</div>

</body>
</html>
