<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/dipnot.php';

if (is_admin() && !ogrenci()) {
    flash('Gönderim öğrenci hesabıyla yapılır. Gönderilen haberleri panelden inceleyebilirsiniz.', 'warn');
    redirect('egitmen.php');
}
require_ogrenci();
$u = ogrenci();
$kur = kur_aktif();
$acikHafta = kur_hafta();

/* Düzenleme */
$h = null;
if (!empty($_GET['id'])) {
    $h = haber_yukle($_GET['id']);
    if (!$h || ($h['yazar_id'] ?? '') !== $u['id'] || !in_array($h['durum'], ['taslak', 'bekliyor', 'duzeltme'], true)) {
        flash('Bu gönderi artık düzenlenemez.', 'err');
        redirect('odevim.php');
    }
}
$hata = '';

function foto_isle($file, $eskiAd = null)
{
    $maxMb = (int)cfg('max_photo_mb', 10);
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > $maxMb * 1048576) {
        throw new RuntimeException('Fotoğraf ' . $maxMb . ' MB sınırını aşıyor.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
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
        throw new RuntimeException('Fotoğraf JPG, PNG ya da WEBP olmalı. iPhone\'da HEIC çekiyorsanız Ayarlar > Kamera > Biçimler > "En Uyumlu" seçin.');
    }
    if (!$img) {
        throw new RuntimeException('Fotoğraf okunamadı.');
    }
    // Telefonun döndürme bilgisini uygula; sonra tüm EXIF (konum dahil) yeniden kayıtla silinir
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $o = (int)($exif['Orientation'] ?? 1);
        if ($o === 3) {
            $img = imagerotate($img, 180, 0);
        } elseif ($o === 6) {
            $img = imagerotate($img, -90, 0);
        } elseif ($o === 8) {
            $img = imagerotate($img, 90, 0);
        }
    }
    $w = imagesx($img);
    $hgt = imagesy($img);
    $max = 1600;
    if ($w > $max || $hgt > $max) {
        $r = min($max / $w, $max / $hgt);
        $nw = (int)round($w * $r);
        $nh = (int)round($hgt * $r);
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $hgt);
        imagedestroy($img);
        $img = $dst;
    } elseif ($type !== IMAGETYPE_JPEG) {
        $dst = imagecreatetruecolor($w, $hgt);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopy($dst, $img, 0, 0, 0, 0, $w, $hgt);
        imagedestroy($img);
        $img = $dst;
    }
    $ad = new_id() . bin2hex(random_bytes(4)) . '.jpg';
    ensure_dir(UPLOADS . '/foto');
    if (!imagejpeg($img, UPLOADS . '/foto/' . $ad, 82)) {
        throw new RuntimeException('Fotoğraf kaydedilemedi.');
    }
    imagedestroy($img);
    @unlink($file['tmp_name']);
    if ($eskiAd) {
        @unlink(UPLOADS . '/foto/' . basename($eskiAd));
    }
    return $ad;
}

$v = $h ?: ['tur' => 'haber', 'hafta' => max(1, min(max(1, $acikHafta), (int)($_GET['hafta'] ?? $acikHafta))), 'baslik' => '', 'spot' => '', 'metin' => '', 'kaynaklar' => [], 'foto' => null, 'altyazi' => '', 'alt' => '', 'onay' => [], 'yz' => null, 'yer' => '', 'konu' => ''];

