<?php
declare(strict_types=1);

/**
 * Sıralama ve profil sayfalarının ortak temeli: tema kataloğu, rütbe
 * merdiveni, silah eşlemesi ve biçimlendirme.
 *
 * Görsel kimlik (rütbe adları, silah adları, ikonlar) TEK YERDE, bir tema
 * klasöründe duruyor: `tema/<TEMA>/katalog.php`. Bu dosya ve MOTD sayfaları
 * temayı yalnızca okuyor. Yeni bir görünüm istiyorsanız yeni bir klasör
 * açıyorsunuz, buraya dokunmuyorsunuz — bkz. tema/README.md.
 *
 * Katalog bilerek PHP dizisi, JSON değil: her istekte ayrıştırma yok ve
 * `opcache` ile bellekte tek kopya duruyor. Ham bir oyun verisi dosyası
 * megabaytlarca olabiliyor; sayfayı yavaşlatan şey o olurdu.
 */

/**
 * Etkin tema. Değiştirmek için burayı düzenleyin ya da KAESRA_TEMA ortam
 * değişkenini kullanın (test sunucusunda iki temayı yan yana görmek için).
 */
const TEMA = 'klasik';

/** Tema varlıklarının web köküne göre yolu; sayfalar bunu doğrudan basıyor. */
function temaKok(): string
{
    return 'tema/' . TEMA . '/';
}

function katalog(): array
{
    static $k = null;
    if ($k === null) {
        $dosya = __DIR__ . '/tema/' . TEMA . '/katalog.php';
        if (!is_file($dosya)) {
            throw new RuntimeException('Tema bulunamadi: ' . TEMA . ' (' . $dosya . ')');
        }
        $k = require $dosya;
    }
    return $k;
}

/**
 * Temanın bir özelliği var mı?
 *
 * "ajan" gibi kavramlar her temada yok. Sayfalar bunu sorgulayıp ilgili
 * bloğu hiç basmıyor; boş bir kutu ya da "?" göstermek yanlış bilgi olurdu.
 */
function temaOzellik(string $ad): bool
{
    return (bool) (katalog()[$ad] ?? false);
}

/**
 * Görünen kimlik ayarı: sunucu adı, sezon, marka.
 *
 * Değerler yapilandirma.php içinde, sayfalarda sabit metin olarak DURMUYOR.
 * Bunun sebebi somut: ilk sürümde sıralama sayfasının başlığında geliştiricinin
 * kendi sunucu adı ve gerçek IP adresi elle yazılıydı — çatallayan herkes
 * başkasının IP'sini kendi MOTD'sinde gösterecekti. Dosya yoksa veya anahtar
 * tanımsızsa varsayılana düşülüyor, yani silinmiş bir yapılandırma sayfayı
 * düşürmüyor.
 */
function ayar(string $anahtar, string $varsayilan = ''): string
{
    static $a = null;
    if ($a === null) {
        $dosya = __DIR__ . '/yapilandirma.php';
        $a = is_file($dosya) ? (array) require $dosya : [];
    }
    return (string) ($a[$anahtar] ?? $varsayilan);
}

/**
 * Rütbe merdiveni: temanın tanımladığı kademeler (klasik temada 25:
 * Acemi 1'den Zirve'ye). Numaralar 1'den başlıyor, artan ve BOŞLUKSUZ —
 * eşikler bu sıradan türetiliyor.
 *
 * Aralık sabit değil, yukarı çıktıkça açılıyor: Acemi 1'den 2'ye 90 KP,
 * Kahraman 2'den 3'e 228 KP. Sabit aralık üst kademeleri bir haftada
 * geçilebilir yapardı, ki rütbenin anlamı da orada biterdi.
 *
 * 25 kademenin tavanı 3816 KP. Kendi merdiveninizi kurarken tohum verinin
 * ve gerçek sunucunuzdaki KP dağılımının bu tavanın altında kaldığını
 * kontrol edin, yoksa herkes en üst kademede yığılır.
 */
