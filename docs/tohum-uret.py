#!/usr/bin/env python3
"""
Tohum veri üretici — sunucu/veri-tohum.php dosyasını yeniden üretir.

    python3 docs/tohum-uret.py            # sunucu/veri-tohum.php'yi yazar
    python3 docs/tohum-uret.py --yazma    # yalnız özet basar

Neden elle yazılmıyor: 16 oyuncunun sayacını, silah kırılımını ve harita
sürelerini elle dengelemek saatler sürüyor ve ilk değişiklikte bozuluyor.
Betik sabit tohumla (random.seed) çalışıyor, yani aynı girdiyle BİREBİR
aynı dosyayı üretiyor — ekran görüntüleri ve sınamalar tekrarlanabilir.

Kurallar (veri-tohum.php başındaki yorumla aynı):
  - K/D 0,59-2,58, kafadan vuruş %34-61, isabet %12-23
  - silah kırılımı favorilere ağırlıklı ama tek silahlı değil
  - harita süreleri tek haritaya yığılmaz, 30 dk dilimlerine yuvarlanır
  - kp ELLE YAZILMAZ, kazanilanPuan() formülünden hesaplanır:
        kill*1 + hs*1 + asist*1 + kurma*2 + cozme*3 + mvp*5
  - EN ÖNEMLİSİ: en iyi oyuncunun KP'si merdiven tavanının (3816) altında
    kalır. Tavan aşılırsa ilk sekiz oyuncunun hepsi "Zirve" görünür.
"""

import random
import sys

TOHUM = 20260831
TAVAN = sum(90 + 6 * i for i in range(24))          # 3816
KP_AGIRLIK = {'kill': 1, 'hs': 1, 'asist': 1, 'kurma': 2, 'cozme': 3, 'mvp': 5}

HARITALAR = ['de_dust2', 'de_inferno', 'de_nuke', 'de_train', 'cs_assault',
             'cs_italy', 'de_cbble', 'cs_office', 'awp_india', 'fy_snow']

# ad, ülke, K/D, hs oranı, isabet oranı, tecrübe(0..1), favori silahlar
OYUNCULAR = [
    ('kaptan',        'tr', 2.58, .61, .231, 1.00, ['ak47', 'm4a1', 'deagle', 'awp', 'usp', 'glock18', 'knife']),
    ('sessiz_adim',   'tr', 2.24, .55, .218, 0.93, ['awp', 'scout', 'ak47', 'usp', 'glock18', 'knife']),
    ('namlu',         'az', 2.01, .48, .204, 0.87, ['ak47', 'galil', 'mp5navy', 'deagle', 'glock18']),
    ('Gokhan',        'tr', 1.86, .44, .196, 0.81, ['m4a1', 'ak47', 'deagle', 'mp5navy', 'usp', 'glock18']),
    ('kadraj',        'tr', 1.62, .52, .181, 0.74, ['awp', 'scout', 'sg550', 'deagle', 'usp']),
    ('barut',         'nl', 1.48, .39, .175, 0.68, ['ak47', 'galil', 'mp5navy', 'deagle', 'glock18']),
    ('SUKRUU',        'tr', 1.41, .53, .140, 0.63, ['mp5navy', 'p90', 'ak47', 'usp', 'glock18']),
    ('oyunyonetici',  'tr', 1.29, .45, .186, 0.58, ['ak47', 'm4a1', 'awp', 'deagle', 'usp', 'glock18']),
    ('tolgshn',       'tr', 1.24, .60, .222, 0.54, ['scout', 'ak47', 'awp', 'usp', 'glock18']),
    ('mavi',          'tr', 1.09, .46, .167, 0.49, ['m4a1', 'mp5navy', 'ak47', 'usp', 'glock18']),
    ('eminalp',       'tr', 1.17, .51, .174, 0.45, ['m3', 'xm1014', 'ak47', 'usp', 'glock18']),
    ('golgede_kalan', 'de', 1.03, .45, .160, 0.39, ['usp', 'ak47', 'm4a1', 'glock18']),
    ('bilalcan',      'tr', 0.93, .37, .143, 0.33, ['p90', 'mp5navy', 'ak47', 'glock18']),
    ('nazar_boncugu', 'bg', 0.88, .54, .141, 0.26, ['glock18', 'usp', 'ak47', 'mp5navy']),
    ('yeni_baslayan', 'tr', 0.72, .34, .149, 0.16, ['tmp', 'mac10', 'ump45', 'glock18', 'usp']),
    ('tunahan',       'tr', 0.59, .34, .119, 0.06, ['glock18', 'usp', 'mp5navy']),
]


def bol(toplam, agirliklar):
    """toplam'ı ağırlıklara göre tamsayılara böler; toplam tam korunur."""
    t = sum(agirliklar)
    parca = [int(toplam * a / t) for a in agirliklar]
    kalan = toplam - sum(parca)
    for i in sorted(range(len(parca)), key=lambda i: -agirliklar[i])[:kalan]:
        parca[i] += 1
    return parca


