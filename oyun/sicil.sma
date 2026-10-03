/**
 * Sicil — CS 1.6 için rank ve istatistik sistemi.
 *
 * Telif (c) 2026 Berkay. Tüm hakları saklıdır.
 * Bu dosya depodaki LICENSE koşullarıyla dağıtılır: kullanmak, değiştirmek
 * ve paylaşmak serbest; SATMAK, ücretli bir pakete koymak veya yazar olarak
 * başkasını göstermek yasaktır. Ayrıntı: LICENSE ve NOTICE.
 *
 * Bu dosyada üç katman var:
 *
 *   SUNUM   — komutlar, MOTD gezinmesi, sohbet rütbe etiketi, HUD, rütbe
 *             atlama duyurusu, öldüren bilgisi.
 *   TOPLAMA — kill, death, hs, asist, hasar, atış, isabet, bomba kurma
 *             ve çözme, MVP, kazanılan/kaybedilen tur, oynama süresi ve
 *             silah kırılımı. Hepsi ReAPI ve core event'lerle; csstats
 *             modülüne bağımlılık yok.
 *   SENKRON — sayaçların tur sonunda DELTA olarak api-senkron.php'ye
 *             gönderilmesi ve dönen rütbenin önbelleğe yazılması.
 *
 * KP ve rütbe burada HESAPLANMIYOR. Eklenti yalnız ham sayaç gönderiyor,
 * formül web tarafında. Böylece .sma'yı düzenleyerek rütbe şişirilemiyor
 * ve formül değişince kimseye yeni .amxx dağıtmak gerekmiyor.
 *
 * ÖLÇÜLMÜŞ KURALLAR — hepsi deneyerek bulundu, tahmin değil:
 *
 *   1. MOTD penceresi bir kez açıldıktan sonra ikinci show_motd onu
 *      GEZDİRMİYOR; eski sayfa kalıyor, log "açıldı" diyor. Tek yol
 *      cancelselect ile kapatıp ~1 sn sonra tek seferde açmak. Araya boş
 *      sayfa koymak işe yaramıyor, orada takılıyor.
 *   2. Sayfa agresif önbellekleniyor; adrese zaman damgası şart.
 *   3. RG_CBasePlayer_Killed'ın "pevAttacker" parametresi pratikte entity
 *      indeksi olarak geliyor, pev değil.
 *   4. Cvar'lar plugin_init'te değil plugin_cfg'de okunmalı; server.cfg
 *      init'ten sonra çalışıyor.
 *   5. ezjson'da geçersiz tanıtıcı EzInvalid_JSON, yani -1. Sıfır GEÇERLİ bir
 *      tanıtıcı — ilk ayrılan handle sıfır oluyor. "== 0" ile hata arayan kod
 *      her başarılı çözümlemeyi hata sanıyor. (easy_http_json.inc:29)
 *   6. amxxpc kaynaktaki UTF-8 baytlarını olduğu gibi bırakıyor: "şğüöçı
 *      ŞĞÜÖÇİ" derlemeden sonra 25 bayt. Sohbet tek baytlık çizildiği için
 *      çıkışta çevrilmesi gerekiyor; bkz. SohbetKodla.
 *
 * Modüller: reapi, easy_http (AmxxEasyHttp 1.5.0), cstrike.
 */

#include <amxmodx>
#include <amxmisc>
#include <reapi>
#include <easy_http>
#include <easy_http_json>

#define SURUM "0.5"

#define GOREV_MOTD    4200
#define GOREV_TOPLA   4300
#define GOREV_AYAR    4400

/* Bir ölümde asist sayılması için gereken en az hasar. Sıyırıp geçen
   mermi asist yazmasın; 40 can yaklaşık iki AK gövde vuruşu. */
#define ASIST_ESIGI 40

/**
 * API'nin tanıdığı silah adları — sicil-ortak.php içindeki SILAH_ESLEME
 * anahtarlarının aynısı.
 *
 * Liste burada da duruyor çünkü api-senkron.php tanımadığı bir ad görürse
 * partinin TAMAMINI 422 ile reddediyor. Bomba, el bombası ve dumanla
 * yapılan öldürmeler bu listede yok; süzgeç eklentide olmazsa tek sis
 * bombası ölümü bütün turun verisini düşürürdü.
 */
new const BILINEN_SILAHLAR[][] = {
    "ak47", "m4a1", "awp", "scout", "sg550", "g3sg1", "aug", "sg552",
    "galil", "famas", "m249", "mp5navy", "tmp", "p90", "mac10", "ump45",
    "xm1014", "m3", "deagle", "usp", "glock18", "elite", "fiveseven",
    "p228", "knife"
}

/* Rütbe bilgisi gelmemiş oyuncu için kademe numarası. */
#define KADEME_YOK -1

/* Sohbet/HUD çıkış kodlaması; ayrıntı SohbetKodla'nın başında. */
#define KOD_UTF8   0
#define KOD_CP1254 1
#define KOD_ASCII  2

/* Yönetim menüsü iki kademeli: menüyü açmak ile sunucunun tamamını
   etkileyen maddeleri çalıştırmak ayrı yetkiler. Yardımcı admin bakabilsin,
   herkesin ekranına MOTD açamasın. */
#define YETKI_MENU ADMIN_LEVEL_A
#define YETKI_TAM  ADMIN_RCON

new g_api[128]
/*
 * Sunucu ile web servisi arasındaki paylaşılan anahtar. Her kurulum kendi
 * anahtarını üretir (bkz. KURULUM.md); API'ye yalnız bu anahtarı bilen
 * sunucu veri yazabiliyor.
 *
 * Anahtar 32 bayt, yani 64 haneli onaltılık. Dizi 64 hücre olduğunda son
 * hane EOS'a kurban gidiyor ve API isteği "anahtar taninmiyor" diye
 * reddediyordu — eklenti de kendini kurulum aşamasında sanıp veri
 * göndermiyordu. Payı bilerek geniş.
 */
new g_anahtar[96]
new g_gunluk
new g_sohbetEtiketi
new g_sohbetKodlama
new bool:g_botlariSay

new EzHttpQueue:g_kuyruk

new g_bekleyen[MAX_PLAYERS + 1][160]

/* Web'den gelen rütbe özeti. Bağlanınca bir kez çekiliyor, sonra hep
   buradan okunuyor — her sohbet satırında istek atmak 32 kişilik sunucuda
   saniyede onlarca çağrı demekti. */
new g_kademeAdi[MAX_PLAYERS + 1][20]
new g_kademeNo[MAX_PLAYERS + 1]
new g_kp[MAX_PLAYERS + 1]
new g_sira[MAX_PLAYERS + 1]
new g_kalan[MAX_PLAYERS + 1]
new g_ustKademe[MAX_PLAYERS + 1][20]
new Float:g_kd[MAX_PLAYERS + 1]
new g_hsYuzde[MAX_PLAYERS + 1]

/* Oturum sayaçları: bu haritada, bu bağlantıda. Toplam istatistik web'de. */
new g_oturumKill[MAX_PLAYERS + 1]
new g_oturumDeath[MAX_PLAYERS + 1]
new g_oturumHs[MAX_PLAYERS + 1]
new g_oturumAsist[MAX_PLAYERS + 1]

/* Tur içi seri. g_turKill her tur başında sıfırlanıyor; g_enIyiSeri
   oturum boyunca kalıyor ve /oturum çıktısında görünüyor. */
new g_turKill[MAX_PLAYERS + 1]
new g_enIyiSeri[MAX_PLAYERS + 1]
new bool:g_ilkKanAlindi

/* Rütbe çekilmeyi bekleyen oyuncular; harita başında 20 kişi birden
   bağlanıyor ve 20 ayrı istek yerine tek toplu istek gidiyor. */
new g_toplamaKuyrugu[MAX_PLAYERS + 1]
new g_toplamaSayisi = 0

new g_hataSayaci = 0
new g_sonYenileme = 0

/* ------------------------------------------------------------------ *
 *  Senkron sayaçları
 *
 *  Oturum sayaçlarından ayrılar, bilerek. Oturum sayaçları ekranda
 *  gösteriliyor ve harita boyunca duruyor; bunlar her partide web'e
 *  gönderilip sıfırlanan DELTA. İkisini tek dizide tutmak, gönderim
 *  sonrası sıfırlamanın /oturum çıktısını da silmesi demekti.
 * ------------------------------------------------------------------ */

new g_sKill[MAX_PLAYERS + 1]
new g_sDeath[MAX_PLAYERS + 1]
new g_sAsist[MAX_PLAYERS + 1]
new g_sHs[MAX_PLAYERS + 1]
new g_sHasar[MAX_PLAYERS + 1]
new g_sAtis[MAX_PLAYERS + 1]
new g_sIsabet[MAX_PLAYERS + 1]
new g_sKurma[MAX_PLAYERS + 1]
new g_sCozme[MAX_PLAYERS + 1]
new g_sMvp[MAX_PLAYERS + 1]
new g_sKazanilan[MAX_PLAYERS + 1]
new g_sKaybedilen[MAX_PLAYERS + 1]

/* Silah kırılımı. Dizin BILINEN_SILAHLAR içindeki sıra; böylece hem
   süzgeç hem depolama tek listeye bağlı ve cstrike modülünün CSW_*
   sabitlerine ihtiyaç kalmıyor (silah adı DeathMsg'den string geliyor). */
new g_sSilah[MAX_PLAYERS + 1][sizeof(BILINEN_SILAHLAR)]

/* Oynama süresi anlık görüntüsü — ayrı bir sayaç tutulmuyor, delta
   doğrudan get_user_time farkından çıkarılıyor. */
new g_saniyeIsaret[MAX_PLAYERS + 1]

/* Atış sayacının hafızası: son görülen silah ve şarjördeki mermi. */
new g_sonSilah[MAX_PLAYERS + 1]
new g_sonMermi[MAX_PLAYERS + 1]

/* Asist için tur içi hasar matrisi: kim kime kaç hasar verdi. */
new g_hasarMatrisi[MAX_PLAYERS + 1][MAX_PLAYERS + 1]

/* ------------------------------------------------------------------ *
 *  Senkron durumu
 * ------------------------------------------------------------------ */

new g_oturumKimligi[24]
new g_parti = 0
new g_sonParti = 0            // son başarılı gönderimin zamanı; "gecen" bundan
new bool:g_ucusta = false     // yanıtı beklenen bir parti var mı

/*
 * Gönderilen partinin gövdesi, yanıt gelene kadar saklanıyor.
 *
 * Sayaçlar gövde kurulurken sıfırlanıyor; ağ koparsa veri gövdenin
 * içinde duruyor ve AYNI parti numarasıyla tekrar gönderiliyor.
 * api-senkron.php parti anahtarını idempotency için tuttuğundan tekrar
 * gönderim çift saymıyor. Sayaçları yanıt gelene kadar bekletmek yerine
 * gövdeyi saklamak, tur ortasında çıkan oyuncunun verisini de kurtarıyor:
 * gövde kurulduktan sonra o oyuncunun diziden silinmesi bir şey bozmuyor.
 */
new g_partiGovde[12288]
new bool:g_tekrarBekliyor = false

