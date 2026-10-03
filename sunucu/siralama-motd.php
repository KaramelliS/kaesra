<?php
declare(strict_types=1);

/**
 * Sıralama — MOTD içinde açılan ana ekran.
 *
 * Görsel dil sicil-tema.css'te; buradaki CSS yalnız bu sayfaya özgü olan.
 * O dosyanın başındaki uyarı burada da geçerli: var(), clip-path, mask-image,
 * object-fit, grid ve gap YASAK — motor IE11 sınıfı.
 *
 * Sayfanın ritmi bilerek eşit değil: 62 punto marka, altında geniş bir
 * şampiyon paneli, sonra sıkışık bir tablo. Üç bölgenin de aynı ağırlıkta
 * olduğu ilk sürüm "sade" görünüyordu — sorun renkte değil, hiyerarşi
 * yokluğundaydı.
 */

require __DIR__ . '/sicil-ortak.php';
motdBasliklari();

$oyuncular = oyuncuListesi();

/*
 * Tek oyunculu ve boş sıralama GERÇEK durumlar: sunucuya ilk oyuncu
 * girdiğinde listede bir kişi var, depo sıfırlandığında hiç kimse yok.
 * Eskiden $oyuncular[1] kayıtsız şartsız okunuyordu ve sayfa
 * "Undefined array key 1" uyarısıyla açılıyordu. Bu kozmetik bir sorun
 * değil: MOTD penceresi PHP uyarısını olduğu gibi oyuncunun ekranına
 * basıyor, yani sunucusuna ilk kez bir oyuncu giren herkes bunu görüyordu.
 */
$bir = $oyuncular[0] ?? null;
$iki = $oyuncular[1] ?? null;

$birKademe = kademe((int) ($bir['kp'] ?? 0));
$birAjan   = ajan($bir['ajan'] ?? null);
$birSilah  = silah(array_key_first($bir['silahlar'] ?? []));
$fark      = ($bir !== null && $iki !== null) ? $bir['kp'] - $iki['kp'] : null;
$toplamKill = array_sum(array_column($oyuncular, 'kill'));

/* KP çubuklarının ölçeği. Birinci her zaman tam dolu, gerisi ona oranlı. */
$enYuksek = max(1, (int) ($bir['kp'] ?? 0));

/*
 * Sıra numarası oyuncuListesi()'nden geliyor, yani filtreden ÖNCE bağlanmış
 * oluyor: "efe" arayan oyuncu onun 9. sırada olduğunu görüyor, arama
 * sonucundaki 1. satır olduğunu değil. Filtrelenmiş listeyi 1'den
 * numaralamak, sıralama sayfasında verilebilecek en yanıltıcı bilgi olurdu.
 */

$ara = trim((string) ($_GET['ara'] ?? ''));
$suzuluyor = $ara !== '';

$gosterilen = $oyuncular;
if ($suzuluyor) {
    $anahtar = aramaAnahtari($ara);
    $gosterilen = array_values(array_filter(
        $oyuncular,
        static fn(array $o): bool => str_contains(aramaAnahtari($o['ad']), $anahtar)
    ));
} else {
    // Birinci üstteki şampiyon panelinde zaten duruyor, tabloda tekrar etmiyor.
    $gosterilen = array_slice($oyuncular, 1);
}

/*
 * Sayfalama.
 *
 * MOTD penceresi kaydırılamıyor — GoldSrc'in HTML denetimi fare tekerini
 * iletmiyor ve sayfa panelden çok daha uzun. Referans üründe de aynı sorun
 * var, onlar sağa ▲▼ oklu bir kaydırma çubuğu koymuş; çubuk sürüklemek bir
 * FPS'in ortasında yapılacak iş değil.
 *
 * Bu yüzden içerik kaydırılmıyor, sayfalanıyor. Bağlantıların çalıştığı
 * ölçüldü (profil sayfasına tıklanarak giriliyor), dolayısıyla sayfa
 * bağlantısı güvenilir bir yol.
 *
 * Gezinme şeridi TABLONUN ÜSTÜNDE: altta olsaydı, ulaşmak için kaydırmak
 * gerekirdi ve çözmeye çalıştığımız sorunun aynısı olurdu.
 */