function kademeEsikleri(): array
{
    static $esikler = null;
    if ($esikler !== null) {
        return $esikler;
    }

    $esikler = [];
    $toplam = 0;
    $adim = 90;

    foreach (array_keys(katalog()['kademe']) as $no) {
        $esikler[$no] = $toplam;
        $toplam += $adim;
        $adim += 6;
    }

    return $esikler;
}

/**
 * Sayfaların okuduğu oyuncu listesi: KP'ye göre sıralı, sıra numarası ekli.
 *
 * Depoda kayıt varsa depo, yoksa tohum veri. Yedeklemenin sebebi tembellik
 * değil: yeni kurulan bir sunucuda ilk senkrona kadar depo boş oluyor ve
 * oyuncu MOTD'yi açtığında bomboş bir sayfa görüyor. Tohum veri o boşluğu
 * doldurup sistemin nasıl görüneceğini gösteriyor.
 *
 * KP burada hesaplanıyor, saklanmıyor — bkz. kazanilanPuan(). Sıralama her
 * çağrıda ham sayaçtan türetiliyor, yani formül değişince geçmiş de düzeliyor.
 * 16 oyuncuda bu bedava; 20.000'de indeksli bir kolon gerekir ve o zaman
 * MySQL'e geçmiş oluruz.
 */
function oyuncuListesi(): array
{
    static $liste = null;
    if ($liste !== null) {
        return $liste;
    }

    require_once __DIR__ . '/kaesra-depo.php';
    $depo = depoOku();

    /*
     * SIFIR VERİ VARSAYILAN. Depo boşsa sayfalar BOŞ durum gösterir; sahte
     * veri YALNIZCA yapilandirma.php içinde 'tohumVeri' => '1' yapıldığında
     * yüklenir (tanıtım görüntüsü çekerken işe yarıyor). Üretimde kapalı:
     * kurulum gerçekten boş başlar ve ilk gerçek turdan sonra dolmaya
     * başlar. Boş sayfa bir hata değil, beklenen durumdur.
     */
    if ($depo['oyuncular'] === [] && ayar('tohumVeri', '0') === '1') {
        $liste = require __DIR__ . '/veri-tohum.php';
    } else {
        $liste = [];
        foreach ($depo['oyuncular'] as $o) {
            $o['kp'] = kazanilanPuan($o);

            // Silah ve harita kırılımları çoktan aza; sayfalar ilk sırayı
            // "en iyi silah" diye kullanıyor.
            arsort($o['silahlar']);
            arsort($o['haritalar']);

            $liste[] = $o;
        }

        usort($liste, static fn(array $a, array $b): int => $b['kp'] <=> $a['kp']);
    }

    foreach ($liste as $i => $o) {
        $liste[$i]['sira'] = $i + 1;
    }

    return $liste;
}

/**
 * Ham sayaçlardan kazanılan puanı hesaplar.
 *
 * BURASI PROJENİN İMZASI. KP oyun sunucusunda hiç hesaplanmıyor; eklenti
 * yalnız ham sayaç gönderiyor ve puan değeri tek bir yerde, burada duruyor.
 * Rakip ürünlerde çarpan bir cvar (`nox_rank_xp_multiplier`) ve sunucu
 * sahibi onu değiştirebiliyor. Üç sonucu var:
 *
 *   - Sunucu sahibi .sma'yı düzenleyip rütbe şişiremiyor.
 *   - Formül değişince kimseye yeni .amxx dağıtmak gerekmiyor.
 *   - Formül düzeltilince BÜTÜN sıralama geriye dönük yeniden hesaplanıyor,
 *     çünkü saklanan şey puan değil ham sayaç.
 *
 * Ölüm 0 puan: public sunucuda negatif ilerleme oyuncuyu kaçırıyor. Kafadan
 * vuruş kill'in üstüne +1 ekliyor, yani kafadan bir kill iki puan.
 */
