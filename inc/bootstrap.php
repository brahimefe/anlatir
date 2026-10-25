<?php
/* Ortak başlangıç: oturum, ayarlar, depolama, kur, kimlik ve yardımcı işlevler */

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('anlsem');
    session_start();
}

define('ROOT', dirname(__DIR__));
define('STORAGE', ROOT . '/storage');
define('UPLOADS', ROOT . '/uploads');
date_default_timezone_set('Europe/Istanbul');

$CONFIG = require ROOT . '/config.php';
$WEEKS  = require __DIR__ . '/weeks.php';
require __DIR__ . '/gorsel.php';
require __DIR__ . '/icerik.php';
$WEEKS  = icerik_haftalar($WEEKS); // panelden yapılan değişiklikler
if (($r = genel_ayar('rumuz')) !== '') {
    $CONFIG['egitmen_rumuz'] = $r; // örnek haberlerin ve eğitmen haberlerinin imzası
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function cfg($key, $default = null)
{
    global $CONFIG;
    return $CONFIG[$key] ?? $default;
}
/* Haber konuları (Haberler sayfasındaki süzgeç bu listeden gelir) */
/* Seçilebilir konular (panelde gizlenenler hariç) */
function konular($gizliler = false)
{
    $ayar = konu_ayarlari();
    $out = [];
    foreach (array_merge(varsayilan_konular(), (array)($ayar['ek'] ?? [])) as $k => $ad) {
        $ad = trim((string)($ayar['ad'][$k] ?? '')) ?: $ad;
        if ($gizliler || !in_array($k, (array)($ayar['gizli'] ?? []), true)) {
            $out[$k] = $ad;
        }
    }
    // "Diğer" hep sonda
    if (isset($out['diger'])) {
        $d = $out['diger'];
        unset($out['diger']);
        $out['diger'] = $d;
    }
    return $out;
}
function konu_adi($k)
{
    return konular(true)[$k] ?? '';
}

/* Yapay zekâ araçları açık mı? (ayar açık ve anahtar girilmiş olmalı) */
function ai_acik()
{
    $key = (string)cfg('openai_api_key', '');
    return (bool)cfg('yapay_zeka', false) && $key !== '' && strpos($key, 'BURAYA') !== 0;
}
function weeks()
{
    global $WEEKS;
    return $WEEKS;
}
function week($n)
{
    global $WEEKS;
    return $WEEKS[(int)$n] ?? null;
}
/* Haftanın ödev türü: 'odev' (yazılı, yalnızca eğitmen görür) ya da 'haber' (yayına aday) */
function hafta_turu($n)
{
    return (week($n)['odev_turu'] ?? 'haber') === 'metin' ? 'odev' : 'haber';
}

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}
function post($key, $max = 20000)
{
    return cut_text(trim((string)($_POST[$key] ?? '')), $max);
}
function is_post()
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}
function base_url()
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

/* --- CSRF --- */
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}
function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_ok()
{
    $t = (string)($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ''));
    return hash_equals($_SESSION['csrf'] ?? '', $t);
}
function csrf_check()
{
    if (!csrf_ok()) {
        flash('Sayfanın süresi dolmuş. Tekrar deneyin.', 'err');
        redirect(here());
    }
}

function flash($msg = null, $kind = 'ok')
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'kind' => $kind];
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}
function here()
{
    $page = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    return $page . ($qs !== '' ? '?' . $qs : '');
}
function safe_return($target)
{
    $target = (string)$target;
    return preg_match('#^[a-z0-9_\-]+\.php(\?[A-Za-z0-9_\-=&%.]*)?$#i', $target) ? $target : 'index.php';
}

