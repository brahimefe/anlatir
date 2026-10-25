<?php
/*
 * Panelden düzenlenen içerik. Varsayılanlar koddadır (inc/weeks.php, inc/ornekler.php, inc/notlar.php);
 * eğitmenin değişiklikleri storage/icerik.json ve storage/ornekler/ altında tutulur.
 * GitHub güncellemeleri storage'a dokunmaz; "Varsayılana dön" bir değişikliği siler.
 */

function icerik_path()
{
    return STORAGE . '/icerik.json';
}

function icerik($anahtar = null, $varsayilan = null)
{
    static $c = null;
    if ($c === null || $anahtar === false) {
        $c = json_read(icerik_path(), []);
        if ($anahtar === false) {
            return $c;
        }
    }
    if ($anahtar === null) {
        return $c;
    }
    return $c[$anahtar] ?? $varsayilan;
}

function icerik_guncelle(callable $fn)
{
    $r = json_update(icerik_path(), $fn, []);
    icerik(false); // önbelleği tazele
    return $r;
}

/* Haftaların düzenlenebilir alanları */
function hafta_alanlari()
{
    return ['baslik', 'kisa', 'ozet', 'atolye', 'foto', 'odev', 'odev_turu', 'canta', 'ornekler', 'sorular'];
}

/* Varsayılan haftalara panel değişikliklerini ve panelden eklenen örnek haberleri uygular */
function icerik_haftalar(array $haftalar)
{
    $deg = (array)icerik('haftalar', []);
    foreach ($haftalar as $n => &$w) {
        $w['_varsayilan'] = array_intersect_key($w, array_flip(hafta_alanlari()));
        foreach (hafta_alanlari() as $k) {
            if (array_key_exists($k, (array)($deg[$n] ?? []))) {
                $w[$k] = $deg[$n][$k];
            }
        }
        $w['_degisti'] = !empty($deg[$n]);
    }
    unset($w);
    // panelden eklenen örnek haberler kendi haftalarının sayfasında görünür
    foreach (panel_ornekleri() as $o) {
        $n = (int)($o['ornek_hafta'] ?? 0);
        if (isset($haftalar[$n]) && !in_array($o['id'], (array)$haftalar[$n]['ornekler'], true)) {
            $haftalar[$n]['ornekler'][] = $o['id'];
        }
    }
    return $haftalar;
}

/* Bir haftanın hazırlık durumu: [etiket, tamam mı] */
function hafta_hazirlik($n, $w)
{
    $ornekSayi = count(array_filter(array_map('haber_yukle', (array)($w['ornekler'] ?? []))));
    return [
        ['Ders sayfası', count((array)$w['ozet']) > 0 && trim((string)$w['baslik']) !== ''],
        ['Ödev', trim((string)$w['odev']) !== ''],
        ['Sunum', is_file(sunum_path($n))],
        ['Canlı sorular: ' . count((array)$w['sorular']), count((array)$w['sorular']) > 0],
        ['Örnek haber: ' . $ornekSayi, $ornekSayi > 0],
    ];
}

/* --- Panelden eklenen örnek haberler --- */
function ornek_dir()
{
    return ensure_dir(STORAGE . '/ornekler');
}
function panel_ornekleri()
{
    $out = [];
    foreach (glob(STORAGE . '/ornekler/*.json') ?: [] as $f) {
        $o = json_read($f, null);
        if (is_array($o) && !empty($o['id'])) {
            $out[] = $o;
        }
    }
    usort($out, function ($a, $b) {
        return strcmp($b['guncelleme'] ?? '', $a['guncelleme'] ?? '');
    });
    return $out;
}
function panel_ornegi_kaydet(array $o)
{
    return json_write(ornek_dir() . '/' . $o['id'] . '.json', $o);
}
function panel_ornegi_sil($id)
{
    if (!preg_match('/^ornek-p[a-f0-9]{16}$/', (string)$id)) {
        return;
    }
    $o = json_read(STORAGE . '/ornekler/' . $id . '.json', null);
    if ($o && !empty($o['foto'])) {
        @unlink(UPLOADS . '/foto/' . basename($o['foto']));
    }
    @unlink(STORAGE . '/ornekler/' . $id . '.json');
}
function gizli_ornekler()
{
    return (array)icerik('gizli_ornekler', []);
}

