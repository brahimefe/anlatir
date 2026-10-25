<?php
/* Öğrencinin profili: rumuz, imza, yazar tanıtımı, şifre */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

if (is_admin() && !ogrenci()) {
    redirect('egitmen.php');
}
require_ogrenci();
$u = ogrenci();
$kur = kur_aktif();
$hata = '';

if (is_post()) {
    csrf_check();
    if (($_POST['islem'] ?? '') === 'sifre') {
        $yeni = (string)($_POST['yeni'] ?? '');
        if (!password_verify((string)($_POST['eski'] ?? ''), $u['hash'])) {
            $hata = 'Mevcut şifre hatalı.';
        } elseif (strlen($yeni) < 8) {
            $hata = 'Yeni şifre en az 8 karakter olmalı.';
        } else {
            kullanici_guncelle($kur['id'], $u['id'], ['hash' => password_hash($yeni, PASSWORD_DEFAULT)]);
            flash('Şifren değişti.');
            redirect('profil.php');
        }
    } else {
        $rumuz = preg_replace('/\s+/u', ' ', post('rumuz', 40));
        $tanitim = preg_replace('/\s+/u', ' ', post('tanitim', 200));
        if (text_len($rumuz) < 2) {
            $hata = 'Rumuz en az 2 karakter olmalı.';
        } else {
            $alan = ['rumuz' => $rumuz, 'imza' => ($_POST['imza'] ?? '') === 'ad' ? 'ad' : 'rumuz'];
            if ($tanitim === '') {
                $alan['tanitim'] = '';
                $alan['tanitim_bekleyen'] = '';
            } elseif ($tanitim !== ($u['tanitim'] ?? '')) {
                $alan['tanitim_bekleyen'] = $tanitim; // eğitmen onayından sonra görünür
            }
            kullanici_guncelle($kur['id'], $u['id'], $alan);
            if ($tanitim === '') {
                yazar_tanitim_yay($kur['id'], $u['id'], '');
            }
            if (isset($_SESSION['katilim'])) {
                $_SESSION['katilim']['rumuz'] = $rumuz;
            }
            flash(isset($alan['tanitim_bekleyen']) && $alan['tanitim_bekleyen'] !== '' ? 'Kaydedildi. Tanıtımın eğitmen onayından sonra görünecek.' : 'Kaydedildi.');
            redirect('profil.php');
        }
    }
}

page_head('Profilim');
?>
<div class="wrap narrow section stack" style="max-width:640px">
  <h1>Profilim</h1>
  <?php if ($hata): ?><p class="flash err" style="margin:0"><?= e($hata) ?></p><?php endif; ?>
  <form method="post" class="card stack">
    <?= csrf_field() ?>
    <div class="field"><label for="rumuz">Rumuz</label><input id="rumuz" name="rumuz" type="text" maxlength="40" required value="<?= e($u['rumuz']) ?>"></div>
    <fieldset class="card" style="padding:12px 16px;margin:0">
      <legend style="font-weight:600;font-size:16px;padding:0 4px">Haberlerimde görünsün</legend>
      <label class="check" style="min-height:40px"><input type="radio" name="imza" value="rumuz"<?= ($u['imza'] ?? 'rumuz') === 'rumuz' ? ' checked' : '' ?>> Rumuzum</label>
      <label class="check" style="min-height:40px"><input type="radio" name="imza" value="ad"<?= ($u['imza'] ?? '') === 'ad' ? ' checked' : '' ?>> Ad soyadım</label>
    </fieldset>
    <div class="field">
      <label for="tanitim">Yazar tanıtımı <span class="help">(isteğe bağlı, en fazla 200 karakter)</span></label>
      <textarea id="tanitim" name="tanitim" rows="3" maxlength="200" style="min-height:90px"><?= e(($u['tanitim_bekleyen'] ?? '') !== '' ? $u['tanitim_bekleyen'] : ($u['tanitim'] ?? '')) ?></textarea>
      <span class="help">Haberlerinin altında görünür ve kur bittikten sonra da kalır. Rumuzla yazıyorsan kimliğini belli edecek bilgi verme.</span>
      <?php if (($u['tanitim_bekleyen'] ?? '') !== ''): ?><span class="tag tag-gold">Eğitmen onayı bekliyor</span><?php endif; ?>
    </div>
    <button class="btn btn-primary" type="submit" style="align-self:flex-start">Kaydet</button>
  </form>

  <form method="post" class="card stack">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="sifre">
    <h2 style="font-size:22px">Şifre</h2>
    <div class="field"><label for="eski">Mevcut şifre</label><input id="eski" name="eski" type="password" autocomplete="current-password" required></div>
    <div class="field"><label for="yeni">Yeni şifre</label><input id="yeni" name="yeni" type="password" minlength="8" autocomplete="new-password" required><span class="help">En az 8 karakter</span></div>
    <button class="btn" type="submit" style="align-self:flex-start">Şifreyi değiştir</button>
  </form>
  <p class="small muted"><?= e($u['ad']) ?> · <?= e($u['eposta']) ?></p>
</div>
<?php page_foot();