/* Altı satır: MOTD panelinin iç yüksekliği 1280x720'de ~550 piksel ve
   sayfanın geri kalanı 300'ünü alıyor. Referans üründeki panelde de
   tabloda altı satır görünüyor — bağımsız bir doğrulama. */
const SAYFA_BOYU = 6;

$sayfaSayisi = max(1, (int) ceil(count($gosterilen) / SAYFA_BOYU));
$sayfa = max(1, min($sayfaSayisi, (int) ($_GET['sayfa'] ?? 1)));
$dilim = array_slice($gosterilen, ($sayfa - 1) * SAYFA_BOYU, SAYFA_BOYU);

/** Arama ve sayfa birlikte taşınıyor; arama yapıp 2. sayfaya geçmek çalışsın. */
function baglanti(int $sayfa, string $ara): string
{
    $p = ['sayfa' => $sayfa];
    if ($ara !== '') {
        $p['ara'] = $ara;
    }
    return 'siralama-motd.php?' . http_build_query($p);
}
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<style><?= tema() ?>

/* --- yalniz bu sayfaya ozgu --- */
.ustSerit {
  display:flex; align-items:center; margin:0;
  padding:5px 22px; background:#0E161F;
  border-bottom:1px solid rgba(236,232,225,.10);
}
.araKutu {
  width:170px; padding:5px 9px;
  background:#0B1219; color:#ECE8E1;
  border:1px solid rgba(236,232,225,.16);
  font-family:'Sicil Govde',sans-serif; font-size:13px;
}
.araDugme {
  margin-left:6px; padding:6px 14px;
  background:#FF4655; color:#0B1219; border:0; cursor:pointer;
  font-family:'Sicil Dar',sans-serif; font-weight:600;
  font-size:12px; letter-spacing:.16em;
}

