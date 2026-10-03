<?php
declare(strict_types=1);

/**
 * Oyuncu profili — MOTD içinde açılan büyük istatistik sayfası.
 *
 * Sıralamadaki satıra tıklanınca buraya geliniyor, üstteki bağlantıyla geri
 * dönülüyor. MOTD penceresi bir tarayıcı denetimi olduğu için gezinme
 * çalışıyor; oyuncunun oyundan çıkması gerekmiyor.
 *
 * Blok sırası bilinçli: kim (ajan + rütbe), ne kadar (altı büyük sayı),
 * nasıl (silah ve harita kırılımı), sonra ayrıntı. Oyuncu önce rozetine,
 * sonra kill sayısına, en son hangi silahla iyi olduğuna bakıyor.
 *
 * kaesra-tema.css'teki yasak listesi burada da geçerli: var(), clip-path,
 * mask-image, object-fit, grid, gap yok — motor IE11 sınıfı.
 */

require __DIR__ . '/kaesra-ortak.php';
motdBasliklari();

$oyuncular = oyuncuListesi();

$istenen = (string) ($_GET['kimlik'] ?? '');
$oyuncu = null;
$sira = 0;

foreach ($oyuncular as $i => $o) {
    if ($o['kimlik'] === $istenen || ($istenen === '' && $i === 0)) {
        $oyuncu = $o;
        $sira = $i + 1;
        break;
    }
}

/** Önceki/sonraki sıradaki oyuncu — profilden çıkmadan gezinmek için. */
$onceki = $sira > 1 ? $oyuncular[$sira - 2] : null;
$sonraki = $oyuncu !== null && $sira < count($oyuncular) ? $oyuncular[$sira] : null;
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<style><?= tema() ?>

/* --- yalniz bu sayfaya ozgu --- */
.gezinme {
  display:flex; justify-content:space-between; align-items:center;
  padding:6px 22px; background:#0E161F; border-bottom:1px solid rgba(236,232,225,.10);
}
.gezinme a { color:#93A2AE; text-decoration:none; font-family:'Kaesra Dar',sans-serif;
             font-weight:600; font-size:13px; letter-spacing:.18em; text-transform:uppercase; }