/* Uzatma: 1 ise 12-12'de maç 13'te bitmez, 2 fark aranır (14, 15, ...). */
new g_uzatmaAcik
new g_uzatmaSayisi = 0    // bu haritada kaç kez uzatıldı; duyuru metni için

public plugin_init()
{
    register_plugin("Sicil", SURUM, "sicil")

    g_kuyruk = ezhttp_create_queue()

    register_clcmd("say", "SohbetYakala")
    register_clcmd("say_team", "SohbetYakala")

    /*
     * Konsol karşılıkları. Oyuncu tuşa bağlayabilsin diye: "bind F5 rank"
     * çalışmıyorsa oyuncu her seferinde sohbeti açıp yazmak zorunda ve tur
     * ortasında kimse bunu yapmıyor. Aynı işleyiciye gidiyorlar.
     */
    register_clcmd("rank", "KonsolKomut")
    register_clcmd("sicil", "KonsolKomut")
    register_clcmd("top", "KonsolKomut")
    register_clcmd("kp", "KonsolKomut")
    register_clcmd("oturum", "KonsolKomut")
    register_clcmd("silahlar", "KonsolKomut")
    register_clcmd("haritalar", "KonsolKomut")
    register_clcmd("rutbeler", "KonsolKomut")
    register_clcmd("yonetim", "KonsolKomut")

    RegisterHookChain(RG_CBasePlayer_Killed, "OlumReapi", true)
    register_event("DeathMsg", "OlumMesaji", "a", "1>0")
    register_logevent("TurBasladi", 2, "1=Round_Start")

    /* Hasar, isabet ve asistin tek kaynağı. */
    RegisterHookChain(RG_CBasePlayer_TakeDamage, "HasarAldi", false)

    /* Bomba. KP'de kurma 2, çözme 3 puan — ikisi de rütbeyi etkiliyor. */
    RegisterHookChain(RG_PlantBomb, "BombaKuruldu", true)
    RegisterHookChain(RG_CGrenade_DefuseBombEnd, "BombaCozuldu", true)

    /* Tur sonu: kazanılan/kaybedilen, MVP ve senkron gönderimi. */
    RegisterHookChain(RG_RoundEnd, "TurBitti", true)

    /*
     * Atış sayacı. Her silahın ateş fonksiyonunu ayrı hooklamak yerine
     * şarjördeki mermiyi izliyoruz: CurWeapon her değişimde düşüyor ve
     * AYNI silahta mermi azaldıysa aradaki fark kadar atış yapılmış
     * demektir. Şarj etme ve silah değiştirme mermiyi artırdığı için
     * yalnız azalma sayılıyor. Tek olay, bütün mermili silahlar.
     */
    register_event("CurWeapon", "SilahDurumu", "be", "1=1")

    register_srvcmd("sicil_durum", "KonsolDurum")
    register_srvcmd("sicil_yenile", "KonsolYenile")

    /*
     * Sunucudan bir oyuncuya sayfa açtırma: sicil_goster <ad parçası> <komut>
     * Örn: sicil_goster Berkay /top
     *
     * İki işi var: rcon üzerinden uzaktan destek ("menün açılmıyor mu,
     * ben açayım bak") ve tanıtım görüntülerinin elle oynamadan alınması.
     * KomutDagit'e aynen giriyor; oyuncunun kendi yazdığından farksız.
     */
    register_srvcmd("sicil_goster", "KonsolGoster")

    /*
     * sicil_istemci <ad parçası> <komut> — oyuncuya istemci komutu iter.
     * MOTD penceresi yalnız OK tıklamasıyla kapanıyor ve tanıtım çekimi
     * sırasında pencereyi dışarıdan kapatmanın tek yolu istemciye komut
     * bastırmak (retry gibi). rcon gerektirir; kayda geçer.
     */
    register_srvcmd("sicil_istemci", "KonsolIstemci")

    register_cvar("sicil_surum", SURUM, FCVAR_SERVER | FCVAR_SPONLY)
}

public plugin_cfg()
{
    AyarlariOku()

    /*
     * Oturum kimliği her açılışta yeni. API parti sayacını oturumla
     * birlikte anahtarladığı için harita değişince sayaç sıfırdan
     * başlayabiliyor; aynı oturumda aynı parti iki kez yazılmıyor.
     */
    formatex(g_oturumKimligi, charsmax(g_oturumKimligi), "%x%x",
        get_systime(), random_num(0x10000000, 0x7FFFFFFF))

    g_sonParti = get_systime()

    server_print("[sicil] %s yuklendi, api=%s, sohbet etiketi=%d", SURUM, g_api, g_sohbetEtiketi)

    /*
     * ÖLÇÜLDÜ: bu kurulumda plugin_cfg, server.cfg'den ÖNCE çalışıyor.
     * İlk okumada sicil_api ve sicil_anahtar hâlâ create_cvar'ın
     * varsayılanı oluyor — yani anahtar boş görünüyor ve eklenti kendini
     * kurulum aşamasında sanıp hiç veri göndermiyor. Ayarlar üç saniye
     * sonra bir kez daha okunuyor; asıl karar orada veriliyor.
     */
    set_task(3.0, "AyarlariTazele", GOREV_AYAR)
}

public AyarlariTazele()
{
    new onceki[128]
    copy(onceki, charsmax(onceki), g_api)

    AyarlariOku()

    if (!equal(onceki, g_api)) {
        server_print("[sicil] api adresi guncellendi: %s", g_api)

        /* İlk okumadaki yanlış adrese gitmiş sorgular boş dönmüş olabilir. */
        KuyrugaHerkesiKoy()
    }

    if (g_anahtar[0] == EOS) {
        /*
         * Kurulum aşaması. Eklenti durdurulmuyor: admin oyun içi komutları
         * ve MOTD sayfalarını deneyebilsin, yalnız web servisine veri
         * gönderilmiyor.
         */
        server_print("[sicil] sicil_anahtar bos. Veri gonderilmeyecek;")
        server_print("[sicil] php sunucu/anahtar-uret.php ile bir anahtar uretip sicil.cfg icine yazin.")
    }
}

AyarlariOku()
{
    g_api[0] = EOS
    get_pcvar_string(OlusturVeyaBul("sicil_api", "http://127.0.0.1:8130",
        "Sicil web servisinin kok adresi, sonunda / olmadan"), g_api, charsmax(g_api))

    get_pcvar_string(OlusturVeyaBul("sicil_anahtar", "",
        "Web servisiyle paylasilan anahtar. Bos ise eklenti veri gondermez."), g_anahtar, charsmax(g_anahtar))

    g_gunluk = get_pcvar_num(OlusturVeyaBul("sicil_gunluk", "0", "1 ise her komut konsola yazilir"))
    g_sohbetEtiketi = get_pcvar_num(OlusturVeyaBul("sicil_sohbet_etiketi", "1",
        "1 ise sohbette oyuncu adinin onunde rutbesi gorunur"))
    g_botlariSay = get_pcvar_num(OlusturVeyaBul("sicil_botlari_say", "0",
        "1 ise botlarin istatistigi de tutulur; test icin")) != 0

    g_sohbetKodlama = get_pcvar_num(OlusturVeyaBul("sicil_sohbet_kodlama", "0",
        "Sohbet ve HUD kodlamasi: 0 UTF-8, 1 CP1254 (eski istemci), 2 ASCII"))

    g_uzatmaAcik = get_pcvar_num(OlusturVeyaBul("sicil_uzatma", "1",
        "1 ise beraberlikte mac uzar: 12-12'de 14, 13-13'te 15 kazanir"))

    if (g_sohbetKodlama < KOD_UTF8 || g_sohbetKodlama > KOD_ASCII) {
        server_print("[sicil] sicil_sohbet_kodlama %d gecersiz, UTF-8'e donuldu", g_sohbetKodlama)
        g_sohbetKodlama = KOD_UTF8
    }

    new son = strlen(g_api) - 1
    if (son >= 0 && g_api[son] == '/') {
        g_api[son] = EOS
    }
}

/** create_cvar var olan cvar'ı ikinci kez kaydetmiyor; ikisini tek yerde topluyoruz. */
OlusturVeyaBul(const ad[], const varsayilan[], const aciklama[])
{
    new isaretci = get_cvar_pointer(ad)
    return isaretci == 0 ? create_cvar(ad, varsayilan, _, aciklama) : isaretci
}

/* ------------------------------------------------------------------ *
 *  Bağlantı ve rütbe önbelleği
 * ------------------------------------------------------------------ */

public client_putinserver(id)
{
    Sifirla(id)

    if (is_user_bot(id) && !g_botlariSay) {
        return
    }

    /*
     * Hemen istek atmıyoruz: harita başında herkes aynı anda giriyor ve
     * 20 ayrı HTTP isteği çıkıyor. Yarım saniyelik pencerede biriktirip
     * tek toplu sorgu gönderiyoruz.
     */
    if (g_toplamaSayisi < MAX_PLAYERS) {
        g_toplamaKuyrugu[g_toplamaSayisi++] = id
    }

    remove_task(GOREV_TOPLA)
    set_task(0.6, "KuyruguGonder", GOREV_TOPLA)
}

/*
 * Not: tur ortasında çıkan oyuncunun o turdaki deltası kaydedilmiyor.
 * Buradan senkron tetiklemek işe yaramaz — bu noktada oyuncu artık
 * get_players listesinde değil, dolayısıyla partiye giremez. Önceki
 * turların verisi zaten gönderilmiş durumda, kayıp tek turla sınırlı.
 */
public client_disconnected(id)
{
    remove_task(GOREV_MOTD + id)
    Sifirla(id)
}

Sifirla(id)
{
    g_kademeAdi[id][0] = EOS
    g_ustKademe[id][0] = EOS
    g_kademeNo[id] = KADEME_YOK
    g_kp[id] = 0
    g_sira[id] = 0
    g_kalan[id] = 0
    g_kd[id] = 0.0
    g_hsYuzde[id] = 0
    g_oturumKill[id] = 0
    g_oturumDeath[id] = 0
    g_oturumHs[id] = 0
    g_oturumAsist[id] = 0
    g_turKill[id] = 0
    g_enIyiSeri[id] = 0
    g_bekleyen[id][0] = EOS

    SenkronSayaclariniSifirla(id)

    g_saniyeIsaret[id] = 0
    g_sonSilah[id] = 0
    g_sonMermi[id] = 0

    for (new i = 0; i <= MAX_PLAYERS; i++) {
        g_hasarMatrisi[id][i] = 0
        g_hasarMatrisi[i][id] = 0
    }
}

/** Web'e gidecek delta sayaçları. Gövde kurulduktan sonra çağrılıyor. */
SenkronSayaclariniSifirla(id)
{
    g_sKill[id] = 0
    g_sDeath[id] = 0
    g_sAsist[id] = 0
    g_sHs[id] = 0
    g_sHasar[id] = 0
    g_sAtis[id] = 0
    g_sIsabet[id] = 0
    g_sKurma[id] = 0
    g_sCozme[id] = 0
    g_sMvp[id] = 0
    g_sKazanilan[id] = 0
    g_sKaybedilen[id] = 0

    for (new i = 0; i < sizeof(BILINEN_SILAHLAR); i++) {
        g_sSilah[id][i] = 0
    }
}