.bolumler { flex:1; margin-left:22px; }
.bolumler a {
  color:#93A2AE; text-decoration:none; margin-right:18px;
  font-family:'Sicil Dar',sans-serif; font-weight:600;
  font-size:12px; letter-spacing:.14em; text-transform:uppercase;
}
.bolumler a:hover { color:#FF4655; }

/* Sayfa dugmeleri: 26px'lik kare hedefler. MOTD icinde fare hassasiyeti
   dusuk ve oyuncu tur ortasinda tikliyor, kucultmek isabet ettirmeyi
   zorlastiriyor. */
.sayfalar a, .sayfalar b {
  display:inline-block; min-width:26px; padding:4px 6px; margin-left:4px;
  font-family:'Sicil Dar',sans-serif; font-weight:600; font-size:12px;
  text-align:center; text-decoration:none;
}
.sayfalar a { color:#93A2AE; border:1px solid rgba(236,232,225,.14); }
.sayfalar a:hover { color:#0B1219; background:#FF4655; border-color:#FF4655; }
.sayfalar b { color:#0B1219; background:#FF4655; font-weight:600; }

.sonucSerit {
  padding:10px 22px; background:#101A24;
  border-bottom:1px solid rgba(236,232,225,.10);
}
.sonucSerit .baslikSonuc {
  font-family:'Sicil Display',sans-serif; font-size:19px;
  text-transform:uppercase; margin-top:3px; color:#ECE8E1;
}
.sonucSerit .baslikSonuc b { color:#FF4655; font-weight:400; }

.bayrakKucuk {
  width:16px; height:11px; vertical-align:middle; margin-right:7px;
  border:1px solid rgba(236,232,225,.14);
}
.bayrakBuyuk {
  width:24px; height:16px; vertical-align:-1px; margin-right:10px;
  border:1px solid rgba(236,232,225,.18);
}

.sampiyon {
  position:relative; overflow:hidden; padding:9px 22px;
  background:#101A24;
  border-bottom:1px solid rgba(236,232,225,.10);
}
/* Kırmızı yıkama ayrı katman: gradient'i arka planla birleştirmek yerine
   üstüne koymak, ajan büstünün de altında kalmasını sağlıyor. */
.sampiyon .yikama {
  position:absolute; left:0; top:0; right:0; bottom:0;
  background:linear-gradient(100deg, rgba(236,232,225,.05) 0%, rgba(236,232,225,0) 52%);
}
/* Büst sağdan sızıyor. mask-image yok; solma işini üstteki gradient perde
   yapıyor. */
.sampiyon .bust { position:absolute; right:6px; bottom:-40px; height:168px; opacity:.26; }
.sampiyon .perde2 {
  position:absolute; left:0; top:0; right:0; bottom:0;
  background:linear-gradient(90deg, #101A24 0%, rgba(16,26,36,.92) 46%, rgba(16,26,36,0) 88%);
}
.sampiyon .ic { position:relative; display:flex; align-items:center; }

.sampiyon .rozet { width:64px; height:64px; display:block; margin-right:16px; }
.sampiyon .kim { flex:1; min-width:0; }
.sampiyon .isim {
  font-family:'Sicil Display',sans-serif; font-weight:400; font-size:26px; line-height:.98;
  margin:3px 0 0; text-transform:uppercase; color:#ECE8E1;
}
.sampiyon .kademe {
  font-family:'Sicil Dar',sans-serif; font-weight:600; font-size:13px;
  letter-spacing:.16em; text-transform:uppercase; margin-top:3px;
}
.sampiyon .fark { color:#93A2AE; font-size:12px; margin-top:4px; }
.sampiyon .fark b { color:#ECE8E1; font-weight:600; }
.sampiyon .ajan { margin-top:5px; color:#93A2AE; font-size:12px; }
.sampiyon .ajan img { width:20px; height:20px; vertical-align:middle; margin-right:7px; }

.sampiyon .puan { text-align:right; margin-left:20px; }
.sampiyon .puan .n {
  font-family:'Sicil Display',sans-serif; font-weight:400; font-size:42px; line-height:.85; color:#FF4655;
}
.sampiyon .puan .rr { width:150px; height:3px; background:rgba(236,232,225,.12); margin-top:8px; }
.sampiyon .puan .rr i { display:block; height:3px; background:#FF4655; }

.kunye { display:flex; border-bottom:1px solid rgba(236,232,225,.10); background:rgba(236,232,225,.018); }
.kunye > div { flex:1; padding:11px 26px; border-right:1px solid rgba(236,232,225,.05); }
.kunye > div:last-child { border-right:0; }
.kunye .v { font-family:'Sicil Display',sans-serif; font-size:26px; line-height:1.15; margin-top:3px; color:#ECE8E1; }
.kunye .v img { width:70px; height:24px; vertical-align:middle; margin-right:10px; }

/* Satırın tamamı tıklanabilir olamıyor (<a> bir <tr>'yi saramaz), o yüzden
   bağlantı ad hücresini dolduruyor ve satırın üstüne gelince renk değişiyor.
   Oyuncunun tıklanacak yeri araması gerekmesin. */
.satirBag { color:#ECE8E1; text-decoration:none; display:block; }
tbody tr:hover .satirBag { color:#FF4655; }
tbody tr:hover td { cursor:pointer; }
.sampiyon .isim a { color:#ECE8E1; text-decoration:none; }
.sampiyon .isim a:hover { color:#FF4655; }

.rozetKucuk { display:block; width:26px; height:26px; }
.ajanKucuk { width:20px; height:20px; vertical-align:middle; margin-right:8px; }
</style>
</head>
<body style="background:#0B1219;color:#ECE8E1">

<div class="tepe">
  <div class="perde"></div>
  <div class="ic">
    <div>
      <?php /* markaHtml kaçışsız: stil için etiket taşıyor ve
             yapilandirma.php site sahibinin kendi dosyası. */ ?>
      <h1 class="marka"><?= ayar('markaHtml', 'Sicil') ?></h1>
      <span class="etiket"><?= esc(ayar('sezon', 'Sezon 1')) ?><span class="tik"><i></i><i class="b"></i><i class="c"></i></span><?= esc(ayar('sunucuAdi', 'Sunucum')) ?><?php if (ayar('sunucuAdres') !== ''): ?><span class="tik"><i></i><i class="b"></i><i class="c"></i></span><?= esc(ayar('sunucuAdres')) ?><?php endif; ?></span>
    </div>
    <div class="sag">
      <div class="n"><?= sayi(count($oyuncular)) ?></div>
      <span class="etiket">Kayıtlı oyuncu</span>
      <div class="n" style="margin-top:10px"><?= sayi($toplamKill) ?></div>
      <span class="etiket">Toplam kill</span>
    </div>
  </div>
</div>

<?php /* Tek şerit: arama, bölüm bağlantıları ve sayfa numaraları.
         Üçü ayrı satırdayken 146 piksel yiyordu; MOTD penceresinde bu, iki
         oyuncu satırı demek. Şerit tablonun ÜSTÜNDE duruyor — altta olsaydı
         ona ulaşmak için kaydırmak gerekirdi, çözdüğümüz sorunun aynısı. */ ?>
<form class="ustSerit" method="get" action="siralama-motd.php">
  <input class="araKutu" type="text" name="ara" value="<?= esc($ara) ?>" placeholder="Oyuncu ara">
  <input class="araDugme" type="submit" value="ARA">

  <span class="bolumler">
    <?php if ($suzuluyor): ?><a href="siralama-motd.php">Tümü</a><?php endif; ?>
    <a href="silahlar-motd.php">Silahlar</a>
    <a href="haritalar-motd.php">Haritalar</a>
    <a href="rutbeler-motd.php">Rütbeler</a>
  </span>

  <?php if ($sayfaSayisi > 1): ?>
    <span class="sayfalar">
      <?php if ($sayfa > 1): ?><a href="<?= esc(baglanti($sayfa - 1, $ara)) ?>">&larr;</a><?php endif; ?>
      <?php for ($p = 1; $p <= $sayfaSayisi; $p++): ?>
        <?php if ($p === $sayfa): ?><b><?= $p ?></b><?php else: ?><a href="<?= esc(baglanti($p, $ara)) ?>"><?= $p ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($sayfa < $sayfaSayisi): ?><a href="<?= esc(baglanti($sayfa + 1, $ara)) ?>">&rarr;</a><?php endif; ?>
    </span>
  <?php endif; ?>
</form>

<?php if ($suzuluyor): ?>
  <div class="sonucSerit">
    <span class="etiket">Arama</span>
    <div class="baslikSonuc">
      &laquo;<?= esc($ara) ?>&raquo; için <b><?= count($gosterilen) ?></b> sonuç
      <?php if ($gosterilen === []): ?>
        &middot; sicilde bu adla kayıtlı oyuncu yok
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>

<?php if ($bir !== null): ?>
<div class="sampiyon">
  <?php if ($birAjan['bust'] !== null): ?>
    <?= img($birAjan['bust'], 'bust') ?>
  <?php endif; ?>
  <div class="perde2"></div>
  <div class="yikama"></div>

  <div class="ic">
    <?= img(kademeIkonu($birKademe), 'rozet', (string) $birKademe['ad']) ?>

    <div class="kim">
      <span class="etiket">Sezonun birincisi</span>
      <h2 class="isim">
        <?php $birBayrak = bayrak($bir['ulke'] ?? null); ?>
        <?php if ($birBayrak !== null): ?><img class="bayrakBuyuk" src="<?= esc($birBayrak) ?>" alt="<?= esc(strtoupper((string) $bir['ulke'])) ?>"><?php endif; ?><a href="oyuncu-motd.php?kimlik=<?= urlencode($bir['kimlik']) ?>"><?= esc($bir['ad']) ?></a>
      </h2>
      <div class="kademe" style="color:<?= esc($birKademe['renk']) ?>"><?= esc($birKademe['ad']) ?></div>
      <?php if ($iki !== null): ?>
        <div class="fark">İkinci sıradaki <?= esc($iki['ad']) ?>'ten <b><?= sayi($fark) ?> KP</b> önde</div>
      <?php else: ?>
        <div class="fark">Sıralamada henüz tek oyuncu var</div>
      <?php endif; ?>
      <?php if ($birAjan['ad'] !== null): ?>
        <div class="ajan"><?= img($birAjan['dosya']) ?><?= esc($birAjan['ad']) ?><?php if ($birAjan['rol'] !== ''): ?> &middot; <?= esc($birAjan['rol']) ?><?php endif; ?></div>
      <?php endif; ?>
    </div>

    <?php /* Sayı ve çubuk birincinin KENDİ kademe renginde. Sabit kırmızı,
             altın bir Radiant rozetinin yanında iki ayrı vurgu rengi demekti
             ve ikisi de kazanamıyordu. */ ?>
    <div class="puan">
      <span class="etiket">Kazanılan puan</span>
      <div class="n" style="color:<?= esc($birKademe['renk']) ?>"><?= sayi($bir['kp']) ?></div>
      <div class="rr"><i style="width:<?= $birKademe['rr'] ?>%;background:<?= esc($birKademe['renk']) ?>"></i></div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="sampiyon">
  <div class="ic">
    <div class="kim">
      <span class="etiket">Sezonun birincisi</span>
      <h2 class="isim">Henüz kimse yok</h2>
      <div class="kademe">Sunucuya ilk oyuncu girip bir tur bitirdiğinde sıralama burada görünecek.</div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<table>
  <thead>
    <tr>
      <th colspan="2">#</th><th colspan="2">Oyuncu</th><th>Rütbe</th>
      <th class="sag">KP</th><th class="sag">K / D</th><th class="sag">HS</th>
      <th>En iyi silah</th><th class="sag">Süre</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($dilim as $o): ?>
    <?php
    $k = kademe($o['kp']);
    $a = ajan($o['ajan']);
    $s = silah(array_key_first($o['silahlar']));
    ?>
    <tr>
      <td class="tirnak"><i style="background:<?= esc($k['renk']) ?>"></i></td>
      <td class="sira"><?= $o['sira'] ?></td>
      <td style="width:46px"><?= img(kademeIkonu($k), 'rozetKucuk') ?></td>
      <td class="oyuncuAd">
        <?php $bayrak = bayrak($o['ulke'] ?? null); ?>
        <a class="satirBag" href="oyuncu-motd.php?kimlik=<?= urlencode($o['kimlik']) ?>"><?php if ($bayrak !== null): ?><img class="bayrakKucuk" src="<?= esc($bayrak) ?>" alt="<?= esc(strtoupper((string) $o['ulke'])) ?>"><?php endif; ?><?= img($a['dosya'], 'ajanKucuk') ?><?= esc($o['ad']) ?></a>
      </td>
      <td class="kademeAd" style="color:<?= esc($k['renk']) ?>"><?= esc($k['ad']) ?></td>
      <td class="sag kpHucre">
        <?= sayi($o['kp']) ?>
        <span class="cubuk" style="width:74px"><i style="width:<?= $enYuksek > 0 ? max(3, (int) round($o['kp'] / $enYuksek * 100)) : 0 ?>%;background:<?= esc($k['renk']) ?>"></i></span>
      </td>
      <td class="sag kucuk"><?= kd($o['kill'], $o['death']) ?></td>
      <td class="sag kucuk">%<?= hsYuzde($o['hs'], $o['kill']) ?></td>
      <td class="silahHucre">
        <?php if ($s['dosya'] !== null): ?><img src="<?= esc($s['dosya']) ?>" alt=""><?php endif; ?><?= esc($s['ad']) ?>
      </td>
      <td class="sag kucuk"><?= sure($o['saniye']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<div class="dip">
  <span class="etiket">Kafadan kill 2 KP<span class="tik"><i></i><i class="b"></i><i class="c"></i></span>Tur MVP 5 KP<span class="tik"><i></i><i class="b"></i><i class="c"></i></span>Spike çözme 3 KP</span>
  <span class="etiket">30 saniyede bir güncelleniyor</span>
</div>

</body>
</html>