.gezinme a:hover { color:#FF4655; }
.gezinme .ortada { color:#5E7080; }

.kimlikBlok {
  position:relative; overflow:hidden; min-height:112px;
  border-bottom:2px solid #FF4655;
}
.kimlikBlok .zemin { position:absolute; left:0; top:0; right:0; bottom:0; }
.kimlikBlok .bust { position:absolute; left:4px; bottom:-18px; height:152px; }
.kimlikBlok .perde3 {
  position:absolute; left:0; top:0; right:0; bottom:0;
  background:linear-gradient(90deg, rgba(11,18,25,0) 0%, rgba(11,18,25,.62) 20%, rgba(11,18,25,.95) 36%, #0B1219 100%);
}
.kimlikBlok .ic { position:relative; padding:11px 22px 10px 152px; display:flex; justify-content:space-between; align-items:flex-start; }

.buyukIsim { font-family:'Kaesra Display',sans-serif; font-weight:400; font-size:28px; line-height:.96;
             margin:2px 0 0; text-transform:uppercase; color:#ECE8E1; }
.kimlikNo { color:#5E7080; font-size:11px; margin-top:4px; letter-spacing:.04em; }
.kimlikNo img.bayrak { width:18px; height:12px; vertical-align:-2px; margin-right:6px;
                       border:1px solid rgba(236,232,225,.16); }
.kimlikNo b { color:#93A2AE; font-weight:600; letter-spacing:.10em; }
.kimlikNo .ayrac { margin:0 7px; color:#33454F; }
.ajanSatir { margin-top:5px; color:#93A2AE; font-size:12px; }
.ajanSatir img { width:20px; height:20px; vertical-align:middle; margin-right:7px; }

.rutbeBlok { text-align:right; white-space:nowrap; }
.rutbeBlok img { width:58px; height:58px; vertical-align:middle; margin-left:13px; }
.rutbeBlok .yazi { display:inline-block; vertical-align:middle; text-align:right; }
.rutbeBlok .ad { font-family:'Kaesra Dar',sans-serif; font-weight:600; font-size:15px;
                 letter-spacing:.20em; text-transform:uppercase; }
.rutbeBlok .kp { font-family:'Kaesra Display',sans-serif; font-size:28px; line-height:1; color:#FF4655; margin-top:1px; }
.rutbeBlok .rr { width:140px; height:3px; background:rgba(236,232,225,.12); margin-top:6px; margin-left:auto; }
.rutbeBlok .rr i { display:block; height:3px; background:#FF4655; }
.rutbeBlok .kalan { color:#6E7F8C; font-size:11px; margin-top:4px; }

.buyukler { display:flex; border-bottom:1px solid rgba(236,232,225,.10); }
.buyukler > div { flex:1; padding:7px 18px; border-right:1px solid rgba(236,232,225,.05); }
.buyukler > div:last-child { border-right:0; }
.buyukler .n { font-family:'Kaesra Display',sans-serif; font-size:21px; line-height:1.06; margin-top:2px; color:#ECE8E1; }
.buyukler .n.vurgu { color:#FF4655; }

.ikili { display:flex; }
.ikili > section { flex:1; padding:10px 22px 12px; }
.ikili > section:first-child { border-right:1px solid rgba(236,232,225,.08); }
/* Tek bolum gosterilirken ayrac gereksiz. */
.ikili.tekli > section { border-right:0; }
.bolumBasi { margin:0 0 8px; font-family:'Kaesra Dar',sans-serif; font-weight:600; font-size:14px;
             letter-spacing:.22em; text-transform:uppercase; color:#93A2AE; }

.satir { padding:3px 0; border-bottom:1px solid rgba(236,232,225,.05); }
.satir:last-child { border-bottom:0; }
.satir .ust { display:flex; justify-content:space-between; align-items:center; }
.satir .sol { color:#ECE8E1; }
.satir .sol img.silahIkon { width:58px; height:20px; vertical-align:middle; margin-right:9px; }
.satir .sol img.haritaIkon { width:40px; height:22px; vertical-align:middle; margin-right:9px; }
.satir .ham { color:#4F6376; font-family:'Kaesra Dar',sans-serif; font-weight:600;
              font-size:11px; letter-spacing:.14em; text-transform:uppercase; margin-left:8px; }
.satir .deger { font-family:'Kaesra Display',sans-serif; font-size:19px; color:#ECE8E1; }
.cizgi { height:3px; background:rgba(236,232,225,.08); margin-top:5px; }
.cizgi i { display:block; height:3px; background:#FF4655; }
.cizgi.mavi i { background:#4FC9D6; }

/* Karsilastirma grafigi. canvas/SVG yok; her cubuk konumlandirilmis bir
   div ve yuksekligi yuzde. Motor IE11 sinifi, cizim API'si guvenilmez. */
.grafik { padding:11px 22px 13px; border-bottom:1px solid rgba(236,232,225,.10); }
.grafikBasi { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:9px; }
.grafikBasi .anahtar span { font-family:'Kaesra Dar',sans-serif; font-weight:600; font-size:11px;
                            letter-spacing:.16em; text-transform:uppercase; color:#5E7080; margin-left:18px; }
.grafikBasi .anahtar i { display:inline-block; width:11px; height:11px; vertical-align:-1px; margin-right:6px; }

.sutunlar { display:flex; height:132px; }
.sutun { flex:1; position:relative; margin-right:22px; }
.sutun:last-child { margin-right:0; }

/* Sifir cizgisi: cubuklar bunun uzerinde duruyor. */
.sutun .taban { position:absolute; left:0; right:0; bottom:26px; height:1px; background:rgba(236,232,225,.14); }

.sutun .cift { position:absolute; left:0; right:0; bottom:25px; height:86px; }
.sutun .cubuk { position:absolute; bottom:0; width:42%; }
.sutun .cubuk.ben { left:4%; background:#FF4655; }
.sutun .cubuk.ort { right:4%; background:#33454F; }

.sutun .rakam { position:absolute; bottom:100%; left:4%; margin-bottom:5px;
                font-family:'Kaesra Display',sans-serif; font-size:17px; color:#ECE8E1; white-space:nowrap; }
.sutun .oran { position:absolute; bottom:8px; right:4%;
               font-family:'Kaesra Dar',sans-serif; font-weight:600; font-size:11px;
               letter-spacing:.10em; color:#5E7080; }
.sutun .adAlt { position:absolute; bottom:0; left:0; right:0;
                font-family:'Kaesra Dar',sans-serif; font-weight:600; font-size:12px;
                letter-spacing:.16em; text-transform:uppercase; color:#93A2AE; }

.ek { display:flex; flex-wrap:wrap; border-top:1px solid rgba(236,232,225,.10); }
.ek > div { width:25%; padding:7px 22px; border-right:1px solid rgba(236,232,225,.05);
            border-bottom:1px solid rgba(236,232,225,.05); }
.ek .v { font-family:'Kaesra Display',sans-serif; font-size:17px; margin-top:1px; color:#ECE8E1; }

.bolumSerit {
  display:flex; padding:6px 22px; background:#0E161F;
  border-bottom:1px solid rgba(236,232,225,.10);
}
.bolumSerit a, .bolumSerit b {
  margin-right:6px; padding:5px 14px; text-decoration:none;
  font-family:'Kaesra Dar',sans-serif; font-weight:600;
  font-size:12px; letter-spacing:.14em; text-transform:uppercase;
}
.bolumSerit a { color:#93A2AE; border:1px solid rgba(236,232,225,.14); }
.bolumSerit a:hover { color:#0B1219; background:#FF4655; border-color:#FF4655; }
.bolumSerit b { color:#0B1219; background:#FF4655; }

.acikRozet { color:#6BDB8B; font-weight:600; letter-spacing:.06em; }
.steamBag { color:#93A2AE; text-decoration:none; }
.steamBag:hover { color:#FF4655; }

/* --- mac gecmisi --- */
.macBasi { display:flex; justify-content:space-between; align-items:baseline; padding:10px 22px 2px; }
.macBasi .karne { font-family:'Kaesra Dar',sans-serif; font-weight:600; font-size:12px;
                  letter-spacing:.14em; text-transform:uppercase; color:#93A2AE; }
.macBasi .karne b { color:#ECE8E1; }

/* On mac 550 piksele ancak tek satirlik siralarla sigiyor: harita ile
   tarih alt alta degil yan yana, dikey dolgu iki piksel. */
.mac { display:flex; align-items:center; padding:2px 22px;
       border-bottom:1px solid rgba(236,232,225,.05); }
.mac:hover { background:rgba(236,232,225,.03); }
.mac .yer { width:190px; white-space:nowrap; }
.mac .yer .h { display:inline; color:#ECE8E1; font-weight:500; }
.mac .yer .t { display:inline; color:#5E7080; font-size:11px; margin-left:8px; }
.mac .rozet { width:92px; text-align:center; font-family:'Kaesra Dar',sans-serif; font-weight:600;
              font-size:10px; letter-spacing:.12em; padding:2px 0; }
.mac .rozet.g { background:rgba(107,219,139,.13); color:#6BDB8B; }
.mac .rozet.m { background:rgba(255,70,85,.13); color:#FF4655; }
.mac .rozet.b { background:rgba(236,232,225,.08); color:#93A2AE; }
.mac .skor { width:100px; text-align:center; font-family:'Kaesra Display',sans-serif; font-size:15px; }
.mac .skor s { color:#4F6376; font-size:12px; text-decoration:none; font-family:'Kaesra Dar',sans-serif; }
.mac .kda { flex:1; text-align:center; color:#93A2AE; }
.mac .kda b { color:#ECE8E1; font-weight:600; }
.mac .fark { width:90px; text-align:right; font-family:'Kaesra Display',sans-serif; font-size:14px; }
.mac .fark.arti { color:#6BDB8B; }
.mac .fark.eksi { color:#FF4655; }
.mac .fark.sifir { color:#5E7080; }

.macYok { padding:30px 22px; color:#6E7F8C; }

.ozet { display:flex; border-top:1px solid rgba(236,232,225,.10); background:rgba(236,232,225,.018); }
.ozet > div { flex:1; padding:9px 18px 10px; border-right:1px solid rgba(236,232,225,.05); }
.ozet > div:last-child { border-right:0; }
.ozet .genis { flex:1.35; }
.ozet .v { font-family:'Kaesra Display',sans-serif; font-size:20px; line-height:1.1; margin-top:3px; color:#ECE8E1; white-space:nowrap; }
.ozet .alt { font-family:'Kaesra Dar',sans-serif; font-weight:500; font-size:12px; letter-spacing:.08em; color:#6E7F8C; margin-left:6px; }
.ozet .kOran { height:3px; background:rgba(236,232,225,.10); margin-top:6px; }
.ozet .kOran i { display:block; height:3px; }
.ozet .ozetSilah { width:52px; height:18px; vertical-align:middle; margin-right:8px; }

.yok { padding:40px 22px; }
</style>
</head>
<body style="background:#0B1219;color:#ECE8E1">

<div class="gezinme">
  <a href="siralama-motd.php">&larr; Sıralamaya dön</a>
  <span class="ortada etiket"><?= $oyuncu === null ? 'Kayıt bulunamadı' : $sira . '. sıra' ?></span>
  <span>
    <?php if ($onceki !== null): ?>
      <a href="oyuncu-motd.php?kimlik=<?= urlencode($onceki['kimlik']) ?>">&uarr; <?= esc($onceki['ad']) ?></a>
    <?php endif; ?>
    <?php if ($sonraki !== null): ?>
      <a href="oyuncu-motd.php?kimlik=<?= urlencode($sonraki['kimlik']) ?>" style="margin-left:18px">&darr; <?= esc($sonraki['ad']) ?></a>
    <?php endif; ?>
  </span>
</div>

<?php if ($oyuncu === null): ?>
  <div class="yok">
    <span class="etiket">Kayıt yok</span>
    <h1 class="buyukIsim">Bu kimlik kaesrade değil</h1>
    <p style="color:#6E7F8C;max-width:620px">
      Aranan kimlik <b style="color:#ECE8E1"><?= esc($istenen) ?></b>. Sunucuya hiç bağlanmamış bir
      hesap olabilir ya da yazımda hata var. Oyun içinde <b style="color:#ECE8E1">status</b> yazarak
      kendi kimliğini görebilirsin.
    </p>
  </div>
<?php else: ?>

<?php
$k = kademe($oyuncu['kp']);
$a = ajan($oyuncu['ajan']);

/*
 * Kırılımlar depodan EKLENME sırasıyla geliyor (ilk kullanılan silah en
 * üstte), tohum veride ise zaten büyükten küçüğe. Gerçek senkron verisinde
 * sıralamadan önce çubuk çizmek "en iyi silah" başlığını yalancı çıkarır.
 *
 * max([]) diye bir şey de yok: sunucuya yeni bağlanmış oyuncunun silah
 * listesi boş ve sayfa patlıyordu. Boş listede tavan 1 — çubuk çizilmez.
 */
$silahlar = $oyuncu['silahlar'];
arsort($silahlar);
$haritalar = $oyuncu['haritalar'];
arsort($haritalar);

$enCokSilah = $silahlar === [] ? 1 : max($silahlar);
$enCokHarita = $haritalar === [] ? 1 : max($haritalar);

$toplamTur = $oyuncu['kazanilan'] + $oyuncu['kaybedilen'];
$kazanmaOrani = $toplamTur > 0 ? (int) round($oyuncu['kazanilan'] / $toplamTur * 100) : 0;
$isabetOrani = $oyuncu['atis'] > 0 ? $oyuncu['isabet'] / $oyuncu['atis'] * 100 : 0.0;

/* Maç geçmişi: en yeni üstte. Karne satırı sekme başlığının yanına gidiyor. */
$maclar = array_reverse($oyuncu['maclar'] ?? []);
$macG = $macM = $macB = $macKpToplam = 0;
foreach ($maclar as $mc) {
    if ($mc['sonuc'] > 0)      { $macG++; }
    elseif ($mc['sonuc'] < 0)  { $macM++; }
    else                       { $macB++; }
    $macKpToplam += $mc['kp'];
}

/* Çevrimiçi rozeti: son partisi üç dakikadan yeniyse oyuncu içeride demektir
   (senkron her tur sonu çalışıyor, bir tur bundan uzun sürmüyor). */
$simdi = time();
$sonAn = (int) ($oyuncu['sonAn'] ?? 0);
$cevrimici = $sonAn > 0 && $simdi - $sonAn < 180;

$steamBag = steam64($oyuncu['kimlik']);

$ilkKademe = ($oyuncu['ilkKademeNo'] ?? null) === null
    ? null
    : (katalog()['kademe'][$oyuncu['ilkKademeNo']] ?? null);

/*
 * Grafik için sunucu ortalaması.
 *
 * Referans üründe bu grafik oyuncunun dört sayısını yan yana koyuyor:
 * öldürme 873, ölüm 455, asist 202, headshot 470. Çubukların birbirine
 * göre yüksekliği hiçbir şey söylemiyor — herkesin öldürmesi ölümünden
 * fazla, herkeste aynı şekil çıkıyor. Buradaki grafik onun yerine oyuncuyu
 * sunucu ortalamasıyla karşılaştırıyor: "873 kill" tek başına iyi mi kötü
 * mü belli değil, "ortalamanın 2,4 katı" belli.
 */
$kisi = max(1, count($oyuncular));
$ortalama = [
    'kill'  => array_sum(array_column($oyuncular, 'kill')) / $kisi,
    'death' => array_sum(array_column($oyuncular, 'death')) / $kisi,
    'asist' => array_sum(array_column($oyuncular, 'asist')) / $kisi,
    'hs'    => array_sum(array_column($oyuncular, 'hs')) / $kisi,
    'mvp'   => array_sum(array_column($oyuncular, 'mvp')) / $kisi,
];

/** [etiket, oyuncunun değeri, ortalama, düşük olması iyi mi] */
$olculer = [
    ['Öldürme', $oyuncu['kill'],  $ortalama['kill'],  false],
    ['Ölüm',    $oyuncu['death'], $ortalama['death'], true],
    ['Asist',   $oyuncu['asist'], $ortalama['asist'], false],
    ['Headshot',$oyuncu['hs'],    $ortalama['hs'],    false],
    ['Tur MVP', $oyuncu['mvp'],   $ortalama['mvp'],   false],
];

// Tek ölçek: beş sütun aynı tavana göre çiziliyor, yoksa çubuk yükseklikleri
// karşılaştırılamaz olurdu ve grafik süse dönerdi.
$tavan = 1.0;
foreach ($olculer as [$ad, $deger, $ort, $tersi]) {
    $tavan = max($tavan, (float) $deger, $ort);
}

/*
 * Bölümler.
 *
 * Sayfanın tamamı 1111 piksel; MOTD panelinin iç yüksekliği ~550 ve pencere
 * kaydırılamıyor. Küçültmekle kapanacak bir fark değil — bir şeyin yarısını
 * göstermektense dördü ayrı bölüm yapmak dürüst olan.
 *
 * Bölüm bağlantıları kimlik bloğunun hemen altında, yani her zaman ekranda.
 */
$bolumler = [
    'genel'   => 'Genel',
    'maclar'  => 'Maçlar',
    'silah'   => 'Silahlar',
    'harita'  => 'Haritalar',
    'ayrinti' => 'Ayrıntı',
];

$bolum = (string) ($_GET['bolum'] ?? 'genel');
if (!isset($bolumler[$bolum])) {
    $bolum = 'genel';
}

$bolumAdres = static fn(string $b): string =>
    'oyuncu-motd.php?kimlik=' . urlencode($oyuncu['kimlik']) . '&bolum=' . $b;
?>

<?php /* Alt çizgi oyuncunun kademe renginde: sayfanın kimliği yukarıdan
         aşağıya tek renkten okunuyor — rozet, KP, çizgi, grafik, kazanma
         çubuğu. Sabit kırmızı burada beşinci tekerlekti. */ ?>
<div class="kimlikBlok" style="border-bottom-color:<?= esc($k['renk']) ?>">
  <div class="zemin" style="background:linear-gradient(96deg, <?= esc($a['renk']) ?> 0%, #131E29 42%, #0B1219 100%)"></div>
  <?= img($a['bust'], 'bust') ?>
  <div class="perde3"></div>

  <div class="ic">
    <div>
      <span class="etiket"><?= esc(ayar('marka', 'Kaesra')) ?><span class="tik"><i></i><i class="b"></i><i class="c"></i></span><?= esc(ayar('sezon', 'Sezon 1')) ?></span>
      <h1 class="buyukIsim"><?= esc($oyuncu['ad']) ?></h1>
      <div class="kimlikNo">
        <?php $bayrak = bayrak($oyuncu['ulke'] ?? null); ?>
        <?php if ($bayrak !== null): ?>
          <img class="bayrak" src="<?= esc($bayrak) ?>" alt=""><b><?= esc(strtoupper((string) $oyuncu['ulke'])) ?></b>
          <span class="ayrac">&middot;</span>
        <?php endif; ?>
        <?php if ($cevrimici): ?>
          <b class="acikRozet">&#9679; Çevrimiçi</b>
        <?php elseif ($sonAn > 0): ?>
          Son görülme <?= esc(gorel($sonAn, $simdi)) ?>
        <?php endif; ?>
        <?php if ($cevrimici || $sonAn > 0): ?><span class="ayrac">&middot;</span><?php endif; ?>
        <?= esc($oyuncu['kimlik']) ?>
        <?php if ($steamBag !== null): ?>
          <span class="ayrac">&middot;</span>
          <a class="steamBag" href="https://steamcommunity.com/profiles/<?= esc($steamBag) ?>">Steam profili &raquo;</a>
        <?php endif; ?>
      </div>
      <?php if ($a['ad'] !== null): ?>
        <div class="ajanSatir"><?= img($a['dosya']) ?><?= esc($a['ad']) ?><?php if ($a['rol'] !== ''): ?> &middot; <?= esc($a['rol']) ?><?php endif; ?></div>
      <?php endif; ?>
    </div>

    <?php /* Sayı, çubuk ve ad rozetle AYNI renkte — sıralama sayfasındaki
             kuralın aynısı: kırmızı yalnız tıklanabilir şeylerde. */ ?>
    <div class="rutbeBlok">
      <div class="yazi">
        <div class="ad" style="color:<?= esc($k['renk']) ?>"><?= esc($k['ad']) ?></div>
        <div class="kp" style="color:<?= esc($k['renk']) ?>"><?= sayi($oyuncu['kp']) ?></div>
        <div class="rr"><i style="width:<?= $k['rr'] ?>%;background:<?= esc($k['renk']) ?>"></i></div>
        <div class="kalan">
          <?= $k['kalan'] === null
              ? 'Merdivenin tepesi'
              : esc($k['ustAdi']) . ' için ' . sayi($k['kalan']) . ' KP' ?>
        </div>
      </div>
      <?= img(kademeIkonu($k), '', (string) $k['ad']) ?>
    </div>
  </div>
</div>

<div class="bolumSerit">
  <?php foreach ($bolumler as $anahtar => $etiket): ?>
    <?php if ($anahtar === $bolum): ?>
      <b><?= esc($etiket) ?></b>
    <?php else: ?>
      <a href="<?= esc($bolumAdres($anahtar)) ?>"><?= esc($etiket) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</div>

<?php if ($bolum === 'genel'): ?>

<div class="buyukler">
  <div><span class="etiket">Öldürme</span><div class="n" style="color:<?= esc($k['renk']) ?>"><?= sayi($oyuncu['kill']) ?></div></div>
  <div><span class="etiket">Ölüm</span><div class="n"><?= sayi($oyuncu['death']) ?></div></div>
  <div><span class="etiket">K / D</span><div class="n"><?= kd($oyuncu['kill'], $oyuncu['death']) ?></div></div>
  <div><span class="etiket">Headshot</span><div class="n">%<?= hsYuzde($oyuncu['hs'], $oyuncu['kill']) ?></div></div>
  <div><span class="etiket">Tur MVP</span><div class="n"><?= sayi($oyuncu['mvp']) ?></div></div>
  <div><span class="etiket">Sunucuda</span><div class="n"><?= sure($oyuncu['saniye']) ?></div></div>
</div>

<div class="grafik">
  <div class="grafikBasi">
    <h2 class="bolumBasi" style="margin:0">Sunucu ortalamasına göre</h2>
    <div class="anahtar">
      <span><i style="background:<?= esc($k['renk']) ?>"></i><?= esc($oyuncu['ad']) ?></span>
      <span><i style="background:#33454F"></i><?= count($oyuncular) ?> oyuncu ortalaması</span>
    </div>
  </div>

  <div class="sutunlar">
    <?php foreach ($olculer as [$etiket, $deger, $ort, $azIyi]): ?>
      <?php
      $kat = $ort > 0 ? $deger / $ort : 0.0;
      // Ölümde az olan iyi; oranı ters çevirmiyoruz ama işaretini çeviriyoruz.
      $iyi = $azIyi ? $kat < 1 : $kat > 1;
      ?>
      <div class="sutun">
        <div class="cift">
          <i class="cubuk ben" style="height:<?= max(2, (int) round($deger / $tavan * 100)) ?>%;background:<?= esc($k['renk']) ?>"></i>
          <i class="cubuk ort" style="height:<?= max(2, (int) round($ort / $tavan * 100)) ?>%"></i>
          <div class="rakam"><?= sayi((int) $deger) ?></div>
        </div>
        <div class="taban"></div>
        <div class="oran" style="color:<?= $iyi ? '#ECE8E1' : '#5E7080' ?>">
          <?= $kat >= 10 ? sayi($kat, 0) : sayi($kat, 1) ?>&times;
        </div>
        <div class="adAlt"><?= esc($etiket) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php /* Alt özet: sayfanın son ~110 pikseli boş kalıyordu ve "Genel" sekmesi
         yarım bitmiş görünüyordu. Buradaki dört hücre diğer üç sekmenin
         özeti — kazanma oranı, isabet, en iyi silah, en çok harita. Oyuncu
         sekmelere hiç tıklamasa bile profil tek ekranda tamam. */ ?>
<div class="ozet">
  <div class="genis">
    <span class="etiket">Kazanma oranı</span>
    <div class="v">%<?= $kazanmaOrani ?> <span class="alt"><?= sayi($oyuncu['kazanilan']) ?> G &middot; <?= sayi($oyuncu['kaybedilen']) ?> M</span></div>
    <div class="kOran"><i style="width:<?= $kazanmaOrani ?>%;background:<?= esc($k['renk']) ?>"></i></div>
  </div>
  <div>
    <span class="etiket">İsabet</span>
    <div class="v">%<?= sayi($isabetOrani, 1) ?> <span class="alt"><?= sayi($oyuncu['isabet']) ?> / <?= sayi($oyuncu['atis']) ?></span></div>
  </div>
  <div>
    <span class="etiket">En iyi silah</span>
    <div class="v">
      <?php if ($silahlar === []): ?>
        <span class="alt">Henüz yok</span>
      <?php else: $ozS = silah(array_key_first($silahlar)); ?>
        <?php if ($ozS['dosya'] !== null): ?><img class="ozetSilah" src="<?= esc($ozS['dosya']) ?>" alt=""><?php endif; ?><?= esc($ozS['ad']) ?>
      <?php endif; ?>
    </div>
  </div>
  <div>
    <span class="etiket">En çok oynanan</span>
    <div class="v">
      <?php if ($haritalar === []): ?>
        <span class="alt">Henüz yok</span>
      <?php else: ?>
        <?= esc((string) array_key_first($haritalar)) ?> <span class="alt"><?= sure((int) reset($haritalar)) ?></span>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php endif; ?>

<?php if ($bolum === 'maclar'): ?>

<div class="macBasi">
  <h2 class="bolumBasi" style="margin:0">Son maçlar</h2>
  <?php if ($maclar !== []): ?>
    <span class="karne">
      <b><?= $macG ?>G</b> <?= $macM ?>M<?= $macB > 0 ? " {$macB}B" : '' ?>
      &middot; toplam <b><?= $macKpToplam >= 0 ? '+' : '' ?><?= sayi($macKpToplam) ?> KP</b>
    </span>
  <?php endif; ?>
</div>

<?php if ($maclar === []): ?>
  <div class="macYok">
    Henüz kapanmış maç kaydı yok. Maçlar 13 turda biter, 12-12'de uzatmaya
    gider; harita bitince karne buraya işlenir.
  </div>
<?php else: ?>
  <?php foreach ($maclar as $mc): ?>
    <div class="mac">
      <div class="yer">
        <div class="h"><?= esc((string) $mc['harita']) ?></div>
        <div class="t"><?= esc((string) $mc['tarih']) ?></div>
      </div>
      <?php if ($mc['sonuc'] > 0): ?>
        <div class="rozet g">GALİBİYET</div>
      <?php elseif ($mc['sonuc'] < 0): ?>
        <div class="rozet m">MAĞLUBİYET</div>
      <?php else: ?>
        <div class="rozet b">BERABERE</div>
      <?php endif; ?>
      <div class="skor"><?= $mc['skor'][0] ?> <s>&ndash;</s> <?= $mc['skor'][1] ?></div>
      <div class="kda"><b><?= $mc['kda'][0] ?></b> / <?= $mc['kda'][1] ?> / <?= $mc['kda'][2] ?></div>
      <?php $fk = (int) $mc['kp']; ?>
      <div class="fark <?= $fk > 0 ? 'arti' : ($fk < 0 ? 'eksi' : 'sifir') ?>">
        <?= $fk > 0 ? '+' : '' ?><?= sayi($fk) ?> KP
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php endif; ?>

<?php if ($bolum === 'silah'): ?>
<div class="ikili tekli">
  <section>
    <h2 class="bolumBasi">Silah dağılımı</h2>
    <?php foreach ($silahlar as $csAdi => $adet): ?>
      <?php $s = silah($csAdi); ?>
      <div class="satir">
        <div class="ust">
          <span class="sol">
            <?php if ($s['dosya'] !== null): ?><img class="silahIkon" src="<?= esc($s['dosya']) ?>" alt=""><?php endif; ?>
            <?= esc($s['ad']) ?><span class="ham"><?= esc($s['ham']) ?></span>
          </span>
          <span class="deger"><?= sayi($adet) ?></span>
        </div>
        <div class="cizgi"><i style="width:<?= (int) round($adet / $enCokSilah * 100) ?>%"></i></div>
      </div>
    <?php endforeach; ?>
  </section>
</div>
<?php endif; ?>

<?php if ($bolum === 'harita'): ?>
<div class="ikili tekli">
  <section>
    <h2 class="bolumBasi">Harita dağılımı</h2>
    <?php foreach ($haritalar as $haritaAdi => $saniye): ?>
      <?php $h = harita($haritaAdi); ?>
      <div class="satir">
        <div class="ust">
          <span class="sol">
            <?php if ($h['dosya'] !== null): ?><img class="haritaIkon" src="<?= esc($h['dosya']) ?>" alt=""><?php endif; ?>
            <?= esc($h['ad']) ?>
          </span>
          <span class="deger"><?= sure($saniye) ?></span>
        </div>
        <div class="cizgi mavi"><i style="width:<?= (int) round($saniye / $enCokHarita * 100) ?>%"></i></div>
      </div>
    <?php endforeach; ?>
  </section>
</div>

<?php endif; ?>

<?php if ($bolum === 'ayrinti'): ?>
<div class="ek">
  <div><span class="etiket">Asist</span><div class="v"><?= sayi($oyuncu['asist']) ?></div></div>
  <div><span class="etiket">Toplam hasar</span><div class="v"><?= sayi($oyuncu['hasar']) ?></div></div>
  <div><span class="etiket">Atılan mermi</span><div class="v"><?= sayi($oyuncu['atis']) ?></div></div>
  <div><span class="etiket">İsabet oranı</span><div class="v">%<?= sayi($isabetOrani, 1) ?></div></div>
  <div><span class="etiket">Spike kurma</span><div class="v"><?= sayi($oyuncu['kurma']) ?></div></div>
  <div><span class="etiket">Spike çözme</span><div class="v"><?= sayi($oyuncu['cozme']) ?></div></div>
  <div><span class="etiket">Kazanılan / kaybedilen tur</span><div class="v"><?= sayi($oyuncu['kazanilan']) ?> / <?= sayi($oyuncu['kaybedilen']) ?></div></div>
  <div>
    <span class="etiket">İlk rütbe</span>
    <div class="v">
      <?php if ($ilkKademe !== null): ?>
        <span style="color:<?= esc($ilkKademe['renk']) ?>"><?= esc($ilkKademe['ad']) ?></span>
      <?php else: ?>&mdash;<?php endif; ?>
      <span style="color:#5E7080;font-family:'Kaesra Dar',sans-serif;font-size:12px"><?= esc($oyuncu['ilk']) ?></span>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>
</body>
</html>