/**
 * Gönderilecek bir şey var mı?
 *
 * Süre de sayılıyor: hiç öldürmeyen bir oyuncunun oynama süresi de
 * kaydedilmeli, yoksa "haritalar" istatistiği yalnız katilleri sayar.
 */
bool:SenkronVerisiVar(id)
{
    if (get_user_time(id, 1) > g_saniyeIsaret[id]) {
        return true
    }

    if (g_sKill[id] || g_sDeath[id] || g_sAsist[id] || g_sHs[id]
        || g_sHasar[id] || g_sAtis[id] || g_sIsabet[id] || g_sKurma[id]
        || g_sCozme[id] || g_sMvp[id] || g_sKazanilan[id]
        || g_sKaybedilen[id]) {
        return true
    }

    return false
}

public KuyruguGonder()
{
    if (g_toplamaSayisi == 0) {
        return
    }

    new kimlikler[512]
    new eklenen = 0

    for (new i = 0; i < g_toplamaSayisi; i++) {
        new id = g_toplamaKuyrugu[i]
        if (!is_user_connected(id)) {
            continue
        }

        new kimlik[40]
        get_user_authid(id, kimlik, charsmax(kimlik))
        if (kimlik[0] == EOS || equal(kimlik, "BOT")) {
            continue
        }

        format(kimlikler, charsmax(kimlikler), "%s%s%s", kimlikler, eklenen == 0 ? "" : ",", kimlik)
        eklenen++
    }

    g_toplamaSayisi = 0

    if (eklenen == 0) {
        return
    }

    RutbeCek(kimlikler)
}

RutbeCek(const kimlikler[])
{
    new EzHttpOptions:secenek = ezhttp_create_options()
    ezhttp_option_set_queue(secenek, g_kuyruk)
    ezhttp_option_set_timeout(secenek, 8000)
    ezhttp_option_add_url_parameter(secenek, "kimlik", kimlikler)

    new adres[192]
    formatex(adres, charsmax(adres), "%s/api-oyuncu.php", g_api)

    ezhttp_get(adres, "RutbeGeldi", secenek)

    if (g_gunluk) {
        server_print("[sicil] rutbe cekiliyor: %s", kimlikler)
    }
}

public RutbeGeldi(EzHttpRequest:istek)
{
    if (ezhttp_get_error_code(istek) != EZH_OK) {
        new mesaj[128]
        ezhttp_get_error_message(istek, mesaj, charsmax(mesaj))
        g_hataSayaci++
        server_print("[sicil] rutbe cekilemedi: %s", mesaj)
        return
    }

    new kod = ezhttp_get_http_code(istek)
    if (kod != 200) {
        g_hataSayaci++
        server_print("[sicil] rutbe ucu HTTP %d dondu", kod)
        return
    }

    /*
     * Gövdeyi Pawn tarafına hiç kopyalamıyoruz. ezhttp_get_data ile bir
     * diziye alıp ezjson_parse'a vermek iki tuzak açıyordu: 32 kişilik
     * kadroda yanıt ~10 KB ve tampon küçükse JSON ortasından kesilip
     * "çözümlenemedi" gibi görünüyor; tamponu büyütünce de yerel dizi
     * varsayılan 16 KB'lık yığını taşırıyor. Bu native gövdeyi C++
     * tarafında çözümlüyor, ikisi de olmuyor.
     */
    new EzJSON:kok = ezhttp_parse_json_response(istek)
    if (kok == EzInvalid_JSON) {
        /* Tanı için gövdenin başı; hata yolunda olduğumuz için küçük
           tampon yeterli ve yığın açısından güvenli. */
        new bas[192]
        ezhttp_get_data(istek, bas, charsmax(bas))

        g_hataSayaci++
        server_print("[sicil] rutbe yaniti cozumlenemedi, gelen: %s", bas)
        return
    }

    new EzJSON:dizi = ezjson_object_get_value(kok, "oyuncular")
    if (dizi != EzInvalid_JSON) {
        new adet = ezjson_array_get_count(dizi)
        for (new i = 0; i < adet; i++) {
            KayitIsle(ezjson_array_get_value(dizi, i))
        }
    }

    ezjson_free(kok)
    g_sonYenileme = get_systime()
}

KayitIsle(EzJSON:kayit)
{
    if (kayit == EzInvalid_JSON) {
        return
    }

    new kimlik[40]
    ezjson_object_get_string(kayit, "kimlik", kimlik, charsmax(kimlik))

    new id = KimlikleBul(kimlik)
    if (id == 0) {
        return
    }

    new eskiKademe = g_kademeNo[id]

    ezjson_object_get_string(kayit, "kademe", g_kademeAdi[id], charsmax(g_kademeAdi[]))
    ezjson_object_get_string(kayit, "ustKademe", g_ustKademe[id], charsmax(g_ustKademe[]))
    g_kademeNo[id] = ezjson_object_get_number(kayit, "kademeNo")
    g_kp[id]       = ezjson_object_get_number(kayit, "kp")
    g_sira[id]     = ezjson_object_get_number(kayit, "sira")
    g_kalan[id]    = ezjson_object_get_number(kayit, "kalan")
    g_hsYuzde[id]  = ezjson_object_get_number(kayit, "hs")
    g_kd[id]       = ezjson_object_get_real(kayit, "kd")

    /*
     * Rütbe atlama duyurusu. İlk çekimde (eskiKademe == KADEME_YOK) duyuru
     * yapılmıyor — oyuncu bağlanır bağlanmaz "Elmas oldun!" demek saçma
     * olurdu, o rütbeye dün çıkmış olabilir.
     */
    if (eskiKademe != KADEME_YOK && g_kademeNo[id] > eskiKademe) {
        RutbeAtladi(id)
    }
}

KimlikleBul(const kimlik[])
{
    new oyuncular[MAX_PLAYERS], sayi
    get_players(oyuncular, sayi)

    for (new i = 0; i < sayi; i++) {
        new mevcut[40]
        get_user_authid(oyuncular[i], mevcut, charsmax(mevcut))
        if (equal(mevcut, kimlik)) {
            return oyuncular[i]
        }
    }
    return 0
}

RutbeAtladi(id)
{
    new ad[32]
    get_user_name(id, ad, charsmax(ad))

    Bilgi(0, "^x03%s^x01 rütbe atladı: ^x04%s^x01 (%d KP)", ad, g_kademeAdi[id], g_kp[id])

    set_hudmessage(255, 70, 85, -1.0, 0.22, 0, 0.0, 6.0, 0.2, 0.4, 4)
    HudYaz(id, "RÜTBE ATLADIN^n^n%s^n%d KP", g_kademeAdi[id], g_kp[id])

    client_cmd(id, "spk ^"buttons/bell1^"")
}

/* ------------------------------------------------------------------ *
 *  Oturum sayaçları
 * ------------------------------------------------------------------ */

public OlumReapi(const olen, const saldirgan, const gib)
{
    if (!is_user_connected(olen)) {
        return
    }

    g_oturumDeath[olen]++
    g_sDeath[olen]++

    if (saldirgan == olen || !is_user_connected(saldirgan)) {
        /* İntihar ya da dünya hasarı; asist yok ama kurbanın hasar
           satırı yine de temizlenmeli, sonraki ölümüne taşınmasın. */
        HasarSatiriniTemizle(olen)
        return
    }

    g_oturumKill[saldirgan]++
    g_sKill[saldirgan]++

    OldurenBilgisi(olen, saldirgan)
    AsistVer(olen, saldirgan)
    HasarSatiriniTemizle(olen)

    if (!g_ilkKanAlindi) {
        g_ilkKanAlindi = true
        IlkKan(saldirgan, olen)
    }

    g_turKill[saldirgan]++

    if (g_turKill[saldirgan] > g_enIyiSeri[saldirgan]) {
        g_enIyiSeri[saldirgan] = g_turKill[saldirgan]
    }

    CokluOldurme(saldirgan, g_turKill[saldirgan])
}

/**
 * Hasar, isabet ve asistin tek kaynağı.
 *
 * TakeDamage her hasar olayında düşüyor: düşme, patlama, dünya hasarı
 * dahil. Yalnızca bir oyuncunun BAŞKA bir oyuncuya verdiği hasar
 * sayılıyor. Ön kancada (pre) dinleniyor çünkü asist kararı ölümden
 * önce birikmiş hasara bakıyor; RG_CBasePlayer_Killed son kancada
 * çalıştığı için sıralama doğru oluyor.
 */
public HasarAldi(const kurban, const patlatici, const saldirgan, Float:hasar, hasarTuru)
{
    if (kurban < 1 || kurban > MAX_PLAYERS) {
        return
    }

    if (saldirgan < 1 || saldirgan > MAX_PLAYERS || saldirgan == kurban) {
        return
    }

    if (!is_user_connected(saldirgan)) {
        return
    }

    new miktar = floatround(hasar)
    if (miktar <= 0) {
        return
    }

    g_sHasar[saldirgan] += miktar
    g_sIsabet[saldirgan]++
    g_hasarMatrisi[saldirgan][kurban] += miktar
}

/**
 * Öldüren dışında kurbana en çok hasar veren oyuncuya asist yazar.
 *
 * Eşik var çünkü sıyırıp geçen tek mermi asist sayılmamalı; KP'de asist
 * öldürmeyle aynı puanı veriyor (sicil-ortak.php KP_DEGERLERI).
 */
AsistVer(olen, olduren)
{
    new asistci = 0, enCok = 0

    for (new i = 1; i <= MAX_PLAYERS; i++) {
        if (i == olduren || i == olen || !is_user_connected(i)) {
            continue
        }

        if (g_hasarMatrisi[i][olen] > enCok) {
            enCok = g_hasarMatrisi[i][olen]
            asistci = i
        }
    }

    if (asistci != 0 && enCok >= ASIST_ESIGI) {
        g_oturumAsist[asistci]++
        g_sAsist[asistci]++
    }
}

/** Kurbana verilmiş hasar kayıtları; bir sonraki ölümüne taşınmamalı. */
HasarSatiriniTemizle(kurban)
{
    for (new i = 1; i <= MAX_PLAYERS; i++) {
        g_hasarMatrisi[i][kurban] = 0
    }
}

/**
 * Atış sayacı — şarjördeki mermiyi izleyerek.
 *
 * Aynı silahta mermi azaldıysa aradaki fark kadar atış yapılmıştır.
 * Şarj etme ve silah değiştirme mermiyi artırdığı ya da silahı
 * değiştirdiği için o durumlar sayılmıyor.
 */
public SilahDurumu(id)
{
    new silah = read_data(2)
    new mermi = read_data(3)

    if (silah == g_sonSilah[id] && mermi < g_sonMermi[id]) {
        g_sAtis[id] += g_sonMermi[id] - mermi
    }

    g_sonSilah[id] = silah
    g_sonMermi[id] = mermi
}

public BombaKuruldu(const kuran, Float:vecStart[3], Float:vecVelocity[3])
{
    if (kuran >= 1 && kuran <= MAX_PLAYERS && is_user_connected(kuran)) {
        g_sKurma[kuran]++
    }
}

public BombaCozuldu(const bomba, const cozen, bool:cozuldu)
{
    /* Kanca, çözmeyi yarıda bırakanlar için de düşüyor. */
    if (!cozuldu) {
        return
    }

    if (cozen >= 1 && cozen <= MAX_PLAYERS && is_user_connected(cozen)) {
        g_sCozme[cozen]++
    }
}

