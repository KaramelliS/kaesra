<?php
declare(strict_types=1);

/**
 * Veri deposu — şu an tek bir JSON dosyası.
 *
 * BU GEÇİCİ. Asıl tasarım MySQL: sunucular, oyuncular, istatistik,
 * silah, partiler, tek_kullanim tabloları. Bu makinede MySQL kurulu ama hiç
 * başlatılmamış (servisi ve veri dizini yok) ve makineye dokunmamam istendi.
 *
 * Dosya deposu bilerek şemayı birebir taklit ediyor: aynı anahtarlar, aynı
 * benzersizlik kuralları, aynı erişim yolları. MySQL'e geçişte YALNIZ bu
 * dosyanın gövdesi değişecek; api-senkron.php ve api-oyuncu.php'ye
 * dokunulmayacak. Sözleşme burada, depolama arkasında.
 *
 * Neyi taklit EDEMİYOR, dürüst olmak gerekirse:
 *   - Gerçek işlem (transaction) yok. Tüm dosya kilit altında okunup yazılıyor,
 *     yani bir istek boyunca ya hepsi ya hiçbiri geçerli — ama eşzamanlılık
 *     tek kilide indirgenmiş durumda. 30 sunucu altında bu tıkanır.
 *   - İndeks yok. Sıralama her seferinde tüm oyuncuları geziyor. 16 oyuncuda
 *     sorun değil, 20.000'de sorun.
 *   - Kısıtlar (UNIQUE, FK, CHECK) veritabanında değil burada, kodda. Yani
 *     bir hata bozuk satır üretebilir; MySQL'de üretemezdi.
 */

const DEPO_DOSYA = __DIR__ . '/veri/depo.json';

/** Boş depo iskeleti; şema değişince sürüm artar ve göç kodu buraya bakar. */
const DEPO_BOS = [
    'surum'       => 1,
    'sunucular'   => [],   // "ip:port"  => [ilk, sonGorulme]  (yalnız gözlem)
    'oyuncular'   => [],   // "authid"   => sayaçlar
    'partiler'    => [],   // "sunucu|oturum|parti" => an   (idempotency)
    'tekKullanim' => [],   // nonce => an                    (replay penceresi)
    'maclar'      => [],   // sunucuAnahtari => suren macin gecici kaydi
];

/**
 * Depoyu yazma için açar. Dönen tanıtıcı depoKapat()'a verilmek zorunda,
 * yoksa kilit istek bitene kadar tutulur ve sıradaki istek bekler.
 *
 * @return array{0: array, 1: resource}
 */
function depoAc(): array
{
    $klasor = dirname(DEPO_DOSYA);
    if (!is_dir($klasor) && !@mkdir($klasor, 0775, true) && !is_dir($klasor)) {
        throw new RuntimeException('Depo klasörü açılamadı: ' . $klasor);
    }

    $tutamac = @fopen(DEPO_DOSYA, 'c+');
    if ($tutamac === false) {
        throw new RuntimeException('Depo dosyası açılamadı: ' . DEPO_DOSYA);
    }

    if (!flock($tutamac, LOCK_EX)) {
        fclose($tutamac);
        throw new RuntimeException('Depo kilidi alınamadı');
    }

    $ham = stream_get_contents($tutamac);
    $veri = $ham === '' ? DEPO_BOS : json_decode($ham, true);

    if (!is_array($veri) || !isset($veri['surum'])) {
        flock($tutamac, LOCK_UN);
        fclose($tutamac);
        throw new RuntimeException('Depo dosyası bozuk: ' . DEPO_DOSYA);
    }

    return [$veri + DEPO_BOS, $tutamac];
}

/** Yazar ve kilidi bırakır. Yazma başarısızsa eski içerik korunuyor. */
function depoKapat($tutamac, ?array $veri = null): void
{
    if ($veri !== null) {
        $json = json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if ($json === false) {
            flock($tutamac, LOCK_UN);
            fclose($tutamac);
            throw new RuntimeException('Depo JSON kodlanamadı: ' . json_last_error_msg());
        }

        ftruncate($tutamac, 0);
        rewind($tutamac);
        fwrite($tutamac, $json);
        fflush($tutamac);
    }

    flock($tutamac, LOCK_UN);
    fclose($tutamac);
}