/* --- JSON deposu (dosya kilitli) --- */
function ensure_dir($dir)
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}
function json_read($path, $default = [])
{
    if (!is_file($path)) {
        return $default;
    }
    $fh = @fopen($path, 'r');
    if (!$fh) {
        return $default;
    }
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : $default;
}
function json_write($path, $data)
{
    ensure_dir(dirname($path));
    return file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
}
/* Oku-değiştir-yaz; $fn veriyi referansla alır, döndürdüğü değer geri verilir */
function json_update($path, callable $fn, $default = [])
{
    ensure_dir(dirname($path));
    $fh = fopen($path, 'c+');
    if (!$fh) {
        throw new RuntimeException('Depolama klasörüne yazılamıyor.');
    }
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    $data = $raw !== '' ? json_decode((string)$raw, true) : null;
    if (!is_array($data)) {
        $data = $default;
    }
    $result = $fn($data);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
}
function new_id()
{
    return bin2hex(random_bytes(8));
}
function safe_id($id)
{
    return (is_string($id) && preg_match('/^[a-f0-9]{16}$/', $id)) ? $id : null;
}

/* --- Kurlar --- */
function kurlar_path()
{
    return STORAGE . '/kurlar.json';
}
function kurlar()
{
    return json_read(kurlar_path(), ['aktif' => null, 'liste' => []]);
}
function kur_aktif()
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $k = kurlar();
    $cache = null;
    foreach ($k['liste'] as $kur) {
        if ($kur['id'] === $k['aktif']) {
            $cache = $kur;
        }
    }
    return $cache;
}
function kur_bul($id)
{
    foreach (kurlar()['liste'] as $kur) {
        if ($kur['id'] === $id) {
            return $kur;
        }
    }
    return null;
}
function kur_guncelle($id, array $alanlar)
{
    json_update(kurlar_path(), function (&$d) use ($id, $alanlar) {
        foreach ($d['liste'] as &$kur) {
            if ($kur['id'] === $id) {
                $kur = array_merge($kur, $alanlar);
            }
        }
    }, ['aktif' => null, 'liste' => []]);
}
function kur_dir($kid)
{
    return STORAGE . '/kur/' . preg_replace('/[^a-f0-9]/', '', (string)$kid);
}
function kur_hafta()
{
    $kur = kur_aktif();
    return $kur ? max(1, (int)($kur['hafta'] ?? 1)) : 0;
}
function kayit_acik()
{
    $kur = kur_aktif();
    return $kur && !empty($kur['kayit_acik']);
}
function kur_kodu_uret()
{
    $chars = 'ABCDEFGHJKLMNPRSTUVYZ23456789';
    $s = '';
    for ($i = 0; $i < 5; $i++) {
        $s .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $s;
}
function kod_esit($a, $b)
{
    $n = function ($s) {
        return strtoupper(preg_replace('/\s+/', '', (string)$s));
    };
    return $n($a) !== '' && hash_equals($n($a), $n($b));
}

/* --- Kullanıcılar (kur başına) --- */
function kullanicilar_path($kid)
{
    return kur_dir($kid) . '/kullanicilar.json';
}
function kullanicilar($kid)
{
    return json_read(kullanicilar_path($kid), []);
}
function kullanici_bul($kid, $uid)
{
    foreach (kullanicilar($kid) as $u) {
        if ($u['id'] === $uid) {
            return $u;
        }
    }
    return null;
}
function kullanici_guncelle($kid, $uid, array $alanlar)
{
    json_update(kullanicilar_path($kid), function (&$list) use ($uid, $alanlar) {
        foreach ($list as &$u) {
            if ($u['id'] === $uid) {
                $u = array_merge($u, $alanlar);
            }
        }
    });
}
/* Onaylanan yazar tanıtımını yazarın yayımlanmış haberlerine işler (kur kapanınca hesap silinse de haberde kalır) */
function yazar_tanitim_yay($kid, $uid, $tanitim)
{
    foreach (haberler(function ($h) use ($kid, $uid) {
        return $h['kur'] === $kid && ($h['yazar_id'] ?? '') === $uid && $h['durum'] === 'yayinda';
    }) as $h) {
        $h['yazar_tanitim'] = $tanitim;
        haber_kaydet($h);
    }
}
function gorunen_ad($u)
{
    return ($u['imza'] ?? 'rumuz') === 'ad' ? $u['ad'] : $u['rumuz'];
}

/* --- Kimlik --- */
function is_admin()
{
    return !empty($_SESSION['egitmen']);
}
/* Giriş yapmış ve onaylanmış öğrenci (aktif kurda) */
function ogrenci()
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    $s = $_SESSION['ogrenci'] ?? null;
    $kur = kur_aktif();
    if ($s && $kur && $s['kur'] === $kur['id']) {
        $u = kullanici_bul($kur['id'], $s['id']);
        if ($u && $u['durum'] === 'onayli') {
            $cache = $u;
        }
    }
    return $cache;
}
function require_admin()
{
    if (!is_admin()) {
        redirect('giris.php?rol=egitmen&donus=' . urlencode(here()));
    }
}
function require_ogrenci()
{
    if (!ogrenci() && !is_admin()) {
        redirect('giris.php?donus=' . urlencode(here()));
    }
}
function current_user_id()
{
    if (is_admin()) {
        return 'egitmen';
    }
    $u = ogrenci();
    return $u ? $u['id'] : null;
}
/* Canlı derse bağlı katılımcı: QR ile rumuzla gelen ya da giriş yapmış öğrenci */
function katilim()
{
    $kur = kur_aktif();
    if (!$kur) {
        return null;
    }
    $k = $_SESSION['katilim'] ?? null;
    if ($k && $k['kur'] === $kur['id']) {
        return $k;
    }
    $u = ogrenci();
    if ($u) {
        $_SESSION['katilim'] = ['kur' => $kur['id'], 'rumuz' => $u['rumuz'], 'pid' => 'u' . $u['id']];
        return $_SESSION['katilim'];
    }
    return null;
}