const KP_DEGERLERI = [
    'kill'  => 1,
    'hs'    => 1,   // kill'in üstüne
    'asist' => 1,
    'kurma' => 2,
    'cozme' => 3,
    'mvp'   => 5,
];

function kazanilanPuan(array $o): int
{
    $toplam = 0;
    foreach (KP_DEGERLERI as $alan => $deger) {
        $toplam += ((int) ($o[$alan] ?? 0)) * $deger;
    }
    return $toplam;
}

/**
 * KP'den kademeyi çıkarır.
 *
 * Dönen dizide kademenin kendisi, kademe içindeki ilerleme (her kademede
 * 0-100) ve bir üste kalan KP var. En üst kademede üst kademe olmadığı
 * için 'kalan' null ve RR her zaman 100.
 */
function kademe(int $kp): array
{
    $esikler = kademeEsikleri();
    $kademeler = katalog()['kademe'];
    $numaralar = array_keys($esikler);

    $secili = $numaralar[0];
    foreach ($numaralar as $no) {
        if ($kp >= $esikler[$no]) {
            $secili = $no;
        }
    }

    $sonraki = null;
    foreach ($numaralar as $no) {
        if ($esikler[$no] > $kp) {
            $sonraki = $no;
            break;
        }
    }

    $taban = $esikler[$secili];
    $tavan = $sonraki === null ? $taban + 1 : $esikler[$sonraki];

    return $kademeler[$secili] + [
        'no'     => $secili,
        'rr'     => $sonraki === null ? 100 : (int) round(($kp - $taban) / ($tavan - $taban) * 100),
        'kalan'  => $sonraki === null ? null : $tavan - $kp,
        'ustAdi' => $sonraki === null ? null : $kademeler[$sonraki]['ad'],
    ];
}

/**
 * Tema varlığının yolunu kurar; yoksa null.
 *
 * Her tema görsel taşımak zorunda değil. "klasik" tema tamamen metinle
 * çalışıyor: dosya alanı null, sayfalar <img> etiketini hiç basmıyor.
 * Görselsiz temayı bozuk tema sanmayın — bu desteklenen bir durum.
 */
function varlikYolu(?string $dosya): ?string
{
    return ($dosya === null || $dosya === '') ? null : temaKok() . $dosya;
}

/**
 * CS 1.6 silah adı -> temanın gösterdiği ad.
 *
 * Eşleme tablosu TEMANIN İÇİNDE (`silahEsleme`), burada değil: motorun
 * DeathMsg olayı her zaman "ak47" diyor, oyuncunun göreceği adı tema
 * seçiyor. Eşlenmemiş bir silah gelirse ham ad gösteriliyor ve ikon
 * basılmıyor — sessizce başka bir silah adı uydurmak yanlış veri üretmek
 * olurdu.
 */
function silah(?string $csAdi): array
{
    if ($csAdi === null || $csAdi === '') {
        return ['ad' => '—', 'ham' => '', 'sinif' => '', 'dosya' => null];
    }

    $esleme = katalog()['silahEsleme'] ?? [];
    $ad = $esleme[$csAdi] ?? $csAdi;
    $kayit = katalog()['silah'][$ad] ?? null;

    return [
        'ad'    => $ad,
        'ham'   => $csAdi,
        'sinif' => $kayit['sinif'] ?? '',
        'dosya' => varlikYolu($kayit['dosya'] ?? null),
    ];
}

/**
 * Oyuncunun seçtiği karakter/sınıf.
 *
 * Tema bu kavramı desteklemiyorsa (ajanVar false) ad null dönüyor ve
 * sayfalar bloğu hiç basmıyor. Depoda eski bir değer duruyor olabilir;
 * tema değiştiren bir kurulumda o değer görünmez, silinmez.
 */
