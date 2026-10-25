<?php
/*
 * Eğitmenin yüklediği görseller.
 * - Sitenin sabit görselleri (açılış, bant, yer tutucular): uploads/site/<ad>.jpg
 *   Yoksa assets/img/<ad>.jpg (depodaki varsayılan) kullanılır. GitHub güncellemeleri uploads'a dokunmaz.
 * - Genel fotoğraf kutusu: uploads/medya/<ad>-<id>-<boy>.jpg (1600, 960, 480 piksel)
 */

function site_gorselleri()
{
    return [
        'acilis'       => ['Ana sayfa açılış fotoğrafı', 1920, 1080],
        'kapanis'      => ['Sayfa sonundaki bant', 1600, 880],
        'yer-tutucu-1' => ['Yer tutucu 1 (fotoğrafsız haberler)', 960, 640],
        'yer-tutucu-2' => ['Yer tutucu 2 (fotoğrafsız haberler)', 960, 640],
        'yer-tutucu-3' => ['Yer tutucu 3 (fotoğrafsız haberler)', 960, 640],
    ];
}

function site_gorsel_dosya($ad)
{
    return UPLOADS . '/site/' . $ad . '.jpg';
}

/* Sayfalarda kullanılacak adres: yüklenen varsa o, yoksa varsayılan */
function site_gorsel($ad)
{
    $f = site_gorsel_dosya($ad);
    if (is_file($f)) {
        return 'uploads/site/' . $ad . '.jpg?v=' . filemtime($f);
    }
    return 'assets/img/' . $ad . '.jpg';
}

function medya_boylari()
{
    return ['buyuk' => 1600, 'orta' => 960, 'kucuk' => 480];
}

/* Yüklenen dosyayı denetler, okur ve telefonun döndürme bilgisini uygular. EXIF yeniden kayıtla silinir. */
function gorsel_oku(array $file, $maxMb = 15)
{
    if (in_array($file['error'] ?? UPLOAD_ERR_NO_FILE, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > $maxMb * 1048576) {
        throw new RuntimeException('Fotoğraf ' . $maxMb . ' MB sınırını aşıyor.');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Fotoğraf yüklenemedi. Tekrar deneyin.');
    }
    if (!function_exists('imagecreatefromjpeg')) {
        throw new RuntimeException('Sunucuda görsel işleme (GD) etkin değil.');
    }
    $info = @getimagesize($file['tmp_name']);
    $type = $info[2] ?? 0;
    if ($type === IMAGETYPE_JPEG) {
        $img = @imagecreatefromjpeg($file['tmp_name']);
    } elseif ($type === IMAGETYPE_PNG) {
        $img = @imagecreatefrompng($file['tmp_name']);
    } elseif ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        $img = @imagecreatefromwebp($file['tmp_name']);
    } else {
        throw new RuntimeException('Fotoğraf JPG, PNG ya da WEBP olmalı. iPhone fotoğrafıysa (HEIC) önce JPG olarak dışa aktarın.');
    }
    if (!$img) {
        throw new RuntimeException('Fotoğraf okunamadı.');
    }
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($file['tmp_name'])['Orientation'] ?? 1);
        $aci = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($aci) {
            $img = imagerotate($img, $aci, 0);
        }
    }
    return $img;
}

/* Hedef ölçüyü tam dolduracak şekilde ortadan kırpar */
function gorsel_kirp($img, $w, $h)
{
    $sw = imagesx($img);
    $sh = imagesy($img);
    $oran = max($w / $sw, $h / $sh);
    $kw = (int)round($w / $oran);
    $kh = (int)round($h / $oran);
    $dst = imagecreatetruecolor($w, $h);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $img, 0, 0, (int)(($sw - $kw) / 2), (int)(($sh - $kh) / 2), $w, $h, $kw, $kh);
    return $dst;
}

/* Uzun kenarı $max pikseli geçmeyecek şekilde küçültür (büyütmez) */
function gorsel_sigdir($img, $max)
{
    $sw = imagesx($img);
    $sh = imagesy($img);
    $oran = min(1, $max / max($sw, $sh));
    $w = max(1, (int)round($sw * $oran));
    $h = max(1, (int)round($sh * $oran));
    $dst = imagecreatetruecolor($w, $h);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $w, $h, $sw, $sh);
    return $dst;
}