/* --- Haberler --- */
function haber_path($id)
{
    return STORAGE . '/haberler/' . $id . '.json';
}
function haber_yukle($id)
{
    if (is_string($id) && strpos($id, 'ornek-') === 0) {
        foreach (ornekler() as $o) {
            if ($o['id'] === $id) {
                return $o;
            }
        }
        return null;
    }
    $id = safe_id($id);
    if (!$id) {
        return null;
    }
    $h = json_read(haber_path($id), null);
    return is_array($h) ? $h : null;
}
function haber_kaydet($h)
{
    return json_write(haber_path($h['id']), $h);
}
function haberler($filtre = null)
{
    $out = [];
    foreach (glob(ensure_dir(STORAGE . '/haberler') . '/*.json') ?: [] as $f) {
        $h = json_read($f, null);
        if (is_array($h) && (!$filtre || $filtre($h))) {
            $out[] = $h;
        }
    }
    usort($out, function ($a, $b) {
        return strcmp($b['yayin_tarihi'] ?? $b['tarih'] ?? '', $a['yayin_tarihi'] ?? $a['tarih'] ?? '');
    });
    return $out;
}
function yayindakiler($kid = null)
{
    return haberler(function ($h) use ($kid) {
        return $h['durum'] === 'yayinda' && ($kid === null || $h['kur'] === $kid);
    });
}
function ornekler()
{
    static $o = null;
    if ($o === null) {
        // koddaki örnekler + panelden eklenenler; panelden gizlenenler hariç
        $gizli = gizli_ornekler();
        $o = array_values(array_filter(array_merge(array_map('ornek_foto_uygula', require __DIR__ . '/ornekler.php'), panel_ornekleri()), function ($x) use ($gizli) {
            return !in_array($x['id'], $gizli, true);
        }));
    }
    return $o;
}
function kaynak_sayisi($h)
{
    $n = 0;
    foreach ((array)($h['kaynaklar'] ?? []) as $k) {
        if (trim((string)($k['kaynak'] ?? '')) !== '') {
            $n++;
        }
    }
    return $n;
}
function kaynaksiz_sayisi($h)
{
    $n = 0;
    foreach ((array)($h['kaynaklar'] ?? []) as $k) {
        if (trim((string)($k['bilgi'] ?? '')) !== '' && trim((string)($k['kaynak'] ?? '')) === '') {
            $n++;
        }
    }
    foreach ((array)($h['dipnot']['cumleler'] ?? []) as $c) {
        if (($c['durum'] ?? '') === 'kaynak_yok') {
            $n++;
        }
    }
    return $n;
}
function foto_url($h)
{
    if (empty($h['foto'])) {
        return null;
    }
    $kod = ($h['tur'] ?? '') === 'ornek' && ($h['foto_yer'] ?? '') !== 'uploads'; // koddaki örneklerin görselleri assets/img'de
    return ($kod ? 'assets/img/' : 'uploads/foto/') . rawurlencode($h['foto']);
}

