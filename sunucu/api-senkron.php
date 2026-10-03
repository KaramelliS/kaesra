<?php
declare(strict_types=1);

/**
 * İstatistik alımı. Eklenti tur sonunda ham sayaç DELTASI gönderiyor.
 *
 *   POST /api-senkron.php
 *   X-Sicil-Anahtar: <paylasilan anahtar>
 *   X-Sicil-Zaman / X-Sicil-Tek
 *   {
 *     "port": 27015, "oturum": "a1b2c3d4", "parti": 7,
 *     "gecen": 96, "harita": "de_dust2",
 *     "oyuncular": [
 *       {"kimlik":"STEAM_0:1:492817364","ad":"efeliman","ulke":"tr",
 *        "kill":3,"death":2,"asist":1,"hs":1,"hasar":450,
 *        "atis":40,"isabet":12,"kurma":0,"cozme":1,"mvp":1,
 *        "kazanilan":2,"kaybedilen":1,"saniye":96,
 *        "silahlar":{"ak47":2,"deagle":1}}
 *     ]
 *   }
 *
 * Yanıtta her oyuncunun GÜNCEL rütbesi dönüyor. Bu bilerek: /rank komutu
 * ayrı bir HTTP isteği yapmıyor, son senkron yanıtından okuyor.
 *
 * SIGNATURE — KP burada hesaplanıyor, oyun sunucusunda değil. Eklenti yalnız
 * ham sayaç gönderiyor. Üç sonucu var: sunucu sahibi .sma'yı düzenleyip rütbe
 * şişiremiyor, formül değişince kimseye yeni .amxx dağıtılmıyor, ve formül
 * düzeltilince bütün sıralama geriye dönük yeniden hesaplanabiliyor.
 */

require __DIR__ . '/sicil-api.php';
require_once __DIR__ . '/sicil-ortak.php';

yontemDayat('POST');

/**
 * Akıl sınırı: bir saniyede bir oyuncunun atabileceği azami kill.
 *
 * 45 saniyelik turda 45 kill fiziksel olarak mümkün değil ama teorik tavan
 * orada. Bunun ÜSTÜ reddediliyor (422), yarısını aşan kabul ediliyor ama
 * "şüpheli" işaretleniyor ve panelde görünüyor. Gerçek veriyi reddetmek,
 * şüpheliyi kaydetmekten daha kötü.
 */
const KILL_TAVAN_SANIYE = 1.0;
const SUPHE_ORANI = 0.5;

/**
 * Kabul edilen silah adları; bilinmeyeni sessizce yutmak yerine 422.
 *
 * Liste TEMADAN okunuyor, burada sabit durmuyor: silah adlarını tema
 * seçtiği için API'nin de aynı temaya bakması gerekiyor. İkisi ayrışırsa
 * eklentinin gönderdiği meşru bir ad API'de "bilinmeyen silah" olur ve
 * partinin TAMAMI düşer — tek bir sis bombası ölümü bütün turun verisini
 * kaybettirir.
 */
function bilinenSilah(string $ad): bool
{
    return isset(katalog()['silahEsleme'][$ad]);
}

$simdi = time();
$govde = govdeyiCoz();

$port = (int) ($govde['port'] ?? 0);

/*
 * KİMLİK ÖNCE, DOĞRULAMA SONRA.
 *
 * Gövde doğrulaması kimlikten önce çalışırsa anahtarı olmayan biri hangi
 * alanın hangi kurala takıldığını 422 yanıtlarından tek tek okuyup API'nin
 * şemasını ve sınırlarını ücretsiz keşfedebiliyor. Önce anahtar soruluyor;
 * anahtar yoksa alanlara hiç bakılmıyor.
 *
 * port burada erken okunuyor çünkü anahtar kontrolü sunucu anahtarını
 * (ip:port) ondan kuruyor — ama yalnız OKUNUYOR, doğrulanmıyor. Geçersiz
 * port değerinin 422'si anahtar kontrolünden sonra geliyor.
 *
 * Yan faydası: anahtar reddedilince depo kilidi hiç alınmamış oluyor. Eskiden
 * red depoAc()'den sonra geldiği için sorun() süreci sonlandırıyor ve kilit
 * ancak süreç ölünce bırakılıyordu — o arada gelen istekler bekliyordu.
 */
$sunucuAnahtari = anahtarDogrula($port);

/* ---------------- gövde doğrulama ---------------- */