/** Yalnız okuma; kilit tutmuyor, MOTD sayfaları bunu kullanıyor. */
function depoOku(): array
{
    if (!is_file(DEPO_DOSYA)) {
        return DEPO_BOS;
    }

    $ham = @file_get_contents(DEPO_DOSYA);
    if ($ham === false || $ham === '') {
        return DEPO_BOS;
    }

    $veri = json_decode($ham, true);
    return is_array($veri) && isset($veri['surum']) ? $veri + DEPO_BOS : DEPO_BOS;
}

/** Boş oyuncu kaydı; senkron gelen deltaları bunun üstüne ekliyor. */
function bosOyuncu(string $authid, string $ad): array
{
    return [
        'kimlik'    => $authid,
        'ad'        => $ad,
        'ulke'      => null,
        'ajan'      => null,   // tema ajan kavramini desteklemiyorsa null kalir
        'kill'      => 0, 'death' => 0, 'asist' => 0, 'hs' => 0,
        'hasar'     => 0, 'atis'  => 0, 'isabet' => 0,
        'kurma'     => 0, 'cozme' => 0, 'mvp'   => 0,
        'kazanilan' => 0, 'kaybedilen' => 0,
        'saniye'    => 0,
        'ilk'       => gmdate('Y-m-d'),
        'son'       => gmdate('Y-m-d'),
        'silahlar'  => [],
        'haritalar' => [],
        'maclar'    => [],   // son 10 mac: harita, tarih, sonuc, skor, kda, kp
        'ilkKademeNo' => null, // yerlestirme rutbesi; ilk partide bir kez yazilir
        'sonAn'     => 0,    // son parti zamani (unix); cevrimici rozeti bundan
        'supheli'   => 0,
    ];
}

/**
 * Eski nonce'ları ve parti kayıtlarını atar.
 *
 * Sınırsız büyüyen bir tekrar-koruma listesi bellek sızıntısının dosya
 * hâli: bir yıl sonra depo dosyası nonce'lardan ibaret olurdu. Pencere
 * dışındakiler zaten reddedileceği için saklamanın anlamı yok.
 */
/* ------------------------------------------------------------------ *
 *  Maç kaydı
 *
 *  Eklenti maç diye bir şey bilmiyor, tur tur parti gönderiyor. Maçı web
 *  kuruyor: aynı sunucudan aynı oturum + harita ile gelen partiler tek
 *  maçtır; oturum ya da harita değişince maç kapanır ve katılımcıların
 *  "maclar" listesine yazılır. Kapanışın tetiği bir SONRAKİ parti olduğu
 *  için sunucu susarsa maç açık kalırdı — onu da budama süpürüyor.
 * ------------------------------------------------------------------ */

/** Sunucunun süren maçını ilerletir; oturum/harita değiştiyse eskisini kapatır. */
function macIlerlet(array $veri, string $sunucu, string $oturum, string $harita, int $simdi): array
{
    $harita = $harita === '' ? '-' : $harita;
    $acik = $veri['maclar'][$sunucu] ?? null;

    if ($acik !== null && ($acik['oturum'] !== $oturum || $acik['harita'] !== $harita)) {
        $veri = macKapat($veri, $sunucu);
        $acik = null;
    }

    if ($acik === null) {
        $veri['maclar'][$sunucu] = [
            'oturum'        => $oturum,
            'harita'        => $harita,
            'baslama'       => $simdi,
            'sonGuncelleme' => $simdi,
            'oyuncular'     => [],
        ];
    } else {
        $veri['maclar'][$sunucu]['sonGuncelleme'] = $simdi;
    }

    return $veri;
}