/* --- Haftanın haberi --- */
function oylama_path($kid, $hafta)
{
    return kur_dir($kid) . '/oylama-h' . (int)$hafta . '.json';
}
function oylama($kid, $hafta)
{
    return json_read(oylama_path($kid, $hafta), ['finalistler' => [], 'durum' => 'hazir', 'oylar' => [], 'kazanan' => null]);
}
/* Sıradaki oylamanın finalist havuzu (haberin yazıldığı haftadan bağımsız) */
function finalist_path($kid)
{
    return kur_dir($kid) . '/finalistler.json';
}
function finalistler($kid)
{
    return array_values(array_filter(json_read(finalist_path($kid), []), function ($id) {
        $h = haber_yukle($id);
        return $h && $h['durum'] === 'yayinda';
    }));
}
/* Sonuçlanmamış oylamayı iptal eder; finalist havuzu korunur */
function oylama_iptal($kid, $hafta)
{
    if (!$hafta) {
        return;
    }
    $o = oylama($kid, $hafta);
    if (empty($o['kazanan']) && is_file(oylama_path($kid, $hafta))) {
        @unlink(oylama_path($kid, $hafta));
    }
}
function kazandi_mi($kid, $uid)
{
    foreach (glob(kur_dir($kid) . '/oylama-h*.json') ?: [] as $f) {
        $o = json_read($f);
        if (!empty($o['kazanan'])) {
            $h = haber_yukle($o['kazanan']);
            if ($h && $h['yazar_id'] === $uid) {
                return true;
            }
        }
    }
    return false;
}
function finalist_olabilir($h)
{
    return $h['durum'] === 'yayinda' && kaynak_sayisi($h) > 0 && kaynaksiz_sayisi($h) === 0
        && !empty($h['onay']['lisans']) && (empty($h['foto']) || !empty($h['onay']['foto']));
}

/* --- Canlı ders durumu --- */
function canli_path($kid)
{
    return kur_dir($kid) . '/canli.json';
}
function canli_varsayilan()
{
    return ['aktif' => false, 'hafta' => 1, 'sayfa' => 1, 'sayfa_sayisi' => 0, 'baslangic' => 0, 'soru' => null, 'oylama' => null, 'bitti' => null, 'qr' => true, 'rev' => 0, 'ders_kodu' => null, 'bekleyen_katilim' => []];
}
function canli($kid)
{
    return array_merge(canli_varsayilan(), json_read(canli_path($kid), []));
}
function canli_guncelle($kid, callable $fn)
{
    return json_update(canli_path($kid), function (&$d) use ($fn) {
        $d = array_merge(canli_varsayilan(), $d);
        $r = $fn($d);
        $d['rev'] = (int)($d['rev'] ?? 0) + 1;
        return $r;
    });
}
/* --- Ders kodu ve katılım (yoklama) ---
 * Ders kodu her derste yeniden üretilir; "Dersi bitir" ile geçersiz olur.
 * Onaylı öğrenci kodu okutunca o haftanın katılımı işlenir. Ders başlamadan okutanlar
 * bekleyen listesinde tutulur ve ders başlatılınca o haftaya yazılır. */