function ajan(?string $ad): array
{
    $bos = ['ad' => null, 'rol' => '', 'dosya' => null, 'bust' => null,
            'renk' => '#2A3A47'];

    if ($ad === null || $ad === '' || !temaOzellik('ajanVar')) {
        return $bos;
    }

    $kayit = katalog()['ajan'][$ad] ?? null;
    if ($kayit === null) {
        return $bos;
    }

    return [
        'ad'    => $ad,
        'rol'   => $kayit['rol'] ?? '',
        'dosya' => varlikYolu($kayit['dosya'] ?? null),
        'bust'  => varlikYolu($kayit['bust'] ?? null),
        'renk'  => $kayit['gradient'][0] ?? '#2A3A47',
    ];
}

function harita(?string $ad): array
{
    if ($ad === null || $ad === '') {
        return ['ad' => '—', 'dosya' => null, 'splash' => null];
    }

    $kayit = katalog()['harita'][$ad] ?? null;

    return [
        'ad'     => $ad,
        'dosya'  => varlikYolu($kayit['dosya'] ?? null),
        'splash' => varlikYolu($kayit['splash'] ?? null),
    ];
}

/** Rütbe rozetinin yolu; tema görsel taşımıyorsa null. */
function kademeIkonu(array $k): ?string
{
    return varlikYolu($k['dosya'] ?? null);
}

/**
 * Uzun listeleri sayfalara böler ve gezinme şeridini üretir.
 *
 * MOTD penceresi kaydırılamıyor: GoldSrc'in HTML denetimi fare tekerini
 * iletmiyor. İlk çözüm "ilk N satır + tümünü göster" idi ama "tümünü göster"
 * yine kaydırılamayan uzun bir sayfa üretiyordu, yani tuzaktı. Sayfalama
 * hepsine erişim veriyor ve hiçbir sayfa pencereyi aşmıyor.
 *
 * Şerit listenin ÜSTÜNE basılıyor. Altta olsaydı ona ulaşmak için kaydırmak
 * gerekirdi — çözmeye çalıştığımız sorunun aynısı.
 *
 * @param  int $odak  Bu indeksi içeren sayfa varsayılan olsun (yoksa 0).
 * @return array{0: array, 1: string} sayfanın dilimi ve şerit HTML'i
 */
function sayfala(array $liste, int $boy, string $adres, array $ekParam = [], int $odak = 0): array
{
    $toplam = count($liste);
    $sayfaSayisi = max(1, (int) ceil($toplam / $boy));

    $varsayilan = $odak > 0 ? (int) floor($odak / $boy) + 1 : 1;
    $sayfa = max(1, min($sayfaSayisi, (int) ($_GET['sayfa'] ?? $varsayilan)));

    $dilim = array_slice($liste, ($sayfa - 1) * $boy, $boy, true);

    if ($sayfaSayisi === 1) {
        return [$dilim, ''];
    }

    $bag = static function (int $p) use ($adres, $ekParam): string {
        return esc($adres . '?' . http_build_query($ekParam + ['sayfa' => $p]));
    };

    $dugmeler = '';
    if ($sayfa > 1) {
        $dugmeler .= '<a href="' . $bag($sayfa - 1) . '">&larr;</a>';
    }
    for ($p = 1; $p <= $sayfaSayisi; $p++) {
        $dugmeler .= $p === $sayfa
            ? '<b>' . $p . '</b>'
            : '<a href="' . $bag($p) . '">' . $p . '</a>';
    }
    if ($sayfa < $sayfaSayisi) {
        $dugmeler .= '<a href="' . $bag($sayfa + 1) . '">&rarr;</a>';
    }

    $ilk = ($sayfa - 1) * $boy + 1;
    $son = $ilk + count($dilim) - 1;

    $serit = '<div class="kisaltSerit">'
        . '<span class="etiket">' . $ilk . '&ndash;' . $son . ' / ' . $toplam . '</span>'
        . '<span class="sayfalar">' . $dugmeler . '</span>'
        . '</div>';

    return [$dilim, $serit];
}