$v['tur'] = hafta_turu($v['hafta']); // ödev türünü hafta belirler
if (is_post()) {
    $v['hafta'] = max(1, min(8, (int)($_POST['hafta'] ?? 1)));
    $v['tur'] = hafta_turu($v['hafta']);
    $v['baslik'] = post('baslik', 160);
    $v['spot'] = post('spot', 400);
    $v['yer'] = post('yer', 80);
    $v['konu'] = isset(konular()[$_POST['konu'] ?? '']) ? $_POST['konu'] : '';
    $taslak = ($_POST['islem'] ?? '') === 'taslak';
    $yeniGonderim = !$h || $h['durum'] === 'taslak';
    $v['metin'] = post('metin', (int)cfg('max_chars', 12000));
    $v['altyazi'] = post('altyazi', 300);
    $v['alt'] = post('alt', 300);
    $v['kaynaklar'] = [];
    $bilgi = (array)($_POST['bilgi'] ?? []);
    $kaynak = (array)($_POST['kaynak'] ?? []);
    foreach ($bilgi as $i => $b) {
        $b = cut_text(trim((string)$b), 300);
        $k = cut_text(trim((string)($kaynak[$i] ?? '')), 300);
        if ($b !== '' || $k !== '') {
            $v['kaynaklar'][] = ['bilgi' => $b, 'kaynak' => $k];
        }
    }
    $v['kaynaklar'] = array_slice($v['kaynaklar'], 0, 30);
    $v['onay'] = [
        'lisans' => !empty($_POST['onay_lisans']),
        'foto'   => !empty($_POST['onay_foto']),
        'ornek'  => !empty($_POST['onay_ornek']),
    ];
    $yzSecim = (string)($_POST['yz'] ?? '');
    $v['yz'] = in_array($yzSecim, ['hayir', 'evet'], true) ? ['kullandi' => $yzSecim === 'evet', 'aciklama' => post('yz_aciklama', 300)] : null;
    $yeniFoto = $_FILES['foto'] ?? null;
    $fotoVar = ($yeniFoto && ($yeniFoto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) || (!empty($v['foto']) && empty($_POST['foto_sil']));

    if (!csrf_ok()) {
        $hata = 'Sayfanın süresi dolmuş. Tekrar deneyin.';
    } elseif ($taslak && text_len($v['baslik'] . $v['metin']) < 1) {
        $hata = 'Boş taslak kaydedilemez.';
    } elseif ($taslak) {
        // Taslak: denetim yok, eğitmen görmez
    } elseif (text_len($v['metin']) < 30) {
        $hata = 'Metin çok kısa.';
    } elseif ($v['tur'] === 'haber' && text_len($v['baslik']) < 5) {
        $hata = 'Habere bir başlık yaz.';
    } elseif ($v['tur'] === 'haber' && text_len($v['yer']) < 2) {
        $hata = 'Haberin geçtiği yeri yaz (mahalle, ilçe).';
    } elseif ($v['tur'] === 'haber' && $v['konu'] === '') {
        $hata = 'Haberin konusunu seç.';
    } elseif ($v['tur'] === 'haber' && !$v['onay']['lisans']) {
        $hata = 'Haberin yayımlanabilmesi için kullanım izni onayını işaretle.';
    } elseif ($v['tur'] === 'haber' && $fotoVar && !$v['onay']['foto']) {
        $hata = 'Fotoğrafı kendin çektiğini ve gerekli izinleri aldığını onayla.';
    } elseif ($v['tur'] === 'haber' && !$v['yz']) {
        $hata = 'Yapay zekâ kullanıp kullanmadığını belirt.';
    } elseif ($v['tur'] === 'haber' && $v['yz']['kullandi'] && text_len($v['yz']['aciklama']) < 5) {
        $hata = 'Yapay zekâyı hangi iş için kullandığını kısaca yaz.';
    } elseif ($v['tur'] === 'haber' && $fotoVar && text_len($v['altyazi']) < 5) {
        $hata = 'Fotoğrafa bir altyazı yaz: ne, nerede, ne zaman.';
    }
    if (!$hata && !$taslak && $yeniGonderim && !kota_kullan($u['id'], 'submit')) {
        $hata = 'Bugünkü gönderim sınırına ulaştın. Yarın tekrar deneyebilirsin.';
    }
    if (!$hata) {
        try {
            if ($v['tur'] === 'odev') {
                $v['onay'] = ['lisans' => false, 'foto' => false, 'ornek' => false];
                $v['yz'] = null;
            }
            if (!empty($_POST['foto_sil']) && !empty($v['foto'])) {
                @unlink(UPLOADS . '/foto/' . basename($v['foto']));
                $v['foto'] = null;
            }
            if ($yeniFoto && ($yeniFoto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $v['foto'] = foto_isle($yeniFoto, $v['foto'] ?? null);
            }
            $v['dipnot'] = null;
            if (!$taslak && $v['tur'] === 'haber' && ai_acik()) {
                try {
                    $v['dipnot'] = dipnot_kontrol($v['metin'], array_values($v['kaynaklar']));
                } catch (Throwable $ex) {
                    $v['dipnot'] = null; // kontrol yapılamazsa gönderim yine de alınır
                }
            }
            $simdi = date('c');
            $kayit = array_merge($v, [
                'id'       => $h['id'] ?? new_id(),
                'kur'      => $kur['id'],
                'yazar_id' => $u['id'],
                'yazar'    => gorunen_ad($u),
                'durum'    => $taslak ? 'taslak' : 'bekliyor',
                'tarih'    => ($h && $h['durum'] !== 'taslak') ? $h['tarih'] : $simdi, // ilk gönderim anı (geç teslim hesabı)
                'guncelleme' => $simdi,
                'not'      => $h['not'] ?? '',
            ]);
            haber_kaydet($kayit);
            if ($taslak) {
                flash('Taslak kaydedildi.');
                redirect('gonder.php?id=' . $kayit['id']);
            }
            flash($h && $h['durum'] !== 'taslak' ? 'Düzeltmen eğitmene gönderildi.' : 'Gönderin eğitmene ulaştı.');
            redirect('odevim.php#g-' . $kayit['id']);
        } catch (RuntimeException $ex) {
            $hata = $ex->getMessage();
        }
    }
}

if (!$v['kaynaklar']) {
    $v['kaynaklar'] = [['bilgi' => '', 'kaynak' => ''], ['bilgi' => '', 'kaynak' => '']];
}
$fotoUrl = !empty($v['foto']) ? foto_url($v) : null;
$sayfaBaslik = $v['tur'] === 'odev' ? 'Ödevini gönder' : 'Haberini gönder';
page_head($sayfaBaslik);
?>
<div class="wrap narrow section stack">
  <h1 id="gonder-baslik" data-haber="Haberini gönder" data-odev="Ödevini gönder"><?= $h && $h['durum'] !== 'taslak' ? 'Gönderini düzelt' : $sayfaBaslik ?></h1>
  <?php if ($h && $h['durum'] === 'taslak'): ?><p class="tag" style="margin:0">Taslak · <?= e(tr_date($h['guncelleme'], true)) ?></p><?php endif; ?>
  <?php if ($h && !empty($h['not'])): ?><div class="flash warn" style="margin:0"><strong>Eğitmen notu:</strong> <?= nl2br(e($h['not'])) ?></div><?php endif; ?>
  <?php if ($hata): ?><p class="flash err" style="margin:0" role="alert"><?= e($hata) ?></p><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="stack" id="gonder-form" data-taslak="<?= $h || is_post() ? '0' : '1' ?>">
    <?= csrf_field() ?>
    <section class="card stack">
      <h2 class="step-title"><span class="n">1</span> Gönderi</h2>
      <div class="field"><label for="hafta">Hafta</label>
        <select id="hafta" name="hafta">
          <?php for ($i = 1; $i <= max(1, $acikHafta); $i++): ?>
            <option value="<?= $i ?>" data-tur="<?= hafta_turu($i) ?>"<?= (int)$v['hafta'] === $i ? ' selected' : '' ?>>Hafta <?= $i ?>: <?= e(week($i)['baslik']) ?></option>
          <?php endfor; ?>
        </select>
        <span class="help" id="tur-bilgi" data-haber="Haber · onaylanırsa yayımlanır" data-odev="Yazılı ödev · yalnızca eğitmen görür"><?= $v['tur'] === 'odev' ? 'Yazılı ödev · yalnızca eğitmen görür' : 'Haber · onaylanırsa yayımlanır' ?></span></div>
      <div class="field only-haber"><label for="baslik">Başlık</label><input id="baslik" name="baslik" type="text" maxlength="160" value="<?= e($v['baslik']) ?>"></div>
      <div class="field only-haber"><label for="spot">Spot <span class="help">(isteğe bağlı)</span></label><input id="spot" name="spot" type="text" maxlength="400" value="<?= e($v['spot']) ?>"></div>
      <div class="row only-haber" style="gap:12px;align-items:flex-start">
        <div class="field" style="flex:1 1 220px"><label for="yer">Yer</label><input id="yer" name="yer" type="text" maxlength="80" placeholder="Mahalle, ilçe" value="<?= e($v['yer'] ?? '') ?>"></div>
        <div class="field" style="flex:1 1 220px"><label for="konu">Konu</label>
          <select id="konu" name="konu"><option value="">Seç</option><?php foreach (konular() as $kk => $ka): ?><option value="<?= $kk ?>"<?= ($v['konu'] ?? '') === $kk ? ' selected' : '' ?>><?= e($ka) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label for="metin">Metin</label><textarea id="metin" name="metin" rows="12" required><?= e($v['metin']) ?></textarea></div>
    </section>

    <section class="card stack only-haber">
      <h2 class="step-title"><span class="n">2</span> Kaynaklar</h2>
      <div class="src-rows" id="src-rows">
        <?php foreach ($v['kaynaklar'] as $k): ?>
          <div class="src-row">
            <input type="text" name="bilgi[]" placeholder="Bilgi (ör. Mart'ta açılacağı)" aria-label="Bilgi" value="<?= e($k['bilgi']) ?>">
            <input type="text" name="kaynak[]" placeholder="Kaynak (ör. Belediye basın birimi, telefonla)" aria-label="Kaynak" value="<?= e($k['kaynak']) ?>">
            <button type="button" class="btn btn-sm" data-sil aria-label="Satırı sil">×</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-sm" id="src-ekle" style="align-self:flex-start">+ Kaynak ekle</button>
    </section>

    <section class="card stack only-haber">
      <h2 class="step-title"><span class="n">3</span> Fotoğraf <span class="help">isteğe bağlı</span></h2>
      <?php if ($fotoUrl): ?>
        <img class="foto-preview" src="<?= e($fotoUrl) ?>" alt="">
        <label class="check"><input type="checkbox" name="foto_sil" value="1"> <span>Bu fotoğrafı kaldır</span></label>
      <?php endif; ?>
      <div class="field"><label for="foto"><?= $fotoUrl ? 'Yeni fotoğraf yükle' : 'Fotoğraf' ?></label>
        <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
        <span class="help">JPG, PNG ya da WEBP · en fazla <?= (int)cfg('max_photo_mb', 10) ?> MB</span></div>
      <img class="foto-preview hidden" id="foto-onizleme" alt="">
      <div class="field"><label for="altyazi">Fotoğraf altı yazısı</label><input id="altyazi" name="altyazi" type="text" maxlength="300" placeholder="Ne, nerede, ne zaman" value="<?= e($v['altyazi']) ?>"></div>
      <div class="field"><label for="alt">Görsel açıklaması <span class="help">(görme engelli okurlar için)</span></label><input id="alt" name="alt" type="text" maxlength="300" value="<?= e($v['alt']) ?>"></div>
    </section>

    <section class="card stack only-haber">
      <h2 class="step-title"><span class="n">4</span> Onaylar</h2>
      <label class="check"><input type="checkbox" name="onay_lisans" value="1"<?= !empty($v['onay']['lisans']) ? ' checked' : '' ?>> <span>Haberi ben yazdım. Sitenin haberi yayımlaması, düzenlemesi ve arşivlemesi için kullanım izni veriyorum; imzam her zaman belirtilir. <a href="kullanim-izni.php" target="_blank">Metni oku</a></span></label>
      <label class="check"><input type="checkbox" name="onay_foto" value="1"<?= !empty($v['onay']['foto']) ? ' checked' : '' ?>> <span>Fotoğrafı ben çektim; fotoğrafta tanınabilir kişi varsa iznini aldım. <span class="help">(fotoğraf yüklediysen)</span></span></label>
      <label class="check"><input type="checkbox" name="onay_ornek" value="1"<?= !empty($v['onay']['ornek']) ? ' checked' : '' ?>> <span>İsteğe bağlı: Haberimin eğitim ve tanıtım amacıyla örnek olarak kullanılmasına izin veriyorum.</span></label>
    </section>

    <section class="card stack only-haber">
      <h2 class="step-title"><span class="n">5</span> Yapay zekâ beyanı</h2>
      <?php $yz = $v['yz'] ?? null; ?>
      <label class="check"><input type="radio" name="yz" value="hayir"<?= $yz && !$yz['kullandi'] ? ' checked' : '' ?>> <span>Bu haberi yazarken yapay zekâ aracı kullanmadım.</span></label>
      <label class="check"><input type="radio" name="yz" value="evet"<?= $yz && $yz['kullandi'] ? ' checked' : '' ?>> <span>Yapay zekâ aracı kullandım.</span></label>
      <div class="field" id="yz-aciklama-alan"<?= $yz && $yz['kullandi'] ? '' : ' hidden' ?>><label for="yz_aciklama">Hangi iş için? <span class="help">(ör. ses kaydını yazıya dökmek, yazım denetimi)</span></label>
        <input id="yz_aciklama" name="yz_aciklama" type="text" maxlength="300" value="<?= e($yz['aciklama'] ?? '') ?>"></div>
      <p class="help">Beyanın haberle birlikte yayımlanır.</p>
    </section>

    <div class="row">
      <button class="btn btn-primary" type="submit" name="islem" value="gonder" id="gonder-btn">Eğitmene gönder</button>
      <?php if (!$h || $h['durum'] === 'taslak'): ?><button class="btn" type="submit" name="islem" value="taslak" id="taslak-btn" formnovalidate>Taslak olarak kaydet</button><?php endif; ?>
    </div>
  </form>
</div>
<?php page_foot(['assets/gonder.js?v=4']);