def uret():
    random.seed(TOHUM)
    satirlar = []
    for ad, ulke, kd, hs_o, isb_o, tec, sil in OYUNCULAR:
        olum = int(round(40 + tec * 420 + random.uniform(-12, 12)))
        kill = int(round(olum * kd))
        hs = int(round(kill * hs_o))
        asist = int(round(kill * random.uniform(0.16, 0.24)))
        atis = int(round(kill / random.uniform(0.055, 0.085)))
        isb = int(round(atis * isb_o))
        hasar = int(round(kill * random.uniform(148, 178)))
        mvp = int(round(kill * random.uniform(0.11, 0.15)))
        kaz = int(round((kill + olum) * random.uniform(0.28, 0.36)))
        kay = int(round((kill + olum) * random.uniform(0.24, 0.32)))
        kurma = int(round(kaz * random.uniform(0.08, 0.14)))
        cozme = int(round(kaz * random.uniform(0.02, 0.07)))
        sn = (int(round((kill + olum) * random.uniform(205, 265))) // 1800) * 1800

        sayac = {'kill': kill, 'hs': hs, 'asist': asist,
                 'kurma': kurma, 'cozme': cozme, 'mvp': mvp}
        kp = sum(KP_AGIRLIK[k] * sayac[k] for k in KP_AGIRLIK)

        ag = [random.uniform(0.6, 1.0) * (1.9 ** -j) for j in range(len(sil))]
        silahlar = {k: v for k, v in zip(sil, bol(kill, ag)) if v > 0}
        silahlar = dict(sorted(silahlar.items(), key=lambda kv: -kv[1]))

        har = random.sample(HARITALAR, k=min(len(HARITALAR), 4 + int(tec * 3)))
        ha = [random.uniform(0.5, 1.0) * (1.55 ** -j) for j in range(len(har))]
        haritalar = {k: v for k, v in zip(har, [(x // 1800) * 1800 for x in bol(sn, ha)]) if v > 0}
        haritalar = dict(sorted(haritalar.items(), key=lambda kv: -kv[1]))

        import datetime
        ilk = (datetime.date(2026, 1, 1) + datetime.timedelta(days=random.randint(0, max(1, int(tec * 40))))).isoformat()
        son = (datetime.date(2026, 8, 21) - datetime.timedelta(days=random.randint(0, 10))).isoformat()
        kimlik = f"STEAM_{random.randint(0,1)}:{random.randint(0,1)}:{random.randint(10**8, 9*10**8)}"

        satirlar.append(dict(ad=ad, kimlik=kimlik, ulke=ulke, kp=kp, kill=kill,
                             death=olum, asist=asist, hs=hs, hasar=hasar,
                             atis=atis, isabet=isb, kurma=kurma, cozme=cozme,
                             mvp=mvp, kazanilan=kaz, kaybedilen=kay, saniye=sn,
                             ilk=ilk, son=son, silahlar=silahlar, haritalar=haritalar))
    satirlar.sort(key=lambda r: -r['kp'])
    return satirlar


def php_dizi(d):
    return '[' + ', '.join(f"'{k}' => {v}" for k, v in d.items()) + ']'


def yaz(satirlar):
    en = max(r['kp'] for r in satirlar)
    assert en < TAVAN, f"tavan asildi: {en} >= {TAVAN}"

    o = []
    o.append("<?php\ndeclare(strict_types=1);\n\n")
    o.append("/**\n * TOHUM VERİ — docs/tohum-uret.py ile üretildi, elle düzenlemeyin.\n")
    o.append(f" * seed={TOHUM}, en yüksek KP={en}, merdiven tavanı={TAVAN}.\n")
    o.append(" * Ayrıntı: docs/TOHUM.md\n */\n\n")
    o.append("$SICIL_OYUNCULAR = [\n")
    for r in satirlar:
        o.append("    [\n")
        o.append(f"        'ad' => '{r['ad']}', 'kimlik' => '{r['kimlik']}',\n")
        o.append(f"        'ajan' => null, 'ulke' => '{r['ulke']}',\n")
        o.append(f"        'kp' => {r['kp']}, 'kill' => {r['kill']}, 'death' => {r['death']},\n")
        o.append(f"        'asist' => {r['asist']}, 'hs' => {r['hs']}, 'hasar' => {r['hasar']},\n")
        o.append(f"        'atis' => {r['atis']}, 'isabet' => {r['isabet']},\n")
        o.append(f"        'kurma' => {r['kurma']}, 'cozme' => {r['cozme']}, 'mvp' => {r['mvp']},\n")
        o.append(f"        'kazanilan' => {r['kazanilan']}, 'kaybedilen' => {r['kaybedilen']},\n")
        o.append(f"        'saniye' => {r['saniye']}, 'ilk' => '{r['ilk']}', 'son' => '{r['son']}',\n")
        o.append(f"        'silahlar' => {php_dizi(r['silahlar'])},\n")
        o.append(f"        'haritalar' => {php_dizi(r['haritalar'])},\n")
        o.append("    ],\n")
    o.append("""];

/*
 * Sıralamayı dosyadaki yazım sırasına bırakmak kırılgan: yeni bir satırı
 * yanlış yere eklemek tabloyu sessizce bozuyor. Gerçek sorgu da ORDER BY
 * kp DESC olacak, o yüzden burada da aynı garanti veriliyor.
 */
usort($SICIL_OYUNCULAR, static fn(array $a, array $b): int => $b['kp'] <=> $a['kp']);

return $SICIL_OYUNCULAR;
""")
    return ''.join(o)


if __name__ == '__main__':
    satirlar = uret()
    en = max(r['kp'] for r in satirlar)
    print(f"{len(satirlar)} oyuncu, en yüksek KP={en}, tavan={TAVAN} "
          f"(oran {en/TAVAN:.2f})")

    if '--yazma' in sys.argv:
        sys.exit(0)

    hedef = 'sunucu/veri-tohum.php'
    with open(hedef, 'w', encoding='utf-8', newline='\n') as f:
        f.write(yaz(satirlar))
    print(f"yazıldı: {hedef}")
