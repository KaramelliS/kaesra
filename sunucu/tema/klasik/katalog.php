<?php
declare(strict_types=1);

/**
 * Tema: KLASİK — Kaesra'in varsayılan, görselsiz teması.
 *
 * Bu tema bilerek hiçbir görsel taşımıyor. Bütün `dosya` alanları null ve
 * sayfalar buna göre <img> etiketini hiç basmıyor (bkz. varlikYolu()).
 * Yani kurulumda sıfır ek dosya, sıfır telif riski, sıfır yükleme.
 *
 * Kendi görsellerinizi eklemek için bu dosyadaki null değerleri yol ile
 * değiştirin ve dosyaları bu klasöre koyun. Ayrıntı: ../README.md
 *
 * ŞEMA — dört bölüm, hepsi zorunlu:
 *   kademe[no]  => ['ad' => string, 'dosya' => ?string, 'renk' => '#RRGGBB']
 *   silah[ad]   => ['dosya' => ?string, 'sinif' => string]
 *   silahEsleme[cs_adi] => silah adi
 *   harita[ad]  => ['dosya' => ?string, 'splash' => ?string]
 *   ajan[ad]    => ['dosya' => ?string, 'bust' => ?string, 'rol' => string,
 *                   'gradient' => [4 x '#RRGGBB']]   (ajanVar true ise)
 *
 * Kademe numaraları 1'den başlayıp ARTAN ve BOŞLUKSUZ olmalı: KP eşikleri
 * bu numaraların sırasından türetiliyor (bkz. kademeEsikleri()). Son kademe
 * "en üst" kabul ediliyor ve onda RR her zaman 100 görünüyor.
 */