/**
 * Tur sonu: kazanılan/kaybedilen, MVP ve senkron gönderimi.
 *
 * MVP kazanan takımda o turda en çok devireni alıyor. Beraberlikte
 * kimseye verilmiyor — MVP, KP'de en ağır madde (5 puan) ve kimsenin
 * kazanmadığı bir tur için dağıtmak onu ucuzlatırdı.
 */
public TurBitti(WinStatus:durum, ScenarioEventEndRound:olay, Float:gecikme)
{
    new kazananTakim = 0
    if (durum == WINSTATUS_CTS) {
        kazananTakim = 2
    } else if (durum == WINSTATUS_TERRORISTS) {
        kazananTakim = 1
    }

    new mvp = 0, mvpKill = 0

    new oyuncular[MAX_PLAYERS], sayi
    get_players(oyuncular, sayi, "ch")

    for (new i = 0; i < sayi; i++) {
        new id = oyuncular[i]
        new takim = get_user_team(id)

        /* Seyirci ve takımsızlar tur sonucundan pay almıyor. */
        if (takim != 1 && takim != 2) {
            continue
        }

        if (kazananTakim == 0) {
            continue
        }

        if (takim == kazananTakim) {
            g_sKazanilan[id]++

            if (g_turKill[id] > mvpKill) {
                mvpKill = g_turKill[id]
                mvp = id
            }
        } else {
            g_sKaybedilen[id]++
        }
    }

    if (mvp != 0) {
        g_sMvp[mvp]++
    }

    UzatmaKontrol()
    SenkronGonder()
}

/**
 * Valorant maç formatı: 13 kazanan alır ama beraberlikte 2 fark aranır.
 *
 * 12-12'de tavan 14'e çekilir, 13-13 olursa 15'e, sonu gelene kadar.
 * mp_winlimit'i motorun kendisi uyguluyor; biz yalnız beraberlik anında
 * tavanı ileri itiyoruz. Harita değişince cfg tavanı 13'e geri kurar,
 * yani uzatma haritaya hapsolur — kalıcı bir ayar bozulmaz.
 *
 * mp_winlimit 0 (kapalı) ya da sicil_uzatma 0 ise hiç karışmıyoruz:
 * maxrounds ile dönen ya da süre limitli sunucular kendi bildiğinde.
 */
UzatmaKontrol()
{
    if (!g_uzatmaAcik) {
        return
    }

    new tavan = get_cvar_num("mp_winlimit")
    if (tavan < 2) {
        return
    }

    new ct = get_member_game(m_iNumCTWins)
    new tero = get_member_game(m_iNumTerroristWins)

    /* Beraberlik ve taraflardan biri tavanın bir eksiğinde: bir sonraki
       turda maç 1 farkla biterdi, oysa kural 2 fark istiyor. */
    if (ct != tero || ct + 2 <= tavan) {
        return
    }

    new yeniTavan = ct + 2
    server_cmd("mp_winlimit %d", yeniTavan)
    g_uzatmaSayisi++

    server_print("[sicil] uzatma %d: skor %d-%d, mac %d kazanan alir",
        g_uzatmaSayisi, ct, tero, yeniTavan)

    Bilgi(0, "^x04UZATMA!^x01 Skor ^x03%d - %d^x01 · maçı ^x04%d^x01 kazanan tur alır", ct, tero, yeniTavan)

    set_hudmessage(255, 70, 85, -1.0, 0.30, 1, 0.0, 5.0, 0.3, 0.5, 3)
    HudYaz(0, "UZATMA^n%d - %d^n^n%d kazanan tur maçı alır", ct, tero, yeniTavan)

    client_cmd(0, "spk ^"ambience/siren^"")
}

/**
 * Silah adının bilinen listedeki sırası; tanınmıyorsa -1.
 *
 * API bilinmeyen bir ad görürse partinin tamamını reddettiği için
 * el bombası, sis ve bomba ölümleri burada eleniyor.
 */
SilahIndeksi(const ad[])
{
    for (new i = 0; i < sizeof(BILINEN_SILAHLAR); i++) {
        if (equal(ad, BILINEN_SILAHLAR[i])) {
            return i
        }
    }
    return -1
}

/* ------------------------------------------------------------------ *
 *  Senkron — sayaçların web'e aktarılması
 * ------------------------------------------------------------------ */

/**
 * Tur sonunda çağrılıyor.
 *
 * Uçuşta bir parti varken yenisi kurulmuyor: sayaçlar birikmeye devam
 * eder ve bir sonraki tur sonunda tek partide gider. Sıra bozulmasın
 * diye böyle — API parti numarasını oturum içinde artan kabul ediyor.
 */
SenkronGonder()
{
    if (g_anahtar[0] == EOS || g_ucusta) {
        return
    }

    /* Önceki parti yolda kaldıysa yenisini kurmadan onu tekrar gönder. */
    if (!g_tekrarBekliyor && !PartiyiKur()) {
        return
    }

    PartiyiYolla()
}

/**
 * Gönderilecek gövdeyi kurar ve gövdeye giren sayaçları sıfırlar.
 *
 * @return partide en az bir oyuncu varsa true
 */
bool:PartiyiKur()
{
    new harita[36]
    get_mapname(harita, charsmax(harita))
    if (!HaritaAdiGecerli(harita)) {
        harita[0] = EOS
    }

    /* API 1-86400 arası bekliyor; ilk partide eklentinin açılışından beri. */
    new gecen = get_systime() - g_sonParti
    if (gecen < 1) {
        gecen = 1
    } else if (gecen > 86400) {
        gecen = 86400
    }

    new uzunluk = formatex(g_partiGovde, charsmax(g_partiGovde),
        "{^"port^":%d,^"oturum^":^"%s^",^"parti^":%d,^"gecen^":%d,^"harita^":^"%s^",^"oyuncular^":[",
        SunucuPortu(), g_oturumKimligi, g_parti, gecen, harita)

    new oyuncular[MAX_PLAYERS], sayi
    get_players(oyuncular, sayi, "ch")

    new eklenen = 0

    for (new i = 0; i < sayi; i++) {
        new id = oyuncular[i]

        if (!SenkronVerisiVar(id)) {
            continue
        }

        /* Botun kimliği "BOT", LAN sunucusunda "STEAM_ID_LAN" gelir; API
           ikisini de reddedip partinin tamamını düşürür. */
        new kimlik[40]
        get_user_authid(id, kimlik, charsmax(kimlik))
        if (!KimlikGecerli(kimlik)) {
            continue
        }

        if (charsmax(g_partiGovde) - uzunluk < 512) {
            server_print("[sicil] parti govdesi doldu, %d oyuncu sonraki partiye kaldi", sayi - i)
            break
        }

        new hamAd[32], ad[72]
        get_user_name(id, hamAd, charsmax(hamAd))
        JsonKacis(hamAd, ad, charsmax(ad))

        new simdiki = get_user_time(id, 1)
        new saniye = simdiki - g_saniyeIsaret[id]
        if (saniye < 0) {
            saniye = 0
        }
        g_saniyeIsaret[id] = simdiki

        /*
         * Kırpma. API isabet > atis ya da hs > kill görürse partinin
         * TAMAMINI 422 ile reddediyor. Pompalıda tek atış 9 pellet, yani
         * 9 ayrı TakeDamage demek: kırpmasak XM1014 kullanan tek oyuncu
         * bütün turun verisini düşürürdü.
         */
        new atis = g_sAtis[id]
        new isabet = g_sIsabet[id] > atis ? atis : g_sIsabet[id]
        new hs = g_sHs[id] > g_sKill[id] ? g_sKill[id] : g_sHs[id]

        uzunluk += formatex(g_partiGovde[uzunluk], charsmax(g_partiGovde) - uzunluk,
            "%s{^"kimlik^":^"%s^",^"ad^":^"%s^",^"kill^":%d,^"death^":%d,^"asist^":%d,^"hs^":%d,^"hasar^":%d,^"atis^":%d,^"isabet^":%d,^"kurma^":%d,^"cozme^":%d,^"mvp^":%d,^"kazanilan^":%d,^"kaybedilen^":%d,^"saniye^":%d,^"silahlar^":{",
            eklenen ? "," : "", kimlik, ad,
            g_sKill[id], g_sDeath[id], g_sAsist[id], hs, g_sHasar[id],
            atis, isabet, g_sKurma[id], g_sCozme[id], g_sMvp[id],
            g_sKazanilan[id], g_sKaybedilen[id], saniye)

        new bool:ilkSilah = true
        for (new s = 0; s < sizeof(BILINEN_SILAHLAR); s++) {
            if (!g_sSilah[id][s]) {
                continue
            }

            uzunluk += formatex(g_partiGovde[uzunluk], charsmax(g_partiGovde) - uzunluk,
                "%s^"%s^":%d", ilkSilah ? "" : ",", BILINEN_SILAHLAR[s], g_sSilah[id][s])
            ilkSilah = false
        }

        uzunluk += formatex(g_partiGovde[uzunluk], charsmax(g_partiGovde) - uzunluk, "}}")

        SenkronSayaclariniSifirla(id)
        eklenen++
    }

    if (!eklenen) {
        g_partiGovde[0] = EOS
        return false
    }

    formatex(g_partiGovde[uzunluk], charsmax(g_partiGovde) - uzunluk, "]}")
    return true
}

PartiyiYolla()
{
    new EzHttpOptions:secenek = ezhttp_create_options()
    ezhttp_option_set_queue(secenek, g_kuyruk)
    ezhttp_option_set_timeout(secenek, 10000)
    Imzala(secenek)
    ezhttp_option_set_body(secenek, g_partiGovde)

    new adres[192]
    formatex(adres, charsmax(adres), "%s/api-senkron.php", g_api)

    g_ucusta = true
    ezhttp_post(adres, "SenkronYaniti", secenek)

    if (g_gunluk) {
        server_print("[sicil] parti %d gonderiliyor, %d bayt%s",
            g_parti, strlen(g_partiGovde), g_tekrarBekliyor ? " (tekrar)" : "")
        server_print("[sicil] govde: %s", g_partiGovde)
    }
}

public SenkronYaniti(EzHttpRequest:istek)
{
    g_ucusta = false

    if (ezhttp_get_error_code(istek) != EZH_OK) {
        new mesaj[128]
        ezhttp_get_error_message(istek, mesaj, charsmax(mesaj))

        g_hataSayaci++
        g_tekrarBekliyor = true
        server_print("[sicil] parti %d gonderilemedi (%s), sonraki tur sonunda tekrar denenecek",
            g_parti, mesaj)
        return
    }

    new kod = ezhttp_get_http_code(istek)

    if (kod != 200) {
        g_hataSayaci++

        /*
         * 409 nonce çakışması, 429 hız sınırı, 5xx geçici sunucu arızası:
         * üçü de aynı gövdeyle tekrar denenmeli. Kalan 4xx'ler gövdenin
         * kendisiyle ilgili; ısrar etmek bütün sonraki partileri de
         * kilitlerdi, o yüzden parti düşürülüyor.
         */
        if (kod == 409 || kod == 429 || kod >= 500) {
            g_tekrarBekliyor = true
            server_print("[sicil] parti %d HTTP %d dondu, tekrar denenecek", g_parti, kod)
            return
        }

        new bas[256]
        ezhttp_get_data(istek, bas, charsmax(bas))
        server_print("[sicil] parti %d HTTP %d ile reddedildi ve dusuruldu: %s", g_parti, kod, bas)

        PartiyiKapat()
        return
    }

    PartiyiKapat()

    new EzJSON:kok = ezhttp_parse_json_response(istek)
    if (kok == EzInvalid_JSON) {
        server_print("[sicil] parti islendi ama yanit cozumlenemedi")
        return
    }

    new EzJSON:dizi = ezjson_object_get_value(kok, "oyuncular")
    if (dizi != EzInvalid_JSON) {
        new adet = ezjson_array_get_count(dizi)
        for (new i = 0; i < adet; i++) {
            SenkronKaydiIsle(ezjson_array_get_value(dizi, i))
        }
    }

    ezjson_free(kok)
    g_sonYenileme = get_systime()
}