function ders_kodu($kid)
{
    $c = canli($kid);
    if (!empty($c['ders_kodu'])) {
        return $c['ders_kodu'];
    }
    return canli_guncelle($kid, function (&$d) {
        if (empty($d['ders_kodu'])) {
            $d['ders_kodu'] = kur_kodu_uret();
        }
        return $d['ders_kodu'];
    });
}
function ders_kodu_gecerli($kid, $kod)
{
    $c = canli($kid);
    return !empty($c['ders_kodu']) && kod_esit($kod, $c['ders_kodu']);
}
function yoklama_path($kid)
{
    return kur_dir($kid) . '/yoklama.json';
}
function yoklama($kid)
{
    return json_read(yoklama_path($kid), []);
}
function yoklama_isle($kid, $hafta, array $uidler, $yol = 'qr')
{
    $hafta = (string)(int)$hafta;
    json_update(yoklama_path($kid), function (&$y) use ($hafta, $uidler, $yol) {
        foreach ($uidler as $uid => $t) {
            if (!isset($y[$hafta][$uid])) {
                $y[$hafta][$uid] = ['t' => $t, 'yol' => $yol];
            }
        }
    });
}
function yoklama_sil($kid, $hafta, $uid)
{
    json_update(yoklama_path($kid), function (&$y) use ($hafta, $uid) {
        unset($y[(string)(int)$hafta][$uid]);
    });
}
/* QR ya da kodla gelen onaylı öğrenci: true = işlendi */
function ders_katil($kid, $uid)
{
    $c = canli($kid);
    if ($c['aktif']) {
        yoklama_isle($kid, $c['hafta'], [$uid => date('c')]);
    } else {
        canli_guncelle($kid, function (&$d) use ($uid) {
            $d['bekleyen_katilim'] = (array)($d['bekleyen_katilim'] ?? []);
            $d['bekleyen_katilim'][$uid] = $d['bekleyen_katilim'][$uid] ?? date('c');
        });
    }
}

function sunum_path($hafta)
{
    return STORAGE . '/sunumlar/hafta' . (int)$hafta . '.pdf';
}