$hatalar = [];

$oturum = (string) ($govde['oturum'] ?? '');
$parti  = (int) ($govde['parti'] ?? -1);
$gecen  = (int) ($govde['gecen'] ?? 0);
$harita = (string) ($govde['harita'] ?? '');
$liste  = $govde['oyuncular'] ?? null;

if ($port < 1 || $port > 65535) {
    $hatalar[] = ['alan' => 'port', 'kod' => 'aralik_disi', 'mesaj' => 'port 1-65535 arasi olmali.'];
}

if (!preg_match('/^[A-Za-z0-9_-]{4,32}$/', $oturum)) {
    $hatalar[] = ['alan' => 'oturum', 'kod' => 'bicim',
        'mesaj' => 'oturum 4-32 hane [A-Za-z0-9_-] olmali; sunucu her acilista yeni uretir.'];
}

if ($parti < 0 || $parti > 1000000) {
    $hatalar[] = ['alan' => 'parti', 'kod' => 'aralik_disi',
        'mesaj' => 'parti 0 ile 1000000 arasi, oturum icinde artan bir sayac olmali.'];
}

if ($gecen < 1 || $gecen > 86400) {
    $hatalar[] = ['alan' => 'gecen', 'kod' => 'aralik_disi',
        'mesaj' => 'gecen, onceki partiden bu yana gecen saniye; 1-86400 arasi olmali.'];
}

if ($harita !== '' && !preg_match('/^[A-Za-z0-9_-]{1,32}$/', $harita)) {
    $hatalar[] = ['alan' => 'harita', 'kod' => 'bicim', 'mesaj' => 'harita adi en fazla 32 hane [A-Za-z0-9_-].'];
}

if (!is_array($liste)) {
    $hatalar[] = ['alan' => 'oyuncular', 'kod' => 'tur', 'mesaj' => 'oyuncular bir dizi olmali.'];
    alanHatasi($hatalar);
}

if (count($liste) > AZAMI_OYUNCU) {
    $hatalar[] = ['alan' => 'oyuncular', 'kod' => 'cok_fazla',
        'mesaj' => sprintf('Tek partide en fazla %d oyuncu, %d gonderildi.', AZAMI_OYUNCU, count($liste))];
}

/** Delta alanları; hepsi negatif olmayan tamsayı. */
const SAYAC_ALANLARI = ['kill','death','asist','hs','hasar','atis','isabet',
                        'kurma','cozme','mvp','kazanilan','kaybedilen','saniye'];

$temiz = [];