function gorsel_kaydet($img, $yol, $kalite = 80)
{
    ensure_dir(dirname($yol));
    imageinterlace($img, true); // telefonda kademeli açılır
    $tmp = $yol . '.tmp';
    if (!imagejpeg($img, $tmp, $kalite) || !rename($tmp, $yol)) {
        @unlink($tmp);
        throw new RuntimeException('Görsel kaydedilemedi. Klasör izinlerini kontrol edin.');
    }
}

/* Sabit görseli değiştirir; var olanı bir önceki sürüm olarak saklar */
function site_gorsel_degistir($ad, array $file)
{
    $liste = site_gorselleri();
    if (!isset($liste[$ad])) {
        throw new RuntimeException('Bilinmeyen görsel.');
    }
    [, $w, $h] = $liste[$ad];
    $img = gorsel_oku($file);
    $son = gorsel_kirp($img, $w, $h);
    $yol = site_gorsel_dosya($ad);
    if (is_file($yol)) {
        @rename($yol, UPLOADS . '/site/' . $ad . '.onceki.jpg');
    }
    gorsel_kaydet($son, $yol, $w >= 1600 ? 78 : 80);
}

/* Bir önceki sürümle şimdikinin yerini değiştirir */
function site_gorsel_onceki($ad)
{
    $yol = site_gorsel_dosya($ad);
    $onceki = UPLOADS . '/site/' . $ad . '.onceki.jpg';
    if (!is_file($onceki)) {
        return false;
    }
    $gecici = $yol . '.takas';
    if (is_file($yol)) {
        rename($yol, $gecici);
    }
    rename($onceki, $yol);
    if (is_file($gecici)) {
        rename($gecici, $onceki);
    }
    touch($yol);
    return true;
}

/* Yüklenen görseli kaldırır; varsayılana döner (önceki sürüm saklanır) */
function site_gorsel_varsayilan($ad)
{
    $yol = site_gorsel_dosya($ad);
    if (is_file($yol)) {
        @rename($yol, UPLOADS . '/site/' . $ad . '.onceki.jpg');
    }
}

function medya_yukle(array $file, $ad = '')
{
    $img = gorsel_oku($file);
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $ad !== '' ? $ad : pathinfo((string)$file['name'], PATHINFO_FILENAME)) ?: 'foto')), '-');
    $slug = substr($slug !== '' ? $slug : 'foto', 0, 40);
    $id = $slug . '-' . date('ymd') . bin2hex(random_bytes(3));
    foreach (medya_boylari() as $boy => $px) {
        gorsel_kaydet(gorsel_sigdir($img, $px), UPLOADS . '/medya/' . $id . '-' . $px . '.jpg', 80);
    }
    return $id;
}

/* En yeni önce */
function medya_listesi()
{
    $out = [];
    foreach (glob(UPLOADS . '/medya/*-1600.jpg') ?: [] as $f) {
        $id = substr(basename($f), 0, -strlen('-1600.jpg'));
        $out[$id] = filemtime($f);
    }
    arsort($out);
    return $out;
}

function medya_sil($id)
{
    if (!preg_match('/^[a-z0-9-]{1,80}$/', (string)$id)) {
        return;
    }
    foreach (medya_boylari() as $px) {
        @unlink(UPLOADS . '/medya/' . $id . '-' . $px . '.jpg');
    }
}

/* Habere eklenen fotoğraf: en fazla 1600 px, uploads/foto/ altında (öğrenci gönderimleriyle aynı işlem) */
function haber_foto_kaydet(array $file, $eskiAd = null)
{
    $img = gorsel_oku($file, (int)cfg('max_photo_mb', 10));
    $ad = new_id() . bin2hex(random_bytes(4)) . '.jpg';
    gorsel_kaydet(gorsel_sigdir($img, 1600), UPLOADS . '/foto/' . $ad, 82);
    if ($eskiAd) {
        @unlink(UPLOADS . '/foto/' . basename($eskiAd));
    }
    return $ad;
}