return [
    'ad'      => 'Klasik',
    'ajanVar' => false,          // bu temada karakter/sınıf kavramı yok
    'bayrakVar' => true,         // ülke bayrakları bayrak/ klasöründen

    /* ---------------------------------------------------------------- *
     * Rütbe merdiveni — 25 kademe, 8 grup + zirve.
     *
     * Grup başına üç kademe var çünkü tek bir "Altın" rütbesi çok çabuk
     * geçiliyor ve anlamı kalmıyor; üç basamak oyuncuya görünür ilerleme
     * veriyor. KP aralığı sabit değil, yukarı çıktıkça açılıyor
     * (kademeEsikleri() bunu üretiyor) — üst kademeler bir haftada
     * geçilebilirse rütbenin değeri kalmaz.
     * ---------------------------------------------------------------- */
    'kademe' => [
        // Acemi — gri
        1  => ['ad' => 'Acemi 1',     'dosya' => null, 'renk' => '#8C9196'],
        2  => ['ad' => 'Acemi 2',     'dosya' => null, 'renk' => '#99A0A6'],
        3  => ['ad' => 'Acemi 3',     'dosya' => null, 'renk' => '#A6ADB4'],
        // Çırak — yeşil
        4  => ['ad' => 'Çırak 1',     'dosya' => null, 'renk' => '#5FA85F'],
        5  => ['ad' => 'Çırak 2',     'dosya' => null, 'renk' => '#6CB86C'],
        6  => ['ad' => 'Çırak 3',     'dosya' => null, 'renk' => '#79C879'],
        // Avcı — camgöbeği
        7  => ['ad' => 'Avcı 1',      'dosya' => null, 'renk' => '#3E9FB0'],
        8  => ['ad' => 'Avcı 2',      'dosya' => null, 'renk' => '#4AAFC0'],
        9  => ['ad' => 'Avcı 3',      'dosya' => null, 'renk' => '#56BFD0'],
        // Kıdemli — mavi
        10 => ['ad' => 'Kıdemli 1',   'dosya' => null, 'renk' => '#3D7FD4'],
        11 => ['ad' => 'Kıdemli 2',   'dosya' => null, 'renk' => '#4A8CE0'],
        12 => ['ad' => 'Kıdemli 3',   'dosya' => null, 'renk' => '#5799EC'],
        // Usta — mor
        13 => ['ad' => 'Usta 1',      'dosya' => null, 'renk' => '#7B5FD4'],
        14 => ['ad' => 'Usta 2',      'dosya' => null, 'renk' => '#886CE0'],
        15 => ['ad' => 'Usta 3',      'dosya' => null, 'renk' => '#9579EC'],
        // Üstat — eflatun
        16 => ['ad' => 'Üstat 1',     'dosya' => null, 'renk' => '#B45FA8'],
        17 => ['ad' => 'Üstat 2',     'dosya' => null, 'renk' => '#C06CB4'],
        18 => ['ad' => 'Üstat 3',     'dosya' => null, 'renk' => '#CC79C0'],
        // Efsane — altın
        19 => ['ad' => 'Efsane 1',    'dosya' => null, 'renk' => '#D4A03D'],
        20 => ['ad' => 'Efsane 2',    'dosya' => null, 'renk' => '#E0AC4A'],
        21 => ['ad' => 'Efsane 3',    'dosya' => null, 'renk' => '#ECB857'],
        // Kahraman — turuncu
        22 => ['ad' => 'Kahraman 1',  'dosya' => null, 'renk' => '#E0823D'],
        23 => ['ad' => 'Kahraman 2',  'dosya' => null, 'renk' => '#EC8E4A'],
        24 => ['ad' => 'Kahraman 3',  'dosya' => null, 'renk' => '#F89A57'],
        // Zirve — tek kademe, üstü yok
        25 => ['ad' => 'Zirve',       'dosya' => null, 'renk' => '#FF9F1C'],
    ],

    /* ---------------------------------------------------------------- *
     * Silahlar. Adlar CS 1.6'nın kendi adları — eşleme birebir.
     *
     * Bir tema başka bir oyunun dilini kullanmak isterse (ör. "AK-47"
     * yerine başka bir ad) yalnız bu tabloyu ve silah[] anahtarlarını
     * değiştirir; eklentiye ve motd sayfalarına dokunmaz.
     *
     * BILINEN_SILAHLAR listesi oyun tarafında (kaesra.sma) duruyor ve
     * buradaki silahEsleme ANAHTARLARIYLA birebir aynı olmalı. API
     * tanımadığı bir silah adı görürse partinin TAMAMINI 422 ile
     * reddediyor — yani tek bir sis bombası ölümü bütün turun verisini
     * düşürür. Eklentideki süzgeç bunun için var.
     * ---------------------------------------------------------------- */
    'silahEsleme' => [
        'ak47'      => 'AK-47',
        'm4a1'      => 'M4A1',
        'awp'       => 'AWP',
        'scout'     => 'Scout',
        'sg550'     => 'SG550',
        'g3sg1'     => 'G3SG1',
        'aug'       => 'AUG',
        'sg552'     => 'SG552',
        'galil'     => 'Galil',
        'famas'     => 'FAMAS',
        'm249'      => 'M249',
        'mp5navy'   => 'MP5',
        'tmp'       => 'TMP',
        'p90'       => 'P90',
        'mac10'     => 'MAC-10',
        'ump45'     => 'UMP-45',
        'xm1014'    => 'XM1014',
        'm3'        => 'M3',
        'deagle'    => 'Desert Eagle',
        'usp'       => 'USP',
        'glock18'   => 'Glock-18',
        'elite'     => 'Elite',
        'fiveseven' => 'Five-SeveN',
        'p228'      => 'P228',
        'knife'     => 'Bıçak',
    ],

    'silah' => [
        'AK-47'        => ['dosya' => null, 'sinif' => 'Tüfek'],
        'M4A1'         => ['dosya' => null, 'sinif' => 'Tüfek'],
        'Galil'        => ['dosya' => null, 'sinif' => 'Tüfek'],
        'FAMAS'        => ['dosya' => null, 'sinif' => 'Tüfek'],
        'AUG'          => ['dosya' => null, 'sinif' => 'Tüfek'],
        'SG552'        => ['dosya' => null, 'sinif' => 'Tüfek'],
        'AWP'          => ['dosya' => null, 'sinif' => 'Keskin Nişancı'],
        'Scout'        => ['dosya' => null, 'sinif' => 'Keskin Nişancı'],
        'SG550'        => ['dosya' => null, 'sinif' => 'Keskin Nişancı'],
        'G3SG1'        => ['dosya' => null, 'sinif' => 'Keskin Nişancı'],
        'MP5'          => ['dosya' => null, 'sinif' => 'Hafif Makineli'],
        'TMP'          => ['dosya' => null, 'sinif' => 'Hafif Makineli'],
        'P90'          => ['dosya' => null, 'sinif' => 'Hafif Makineli'],
        'MAC-10'       => ['dosya' => null, 'sinif' => 'Hafif Makineli'],
        'UMP-45'       => ['dosya' => null, 'sinif' => 'Hafif Makineli'],
        'M249'         => ['dosya' => null, 'sinif' => 'Ağır'],
        'XM1014'       => ['dosya' => null, 'sinif' => 'Pompalı'],
        'M3'           => ['dosya' => null, 'sinif' => 'Pompalı'],
        'Desert Eagle' => ['dosya' => null, 'sinif' => 'Tabanca'],
        'USP'          => ['dosya' => null, 'sinif' => 'Tabanca'],
        'Glock-18'     => ['dosya' => null, 'sinif' => 'Tabanca'],
        'Elite'        => ['dosya' => null, 'sinif' => 'Tabanca'],
        'Five-SeveN'   => ['dosya' => null, 'sinif' => 'Tabanca'],
        'P228'         => ['dosya' => null, 'sinif' => 'Tabanca'],
        'Bıçak'        => ['dosya' => null, 'sinif' => 'Yakın Dövüş'],
    ],

    /* ---------------------------------------------------------------- *
     * Haritalar. Anahtar, motorun verdiği ham harita adı.
     *
     * Listede olmayan bir harita gelirse ad aynen gösteriliyor, görsel
     * basılmıyor — sunucunuz özel bir harita çalıştırıyorsa sayfa
     * bozulmaz. Buradaki liste yalnız standart havuz için görsel yolu
     * tanımlamak isterseniz diye var.
     * ---------------------------------------------------------------- */
    'harita' => [
        'de_dust2'    => ['dosya' => null, 'splash' => null],
        'de_dust'     => ['dosya' => null, 'splash' => null],
        'de_inferno'  => ['dosya' => null, 'splash' => null],
        'de_nuke'     => ['dosya' => null, 'splash' => null],
        'de_train'    => ['dosya' => null, 'splash' => null],
        'de_cbble'    => ['dosya' => null, 'splash' => null],
        'de_aztec'    => ['dosya' => null, 'splash' => null],
        'de_piranesi' => ['dosya' => null, 'splash' => null],
        'de_prodigy'  => ['dosya' => null, 'splash' => null],
        'de_torn'     => ['dosya' => null, 'splash' => null],
        'de_vertigo'  => ['dosya' => null, 'splash' => null],
        'cs_assault'  => ['dosya' => null, 'splash' => null],
        'cs_italy'    => ['dosya' => null, 'splash' => null],
        'cs_militia'  => ['dosya' => null, 'splash' => null],
        'cs_office'   => ['dosya' => null, 'splash' => null],
        'cs_siege'    => ['dosya' => null, 'splash' => null],
        'cs_havana'   => ['dosya' => null, 'splash' => null],
        'as_oilrig'   => ['dosya' => null, 'splash' => null],
        'as_tundra'   => ['dosya' => null, 'splash' => null],
        'fy_snow'     => ['dosya' => null, 'splash' => null],
        'fy_pool_day' => ['dosya' => null, 'splash' => null],
        'awp_india'   => ['dosya' => null, 'splash' => null],
        'ka_deagle'   => ['dosya' => null, 'splash' => null],
        'aim_map'     => ['dosya' => null, 'splash' => null],
        'scoutzknive' => ['dosya' => null, 'splash' => null],
    ],

    /* Bu temada karakter/sınıf yok. ajanVar false olduğu için sayfalar
       bu bloğu hiç basmıyor; dizi boş bırakılıyor. */
    'ajan' => [],
];