/** sayfala()'nın şeridi için ortak biçim; her sayfada tekrar yazılmasın. */
function kisaltStili(): string
{
    return '.kisaltSerit { display:flex; justify-content:space-between; align-items:center;'
         . ' padding:5px 22px; background:#101A24;'
         . ' border-bottom:1px solid rgba(236,232,225,.10); }'
         . '.kisaltSerit .sayfalar a, .kisaltSerit .sayfalar b {'
         . ' display:inline-block; min-width:24px; padding:3px 6px; margin-left:4px;'
         . " font-family:'Kaesra Dar',sans-serif; font-weight:600; font-size:12px;"
         . ' text-align:center; text-decoration:none; }'
         . '.kisaltSerit .sayfalar a { color:#93A2AE; border:1px solid rgba(236,232,225,.14); }'
         . '.kisaltSerit .sayfalar a:hover { color:#0B1219; background:#FF4655; border-color:#FF4655; }'
         . '.kisaltSerit .sayfalar b { color:#0B1219; background:#FF4655; }';
}

/**
 * Ülke bayrağının yolu; kod yoksa ya da dosya indirilmemişse null.
 *
 * Kaynak oyun tarafında geoip_code2_ex(), yani oyuncunun IP'si. Bu yüzden
 * bilinmeyen bir durum normal: VPN, yerel ağ, tanınmayan blok. Null dönünce
 * çağıran taraf bayrağı hiç basmıyor — soru işaretli boş bir kutu koymak,
 * bilmediğimiz şeyi bilgi gibi göstermek olurdu.
 */
function bayrak(?string $kod): ?string
{
    if ($kod === null || !preg_match('/^[A-Za-z]{2}$/', $kod)) {
        return null;
    }

    $yol = temaKok() . 'bayrak/' . strtolower($kod) . '.png';
    return is_file(__DIR__ . '/' . $yol) ? $yol : null;
}

/**
 * Arama için ad normalleştirir: "ŞÜKRÜÜÜ" ve "sukru" aynı anahtara iniyor.
 *
 * İki sebep var. Birincisi bu PHP kurulumunda mbstring yok, mb_stripos
 * çağrısı sayfayı komple düşürüyor — denendi. İkincisi ve asıl olanı: Türk
 * oyuncular arama kutusuna şapkasız yazıyor. Doğru büyük/küçük harf
 * dönüşümü yapan bir arama "Şahin"i "sahin" yazana bulduramazdı.
 *
 * Bu yüzden dönüşüm bilerek kayıplı: ı/İ/I/i hepsi i, ş/Ş s, ğ/Ğ g, ü/Ü u,
 * ö/Ö o, ç/Ç c oluyor. Görüntülemede kullanılmaz, yalnız karşılaştırmada.
 */
function aramaAnahtari(string $s): string
{
    $s = strtr($s, [
        'ı' => 'i', 'İ' => 'i', 'I' => 'i',
        'ş' => 's', 'Ş' => 's',
        'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u',
        'ö' => 'o', 'Ö' => 'o',
        'ç' => 'c', 'Ç' => 'c',
    ]);

    // strtolower yalnız ASCII'ye dokunuyor; yukarıdaki tablo Türkçeyi zaten
    // ASCII'ye indirdiği için bu yeterli ve UTF-8'i bozmuyor.
    return strtolower($s);
}

/**
 * HTML kaçışı. Null güvenlidir: tema bir varlığı taşımıyorsa (görselsiz
 * tema, ajan kavramı olmayan tema) yardımcı fonksiyonlar null dönüyor ve
 * her çağrı yerinde ayrı bir if yazmak yerine boş dizi basılıyor.
 */