foreach ($liste as $sira => $ham) {
    $on = 'oyuncular[' . $sira . ']';

    if (!is_array($ham)) {
        $hatalar[] = ['alan' => $on, 'kod' => 'tur', 'mesaj' => 'Her oyuncu bir nesne olmali.'];
        continue;
    }

    $kimlik = (string) ($ham['kimlik'] ?? '');
    if (!preg_match('/^(STEAM_[0-9]:[01]:[0-9]{1,12}|VALVE_[0-9]:[01]:[0-9]{1,12}|[0-9]{17})$/', $kimlik)) {
        $hatalar[] = ['alan' => $on . '.kimlik', 'kod' => 'bicim',
            'mesaj' => 'STEAM_0:1:12345, VALVE_0:0:12345 ya da 17 haneli SteamID64 bekleniyor.'];
        continue;
    }

    $kayit = ['kimlik' => $kimlik];

    foreach (SAYAC_ALANLARI as $alan) {
        $deger = $ham[$alan] ?? 0;

        if (!is_int($deger) || $deger < 0) {
            $hatalar[] = ['alan' => $on . '.' . $alan, 'kod' => 'negatif_veya_tamsayi_degil',
                'mesaj' => $alan . ' negatif olmayan tamsayi olmali, gelen: ' . var_export($deger, true)];
            continue 2;
        }

        $kayit[$alan] = $deger;
    }

    /* Akil siniri. Sinirin USTU reddediliyor, yarisini asan isaretleniyor. */
    $tavan = (int) ceil($gecen * KILL_TAVAN_SANIYE);
    if ($kayit['kill'] > $tavan) {
        $hatalar[] = ['alan' => $on . '.kill', 'kod' => 'akil_disi',
            'mesaj' => sprintf('%d saniyede %d kill kabul edilmiyor, tavan %d.',
                $gecen, $kayit['kill'], $tavan)];
        continue;
    }

    $kayit['supheli'] = $kayit['kill'] > $tavan * SUPHE_ORANI ? 1 : 0;

    if ($kayit['hs'] > $kayit['kill']) {
        $hatalar[] = ['alan' => $on . '.hs', 'kod' => 'tutarsiz',
            'mesaj' => sprintf('hs (%d) kill\'den (%d) buyuk olamaz.', $kayit['hs'], $kayit['kill'])];
        continue;
    }

    if ($kayit['isabet'] > $kayit['atis']) {
        $hatalar[] = ['alan' => $on . '.isabet', 'kod' => 'tutarsiz',
            'mesaj' => sprintf('isabet (%d) atis\'tan (%d) buyuk olamaz.', $kayit['isabet'], $kayit['atis'])];
        continue;
    }

    $silahlar = $ham['silahlar'] ?? [];
    if (!is_array($silahlar)) {
        $hatalar[] = ['alan' => $on . '.silahlar', 'kod' => 'tur', 'mesaj' => 'silahlar bir nesne olmali.'];
        continue;
    }

    $kayit['silahlar'] = [];
    foreach ($silahlar as $silahAdi => $adet) {
        if (!is_string($silahAdi) || !bilinenSilah($silahAdi)) {
            $hatalar[] = ['alan' => $on . '.silahlar', 'kod' => 'bilinmeyen_silah',
                'mesaj' => sprintf('"%s" taninmiyor. Bilinen adlar: %s.',
                    is_string($silahAdi) ? substr($silahAdi, 0, 20) : gettype($silahAdi),
                    implode(', ', array_slice(array_keys(katalog()['silahEsleme'] ?? []), 0, 8)) . ', ...')];
            continue 2;
        }

        if (!is_int($adet) || $adet < 0) {
            $hatalar[] = ['alan' => $on . '.silahlar.' . $silahAdi, 'kod' => 'negatif_veya_tamsayi_degil',
                'mesaj' => 'Silah sayaci negatif olmayan tamsayi olmali.'];
            continue 2;
        }

        $kayit['silahlar'][$silahAdi] = $adet;
    }

    /* Ad ve ulke oyuncunun kendi verisi; kirpiliyor ama reddedilmiyor.
       Uzun bir nick yuzunden turun tamamini atmak orantisiz olurdu. */
    $kayit['ad'] = substr(trim((string) ($ham['ad'] ?? '')), 0, 32) ?: $kimlik;

    $ulke = strtolower((string) ($ham['ulke'] ?? ''));
    $kayit['ulke'] = preg_match('/^[a-z]{2}$/', $ulke) ? $ulke : null;

    $ajan = (string) ($ham['ajan'] ?? '');
    $kayit['ajan'] = preg_match('/^[A-Za-z\/ ]{1,20}$/', $ajan) ? $ajan : null;

    $temiz[] = $kayit;
}

if ($hatalar !== []) {
    alanHatasi($hatalar);
}

/* ---------------- yazma ---------------- */

try {
    [$depo, $tutamac] = depoAc();
} catch (RuntimeException $e) {
    header('Retry-After: 30');
    sorun(503, 'Depo erisilemez', 'Veri deposu acilamiyor, parti islenmedi. '
        . 'Eklenti partiyi kuyrukta tutup 30 saniye sonra tekrar gonderebilir.');
}