/** Parti kapandı: gövde bırakılır, sayaç ilerler, süre sıfırdan sayılır. */
PartiyiKapat()
{
    g_tekrarBekliyor = false
    g_partiGovde[0] = EOS
    g_parti++
    g_sonParti = get_systime()
}

/**
 * Senkron yanıtındaki rütbeyi önbelleğe yazar.
 *
 * /rank bu sayede ayrı bir istek atmıyor. Yanıt "sira", "hs" ve "kd"
 * döndürmüyor (onları api-oyuncu.php veriyor), o üçüne dokunulmuyor —
 * sıfırlamak HizliBakis çıktısını bozardı.
 */
SenkronKaydiIsle(EzJSON:kayit)
{
    if (kayit == EzInvalid_JSON) {
        return
    }

    new kimlik[40]
    ezjson_object_get_string(kayit, "kimlik", kimlik, charsmax(kimlik))

    new id = KimlikleBul(kimlik)
    if (id == 0) {
        return
    }

    new eskiKademe = g_kademeNo[id]

    ezjson_object_get_string(kayit, "kademe", g_kademeAdi[id], charsmax(g_kademeAdi[]))
    ezjson_object_get_string(kayit, "ustKademe", g_ustKademe[id], charsmax(g_ustKademe[]))
    g_kademeNo[id] = ezjson_object_get_number(kayit, "kademeNo")
    g_kp[id]       = ezjson_object_get_number(kayit, "kp")
    g_kalan[id]    = ezjson_object_get_number(kayit, "kalan")

    if (eskiKademe != KADEME_YOK && g_kademeNo[id] > eskiKademe) {
        RutbeAtladi(id)
    }
}

/* ------------------------------------------------------------------ *
 *  İstek imzalama ve biçim yardımcıları
 * ------------------------------------------------------------------ */

/** İki ucun da istediği üç başlık. Nonce her istekte yeniden üretiliyor. */
Imzala(EzHttpOptions:secenek)
{
    new deger[128]

    ezhttp_option_set_header(secenek, "X-Sicil-Anahtar", g_anahtar)

    formatex(deger, charsmax(deger), "%d", get_systime())
    ezhttp_option_set_header(secenek, "X-Sicil-Zaman", deger)

    NonceUret(deger, charsmax(deger))
    ezhttp_option_set_header(secenek, "X-Sicil-Tek", deger)

    ezhttp_option_set_header(secenek, "Content-Type", "application/json")
}

/**
 * 32 haneli onaltılık tek kullanımlık jeton.
 *
 * Alt sınır 0x10000000 seçildi ki her parça sekiz hane olsun; dolgusuz
 * "%x" kısa sayıda daha az hane basar ve toplam 32'nin altına düşerdi.
 */
NonceUret(hedef[], azami)
{
    formatex(hedef, azami, "%x%x%x%x",
        get_systime(),
        random_num(0x10000000, 0x7FFFFFFF),
        random_num(0x10000000, 0x7FFFFFFF),
        random_num(0x10000000, 0x7FFFFFFF))
}

/** Nick JSON'un içine giriyor; tek bir tırnak bütün gövdeyi bozardı. */
JsonKacis(const kaynak[], hedef[], azami)
{
    new j = 0

    for (new i = 0; kaynak[i] != EOS && j < azami - 2; i++) {
        new k = kaynak[i]

        if (k == '"' || k == '\') {
            hedef[j++] = '\'
            hedef[j++] = k
        } else if (k >= 0x20) {
            hedef[j++] = k
        }
    }

    hedef[j] = EOS
}

/**
 * API'nin kabul ettiği kimlik biçimleri: STEAM_x:y:z, VALVE_x:y:z ve
 * 17 haneli SteamID64. "BOT", "HLTV" ve "STEAM_ID_LAN" buradan eleniyor;
 * biri partiye girerse API partinin tamamını 422 ile reddeder.
 */
bool:KimlikGecerli(const kimlik[])
{
    new uzunluk = strlen(kimlik)

    if (uzunluk == 17) {
        for (new i = 0; i < 17; i++) {
            if (kimlik[i] < '0' || kimlik[i] > '9') {
                return false
            }
        }
        return true
    }

    if (uzunluk < 11 || uzunluk > 30) {
        return false
    }

    if (!equal(kimlik, "STEAM_", 6) && !equal(kimlik, "VALVE_", 6)) {
        return false
    }

    if (kimlik[6] < '0' || kimlik[6] > '9') return false
    if (kimlik[7] != ':') return false
    if (kimlik[8] != '0' && kimlik[8] != '1') return false
    if (kimlik[9] != ':') return false

    for (new i = 10; kimlik[i] != EOS; i++) {
        if (kimlik[i] < '0' || kimlik[i] > '9') {
            return false
        }
    }

    return true
}

/** API harita adında [A-Za-z0-9_-] dışına izin vermiyor. */
bool:HaritaAdiGecerli(const ad[])
{
    new uzunluk = strlen(ad)

    if (uzunluk < 1 || uzunluk > 32) {
        return false
    }

    for (new i = 0; i < uzunluk; i++) {
        new k = ad[i]

        if ((k >= 'a' && k <= 'z') || (k >= 'A' && k <= 'Z')
            || (k >= '0' && k <= '9') || k == '_' || k == '-') {
            continue
        }

        return false
    }

    return true
}

SunucuPortu()
{
    new port = get_cvar_num("port")
    return (port >= 1 && port <= 65535) ? port : 27015
}

/* ------------------------------------------------------------------ *
 *  Tur olayları — çoklu öldürme, ilk kan
 * ------------------------------------------------------------------ */

/**
 * Tur başında sayaçları sıfırlar.
 *
 * "Yeni tur" için logevent kullanılıyor, RG_RoundEnd değil: freeze time
 * bitişi oyuncunun ateş edebildiği ilk an, seriyi oradan saymak gerekiyor.
 * Round_Start log satırı tam o anda düşüyor.
 */
public TurBasladi()
{
    g_ilkKanAlindi = false

    for (new id = 1; id <= MAX_PLAYERS; id++) {
        g_turKill[id] = 0
    }

    /*
     * Hasar matrisi tur arası taşınmamalı. Ölümde kurbanın satırı zaten
     * temizleniyor ama tur sonunda hayatta kalanların satırları duruyor;
     * temizlemezsek geçen tur vurduğun oyuncu bu tur başkası tarafından
     * öldürülünce sana asist yazılırdı.
     */
    for (new i = 0; i <= MAX_PLAYERS; i++) {
        for (new j = 0; j <= MAX_PLAYERS; j++) {
            g_hasarMatrisi[i][j] = 0
        }
    }
}

/**
 * İlk kan. Valorant'ta turun ilk ölümü maçın gidişatını belirlediği için
 * ayrı gösteriliyor; burada da öyle.
 */
IlkKan(olduren, olen)
{
    new adA[32], adB[32]
    get_user_name(olduren, adA, charsmax(adA))
    get_user_name(olen, adB, charsmax(adB))

    Bilgi(0, "İLK KAN · ^x03%s^x01 → ^x03%s^x01", adA, adB)
}

/**
 * Tur içi seri duyurusu — Valorant'ın kendi dili.
 *
 * İki öldürmeden başlıyor, beşte ACE'ye çıkıyor. Beşin üstü ayrı bir isim
 * almıyor: Valorant'ta da almıyor, ACE takımın tamamını devirmek demek ve
 * altıncı öldürme için ondan büyük bir söz yok.
 *
 * Duyuru herkese gidiyor, öldürene HUD de basılıyor. Sunucudaki en görünür
 * an bu; sohbette tek satır bırakıp geçmek onu harcamak olurdu.
 */
CokluOldurme(id, seri)
{
    if (seri < 2) {
        return
    }

    new baslik[16]
    switch (seri) {
        case 2:  copy(baslik, charsmax(baslik), "ÇİFT")
        case 3:  copy(baslik, charsmax(baslik), "ÜÇLEME")
        case 4:  copy(baslik, charsmax(baslik), "DÖRTLEME")
        case 5:  copy(baslik, charsmax(baslik), "ACE")
        default: return
    }

    new ad[32]
    get_user_name(id, ad, charsmax(ad))

    Bilgi(0, "^x04%s^x01 · ^x03%s^x01 bu turda ^x04%d^x01 kişi devirdi", baslik, ad, seri)

    // ACE turun sahibi; ötekilerden daha uzun ve daha yukarıda dursun.
    if (seri == 5) {
        set_hudmessage(255, 70, 85, -1.0, 0.28, 0, 0.0, 4.0, 0.1, 0.3, 3)
        HudYaz(id, "ACE^n^nturu tek başına aldın")
        client_cmd(0, "spk ^"buttons/bell1^"")
        return
    }

    set_hudmessage(255, 70, 85, -1.0, 0.34, 0, 0.0, 2.2, 0.1, 0.3, 3)
    HudYaz(id, "%s ÖLDÜRME", baslik)
}

public OlumMesaji()
{
    new olduren = read_data(1)

    if (olduren < 1 || olduren > MAX_PLAYERS || !is_user_connected(olduren)) {
        return
    }

    if (read_data(3)) {
        g_oturumHs[olduren]++
        g_sHs[olduren]++
    }

    /* Silah adı DeathMsg'in dördüncü alanında string olarak geliyor;
       CSW_* sabitlerine ve cstrike modülüne gerek kalmıyor. */
    new silah[24]
    read_data(4, silah, charsmax(silah))

    new indeks = SilahIndeksi(silah)
    if (indeks >= 0) {
        g_sSilah[olduren][indeks]++
    }
}

/**
 * Ölen oyuncuya kimin öldürdüğünü ve o kişinin rütbesini gösterir.
 *
 * Rütbe önbellekten okunuyor, istek atılmıyor. Bilgi gelmemişse rütbe
 * satırı hiç basılmıyor — "Rütbe: bilinmiyor" yazmak gürültü.
 */
OldurenBilgisi(olen, olduren)
{
    if (is_user_bot(olen)) {
        return
    }

    new ad[32]
    get_user_name(olduren, ad, charsmax(ad))

    new can = get_user_health(olduren)

    if (g_kademeAdi[olduren][0] == EOS) {
        Bilgi(olen, "^x03%s^x01 seni öldürdü · kalan canı ^x04%d^x01", ad, can)
        return
    }

    Bilgi(olen, "^x03%s^x01 seni öldürdü ^x01(^x04%s^x01, %d KP) · kalan canı ^x04%d^x01",
        ad, g_kademeAdi[olduren], g_kp[olduren], can)
}