/* --- Metin --- */
function tr_lower($s)
{
    $s = str_replace(['I', 'İ'], ['ı', 'i'], (string)$s);
    return mb_strtolower($s, 'UTF-8');
}
function norm_text($s)
{
    $s = tr_lower($s);
    $s = str_replace(['“', '”', '„', '«', '»'], '"', $s);
    $s = str_replace(['‘', '’', '`'], "'", $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}
function in_source($source, $quote)
{
    $q = norm_text($quote);
    return $q !== '' && mb_strpos(norm_text($source), $q) !== false;
}
function cut_text($s, $max)
{
    return mb_substr((string)$s, 0, $max, 'UTF-8');
}
function text_len($s)
{
    return mb_strlen((string)$s, 'UTF-8');
}
function tr_date($iso, $saat = false)
{
    $months = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    $t = is_numeric($iso) ? (int)$iso : strtotime((string)$iso);
    if (!$t) {
        return '';
    }
    return (int)date('j', $t) . ' ' . $months[(int)date('n', $t) - 1] . ' ' . date('Y', $t) . ($saat ? ' · ' . date('H:i', $t) : '');
}
function ay_yil($iso)
{
    $months = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    $t = strtotime((string)$iso);
    return $t ? $months[(int)date('n', $t) - 1] . ' ' . date('Y', $t) : '';
}
function paragraphs($text)
{
    $html = '';
    foreach (preg_split('/\R{2,}/u', trim((string)$text)) as $p) {
        $p = trim($p);
        if ($p !== '') {
            $html .= '<p>' . nl2br(e($p)) . '</p>';
        }
    }
    return $html;
}

/* --- Anlık soru sonuçları (anonim, toplu sayılar) --- */
function soru_gecmisi_path($kid)
{
    return kur_dir($kid) . '/soru-gecmisi.json';
}
function soru_arsivle($kid, $c)
{
    $s = $c['soru'] ?? null;
    if (!$s) {
        return;
    }
    $y = json_read(kur_dir($kid) . '/canli-yanit.json', []);
    $yan = ($y['soru'] ?? '') === $s['id'] ? (array)($y['yanitlar'] ?? []) : [];
    $say = array_fill(0, count($s['secenekler']), 0);
    foreach ($yan as $v) {
        if (isset($say[$v])) {
            $say[$v]++;
        }
    }
    $kayit = ['id' => $s['id'], 'hafta' => (int)$c['hafta'], 'metin' => $s['metin'], 'secenekler' => $s['secenekler'], 'sayilar' => $say, 'yanit' => count($yan), 'tarih' => date('c')];
    json_update(soru_gecmisi_path($kid), function (&$l) use ($kayit) {
        foreach ($l as $i => $r) {
            if ($r['id'] === $kayit['id']) {
                $kayit['tarih'] = $r['tarih'];
                $l[$i] = $kayit;
                return;
            }
        }
        $l[] = $kayit;
    });
}

/* --- Ders tarihleri ve teslim --- */
function tr_gun($t)
{
    return ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'][(int)date('w', $t)];
}
/* Bir haftanın ödevi, o haftanın canlı dersinden 7 gün sonra (bir sonraki ders saatinde) teslim edilir */
function son_teslim($kur, $hafta)
{
    $d = $kur['ders_tarihleri'][(int)$hafta] ?? null;
    return $d ? strtotime($d) + 7 * 86400 : null;
}
function son_teslim_yazi($kur, $hafta)
{
    $t = $kur ? son_teslim($kur, $hafta) : null;
    return $t ? tr_date($t) . ' ' . tr_gun($t) . ', ' . date('H:i', $t) : null;
}
function gec_mi($kur, $h)
{
    $t = son_teslim($kur, $h['hafta']);
    return $t && strtotime($h['tarih']) > $t;
}
/* Öğrencinin görmediği eğitmen notu / durum değişikliği sayısı */
function yeni_not_sayisi($u)
{
    return $u ? count(haberler(function ($h) use ($u) {
        return ($h['yazar_id'] ?? '') === $u['id'] && !empty($h['bildirim']);
    })) : 0;
}

/* --- Bakım: kapanmış kurlarda saklama süresi dolan verileri siler --- */
function bakim()
{
    $gun = (int)cfg('saklama_gun', 60);
    foreach (kurlar()['liste'] as $kur) {
        if (($kur['durum'] ?? '') !== 'arsiv' || !empty($kur['temizlendi']) || empty($kur['kapanis'])) {
            continue;
        }
        if (strtotime($kur['kapanis']) > time() - $gun * 86400) {
            continue;
        }
        foreach (haberler(function ($h) use ($kur) {
            return $h['kur'] === $kur['id'] && $h['durum'] !== 'yayinda';
        }) as $h) {
            if (!empty($h['foto'])) {
                @unlink(UPLOADS . '/foto/' . $h['foto']);
            }
            @unlink(haber_path($h['id']));
        }
        foreach (glob(kur_dir($kur['id']) . '/*') ?: [] as $f) {
            if (strpos(basename($f), 'oylama-') !== 0 && basename($f) !== 'soru-gecmisi.json') { // kazananlar ve anonim soru sonuçları kalır
                @unlink($f);
            }
        }
        kur_guncelle($kur['id'], ['temizlendi' => date('c')]);
    }
}

/* --- Günlük kullanım sınırları --- */
function kota_path($uid)
{
    return STORAGE . '/kullanim/' . date('Ymd') . '_' . sha1((string)$uid) . '.json';
}
function kota_limit($kind)
{
    $map = ['text' => 'daily_text_limit', 'audio' => 'daily_audio_limit', 'submit' => 'daily_submit_limit'];
    return (int)cfg($map[$kind] ?? 'daily_text_limit', 20);
}
function kota_kalan($uid, $kind)
{
    $c = json_read(kota_path($uid), []);
    return max(0, kota_limit($kind) - (int)($c[$kind] ?? 0));
}
/* Hak varsa bir düşer ve true döner */
function kota_kullan($uid, $kind)
{
    if (is_admin()) {
        return true;
    }
    return json_update(kota_path($uid), function (&$c) use ($kind) {
        $used = (int)($c[$kind] ?? 0);
        if ($used >= kota_limit($kind)) {
            return false;
        }
        $c[$kind] = $used + 1;
        return true;
    });
}