try {
    [$depo, $nonce] = tekrariEngelle($depo, $simdi);

    /* İlk partide sunucu kaydı yok. Yalnız gözlem için tutuluyor
       (sicil_durum ve "son görülme" satırı), bir yetki kararı değil. */
    if (!isset($depo['sunucular'][$sunucuAnahtari])) {
        $depo['sunucular'][$sunucuAnahtari] = ['ilk' => gmdate('c')];
    }

    /*
     * Idempotency. Ag koptugunda eklenti ayni partiyi tekrar gonderiyor;
     * ikinci gelisin sayaclari ikinci kez artirmamasi gerekiyor. Nonce bunu
     * cozmuyor — tekrar denemede nonce YENI olur, cunku farkli bir istek.
     * Ayni partinin ikinci kez yazilmamasini saglayan sey bu anahtar.
     */
    $partiAnahtari = $sunucuAnahtari . '|' . $oturum . '|' . $parti;

    if (isset($depo['partiler'][$partiAnahtari])) {
        $depo = depoBudama($depo, $simdi);
        depoKapat($tutamac, $depo);

        json(200, [
            'durum'     => 'zaten_islendi',
            'parti'     => $parti,
            'oyuncular' => [],
        ]);
    }

    $yanit = [];
    $supheliSayisi = 0;

    /* Mac takibi: ayni oturum + harita = ayni mac. Oturum ya da harita
       degistiyse onceki mac burada kapanir ve gecmise yazilir. */
    $depo = macIlerlet($depo, $sunucuAnahtari, $oturum, $harita, $simdi);

    foreach ($temiz as $kayit) {
        /*
         * Oyuncu anahtarı doğrudan SteamID. Bir kurulum = bir sıralama
         * merdiveni; aynı sunucuya bakan birden çok oyun sunucusu varsa
         * istatistikler doğru şekilde ortak havuzda birleşir.
         */
        $anahtar = $kayit['kimlik'];

        $mevcut = $depo['oyuncular'][$anahtar] ?? bosOyuncu($kayit['kimlik'], $kayit['ad']);

        /* Mac karnesindeki KP farki icin partiden onceki deger. */
        $kpOnce = kazanilanPuan($mevcut);

        foreach (SAYAC_ALANLARI as $alan) {
            $mevcut[$alan] += $kayit[$alan];
        }

        foreach ($kayit['silahlar'] as $silahAdi => $adet) {
            $mevcut['silahlar'][$silahAdi] = ($mevcut['silahlar'][$silahAdi] ?? 0) + $adet;
        }

        if ($harita !== '' && $kayit['saniye'] > 0) {
            $mevcut['haritalar'][$harita] = ($mevcut['haritalar'][$harita] ?? 0) + $kayit['saniye'];
        }

        $mevcut['ad'] = $kayit['ad'];
        $mevcut['son'] = gmdate('Y-m-d');
        $mevcut['supheli'] += $kayit['supheli'];
        $supheliSayisi += $kayit['supheli'];

        if ($kayit['ulke'] !== null) {
            $mevcut['ulke'] = $kayit['ulke'];
        }
        if ($kayit['ajan'] !== null) {
            $mevcut['ajan'] = $kayit['ajan'];
        }

        $mevcut['sonAn'] = $simdi;

        $kp = kazanilanPuan($mevcut);
        $k = kademe($kp);

        /* Yerlestirme rutbesi: oyuncunun ILK partisinden sonraki kademe,
           bir kez yazilir ve bir daha degismez. Profil "ilk rutbe"yi
           gosterip yolun nereden baslandigini anlatiyor. */
        if (($mevcut['ilkKademeNo'] ?? null) === null) {
            $mevcut['ilkKademeNo'] = $k['no'];
        }

        $depo['oyuncular'][$anahtar] = $mevcut;
        $depo = macKatkisi($depo, $sunucuAnahtari, $anahtar, $kayit, $kpOnce, $kp);

        $yanit[] = [
            'kimlik'    => $kayit['kimlik'],
            'kp'        => $kp,
            'kademe'    => $k['ad'],
            'kademeNo'  => $k['no'],
            'rr'        => $k['rr'],
            'kalan'     => $k['kalan'],
            'ustKademe' => $k['ustAdi'],
        ];
    }

    $depo['partiler'][$partiAnahtari] = $simdi;
    $depo['sunucular'][$sunucuAnahtari]['sonGorulme'] = gmdate('c');
    $depo = depoBudama($depo, $simdi);

    depoKapat($tutamac, $depo);
} catch (Throwable $e) {
    depoKapat($tutamac);

    error_log(sprintf('[sicil] senkron ucu patladi: %s @ %s:%d',
        str_replace(["\n", "\r"], ' ', $e->getMessage()), $e->getFile(), $e->getLine()));

    sorun(500, 'Beklenmeyen hata', 'Parti islenirken sunucu tarafinda bir hata olustu. '
        . 'Parti YAZILMADI, tekrar gonderilebilir.');
}

/* Siralama sayfalari icin sira numarasi lazim; yanitta donmuyor cunku
   eklentinin ihtiyaci yok ve her partide tum oyuncularin siralanmasi
   gereksiz is olurdu. /rank sayfayi actiginda hesapliyor. */

json(200, [
    'durum'     => 'islendi',
    'parti'     => $parti,
    'supheli'   => $supheliSayisi,
    'oyuncular' => $yanit,
]);