/* ------------------------------------------------------------------ *
 *  Sohbet
 * ------------------------------------------------------------------ */

public SohbetYakala(id)
{
    new ham[192]
    read_args(ham, charsmax(ham))
    remove_quotes(ham)
    trim(ham)

    if (ham[0] == '/' || ham[0] == '!') {
        return KomutDagit(id, ham)
    }

    if (!g_sohbetEtiketi || ham[0] == EOS || g_kademeAdi[id][0] == EOS) {
        return PLUGIN_CONTINUE
    }

    return EtiketliSohbet(id, ham)
}

/**
 * Sohbete rütbe etiketi ekler.
 *
 * Orijinal mesaj engelleniyor ve yerine renkli sürümü basılıyor. Bu yüzden
 * ölü/canlı ve takım ayrımını burada elle korumak gerekiyor: canlı oyuncu
 * ölünün mesajını görmemeli, takım sohbeti karşı tarafa gitmemeli. Bunu
 * yanlış yapmak "ölüler konuşuyor" hatası demek, ki en can sıkıcı olanı.
 *
 * Rütbe bilgisi henüz gelmemişse etiket eklenmiyor ve mesaj motorun normal
 * yolundan gidiyor — yarım etiket basmaktansa hiç basmamak doğru.
 */
EtiketliSohbet(id, const mesaj[])
{
    new bool:takim = bool:equal("say_team", ArgSifir())
    new bool:olu = !bool:is_user_alive(id)

    new ad[32]
    get_user_name(id, ad, charsmax(ad))

    /*
     * Sabit kısımlar çeviriden geçiyor, oyuncunun kendi mesajı geçmiyor.
     * O baytlar istemcinin gönderdiği baytlar; kendi kodlamamıza çevirmek
     * oyuncunun yazdığını bozmak olurdu.
     */
    new on[48]
    formatex(on, charsmax(on), "%s%s", olu ? " *ÖLÜ*" : "", takim ? " (Takım)" : "")
    SohbetKodla(on, charsmax(on))

    new oyuncular[MAX_PLAYERS], sayi
    get_players(oyuncular, sayi, "ch")

    for (new i = 0; i < sayi; i++) {
        new alici = oyuncular[i]

        if (takim && get_user_team(alici) != get_user_team(id)) {
            continue
        }

        // Canlı oyuncu ölünün mesajını görmez; ölü herkesinkini görür.
        if (olu && is_user_alive(alici)) {
            continue
        }

        client_print_color(alici, id,
            "^x04[%s]^x01%s ^x03%s^x01 : %s",
            g_kademeAdi[id],
            on,
            ad,
            mesaj)
    }

    return PLUGIN_HANDLED
}

/**
 * Konsoldan gelen komut. Sohbet yolundakiyle aynı dağıtıcıya bağlanıyor;
 * iki ayrı liste tutmak, birine komut ekleyip ötekini unutmak demekti.
 */
public KonsolKomut(id)
{
    new komut[24]
    read_argv(0, komut, charsmax(komut))

    new ham[32]
    formatex(ham, charsmax(ham), "/%s", komut)

    return KomutDagit(id, ham)
}

/** read_argv(0) say mı say_team mi olduğunu söylüyor. */
ArgSifir()
{
    static arg[16]
    read_argv(0, arg, charsmax(arg))
    return arg
}

/* ------------------------------------------------------------------ *
 *  Komutlar
 * ------------------------------------------------------------------ */

KomutDagit(id, const ham[])
{
    new komut[32], kalan[160]
    strtok(ham[1], komut, charsmax(komut), kalan, charsmax(kalan), ' ')
    strtolower(komut)
    trim(kalan)

    if (g_gunluk) {
        server_print("[sicil] komut: %d '%s' arg='%s'", id, komut, kalan)
    }

    if (equal(komut, "rank") || equal(komut, "sicil")) {
        KendiProfili(id)
    } else if (equal(komut, "top") || equal(komut, "top15") || equal(komut, "siralama")) {
        MotdIste(id, "siralama-motd.php")
    } else if (equal(komut, "profil")) {
        BaskaProfil(id, kalan)
    } else if (equal(komut, "karsilastir") || equal(komut, "kiyasla")) {
        Karsilastir(id, kalan)
    } else if (equal(komut, "silahlar") || equal(komut, "silah")) {
        MotdIste(id, "silahlar-motd.php")
    } else if (equal(komut, "haritalar") || equal(komut, "harita")) {
        MotdIste(id, "haritalar-motd.php")
    } else if (equal(komut, "rutbeler") || equal(komut, "merdiven")) {
        Merdiven(id)
    } else if (equal(komut, "yonetim") || equal(komut, "admin")) {
        YonetimMenusu(id)
    } else if (equal(komut, "kp")) {
        HizliBakis(id)
    } else if (equal(komut, "oturum")) {
        OturumOzeti(id)
    } else if (equal(komut, "yardim") || equal(komut, "komutlar") || equal(komut, "help")) {
        KomutListesi(id)
    } else {
        return PLUGIN_CONTINUE
    }

    return PLUGIN_HANDLED
}

/**
 * /rutbeler — merdivenin tamamı, oyuncunun kendi basamağı işaretli.
 *
 * Kimlik okunamıyorsa sayfa yine açılıyor, sadece işaret olmuyor: merdiven
 * herkes için aynı, oyuncuyu tanıyamadık diye onu bilgiden mahrum bırakmak
 * gereksiz.
 */
Merdiven(id)
{
    new kimlik[40]
    get_user_authid(id, kimlik, charsmax(kimlik))

    if (equal(kimlik, "BOT") || kimlik[0] == EOS) {
        MotdIste(id, "rutbeler-motd.php")
        return
    }

    new yol[160]
    formatex(yol, charsmax(yol), "rutbeler-motd.php?kimlik=%s", kimlik)
    MotdIste(id, yol)
}

KendiProfili(id)
{
    new kimlik[40]
    get_user_authid(id, kimlik, charsmax(kimlik))

    if (equal(kimlik, "BOT") || kimlik[0] == EOS) {
        Bilgi(id, "Kimliğin okunamadı, profil açılamıyor.")
        return
    }

    new yol[160]
    formatex(yol, charsmax(yol), "oyuncu-motd.php?kimlik=%s", kimlik)
    MotdIste(id, yol)
}

/**
 * Ada göre oyuncu bulur. Parça eşleşme yeter — kimse "VN @ efeliman"ı tam
 * yazmak istemiyor. Birden fazla eşleşirse hangileri olduğu söyleniyor;
 * rastgele birini açmak yanlış oyuncunun profilini göstermek demek.
 *
 * Dönen değer entity indeksi, bulunamazsa 0.
 */
AdlaBul(id, const arananHam[], const nicin[])
{
    new aranan[64]
    copy(aranan, charsmax(aranan), arananHam)
    trim(aranan)

    if (strlen(aranan) < 2) {
        Bilgi(id, "Kullanım: /%s <oyuncu adından bir parça>", nicin)
        return 0
    }

    strtolower(aranan)

    new oyuncular[MAX_PLAYERS], sayi
    get_players(oyuncular, sayi, "ch")

    new bulunan = 0, eslesme = 0, adListe[192]

    for (new i = 0; i < sayi; i++) {
        new ad[32]
        get_user_name(oyuncular[i], ad, charsmax(ad))

        new kucuk[32]
        copy(kucuk, charsmax(kucuk), ad)
        strtolower(kucuk)

        if (containi(kucuk, aranan) == -1) {
            continue
        }

        eslesme++
        bulunan = oyuncular[i]

        if (eslesme <= 4) {
            format(adListe, charsmax(adListe), "%s%s%s", adListe, adListe[0] == EOS ? "" : ", ", ad)
        }
    }

    if (eslesme == 0) {
        Bilgi(id, "'%s' ile eşleşen oyuncu sunucuda yok.", aranan)
        return 0
    }

    if (eslesme > 1) {
        Bilgi(id, "%d oyuncu eşleşti: %s · daha uzun yaz.", eslesme, adListe)
        return 0
    }

    return bulunan
}

BaskaProfil(id, const aranan[])
{
    new hedef = AdlaBul(id, aranan, "profil")
    if (hedef == 0) {
        return
    }

    new kimlik[40]
    get_user_authid(hedef, kimlik, charsmax(kimlik))

    if (equal(kimlik, "BOT")) {
        Bilgi(id, "Botların sicili tutulmuyor.")
        return
    }

    new yol[160]
    formatex(yol, charsmax(yol), "oyuncu-motd.php?kimlik=%s", kimlik)
    MotdIste(id, yol)
}

/** /karsilastir <isim> — kendisiyle hedefi yan yana açar. */
Karsilastir(id, const aranan[])
{
    new hedef = AdlaBul(id, aranan, "karsilastir")
    if (hedef == 0) {
        return
    }

    new benim[40], onun[40]
    get_user_authid(id, benim, charsmax(benim))
    get_user_authid(hedef, onun, charsmax(onun))

    if (equal(benim, "BOT") || equal(onun, "BOT")) {
        Bilgi(id, "Botlarin sicili tutulmuyor.")
        return
    }

    new yol[160]
    formatex(yol, charsmax(yol), "karsilastir-motd.php?sol=%s&sag=%s", benim, onun)
    MotdIste(id, yol)
}

/**
 * /kp — MOTD açmadan hızlı bakış.
 *
 * Ayrı komut olmasının sebebi: MOTD bütün ekranı kapatıyor ve tur ortasında
 * açan oyuncu ölüyor. Rütbesine bakmak isteyen herkesin ölmesi gerekmiyor.
 */
HizliBakis(id)
{
    if (g_kademeAdi[id][0] == EOS) {
        Bilgi(id, "Rütben henüz yüklenmedi, birkaç saniye sonra tekrar dene.")
        return
    }

    set_hudmessage(255, 70, 85, -1.0, 0.23, 0, 0.0, 5.0, 0.15, 0.3, 4)

    if (g_kalan[id] > 0 && g_ustKademe[id][0] != EOS) {
        HudYaz(id, "%s^n%d KP  ·  %d. sıra^n^nK/D %.2f  ·  HS %%%d^n%s için %d KP",
            g_kademeAdi[id], g_kp[id], g_sira[id], g_kd[id], g_hsYuzde[id], g_ustKademe[id], g_kalan[id])
    } else {
        HudYaz(id, "%s^n%d KP  ·  %d. sıra^n^nK/D %.2f  ·  HS %%%d^nMerdivenin tepesi",
            g_kademeAdi[id], g_kp[id], g_sira[id], g_kd[id], g_hsYuzde[id])
    }
}