function esc(?string $s): string
{
    return $s === null ? '' : htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * <img> etiketi üretir; yol null ise HİÇBİR ŞEY üretmez.
 *
 * Görselsiz tema desteklenen bir durum ve sayfalarda onlarca <img> var.
 * Her birinin başına "dosya !== null" yazmak hem gürültülü hem de bir
 * tanesi unutulduğunda src="" basıp tarayıcıyı sayfanın kendisine istek
 * atmaya zorluyor. Tek yardımcı, tek kural.
 */
function img(?string $yol, string $sinif = '', string $alt = ''): string
{
    if ($yol === null) {
        return '';
    }

    return '<img' . ($sinif !== '' ? ' class="' . esc($sinif) . '"' : '')
        . ' src="' . esc($yol) . '"'
        . ' alt="' . esc($alt) . '">';
}
function sayi(int|float $n, int $b = 0): string { return number_format((float) $n, $b, ',', '.'); }
function kd(int $k, int $o): string { return sayi($o === 0 ? $k : $k / $o, 2); }
function hsYuzde(int $hs, int $k): int { return $k === 0 ? 0 : (int) round($hs / $k * 100); }

/**
 * 12840 saniye -> "3 sa 34 dk", 771840 -> "214 sa".
 *
 * "s" kısaltmasından kaçınıldı: hem saat hem saniye okunuyor ve bir istatistik
 * tablosunda bu gerçekten karıştırıyor. Yüz saati geçince dakika da düşüyor —
 * 214 saat oynamış birine 24 dakikanın bir bilgi değeri yok, ama hücreyi iki
 * satıra taşırıyor.
 */
/**
 * Göreli zaman: "3 dk önce", "2 sa önce", "3 gün önce".
 *
 * Eşik seçimi kaba bilerek: son görülme rozetinin işi hassas ölçüm değil,
 * "az önce buradaydı" ile "haftalardır yok" ayrımı.
 */
function gorel(int $an, int $simdi): string
{
    $fark = max(0, $simdi - $an);

    if ($fark < 60)     { return 'az önce'; }
    if ($fark < 3600)   { return intdiv($fark, 60) . ' dk önce'; }
    if ($fark < 86400)  { return intdiv($fark, 3600) . ' sa önce'; }
    if ($fark < 2592000) { return intdiv($fark, 86400) . ' gün önce'; }
    return intdiv($fark, 2592000) . ' ay önce';
}

/**
 * STEAM_X:Y:Z -> SteamID64. Steam profil bağlantısı için.
 * Formül: 76561197960265728 + Z*2 + Y. Bot ve LAN kimliklerinde null.
 */
function steam64(string $kimlik): ?string
{
    if (preg_match('/^[0-9]{17}$/', $kimlik)) {
        return $kimlik;
    }
    if (preg_match('/^(?:STEAM|VALVE)_[0-9]:([01]):([0-9]{1,12})$/', $kimlik, $e)) {
        return (string) (76561197960265728 + (int) $e[2] * 2 + (int) $e[1]);
    }
    return null;
}

function sure(int $saniye): string
{
    $saat = intdiv($saniye, 3600);
    $dakika = intdiv($saniye % 3600, 60);

    if ($saat >= 100 || ($saat > 0 && $dakika === 0)) {
        return sayi($saat) . ' sa';
    }
    return $saat > 0 ? "{$saat} sa {$dakika} dk" : "{$dakika} dk";
}

/**
 * Temayı sayfanın içine gömer.
 *
 * <link rel="stylesheet"> ile denendi ve tutmadı: dosya yükleniyor (fontlar
 * geliyor, .etiket kuralları uygulanıyor) ama gövde arka planı beyaz
 * kalıyordu — MOTD penceresi kendi gövde stilini dış stil sayfasının üstüne
 * yazıyor. Satır içi <style> aynı kuralları uygulatıyor.
 *
 * Yan faydası: MOTD açılırken bir istek eksiliyor.
 */
function tema(): string
{
    return (string) file_get_contents(__DIR__ . '/kaesra-tema.css');
}

function motdBasliklari(): void
{
    header('Content-Type: text/html; charset=utf-8');
    // MOTD penceresi sayfayı agresif önbelleğe alıyor; sıralamanın bayat
    // gösterilmesi bu üründeki en can sıkıcı hata olurdu.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