/** Bir oyuncunun parti deltasını süren maça işler. */
function macKatkisi(array $veri, string $sunucu, string $oyuncuAnahtari,
                    array $kayit, int $kpOnce, int $kpSonra): array
{
    if (!isset($veri['maclar'][$sunucu])) {
        return $veri;
    }

    $m = $veri['maclar'][$sunucu]['oyuncular'][$oyuncuAnahtari]
        ?? ['kill' => 0, 'death' => 0, 'asist' => 0,
            'kazanilan' => 0, 'kaybedilen' => 0, 'kpOnce' => $kpOnce];

    $m['kill']       += $kayit['kill'];
    $m['death']      += $kayit['death'];
    $m['asist']      += $kayit['asist'];
    $m['kazanilan']  += $kayit['kazanilan'];
    $m['kaybedilen'] += $kayit['kaybedilen'];
    $m['kpSonra']     = $kpSonra;

    $veri['maclar'][$sunucu]['oyuncular'][$oyuncuAnahtari] = $m;
    return $veri;
}

/**
 * Süren maçı kapatıp katılımcıların geçmişine yazar.
 *
 * Sonuç oyuncu gözünden: kazandığı tur fazlaysa galibiyet. Takım bilgisini
 * saklamıyoruz ve oyuncu maç ortasında taraf değiştirebiliyor; kendi tur
 * karnesi bu belirsizliği en dürüst özetleyen ölçü.
 */
function macKapat(array $veri, string $sunucu): array
{
    $acik = $veri['maclar'][$sunucu] ?? null;
    unset($veri['maclar'][$sunucu]);

    if ($acik === null) {
        return $veri;
    }

    foreach ($acik['oyuncular'] as $anahtar => $m) {
        $tur = $m['kazanilan'] + $m['kaybedilen'];

        /* Tek turu bile bitmemiş seyirci kaydı maç sayılmaz. */
        if ($tur === 0 && $m['kill'] === 0 && $m['death'] === 0) {
            continue;
        }

        if (!isset($veri['oyuncular'][$anahtar])) {
            continue;
        }

        $gecmis = $veri['oyuncular'][$anahtar]['maclar'] ?? [];

        $gecmis[] = [
            'harita' => $acik['harita'],
            'tarih'  => gmdate('Y-m-d', $acik['baslama']),
            'sonuc'  => $m['kazanilan'] <=> $m['kaybedilen'], // 1 G, 0 B, -1 M
            'skor'   => [$m['kazanilan'], $m['kaybedilen']],
            'kda'    => [$m['kill'], $m['death'], $m['asist']],
            'kp'     => ($m['kpSonra'] ?? $m['kpOnce']) - $m['kpOnce'],
        ];

        // Son 10 maç yeter; profil sayfası zaten fazlasını gösteremiyor.
        $veri['oyuncular'][$anahtar]['maclar'] = array_slice($gecmis, -10);
    }

    return $veri;
}

function depoBudama(array $veri, int $simdi, int $pencere = 900): array
{
    // Bir saattir parti gormeyen mac bitmistir; sunucu kapanmis ya da
    // bos kalmis demektir. Kapanis tetigi normalde sonraki parti, bu
    // supurge tetigi hic gelmeyenler icin.
    foreach (array_keys($veri['maclar'] ?? []) as $sunucu) {
        if ($veri['maclar'][$sunucu]['sonGuncelleme'] < $simdi - 3600) {
            $veri = macKapat($veri, $sunucu);
        }
    }

    foreach ($veri['tekKullanim'] as $nonce => $an) {
        if ($an < $simdi - $pencere) {
            unset($veri['tekKullanim'][$nonce]);
        }
    }

    // Parti kayıtları idempotency içindir; bir gün fazlasıyla yeter, aynı
    // partinin bir gün sonra tekrar gelmesi tekrar değil yeni veridir.
    foreach ($veri['partiler'] as $anahtar => $an) {
        if ($an < $simdi - 86400) {
            unset($veri['partiler'][$anahtar]);
        }
    }

    return $veri;
}