/** /oturum — bu haritada bu bağlantıda ne yaptığın. Toplamlar web'de. */
OturumOzeti(id)
{
    new Float:oran = g_oturumDeath[id] == 0
        ? float(g_oturumKill[id])
        : float(g_oturumKill[id]) / float(g_oturumDeath[id])

    new hs = g_oturumKill[id] == 0 ? 0 : (g_oturumHs[id] * 100) / g_oturumKill[id]

    Bilgi(id, "Bu oturum: ^x04%d^x01 öldürme, ^x04%d^x01 ölüm, K/D ^x04%.2f^x01, HS ^x04%%%d^x01",
        g_oturumKill[id], g_oturumDeath[id], oran, hs)

    if (g_enIyiSeri[id] >= 2) {
        Bilgi(id, "En iyi turun: ^x04%d^x01 öldürme%s",
            g_enIyiSeri[id], g_enIyiSeri[id] >= 5 ? " ^x04(ACE)^x01" : "")
    }

    Bilgi(id, "Toplam istatistiğin için ^x04/rank^x01 yaz.")
}

KomutListesi(id)
{
    Bilgi(id, "^x04Sicil komutları")
    Bilgi(id, "^x04/rank^x01 profilin  ^x04/top^x01 sıralama  ^x04/kp^x01 hızlı bakış")
    Bilgi(id, "^x04/silahlar^x01 silah sıralaması  ^x04/haritalar^x01 harita sıralaması")
    Bilgi(id, "^x04/rutbeler^x01 merdivenin tamamı  ^x04/oturum^x01 bu oturum")
    Bilgi(id, "^x04/profil <isim>^x01 başkasının sicili  ^x04/karsilastir <isim>^x01 yan yana")
    Bilgi(id, "Hepsi ^x04!^x01 ile de çalışır, takım sohbetinden de yazabilirsin.")
    Bilgi(id, "Tuşa bağlamak için: ^x04bind F5 rank^x01 (rank, top, kp, oturum, silahlar)")

    if (get_user_flags(id) & YETKI_MENU) {
        Bilgi(id, "^x04/yonetim^x01 yönetim menüsü")
    }
}

/* ------------------------------------------------------------------ *
 *  MOTD
 * ------------------------------------------------------------------ */

MotdIste(id, const yol[])
{
    if (!is_user_connected(id)) {
        return
    }

    copy(g_bekleyen[id], charsmax(g_bekleyen[]), yol)

    client_cmd(id, "cancelselect")
    remove_task(GOREV_MOTD + id)
    set_task(0.9, "MotdGecikmeli", GOREV_MOTD + id)
}

public MotdGecikmeli(gorev)
{
    new id = gorev - GOREV_MOTD
    if (!is_user_connected(id) || g_bekleyen[id][0] == EOS) {
        return
    }

    new ayrac = containi(g_bekleyen[id], "?") == -1 ? '?' : '&'

    new adres[224]
    formatex(adres, charsmax(adres), "%s/%s%c_=%d", g_api, g_bekleyen[id], ayrac, get_systime())

    show_motd(id, adres, "Sicil")
    g_bekleyen[id][0] = EOS

    if (g_gunluk) {
        server_print("[sicil] motd %d -> %s", id, adres)
    }
}

/* ------------------------------------------------------------------ *
 *  Konsol
 * ------------------------------------------------------------------ */

public KonsolDurum()
{
    new oyuncular[MAX_PLAYERS], sayi, rutbeli = 0
    get_players(oyuncular, sayi, "ch")
    for (new i = 0; i < sayi; i++) {
        if (g_kademeAdi[oyuncular[i]][0] != EOS) {
            rutbeli++
        }
    }

    server_print("--- Sicil %s ---", SURUM)
    server_print("  api             : %s", g_api)
    server_print("  paylasilan anahtar: %s", g_anahtar[0] == EOS ? "AYARLANMAMIS" : "ayarli")
    server_print("  rutbesi yuklu   : %d / %d oyuncu", rutbeli, sayi)
    server_print("  son yenileme    : %s", g_sonYenileme == 0 ? "hic" : "var")
    server_print("  hata sayaci     : %d", g_hataSayaci)
    server_print("  sohbet etiketi  : %s", g_sohbetEtiketi ? "acik" : "kapali")

    /* "Sunucuda Turkce bozuk gorunuyor" destegi ilk buraya bakacak. */
    new kodAdi[24]
    switch (g_sohbetKodlama) {
        case KOD_CP1254: copy(kodAdi, charsmax(kodAdi), "CP1254 (eski istemci)")
        case KOD_ASCII:  copy(kodAdi, charsmax(kodAdi), "ASCII (turkcesiz)")
        default:         copy(kodAdi, charsmax(kodAdi), "UTF-8")
    }
    server_print("  sohbet kodlamasi: %s", kodAdi)

    server_print("  senkron katmani : henuz baglanmadi")
    return PLUGIN_HANDLED
}

/**
 * sicil_yenile — bağlı herkesin rütbesini web'den tekrar çeker.
 *
 * Panelden bir düzeltme yapıldığında sunucuyu yeniden başlatmadan
 * yansıtmak için. Rütbe atlama duyurusu da bunun üstünden çalışıyor.
 */
public KonsolYenile()
{
    new sayi = KuyrugaHerkesiKoy()
    server_print("[sicil] %d oyuncunun rutbesi yeniden cekiliyor", sayi)
    return PLUGIN_HANDLED
}

public KonsolGoster()
{
    new aranan[32], komut[64]
    read_argv(1, aranan, charsmax(aranan))
    read_argv(2, komut, charsmax(komut))

    if (aranan[0] == EOS || komut[0] == EOS) {
        server_print("[sicil] kullanim: sicil_goster <ad parcasi> </komut> — orn: sicil_goster Berkay /top")
        return PLUGIN_HANDLED
    }

    /* AdlaBul kullanılmıyor: o, bulamayınca hatayı OYUNCUYA yazıyor;
       burada muhatap sunucu konsolu. */
    new oyuncular[MAX_PLAYERS], sayi, id = 0
    get_players(oyuncular, sayi, "ch")

    for (new i = 0; i < sayi; i++) {
        new ad[32]
        get_user_name(oyuncular[i], ad, charsmax(ad))
        if (containi(ad, aranan) != -1) {
            id = oyuncular[i]
            break
        }
    }

    if (id == 0) {
        server_print("[sicil] '%s' ile eslesen oyuncu yok", aranan)
        return PLUGIN_HANDLED
    }

    if (komut[0] != '/' && komut[0] != '!') {
        format(komut, charsmax(komut), "/%s", komut)
    }

    KomutDagit(id, komut)
    return PLUGIN_HANDLED
}

public KonsolIstemci()
{
    new aranan[32], komut[128]
    read_argv(1, aranan, charsmax(aranan))
    read_argv(2, komut, charsmax(komut))

    if (aranan[0] == EOS || komut[0] == EOS) {
        server_print("[sicil] kullanim: sicil_istemci <ad parcasi> <komut>")
        return PLUGIN_HANDLED
    }

    new oyuncular[MAX_PLAYERS], sayi, id = 0
    get_players(oyuncular, sayi, "ch")

    for (new i = 0; i < sayi; i++) {
        new ad[32]
        get_user_name(oyuncular[i], ad, charsmax(ad))
        if (containi(ad, aranan) != -1) {
            id = oyuncular[i]
            break
        }
    }

    if (id == 0) {
        server_print("[sicil] '%s' ile eslesen oyuncu yok", aranan)
        return PLUGIN_HANDLED
    }

    server_print("[sicil] istemciye komut: %d <- '%s'", id, komut)
    client_cmd(id, "%s", komut)
    return PLUGIN_HANDLED
}

/** Bağlı herkesi toplu sorgu kuyruğuna alır; kuyruğa giren sayıyı döner. */
KuyrugaHerkesiKoy()
{
    new oyuncular[MAX_PLAYERS], sayi
    get_players(oyuncular, sayi, "ch")

    g_toplamaSayisi = 0
    for (new i = 0; i < sayi && g_toplamaSayisi < MAX_PLAYERS; i++) {
        g_toplamaKuyrugu[g_toplamaSayisi++] = oyuncular[i]
    }

    KuyruguGonder()
    return sayi
}

/* ------------------------------------------------------------------ *
 *  Türkçe çıkış kodlaması
 * ------------------------------------------------------------------ */

/*
 * Kaynak dosya UTF-8 ve derleyici baytları olduğu gibi bırakıyor — ölçüldü:
 * "şğüöçı ŞĞÜÖÇİ" derlemeden sonra 25 bayt, yani her Türkçe harf iki bayt.
 * GoldSrc sohbeti ise tek baytlık bir yazı tipiyle çiziyor. 2013 sonrası
 * istemciler UTF-8 çözüyor, daha eskiler CP1254 bekliyor, bazı derlemeler de
 * yalnız ASCII basıyor.
 *
 * Bu yüzden metinler kaynakta düzgün Türkçe duruyor ve çıkışta çevriliyor.
 * Alternatif, kaynağa "siralama" yazmaktı; ürünün tamamı Türk sunucularına
 * satılırken sohbetin ASCII'ye kırpılmış olması ilk bakışta görülüyor.
 *
 * Tablo yalnız on üç işaret içeriyor — Türkçenin altı harfi, büyük/küçük ve
 * ayraç noktası. Genel bir UTF-8 çeviricisi yazmak, kullanılmayacak yüzlerce
 * kod noktası için tablo taşımak demekti.
 */
/** { UTF-8 birinci bayt, ikinci bayt, CP1254 karşılığı, ASCII karşılığı } */
static const TR_ESLEME[][4] = {
    { 0xC3, 0xA7, 0xE7, 'c' },   // ç
    { 0xC3, 0x87, 0xC7, 'C' },   // Ç
    { 0xC4, 0x9F, 0xF0, 'g' },   // ğ
    { 0xC4, 0x9E, 0xD0, 'G' },   // Ğ
    { 0xC4, 0xB1, 0xFD, 'i' },   // ı
    { 0xC4, 0xB0, 0xDD, 'I' },   // İ
    { 0xC3, 0xB6, 0xF6, 'o' },   // ö
    { 0xC3, 0x96, 0xD6, 'O' },   // Ö
    { 0xC5, 0x9F, 0xFE, 's' },   // ş
    { 0xC5, 0x9E, 0xDE, 'S' },   // Ş
    { 0xC3, 0xBC, 0xFC, 'u' },   // ü
    { 0xC3, 0x9C, 0xDC, 'U' },   // Ü
    { 0xC2, 0xB7, 0xB7, '-' }    // · ayraç
}

/**
 * Metni yerinde çevirir. UTF-8 kipinde hiç dokunmuyor.
 *
 * Çıkış her zaman girişten kısa ya da eşit (iki bayt bire iniyor), o yüzden
 * aynı tampon üstünde çalışmak güvenli.
 */
SohbetKodla(metin[], azami)
{
    if (g_sohbetKodlama == KOD_UTF8) {
        return
    }

    new yaz = 0

    for (new oku = 0; metin[oku] != EOS && yaz < azami; oku++) {
        new bayt = metin[oku] & 0xFF

        if (bayt < 0x80) {
            metin[yaz++] = metin[oku]
            continue
        }

        new ikinci = metin[oku + 1] & 0xFF
        new karsilik = 0

        for (new t = 0; t < sizeof(TR_ESLEME); t++) {
            if (TR_ESLEME[t][0] == bayt && TR_ESLEME[t][1] == ikinci) {
                karsilik = (g_sohbetKodlama == KOD_CP1254) ? TR_ESLEME[t][2] : TR_ESLEME[t][3]
                break
            }
        }

        /* Tabloda olmayan çok baytlı dizi: soru işareti bırakıp ikisini de
           yutuyoruz. Yarım UTF-8 dizisi bırakmak istemcide çöp çiziyor. */
        metin[yaz++] = karsilik == 0 ? '?' : karsilik

        /* ikinci != 0 şartı önemli: metin yarım bir UTF-8 dizisiyle bitiyorsa
           (vformat tamponu tam ortasından kesmişse olur) koşulsuz oku++
           sonlandırıcının ötesine geçip dizinin dışını okuyor. */
        if (bayt >= 0xC0 && ikinci != 0) {
            oku++
        }
    }

    metin[yaz] = EOS
}

