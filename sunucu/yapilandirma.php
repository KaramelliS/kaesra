<?php
declare(strict_types=1);

/**
 * Görünen kimlik. Burası kurulum başına değişen TEK dosya.
 *
 * Sayfalarda sabit metin durmuyor; hepsi buradan okunuyor (bkz.
 * kaesra-ortak.php → ayar()). Kendi sunucunuz için aşağıdaki değerleri
 * değiştirmeniz yeterli, HTML'e dokunmanıza gerek yok.
 *
 * GÜVENLİK NOTU: `markaHtml` kaçışsız basılıyor, çünkü stil için HTML
 * etiketi taşıması gerekiyor. Bu dosya yalnız site sahibinin yazabildiği
 * bir dosya — yani güvenilen girdi. Buraya kullanıcıdan gelen hiçbir şey
 * koymayın.
 */

return [
    /* Sayfaların sol üstündeki büyük başlık. Stil için etiket içerebilir. */
    'markaHtml'   => 'Ka<b>e</b>sra',

    /* Düz metin marka adı (etiket başlıklarında ve profilde kullanılıyor).
       markaHtml ile aynı adı yazın, yalnız etiketsiz. */
    'marka'       => 'Kaesra',

    /* Oyuncu profilinde ve sıralamada görünen sunucu/clan adı. */
    'sunucuAdi'   => 'Sunucum',

    /* Sıralama sayfasının başlık şeridinde görünen adres.
       Boş bırakılırsa o bölüm hiç basılmaz — IP'nizi MOTD'de göstermek
       istemiyorsanız boş bırakın. */
    'sunucuAdres' => '',

    /* Sezon/akt adı. Burayı "Sezon 2" yapmanız yalnız GÖRÜNEN etiketi
       değiştirir; depodaki istatistikler sıfırlanmaz. Gerçek bir sezon
       sıfırlaması için sunucu/veri/depo.json içindeki "oyuncular"
       sözlüğünü boşaltın (dosyanın yedeğini almayı unutmayın).
       Otomatik sezon sıfırlama yol haritasında. */
    'sezon'       => 'Sezon 1',
];