/* --- Duyuru bandı --- */
function duyuru()
{
    $d = (array)icerik('duyuru', []);
    if (empty($d['acik']) || trim((string)($d['metin'] ?? '')) === '') {
        return null;
    }
    if (!empty($d['bitis']) && date('Y-m-d') > $d['bitis']) {
        return null;
    }
    return $d['metin'];
}

/* --- Ana sayfa yazıları --- */
function anasayfa_alanlari()
{
    return [
        'baslik'  => ['Açılış başlığı', 'Haberini kaynağıyla yaz.'],
        'alt'     => ['Açılış alt yazısı', 'Sekiz haftalık uygulamalı seminerde haber fikrini bulmayı, kaynağa ulaşmayı, röportaj yapmayı ve her bilgiyi doğrulamayı öğren. Seçilen haberler imzanla yayımlanır.'],
        'kurlar'  => ['Kurlar bölümünün başlığı', 'Her kur sekiz hafta'],
        'kapanis' => ['Sayfa sonundaki cümle', 'Bir sonraki haber senin olsun.'],
    ];
}
function anasayfa_metin($k)
{
    $v = trim((string)(icerik('anasayfa', [])[$k] ?? ''));
    return $v !== '' ? $v : (anasayfa_alanlari()[$k][1] ?? '');
}

/* --- Hazır not cümleleri --- */
function hazir_notlar()
{
    $n = icerik('notlar');
    return is_array($n) ? $n : (require __DIR__ . '/notlar.php');
}

/* Çok satırlı metni listeye çevirir */
function satirlar($metin, $max = 30, $uzunluk = 400)
{
    $out = [];
    foreach (preg_split('/\R/u', (string)$metin) as $s) {
        $s = trim(preg_replace('/^\s*[-•*]\s*/u', '', $s));
        if ($s !== '') {
            $out[] = cut_text($s, $uzunluk);
        }
    }
    return array_slice($out, 0, $max);
}

/* Sitenin kendi örnek haberlerine panelden eklenen temsilî fotoğraf */
function ornek_foto_uygula(array $o)
{
    $f = (array)(icerik('ornek_foto', [])[$o['id']] ?? []);
    if (!empty($f['foto'])) {
        $o['foto'] = $f['foto'];
        $o['foto_yer'] = 'uploads';
        $o['altyazi'] = $f['altyazi'] ?? '';
        $o['foto_kredi'] = $f['kredi'] ?? '';
    }
    return $o;
}

/* --- Genel: imza adı, alt bilgi, konu listesi --- */
function genel_ayar($k, $varsayilan = '')
{
    $v = trim((string)(icerik('genel', [])[$k] ?? ''));
    return $v !== '' ? $v : $varsayilan;
}
function varsayilan_konular()
{
    return ['belediye' => 'Belediye ve hizmetler', 'ulasim' => 'Ulaşım', 'egitim' => 'Eğitim', 'cevre' => 'Çevre', 'saglik' => 'Sağlık', 'kultur' => 'Kültür ve sanat', 'spor' => 'Spor', 'ekonomi' => 'Esnaf ve ekonomi', 'diger' => 'Diğer'];
}
/* Panelden: etiket değiştirme, gizleme, yeni konu. Anahtarlar sabit kalır; eski haberlerin konusu kaybolmaz. */
function konu_ayarlari()
{
    return (array)icerik('konular', []);
}
function konu_slug($s)
{
    $s = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: '');
    return trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
}