/* ------------------------------------------------------------------ *
 *  Yönetim menüsü
 * ------------------------------------------------------------------ */

/**
 * /yonetim — oyun içi admin menüsü.
 *
 * İki ayrı yetki var, bilerek. Menüyü açmak ADMIN_LEVEL_A istiyor; sunucunun
 * tamamını etkileyen maddeler ayrıca ADMIN_RCON istiyor. "Girişi olan her
 * admin her şeyi yapabilir" kolay olanı; yardımcı adminin herkesin ekranına
 * MOTD açabilmesi ya da kodlamayı değiştirmesi istenmez.
 *
 * Menü içeriği referans üründen kopyalanmadı. Oradaki "cache sıfırla"
 * maddeleri o mimariye ait; bizde önbellek zaten oyuncu bağlanınca tek
 * istekle doluyor, sıfırlanacak bir şey yok. Buradaki 5. madde onun yerine
 * gerçek sorunu çözüyor: sunucu sahibi hangi kodlamanın kendi oyuncularının
 * istemcisinde okunduğunu ancak deneyerek öğrenebiliyor, menü de her
 * değiştirişte örnek bir Türkçe satır bastırıyor.
 */
YonetimMenusu(id)
{
    if (!(get_user_flags(id) & YETKI_MENU)) {
        Bilgi(id, "Bu komut yöneticilere açık.")
        return
    }

    new bool:tam = (get_user_flags(id) & YETKI_TAM) != 0

    new baslik[64]
    formatex(baslik, charsmax(baslik), "\ySicil \w%s^n\dyönetim menüsü", SURUM)
    SohbetKodla(baslik, charsmax(baslik))

    new menu = menu_create(baslik, "YonetimSecim")

    MenuSatiri(menu, "Rütbeleri yenile \d(herkes)", tam)
    MenuSatiri(menu, "Oyuncu yönetimi", true)
    MenuSatiri(menu, "Sıralamayı herkese aç", tam)
    MenuSatiri(menu, "Sunucu durumu", true)

    new kodSatir[64]
    formatex(kodSatir, charsmax(kodSatir), "Sohbet kodlaması \d[\y%s\d]", KodlamaAdi())
    MenuSatiri(menu, kodSatir, tam)

    new etiketSatir[64]
    formatex(etiketSatir, charsmax(etiketSatir), "Sohbet rütbe etiketi \d[\y%s\d]",
        g_sohbetEtiketi ? "açık" : "kapalı")
    MenuSatiri(menu, etiketSatir, tam)

    menu_setprop(menu, MPROP_EXITNAME, CikisAdi())
    menu_display(id, menu)
}

/** Menü çerçevesindeki sabit yazılar da aynı çeviriden geçmeli. */
CikisAdi()
{
    static ad[12]
    copy(ad, charsmax(ad), "Çıkış")
    SohbetKodla(ad, charsmax(ad))
    return ad
}

/** Yetkisi olmayan madde griye düşüyor; gizlemek "menü neden eksik" sorusu. */
MenuSatiri(menu, const metin[], bool:acik)
{
    new satir[80]
    copy(satir, charsmax(satir), metin)
    SohbetKodla(satir, charsmax(satir))

    menu_additem(menu, satir, "", acik ? 0 : (1 << 26))
}

KodlamaAdi()
{
    static ad[8]
    switch (g_sohbetKodlama) {
        case KOD_CP1254: copy(ad, charsmax(ad), "CP1254")
        case KOD_ASCII:  copy(ad, charsmax(ad), "ASCII")
        default:         copy(ad, charsmax(ad), "UTF-8")
    }
    return ad
}

public YonetimSecim(id, menu, madde)
{
    if (madde == MENU_EXIT) {
        menu_destroy(menu)
        return PLUGIN_HANDLED
    }

    menu_destroy(menu)

    if (!is_user_connected(id)) {
        return PLUGIN_HANDLED
    }

    switch (madde) {
        case 0: {
            KuyrugaHerkesiKoy()
            Bilgi(id, "Bağlı herkesin rütbesi yeniden çekiliyor.")
        }
        case 1: OyuncuMenusu(id)
        case 2: {
            new oyuncular[MAX_PLAYERS], sayi
            get_players(oyuncular, sayi, "ch")
            for (new i = 0; i < sayi; i++) {
                MotdIste(oyuncular[i], "siralama-motd.php")
            }
            Bilgi(id, "Sıralama %d oyuncuya açıldı.", sayi)
        }
        case 3: DurumuYaz(id)
        case 4: KodlamayiCevir(id)
        case 5: {
            g_sohbetEtiketi = !g_sohbetEtiketi
            set_cvar_num("sicil_sohbet_etiketi", g_sohbetEtiketi)
            Bilgi(id, "Sohbet rütbe etiketi artık %s.", g_sohbetEtiketi ? "açık" : "kapalı")
            YonetimMenusu(id)
        }
    }

    return PLUGIN_HANDLED
}

/**
 * Kodlamayı sırayla değiştirir ve hemen örnek bastırır.
 *
 * Örnek satır bilerek bütün Türkçe harfleri içeriyor: admin ekranda "şğüöçı"
 * yerine "ÅŸ" görürse yanlış kipte olduğunu tek bakışta anlıyor.
 */
KodlamayiCevir(id)
{
    g_sohbetKodlama = (g_sohbetKodlama + 1) % 3
    set_cvar_num("sicil_sohbet_kodlama", g_sohbetKodlama)

    Bilgi(id, "Kodlama: ^x04%s^x01", KodlamaAdi())
    Bilgi(id, "Örnek: şğüöçı ŞĞÜÖÇİ · sıralama · hızlı bakış")
    Bilgi(id, "Harfler bozuksa menüden bir sonrakini dene.")

    YonetimMenusu(id)
}

/** Oyuncu listesi; seçilince o oyuncunun sicili admin'e açılıyor. */
OyuncuMenusu(id)
{
    new oyuncuBasligi[48]
    copy(oyuncuBasligi, charsmax(oyuncuBasligi), "\ySicil \wOyuncu yönetimi")
    SohbetKodla(oyuncuBasligi, charsmax(oyuncuBasligi))

    new menu = menu_create(oyuncuBasligi, "OyuncuSecim")

    new oyuncular[MAX_PLAYERS], sayi
    get_players(oyuncular, sayi, "ch")

    for (new i = 0; i < sayi; i++) {
        new hedef = oyuncular[i]

        new ad[32]
        get_user_name(hedef, ad, charsmax(ad))

        new satir[80]
        if (g_kademeAdi[hedef][0] == EOS) {
            formatex(satir, charsmax(satir), "%s \d(rütbe yüklenmedi)", ad)
        } else {
            formatex(satir, charsmax(satir), "%s \d(\y%s\d, %d KP)", ad, g_kademeAdi[hedef], g_kp[hedef])
        }
        SohbetKodla(satir, charsmax(satir))

        /* userid saklanıyor, entity indeksi değil: menü açıkken oyuncu
           çıkıp yerine başkası girerse indeks yanlış kişiyi gösterirdi. */
        new veri[12]
        num_to_str(get_user_userid(hedef), veri, charsmax(veri))

        menu_additem(menu, satir, veri)
    }

    if (sayi == 0) {
        new bosSatir[32]
        copy(bosSatir, charsmax(bosSatir), "\dSunucuda oyuncu yok")
        SohbetKodla(bosSatir, charsmax(bosSatir))
        menu_additem(menu, bosSatir, "0", 1 << 26)
    }

    menu_setprop(menu, MPROP_EXITNAME, "Geri")
    menu_display(id, menu)
}

public OyuncuSecim(id, menu, madde)
{
    if (madde == MENU_EXIT) {
        menu_destroy(menu)
        if (is_user_connected(id)) {
            YonetimMenusu(id)
        }
        return PLUGIN_HANDLED
    }

    new veri[12], ad[2], erisim, geri
    menu_item_getinfo(menu, madde, erisim, veri, charsmax(veri), ad, charsmax(ad), geri)
    menu_destroy(menu)

    new hedef = find_player("k", str_to_num(veri))
    if (hedef == 0 || !is_user_connected(hedef)) {
        Bilgi(id, "O oyuncu sunucudan ayrılmış.")
        return PLUGIN_HANDLED
    }

    new kimlik[40]
    get_user_authid(hedef, kimlik, charsmax(kimlik))

    if (equal(kimlik, "BOT") || kimlik[0] == EOS) {
        Bilgi(id, "Botun sicili tutulmuyor.")
        return PLUGIN_HANDLED
    }

    new yol[160]
    formatex(yol, charsmax(yol), "oyuncu-motd.php?kimlik=%s", kimlik)
    MotdIste(id, yol)

    return PLUGIN_HANDLED
}

/** sicil_durum'un oyun içi karşılığı — admin konsola bakamıyor. */
DurumuYaz(id)
{
    new oyuncular[MAX_PLAYERS], sayi, rutbeli = 0
    get_players(oyuncular, sayi, "ch")

    for (new i = 0; i < sayi; i++) {
        if (g_kademeAdi[oyuncular[i]][0] != EOS) {
            rutbeli++
        }
    }

    Bilgi(id, "Sicil ^x04%s^x01 · api ^x04%s^x01", SURUM, g_api)
    Bilgi(id, "Rütbesi yüklü ^x04%d/%d^x01 · hata ^x04%d^x01 · kodlama ^x04%s^x01",
        rutbeli, sayi, g_hataSayaci, KodlamaAdi())
    if (g_anahtar[0] == EOS) {
        Bilgi(id, "Paylaşılan anahtar ^x04ayarlanmamış^x01 · veri gönderilmiyor")
        return
    }

    Bilgi(id, "Oturum ^x04%s^x01 · parti ^x04%d^x01%s",
        g_oturumKimligi, g_parti,
        g_tekrarBekliyor ? " · ^x04bekleyen parti var^x01" : "")
}

/* ------------------------------------------------------------------ */

Bilgi(id, const bicim[], any:...)
{
    new mesaj[192]
    vformat(mesaj, charsmax(mesaj), bicim, 3)
    SohbetKodla(mesaj, charsmax(mesaj))

    client_print_color(id, print_team_default, "^x04[Sicil]^x01 %s", mesaj)
}

/** Bilgi'nin HUD karşılığı; aynı çeviriden geçmesi için tek kapı. */
HudYaz(id, const bicim[], any:...)
{
    new mesaj[224]
    vformat(mesaj, charsmax(mesaj), bicim, 3)
    SohbetKodla(mesaj, charsmax(mesaj))

    show_hudmessage(id, "%s", mesaj)
}
