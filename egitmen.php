<?php
/* Eğitmen paneli */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_admin();
require __DIR__ . '/inc/panel_icerik.php';
bakim();

$kur = kur_aktif();
$kid = $kur['id'] ?? null;

/* ---------- Anlık soru sonuçları: CSV (Excel'de doğrudan açılır) ---------- */
if (($_GET['csv'] ?? '') === 'sorular') {
    $ck = kur_bul((string)($_GET['kur'] ?? '')) ?: $kur;
    if (!$ck) {
        redirect('egitmen.php');
    }
    $ad = preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $ck['ad']) ?: 'kur'));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="soru-sonuclari-' . trim($ad, '-') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Kur', 'Tarih', 'Hafta', 'Soru', 'Seçenek', 'Cevap sayısı', 'Yüzde', 'Toplam cevap'], ';');
    foreach (json_read(soru_gecmisi_path($ck['id']), []) as $r) {
        foreach ($r['secenekler'] as $i => $sec) {
            $n = (int)($r['sayilar'][$i] ?? 0);
            fputcsv($out, [$ck['ad'], date('d.m.Y H:i', strtotime($r['tarih'])), $r['hafta'], $r['metin'], $sec, $n, $r['yanit'] ? round(100 * $n / $r['yanit']) : 0, $r['yanit']], ';');
        }
    }
    fclose($out);
    exit;
}

/* ---------- Öğrenci listesi ve katılım: CSV ---------- */
if (($_GET['csv'] ?? '') === 'ogrenciler' && $kur) {
    $yk = yoklama($kid);
    $teslim = [];
    foreach (haberler(function ($h) use ($kid) {
        return $h['kur'] === $kid && $h['durum'] !== 'taslak';
    }) as $g) {
        $teslim[$g['yazar_id'] ?? ''][(int)$g['hafta']] = true;
        if ($g['durum'] === 'yayinda') {
            $teslim[$g['yazar_id'] ?? '']['yayin'] = ($teslim[$g['yazar_id'] ?? '']['yayin'] ?? 0) + 1;
        }
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ogrenciler-katilim-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $bas = ['Ad soyad', 'Rumuz', 'E-posta', 'Durum'];
    for ($i = 1; $i <= 8; $i++) {
        $bas[] = 'Hafta ' . $i . ' katılım';
    }
    fputcsv($out, array_merge($bas, ['Katılım (ders)', 'Ödev teslim (hafta)', 'Yayımlanan haber']), ';');
    foreach (kullanicilar($kid) as $u) {
        if (!in_array($u['durum'], ['onayli', 'kapali'], true)) {
            continue;
        }
        $satir = [$u['ad'], $u['rumuz'], $u['eposta'], $u['durum'] === 'onayli' ? 'Onaylı' : 'Kapalı'];
        $top = 0;
        for ($i = 1; $i <= 8; $i++) {
            $var = isset($yk[(string)$i][$u['id']]);
            $top += $var ? 1 : 0;
            $satir[] = empty($kur['ders_tarihleri'][$i]) ? '' : ($var ? 'Var' : 'Yok');
        }
        $odev = count(array_filter(array_keys($teslim[$u['id']] ?? []), 'is_int'));
        fputcsv($out, array_merge($satir, [$top . ' / ' . count((array)($kur['ders_tarihleri'] ?? [])), $odev, (int)($teslim[$u['id']]['yayin'] ?? 0)]), ';');
    }
    fclose($out);
    exit;
}

/* Sıradaki incelenecek gönderi (en eski önce) */
function siradaki_gonderi($kid, $haric = null)
{
    $l = haberler(function ($h) use ($kid, $haric) {
        return $h['kur'] === $kid && $h['durum'] === 'bekliyor' && $h['id'] !== $haric;
    });
    usort($l, function ($a, $b) {
        return strcmp($a['guncelleme'] ?? $a['tarih'], $b['guncelleme'] ?? $b['tarih']);
    });
    return $l[0] ?? null;
}

/* ---------- Yedek: tüm veriler tek zip ---------- */
if (isset($_GET['yedek'])) {
    if (!class_exists('ZipArchive')) {
        flash('Sunucuda zip desteği yok. Yedeği Hostinger dosya yöneticisinden storage ve uploads klasörlerini indirerek alın.', 'err');
        redirect('egitmen.php');
    }
    @set_time_limit(300);
    $tmp = tempnam(sys_get_temp_dir(), 'wdy');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    foreach (['storage', 'uploads'] as $kok) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT . '/' . $kok, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $dosya) {
            if ($dosya->isFile()) {
                $zip->addFile($dosya->getPathname(), $kok . '/' . substr($dosya->getPathname(), strlen(ROOT . '/' . $kok) + 1));
            }
        }
    }
    $zip->close();
    session_write_close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="anlatir-yedek-' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

/* ---------- İşlemler ---------- */
if (is_post()) {
    csrf_check();
    $islem = (string)($_POST['islem'] ?? '');
    $uid = (string)($_POST['uid'] ?? '');
    $hid = (string)($_POST['id'] ?? '');
    $git = 'egitmen.php';
    if (($icerikGit = icerik_islem($islem, $kid)) !== null) {
        redirect($icerikGit);
    }

    switch ($islem) {
        case 'kur_ac':
            if ($kur) {
                break;
            }
            $ad = post('ad', 80);
            if (text_len($ad) < 3) {
                flash('Kura bir ad verin (ör. "Arnavutköy · Ekim 2026").', 'err');
                break;
            }
            json_update(kurlar_path(), function (&$d) use ($ad) {
                $id = new_id();
                $d['liste'][] = ['id' => $id, 'ad' => $ad, 'kod' => kur_kodu_uret(), 'durum' => 'aktif', 'acilis' => date('c'), 'hafta' => 1, 'kayit_acik' => true];
                $d['aktif'] = $id;
            }, ['aktif' => null, 'liste' => []]);
            flash('Yeni kur açıldı. Öğrenciler kayıt olur, siz onaylarsınız; derste ekrandaki QR ile katılırlar.');
            break;

        case 'kod_yenile':
            kur_guncelle($kid, ['kod' => kur_kodu_uret()]);
            flash('Kur kodu yenilendi.');
            $git = 'egitmen.php?sekme=ayarlar#kur';
            break;

        case 'kayit_ac':
        case 'kayit_kapat':
            kur_guncelle($kid, ['kayit_acik' => $islem === 'kayit_ac', 'kayit_elle' => true]);
            flash($islem === 'kayit_ac' ? 'Kayıt formu açıldı.' : 'Kayıt formu kapatıldı.');
            $git = 'egitmen.php?sekme=katilimcilar#kayit';
            break;

        case 'hafta':
            kur_guncelle($kid, ['hafta' => max(1, min(8, (int)($_POST['hafta'] ?? 1)))]);
            flash('Açık hafta güncellendi.');
            $git = 'egitmen.php?sekme=ayarlar#kur';
            break;

        case 'kayit_onay':
        case 'kayit_red':
            kullanici_guncelle($kid, $uid, ['durum' => $islem === 'kayit_onay' ? 'onayli' : 'red']);
            $git = 'egitmen.php?sekme=katilimcilar#kayit';
            break;

        case 'kayit_tumu':
            json_update(kullanicilar_path($kid), function (&$list) {
                foreach ($list as &$u) {
                    if ($u['durum'] === 'bekliyor') {
                        $u['durum'] = 'onayli';
                    }
                }
            });
            flash('Bekleyen kayıtların tümü onaylandı.');
            $git = 'egitmen.php?sekme=katilimcilar#kayit';
            break;

        case 'sifre':
            $yeni = substr(str_replace(['0', 'O', 'l', 'I', '1'], '', bin2hex(random_bytes(8))), 0, 8);
            kullanici_guncelle($kid, $uid, ['hash' => password_hash($yeni, PASSWORD_DEFAULT)]);
            $u = kullanici_bul($kid, $uid);
            flash(($u['ad'] ?? '') . ' için yeni şifre: ' . $yeni . ' (öğrenciye iletin; bu mesaj bir kez görünür)', 'warn');
            $git = 'egitmen.php?sekme=katilimcilar#katilimcilar';
            break;

        case 'rumuz':
            $r = post('rumuz', 40);
            if (text_len($r) >= 2) {
                kullanici_guncelle($kid, $uid, ['rumuz' => $r]);
            }
            $git = 'egitmen.php?sekme=katilimcilar#katilimcilar';
            break;

        case 'tanitim_onay':
        case 'tanitim_red':
            $u = kullanici_bul($kid, $uid);
            if ($u && ($u['tanitim_bekleyen'] ?? '') !== '') {
                if ($islem === 'tanitim_onay') {
                    kullanici_guncelle($kid, $uid, ['tanitim' => $u['tanitim_bekleyen'], 'tanitim_bekleyen' => '']);
                    yazar_tanitim_yay($kid, $uid, $u['tanitim_bekleyen']);
                } else {
                    kullanici_guncelle($kid, $uid, ['tanitim_bekleyen' => '']);
                }
            }
            $git = 'egitmen.php?sekme=katilimcilar#kayit';
            break;

        case 'hesap_kapat':
        case 'hesap_ac':
            kullanici_guncelle($kid, $uid, ['durum' => $islem === 'hesap_kapat' ? 'kapali' : 'onayli']);
            $git = 'egitmen.php?sekme=katilimcilar#katilimcilar';
            break;

        case 'yayinla':
        case 'duzeltme':
        case 'red':
        case 'goruldu':
        case 'geri_al':
            $h = haber_yukle($hid);
            if ($h && ($h['durum'] ?? '') === 'yayinda' && in_array($islem, ['duzeltme', 'red', 'goruldu', 'yayinla'], true)) {
                flash('Yayımlanmış haberde önce "Yayından kaldır" deyin.', 'err');
                $git = 'egitmen.php?haber=' . $hid;
                break;
            }
            if ($h && ($h['tur'] ?? '') !== 'ornek') {
                $h['not'] = post('not', 3000);
                $map = ['yayinla' => 'yayinda', 'duzeltme' => 'duzeltme', 'red' => 'red', 'goruldu' => 'goruldu', 'geri_al' => 'bekliyor'];
                $h['durum'] = $map[$islem];
                // öğrenciye bildirim: not yazıldıysa ya da karar onu ilgilendiriyorsa (Ödevim açılınca silinir)
                $h['bildirim'] = trim($h['not']) !== '' || in_array($islem, ['yayinla', 'duzeltme', 'red'], true);
                if ($islem === 'yayinla') {
                    if (empty($h['onay']['lisans'])) {
                        flash('Bu haberde kullanım izni onayı yok; yayımlanamaz.', 'err');
                        $git = 'egitmen.php?haber=' . $hid;
                        break;
                    }
                    $h['yayin_tarihi'] = $h['yayin_tarihi'] ?? date('c');
                    // Yazarın güncel imza tercihi
                    $u = kullanici_bul($h['kur'], $h['yazar_id']);
                    if ($u) {
                        $h['yazar'] = gorunen_ad($u);
                        $h['yazar_tanitim'] = $u['tanitim'] ?? '';
                    }
                }
                if ($islem === 'geri_al') {
                    unset($h['yayin_tarihi']);
                    $h['finalist'] = false;
                    json_update(finalist_path($h['kur']), function (&$f) use ($hid) {
                        $f = array_values(array_diff($f, [$hid]));
                    });
                }
                haber_kaydet($h);
                $msg = ['yayinla' => 'Haber yayımlandı.', 'duzeltme' => 'Düzeltme notu öğrenciye iletildi.', 'red' => 'Gönderi "yayımlanmayacak" olarak işaretlendi.', 'goruldu' => 'Ödev görüldü olarak işaretlendi.', 'geri_al' => 'Haber yayından kaldırıldı.'];
                flash($msg[$islem]);
                if (in_array($islem, ['yayinla', 'duzeltme', 'red', 'goruldu'], true) && ($sonraki = siradaki_gonderi($h['kur'], $hid))) {
                    $git = 'egitmen.php?haber=' . $sonraki['id'];
                    break;
                }
            }
            $git = 'egitmen.php?sekme=gonderiler#haberler';
            break;

        case 'goruldu_tumu':
            // inceleme gerektirmeyen yazılı ödevler tek seferde kapatılır
            $n = 0;
            foreach (haberler(function ($x) use ($kid) {
                return $x['kur'] === $kid && $x['durum'] === 'bekliyor' && ($x['tur'] ?? '') === 'odev';
            }) as $x) {
                $x['durum'] = 'goruldu';
                haber_kaydet($x);
                $n++;
            }
            flash($n . ' yazılı ödev "Görüldü" olarak işaretlendi.');
            $git = 'egitmen.php?sekme=gonderiler#haberler';
            break;

        case 'finalist':
            $h = haber_yukle($hid);
            if ($h && $h['kur'] === $kid) {
                $on = !empty($_POST['on']);
                if ($on && !finalist_olabilir($h)) {
                    flash('Yalnızca tüm bilgileri kaynaklı ve onayları tam yayımlanmış haberler finalist olabilir.', 'err');
                } elseif ($on && kazandi_mi($kid, $h['yazar_id'])) {
                    flash('Bu katılımcı bu kurda zaten bir kez kazandı.', 'err');
                } else {
                    $cl = canli($kid);
                    $gecerli = finalistler($kid); // kilit almadan önce okunur
                    $acikOy = $cl['oylama'] && in_array(oylama($kid, $cl['oylama'])['durum'], ['acik', 'esitlik'], true);
                    $sonuc = $acikOy ? 'Oylama sürerken finalistler değiştirilemez.' : json_update(finalist_path($kid), function (&$f) use ($hid, $on, $gecerli) {
                        $f = array_values(array_intersect($f, $gecerli));
                        $f = array_values(array_diff($f, [$hid]));
                        if ($on) {
                            if (count($f) >= 3) {
                                return 'En fazla 3 finalist seçilebilir.';
                            }
                            $f[] = $hid;
                        }
                        return 'ok';
                    });
                    if ($sonuc === 'ok') {
                        $h['finalist'] = $on;
                        haber_kaydet($h);
                    } else {
                        flash($sonuc, 'err');
                    }
                }
            }
            $git = 'egitmen.php?sekme=gonderiler#haberler';
            break;

        case 'sunum_yukle':
            $hafta = max(1, min(8, (int)($_POST['hafta'] ?? 0)));
            $f = $_FILES['pdf'] ?? null;
            $max = (int)cfg('max_slides_mb', 40);
            if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                flash('Dosya yüklenemedi' . (($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? ': sunucunun yükleme sınırı aşıldı.' : '.'), 'err');
            } elseif ($f['size'] > $max * 1048576) {
                flash('PDF ' . $max . ' MB sınırını aşıyor.', 'err');
            } elseif (file_get_contents($f['tmp_name'], false, null, 0, 5) !== '%PDF-') {
                flash('Dosya PDF değil. Sunumu PowerPoint\'te "Farklı kaydet › PDF" ile kaydedin.', 'err');
            } else {
                ensure_dir(dirname(sunum_path($hafta)));
                move_uploaded_file($f['tmp_name'], sunum_path($hafta));
                flash('Hafta ' . $hafta . ' sunumu yüklendi.');
            }
            $git = 'egitmen.php?sekme=icerik&b=hafta&h=' . $hafta . '#sunum';
            break;

        case 'sunum_sil':
            @unlink(sunum_path((int)($_POST['hafta'] ?? 0)));
            flash('Sunum silindi.');
            $git = 'egitmen.php?sekme=icerik&b=hafta&h=' . (int)($_POST['hafta'] ?? 1) . '#sunum';
            break;

        case 'gorsel_yukle':
        case 'gorsel_onceki':
        case 'gorsel_varsayilan':
            $gad = (string)($_POST['ad'] ?? '');
            $git = icerik_url('gorseller', [], 'gorseller');
            if (!isset(site_gorselleri()[$gad])) {
                break;
            }
            try {
                if ($islem === 'gorsel_yukle') {
                    site_gorsel_degistir($gad, $_FILES['foto'] ?? []);
                    flash(site_gorselleri()[$gad][0] . ' değiştirildi. Eskisine dönmek için "Öncekine dön" deyin.');
                } elseif ($islem === 'gorsel_onceki') {
                    flash(site_gorsel_onceki($gad) ? 'Önceki görsele dönüldü.' : 'Önceki görsel yok.');
                } else {
                    site_gorsel_varsayilan($gad);
                    flash('Varsayılan görsele dönüldü.');
                }
            } catch (RuntimeException $ex) {
                flash($ex->getMessage(), 'err');
            }
            break;

        case 'medya_yukle':
            $git = icerik_url('gorseller', [], 'medya');
            try {
                medya_yukle($_FILES['foto'] ?? [], post('ad', 60));
                flash('Fotoğraf yüklendi. Bağlantısını aşağıdan kopyalayabilirsiniz.');
            } catch (RuntimeException $ex) {
                flash($ex->getMessage(), 'err');
            }
            break;

        case 'medya_sil':
            medya_sil((string)($_POST['id'] ?? ''));
            flash('Fotoğraf silindi.');
            $git = icerik_url('gorseller', [], 'medya');
            break;

        case 'yoklama_ac':
        case 'yoklama_kapat':
            $hf = max(1, min(8, (int)($_POST['hafta'] ?? 0)));
            if (kullanici_bul($kid, $uid)) {
                if ($islem === 'yoklama_ac') {
                    yoklama_isle($kid, $hf, [$uid => date('c')], 'elle');
                } else {
                    yoklama_sil($kid, $hf, $uid);
                }
            }
            $git = 'egitmen.php?sekme=katilimcilar#ogrenciler';
            break;

        case 'egitmen_notu':
            kullanici_guncelle($kid, $uid, ['egitmen_notu' => post('not', 2000)]);
            flash('Not kaydedildi.');
            $git = 'egitmen.php?sekme=katilimcilar';
            break;

        case 'ogrenci_ekle':
            $ad = post('ad', 80);
            $ep = tr_lower(post('eposta', 200));
            $rz = post('rumuz', 40);
            $git = 'egitmen.php?sekme=katilimcilar#ogrenci-ekle';
            if (text_len($ad) < 3 || !filter_var($ep, FILTER_VALIDATE_EMAIL)) {
                flash('Ad soyad ve geçerli bir e-posta yazın.', 'err');
                break;
            }
            $yeni = substr(str_replace(['0', 'O', 'l', 'I', '1'], '', bin2hex(random_bytes(8))), 0, 8);
            $sonuc = json_update(kullanicilar_path($kid), function (&$list) use ($ad, $ep, $rz, $yeni) {
                foreach ($list as $u) {
                    if ($u['eposta'] === $ep) {
                        return 'var';
                    }
                }
                $list[] = ['id' => new_id(), 'ad' => $ad, 'rumuz' => text_len($rz) >= 2 ? $rz : $ad, 'imza' => 'rumuz', 'eposta' => $ep,
                    'hash' => password_hash($yeni, PASSWORD_DEFAULT), 'durum' => 'onayli', 'tarih' => date('c'), 'elle' => true];
                return 'ok';
            });
            if ($sonuc === 'var') {
                flash('Bu e-postayla kayıtlı bir öğrenci zaten var.', 'err');
            } else {
                flash($ad . ' eklendi. Giriş: ' . $ep . ' · şifre: ' . $yeni . ' (öğrenciye iletin; bu mesaj bir kez görünür)', 'warn');
                $git = 'egitmen.php?sekme=katilimcilar#ogrenciler';
            }
            break;

        case 'ders_kodu_yenile':
            canli_guncelle($kid, function (&$d) {
                $d['ders_kodu'] = kur_kodu_uret();
            });
            flash('Yeni ders kodu üretildi. Eski kod artık geçersiz.');
            $git = 'egitmen.php?sekme=ayarlar#kur';
            break;

        case 'kur_kapat':
            if (trim((string)($_POST['onay'] ?? '')) !== 'KAPAT') {
                flash('Kuru kapatmak için kutuya KAPAT yazın.', 'err');
                break;
            }
            $istat = [
                'katilimci' => count(array_filter(kullanicilar($kid), function ($u) {
                    return $u['durum'] === 'onayli';
                })),
                'gonderi' => count(haberler(function ($h) use ($kid) {
                    return $h['kur'] === $kid && $h['durum'] !== 'taslak';
                })),
            ];
            oylama_iptal($kid, canli($kid)['oylama']);
            json_update(kurlar_path(), function (&$d) use ($kid, $istat) {
                foreach ($d['liste'] as &$k) {
                    if ($k['id'] === $kid) {
                        $k['istatistik'] = $istat;
                        $k['durum'] = 'arsiv';
                        $k['kapanis'] = date('c');
                        $k['kayit_acik'] = false;
                    }
                }
                $d['aktif'] = null;
            });
            json_update(kullanicilar_path($kid), function (&$list) {
                foreach ($list as &$u) {
                    if ($u['durum'] === 'onayli') {
                        $u['durum'] = 'kapali';
                    }
                }
            });
            @unlink(canli_path($kid));
            flash('Kur kapatıldı ve arşivlendi. Yayımlanan haberler arşivde kalır; hesaplar ve yayımlanmamış gönderiler ' . (int)cfg('saklama_gun', 60) . ' gün sonra silinir.');
            break;
    }
    if (!empty($_POST['kart']) && $uid !== '' && strpos($git, 'sekme=katilimcilar') !== false) {
        $git = 'egitmen.php?sekme=katilimcilar&ogr=' . rawurlencode($uid);
    }
    redirect($git);
}

/* ---------- Haber inceleme görünümü ---------- */
if (!empty($_GET['haber'])) {
    $h = haber_yukle($_GET['haber']);
    if (!$h || ($h['tur'] ?? '') === 'ornek') {
        redirect('egitmen.php');
    }
    $u = kullanici_bul($h['kur'], $h['yazar_id']);
    $durumAd = ['kaynakli' => ['Kaynaklı', 'ok'], 'kaynak_yok' => ['Kaynak yok', 'bad'], 'yorum' => ['Yorum', 'warn']];
    page_head('İnceleme');
    ?>
<div class="wrap narrow section stack">
  <?php $sonraki = siradaki_gonderi($h['kur'], $h['id']); ?>
  <div class="row between">
    <p class="crumbs"><a href="egitmen.php?sekme=gonderiler#haberler">Eğitmen paneli</a> / İnceleme</p>
    <?php if ($sonraki): ?><a class="btn btn-sm" href="egitmen.php?haber=<?= e($sonraki['id']) ?>">Sonraki gönderi →</a><?php endif; ?>
  </div>
  <div class="card stack">
    <div class="kv">
      <span class="muted">Yazar</span><span><?= e($u['ad'] ?? '—') ?> · <?= e($u['rumuz'] ?? '') ?> · <?= e($u['eposta'] ?? '') ?></span>
      <span class="muted">İmza</span><span><?= e($h['yazar']) ?></span>
      <?php if ($h['tur'] === 'haber'): ?><span class="muted">Konu · yer</span><span><?= e(konu_adi($h['konu'] ?? '') ?: '—') ?> · <?= e(($h['yer'] ?? '') ?: '—') ?></span><?php endif; ?>
      <span class="muted">Tür</span><span><?= $h['tur'] === 'odev' ? 'Ödev' : 'Haber' ?> · Hafta <?= (int)$h['hafta'] ?> · <?= e(tr_date($h['guncelleme'] ?? $h['tarih'], true)) ?></span>
      <?php if ($h['tur'] === 'haber'): ?>
      <span class="muted">Onaylar</span><span>
        <span class="tag <?= !empty($h['onay']['lisans']) ? 'tag-ok' : 'tag-bad' ?>">Kullanım izni</span>
        <?php if (!empty($h['foto'])): ?><span class="tag <?= !empty($h['onay']['foto']) ? 'tag-ok' : 'tag-bad' ?>">Fotoğraf izni</span><?php endif; ?>
        <span class="tag <?= !empty($h['onay']['ornek']) ? 'tag-ok' : '' ?>">Örnek kullanım <?= !empty($h['onay']['ornek']) ? 'var' : 'yok' ?></span>
      </span>
      <span class="muted">Yapay zekâ</span><span><?= empty($h['yz']) ? '—' : ($h['yz']['kullandi'] ? 'Kullandığını beyan etti: ' . e($h['yz']['aciklama']) : 'Kullanmadığını beyan etti') ?></span>
      <?php endif; ?>
    </div>
  </div>
  <article class="card stack">
    <?php if ($h['baslik'] !== ''): ?><h1 style="font-size:32px"><?= e($h['baslik']) ?></h1><?php endif; ?>
    <?php if (($h['spot'] ?? '') !== ''): ?><p class="spot" style="font-weight:500"><?= e($h['spot']) ?></p><?php endif; ?>
    <?php if ($f = foto_url($h)): ?><figure style="margin:0"><img src="<?= e($f) ?>" alt="<?= e($h['alt'] ?? '') ?>" style="border-radius:8px"><figcaption><?= e($h['altyazi'] ?? '') ?></figcaption></figure><?php endif; ?>
    <div class="body-text" style="font-size:19px"><?= paragraphs($h['metin']) ?></div>
    <?php if (!empty($h['kaynaklar'])): ?>
      <h2 style="font-size:22px">Kaynaklar</h2>
      <ol class="src-list"><?php foreach ($h['kaynaklar'] as $i => $k): ?><li><span class="n"><?= $i + 1 ?></span><span><strong><?= e($k['bilgi'] ?: '—') ?></strong><br><span class="<?= $k['kaynak'] === '' ? 'err-text' : 'muted' ?>"><?= e($k['kaynak'] ?: 'Kaynak yazılmamış') ?></span></span></li><?php endforeach; ?></ol>
    <?php endif; ?>
    <?php if (!empty($h['dipnot']['cumleler'])): ?>
      <h2 style="font-size:22px">Otomatik dipnot kontrolü</h2>
      <p class="muted"><?= e($h['dipnot']['ozet']) ?></p>
      <ul class="dipnot-list"><?php foreach ($h['dipnot']['cumleler'] as $c): [$dl, $dc] = $durumAd[$c['durum']] ?? ['', 'none']; ?>
        <li><span class="chip <?= $dc ?>" style="align-self:flex-start"><?= $dl ?><?= $c['kaynak_no'] ? ' · ' . (int)$c['kaynak_no'] : '' ?></span><span class="stmt"><?= e($c['metin']) ?></span><?php if ($c['soru'] !== ''): ?><span class="why">Soru: <?= e($c['soru']) ?></span><?php endif; ?></li>
      <?php endforeach; ?></ul>
    <?php elseif ($h['tur'] === 'haber' && ai_acik()): ?>
      <p class="help">Otomatik dipnot kontrolü yapılamadı (API anahtarı ya da bağlantı). Kaynakları elle kontrol edin.</p>
    <?php endif; ?>
  </article>
  <form method="post" class="card stack">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($h['id']) ?>">
    <div class="field"><label for="not">Öğrenciye not</label><textarea id="not" name="not" rows="4"><?= e($h['not'] ?? '') ?></textarea></div>
    <div class="row hazir-not" style="gap:6px">
      <?php foreach (hazir_notlar() as $nt): ?><button type="button" class="chip-btn" style="font-size:14px;min-height:36px;padding:6px 12px" data-not="<?= e($nt) ?>">+ <?= e($nt) ?></button><?php endforeach; ?>
    </div>
    <script>
    document.querySelectorAll("[data-not]").forEach(function (b) {
      b.addEventListener("click", function () {
        var t = document.getElementById("not");
        t.value = (t.value.trim() ? t.value.trim() + "\n" : "") + b.getAttribute("data-not");
        t.focus();
      });
    });
    </script>
    <div class="row">
      <?php if ($h['tur'] === 'haber' && $h['durum'] !== 'yayinda'): ?><button class="btn btn-primary" name="islem" value="yayinla">Yayımla</button><?php endif; ?>
      <?php if ($h['tur'] === 'odev' && $h['durum'] !== 'goruldu'): ?><button class="btn btn-primary" name="islem" value="goruldu">Görüldü</button><?php endif; ?>
      <?php if ($h['durum'] !== 'yayinda'): ?><button class="btn" name="islem" value="duzeltme">Düzeltme iste</button><?php endif; ?>
      <?php if ($h['durum'] === 'yayinda'): ?><button class="btn btn-danger" name="islem" value="geri_al">Yayından kaldır</button>
      <?php elseif ($h['tur'] === 'haber'): ?><button class="btn btn-danger" name="islem" value="red">Yayımlanmayacak</button><?php endif; ?>
    </div>
  </form>
</div>
<?php
    page_foot();
    exit;
}

/* ---------- Ana panel ---------- */
page_head('Eğitmen paneli');

if (!$kur):
    $arsiv = array_reverse(array_filter(kurlar()['liste'], function ($k) {
        return ($k['durum'] ?? '') === 'arsiv';
    }));
    ?>
<div class="wrap narrow section stack">
  <h1>Eğitmen paneli</h1>
  <form method="post" class="card stack">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="kur_ac">
    <h2>Yeni kur aç</h2>
    <p class="muted">Yeni kur boş başlar; ders içerikleri, örnek haberler ve sunumlar aynen kullanılır.</p>
    <div class="field"><label for="ad">Kur adı</label><input id="ad" name="ad" type="text" placeholder="Ör. Arnavutköy · Ekim 2026" required></div>
    <button class="btn btn-primary" type="submit" style="align-self:flex-start">Kuru aç</button>
  </form>
  <?php if ($arsiv): ?>
  <section class="card stack">
    <h2>Tamamlanan kurlar</h2>
    <p class="muted"><?= count($arsiv) ?> tamamlanmış kur. Haberlerine, katılımcı listelerine ve silme seçeneğine Kurlar sayfasından ulaşabilirsiniz.</p>
    <a class="btn" href="arsiv.php" style="align-self:flex-start">Kurlar →</a>
  </section>
  <?php endif; ?>
  <section class="stack" style="gap:12px">
    <h2 style="margin:0">İçerik</h2>
    <p class="help" style="margin:0">Kur açmadan da haftaların içeriğini, sunumları ve görselleri hazırlayabilirsiniz.</p>
    <?php icerik_paneli(null, null, 'hepsi'); ?>
  </section>
</div>
<?php
    page_foot(['assets/kopyala.js', 'assets/panel.js']);
    exit;
endif;

$kullanicilar = kullanicilar($kid);
$bekleyenKayit = array_values(array_filter($kullanicilar, function ($u) {
    return $u['durum'] === 'bekliyor';
}));
$gonderiler = haberler(function ($h) use ($kid) {
    return $h['kur'] === $kid && $h['durum'] !== 'taslak'; // taslakları yalnızca öğrenci görür
});
$bekleyen = array_values(array_filter($gonderiler, function ($h) {
    return $h['durum'] === 'bekliyor';
}));
usort($bekleyen, function ($a, $b) {
    return strcmp($a['guncelleme'] ?? $a['tarih'], $b['guncelleme'] ?? $b['tarih']);
});
$yayinda = array_values(array_filter($gonderiler, function ($h) {
    return $h['durum'] === 'yayinda';
}));
$digerleri = array_values(array_filter($gonderiler, function ($h) {
    return in_array($h['durum'], ['duzeltme', 'goruldu', 'red'], true);
}));
$c = canli($kid);
$hafta = kur_hafta();
$havuz = finalistler($kid);
$adlar = [];
foreach ($kullanicilar as $u) {
    $adlar[$u['id']] = $u['ad'];
}

function gonderi_satiri($h, $adlar, $finalist = false)
{
    $ks = kaynaksiz_sayisi($h);
    ?>
    <li style="padding:14px 0;border-bottom:1px solid var(--line-2);display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px">
      <span class="stack" style="gap:2px">
        <a href="egitmen.php?haber=<?= e($h['id']) ?>" style="font-family:var(--serif);font-size:20px;font-weight:600;text-decoration:none"><?= e($h['baslik'] ?: mb_strimwidth($h['metin'], 0, 60, '…', 'UTF-8')) ?></a>
        <span class="meta"><?= e($adlar[$h['yazar_id']] ?? $h['yazar']) ?> · Hafta <?= (int)$h['hafta'] ?> · <?= $h['tur'] === 'odev' ? 'ödev' : 'haber' ?><?= !empty($h['foto']) ? ' · fotoğraflı' : '' ?><?= gec_mi(kur_aktif(), $h) ? ' · <span class="err-text">geç teslim</span>' : '' ?></span>
        <?php if ($h['tur'] === 'haber'): ?>
        <span class="row" style="gap:6px">
          <?php if ($ks === 0 && kaynak_sayisi($h) > 0): ?><span class="tag tag-ok">Tüm bilgiler kaynaklı</span><?php else: ?><span class="tag tag-bad"><?= $ks ?: 'Hiç' ?> bilgi kaynaksız</span><?php endif; ?>
          <?php if (!empty($h['onay']['lisans']) && (empty($h['foto']) || !empty($h['onay']['foto']))): ?><span class="tag tag-ok">Onaylar tamam</span><?php else: ?><span class="tag tag-bad">Onay eksik</span><?php endif; ?>
          <?php if (!empty($h['kazanan'])): ?><span class="tag tag-gold">Haftanın haberi</span><?php endif; ?>
        </span>
        <?php endif; ?>
      </span>
      <span class="row" style="gap:8px">
        <?php if ($finalist): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="islem" value="finalist"><input type="hidden" name="id" value="<?= e($h['id']) ?>"><input type="hidden" name="on" value="<?= !empty($h['finalist']) ? '' : '1' ?>">
            <button class="btn btn-sm" type="submit"<?= finalist_olabilir($h) || !empty($h['finalist']) ? '' : ' disabled title="Finalist olamaz: kaynaksız bilgi ya da eksik onay"' ?>><?= !empty($h['finalist']) ? '★ Finalist' : '☆ Finalist yap' ?></button></form>
        <?php endif; ?>
        <a class="btn btn-sm" href="egitmen.php?haber=<?= e($h['id']) ?>">İncele</a>
      </span>
    </li>
<?php
}

function gorseller_karti()
{
    $maxMb = 15;
    $medya = medya_listesi();
    ?>
  <section class="card stack" id="gorseller">
    <h2>Sitenin görselleri</h2>
    <p class="help">Yüklediğiniz fotoğraf doğru yöne çevrilir, konum bilgisi silinir, alanın ölçüsüne ortadan kırpılır ve sıkıştırılır. JPG, PNG ya da WEBP, en fazla <?= $maxMb ?> MB.</p>
    <div class="gorsel-grid">
      <?php foreach (site_gorselleri() as $ad => [$etiket, $w, $h]): $ozel = is_file(site_gorsel_dosya($ad)); $onceki = is_file(UPLOADS . '/site/' . $ad . '.onceki.jpg'); ?>
        <div class="gorsel-kart">
          <img src="<?= e(site_gorsel($ad)) ?>" alt="" loading="lazy" style="aspect-ratio:<?= $w ?> / <?= $h ?>">
          <strong><?= e($etiket) ?></strong>
          <span class="help"><?= $w ?> × <?= $h ?> · <?= $ozel ? 'sizin yüklediğiniz' : 'varsayılan' ?></span>
          <form method="post" enctype="multipart/form-data" class="row" style="gap:6px"><?= csrf_field() ?><input type="hidden" name="islem" value="gorsel_yukle"><input type="hidden" name="ad" value="<?= e($ad) ?>">
            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required aria-label="<?= e($etiket) ?> için fotoğraf"><button class="btn btn-sm btn-primary" type="submit">Değiştir</button></form>
          <?php if ($onceki || $ozel): ?>
          <div class="row" style="gap:6px">
            <?php if ($onceki): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="ad" value="<?= e($ad) ?>"><button class="btn btn-sm" name="islem" value="gorsel_onceki">Öncekine dön</button></form><?php endif; ?>
            <?php if ($ozel): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="ad" value="<?= e($ad) ?>"><button class="btn btn-sm" name="islem" value="gorsel_varsayilan">Varsayılana dön</button></form><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card stack" id="medya">
    <h2>Fotoğraf kutusu</h2>
    <p class="help">Duyuru, ders ya da örnek haber için. Her fotoğrafın üç boyu hazırlanır: büyük (1600 px), orta (960 px), küçük (480 px). Bağlantıyı kopyalayıp istediğiniz yerde kullanın.</p>
    <form method="post" enctype="multipart/form-data" class="row" style="gap:8px;align-items:flex-end"><?= csrf_field() ?><input type="hidden" name="islem" value="medya_yukle">
      <div class="field" style="flex:1 1 220px"><label for="medya-foto">Fotoğraf</label><input id="medya-foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp" required></div>
      <div class="field" style="flex:1 1 220px"><label for="medya-ad">Kısa ad <span class="help">(isteğe bağlı)</span></label><input id="medya-ad" type="text" name="ad" maxlength="60" placeholder="Ör. konser afişi"></div>
      <button class="btn btn-primary" type="submit">Yükle</button>
    </form>
    <?php if (!$medya): ?><p class="muted">Henüz fotoğraf yok.</p><?php else: ?>
    <div class="gorsel-grid">
      <?php foreach ($medya as $id => $t): ?>
        <div class="gorsel-kart">
          <img src="uploads/medya/<?= e($id) ?>-480.jpg" alt="" loading="lazy">
          <span class="help"><?= e($id) ?> · <?= e(tr_date(date('c', $t), true)) ?></span>
          <div class="row" style="gap:6px">
            <?php foreach (['Büyük' => 1600, 'Orta' => 960, 'Küçük' => 480] as $etiket => $px): ?>
              <button class="btn btn-sm" type="button" data-kopyala="<?= e(base_url() . '/uploads/medya/' . $id . '-' . $px . '.jpg') ?>"><?= $etiket ?></button>
            <?php endforeach; ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="islem" value="medya_sil"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="btn btn-sm btn-danger" type="submit" aria-label="<?= e($id) ?> fotoğrafını sil">Sil</button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="help">Düğmeler bağlantıyı panoya kopyalar.</p>
    <?php endif; ?>
  </section>
<?php
}

function sunumlar_karti()
{
    ?>
  <section class="card stack" id="sunumlar">
    <h2>Sunumlar</h2>
    <p class="help">PDF olarak yükleyin. Bir kez yüklenir, sonraki kurlarda da kullanılır. Yalnızca siz ve canlı derse bağlı katılımcılar görebilir.</p>
    <ul class="list-plain">
      <?php foreach (weeks() as $n => $w): $var = is_file(sunum_path($n)); ?>
        <li style="padding:10px 0;border-bottom:1px solid var(--line-2);display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:center">
          <span><strong>Hafta <?= $n ?></strong> <span class="help"><?= $var ? round(filesize(sunum_path($n)) / 1048576, 1) . ' MB' : 'yüklenmedi' ?></span></span>
          <span class="row" style="gap:6px">
            <form method="post" enctype="multipart/form-data" class="row" style="gap:6px"><?= csrf_field() ?><input type="hidden" name="islem" value="sunum_yukle"><input type="hidden" name="hafta" value="<?= $n ?>">
              <input type="file" name="pdf" accept="application/pdf" required style="max-width:200px;min-height:40px;padding:6px;font-size:14px"><button class="btn btn-sm" type="submit"><?= $var ? 'Değiştir' : 'Yükle' ?></button></form>
            <?php if ($var): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="islem" value="sunum_sil"><input type="hidden" name="hafta" value="<?= $n ?>"><button class="btn btn-sm btn-danger" type="submit" aria-label="Hafta <?= $n ?> sunumunu sil">Sil</button></form><?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php
}
?>
<?php
$sekmeler = ['bugun' => 'Bugün', 'gonderiler' => 'Gönderiler', 'katilimcilar' => 'Öğrenciler', 'icerik' => 'İçerik', 'ayarlar' => 'Ayarlar'];
$sekme = ($_GET['sekme'] ?? '') === 'kur' ? 'ayarlar' : ($_GET['sekme'] ?? '');
$sekme = isset($sekmeler[$sekme]) || $sekme === 'site' ? $sekme : 'bugun'; // site: sağ üstteki "Site ayarları" düğmesi
$onayliSayi = count(array_filter($kullanicilar, function ($u) {
    return $u['durum'] === 'onayli';
}));
$buHaftaGonderen = count(array_unique(array_map(function ($g) {
    return $g['yazar_id'] ?? '';
}, array_filter($gonderiler, function ($g) use ($hafta) {
    return (int)$g['hafta'] === $hafta;
}))));
$bekleyenOdev = array_values(array_filter($bekleyen, function ($h) {
    return ($h['tur'] ?? '') === 'odev';
}));
$bekleyenHaber = array_values(array_filter($bekleyen, function ($h) {
    return ($h['tur'] ?? '') !== 'odev';
}));
$bekleyenTanitimSayi = count(array_filter($kullanicilar, function ($u) {
    return ($u['tanitim_bekleyen'] ?? '') !== '' && $u['durum'] === 'onayli';
}));
$rozet = ['gonderiler' => count($bekleyen), 'katilimcilar' => count($bekleyenKayit) + $bekleyenTanitimSayi];
?>
<div class="wrap section stack">
  <div class="row between">
    <div><h1 class="row" style="gap:10px;align-items:center">Eğitmen paneli <a class="ayar-dugme<?= $sekme === 'site' ? ' acik' : '' ?>" href="egitmen.php?sekme=site" title="Sayfa düzeni: ana sayfa yazıları, duyuru, görseller, alt bilgi" aria-label="Sayfa düzeni"<?= $sekme === 'site' ? ' aria-current="page"' : '' ?>><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg></a></h1><p class="muted">Aktif kur: <strong><?= e($kur['ad']) ?></strong> · <?= $onayliSayi ?> onaylı katılımcı</p></div>
    <a class="btn" href="index.php">Siteye dön</a>
  </div>
  <nav class="sekmeler" aria-label="Panel bölümleri">
    <?php foreach ($sekmeler as $sk => $sa): ?>
      <a href="egitmen.php?sekme=<?= $sk ?>" data-sekme="<?= $sk ?>"<?= $sk === $sekme ? ' aria-current="page"' : '' ?>><?= $sa ?><?php if (!empty($rozet[$sk])): ?> <span class="badge"><?= $rozet[$sk] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="sekme-panel stack" id="p-bugun"<?= $sekme === 'bugun' ? '' : ' hidden' ?>>
  <?php
    /* Yapılacaklar */
    $isler = [];
    if ($bekleyenKayit) {
        $isler[] = [count($bekleyenKayit) . ' kayıt onay bekliyor', 'egitmen.php?sekme=katilimcilar#kayit'];
    }
    $bekleyenTanitim = array_values(array_filter($kullanicilar, function ($u) {
        return ($u['tanitim_bekleyen'] ?? '') !== '' && $u['durum'] === 'onayli';
    }));
    if ($bekleyenTanitim) {
        $isler[] = [count($bekleyenTanitim) . ' yazar tanıtımı onay bekliyor', 'egitmen.php?sekme=katilimcilar#tanitim'];
    }
    if ($bekleyen) {
        $isler[] = [count($bekleyen) . ' gönderi incelenmeyi bekliyor', 'egitmen.php?haber=' . $bekleyen[0]['id']];
    }
    $havuzSayi = count(finalistler($kid));
    if ($yayinda && $havuzSayi < 2 && !$c['oylama']) {
        $isler[] = ['Haftanın haberi oylaması için en az 2 finalist seçin (' . $havuzSayi . ' / 3)', 'egitmen.php?sekme=gonderiler#yayimlanan'];
    }
    $siradakiDers = empty($kur['ders_tarihleri'][$hafta]) ? $hafta : min(8, $hafta + 1);
    if (!($siradakiDers === 8 && !empty($kur['ders_tarihleri'][8]))) {
        $eksikler = array_column(array_filter(hafta_hazirlik($siradakiDers, week($siradakiDers)), function ($x) {
            return !$x[1];
        }), 0);
        if ($eksikler) {
            $eksikler = array_map(function ($x) {
                return preg_replace('/:.*$/', '', $x);
            }, $eksikler);
            $isler[] = ['Hafta ' . $siradakiDers . ' içeriğinde eksik: ' . implode(', ', $eksikler), 'egitmen.php?sekme=icerik&b=hafta&h=' . $siradakiDers];
        }
    }
    if ($isler): ?>
  <section class="card yapilacak" aria-label="Yapılacaklar">
    <h2>Yapılacaklar</h2>
    <ul class="list-plain"><?php foreach ($isler as [$metin, $link]): ?><li><a href="<?= e($link) ?>"><?= e($metin) ?> →</a></li><?php endforeach; ?></ul>
  </section>
  <?php endif; ?>
    <div class="grid-2">
      <div class="stack">
      <section class="card stack" style="border-color:var(--accent)">
        <div class="card-head" style="margin:0">
          <?php $kartHafta = $c['aktif'] ? (int)$c['hafta'] : $siradakiDers;
                $tamam = !$c['aktif'] && $hafta === 8 && !empty($kur['ders_tarihleri'][8]); ?>
          <div><span class="kicker"><?= $c['aktif'] ? '● Ders şu an canlı' : ($tamam ? 'Dersler tamamlandı' : 'Sıradaki ders') ?></span>
            <h2>Hafta <?= $kartHafta ?> · <?= e(week($kartHafta)['baslik']) ?></h2></div>
          <span class="mono kod" title="Ders kodu (QR'ın içindeki kod)"><?= e(ders_kodu($kid)) ?></span>
        </div>
        <div class="row">
          <a class="btn btn-primary" href="sunucu.php"><?= $c['aktif'] ? 'Sunucu ekranına dön' : 'Canlı dersi başlat' ?></a>
          <a class="btn" href="projeksiyon.php" target="anlatir-projeksiyon">Sunumu aç</a>
        </div>
        <?php if (!empty($kur['ders_tarihleri'][$hafta])): ?><p class="small"><strong>Hafta <?= $hafta ?> ödevi:</strong> <?= $buHaftaGonderen ?> / <?= $onayliSayi ?> katılımcı <a href="egitmen.php?sekme=gonderiler&amp;takip=<?= $hafta ?>#takip">Ödev takibi →</a></p><?php endif; ?>
        <p class="help">Sunucu ekranı sizin bilgisayarınızda açık kalır; “Sunumu aç” ile açılan pencereyi salondaki ekrana taşıyın. Öğrenciler ekrandaki QR'ı okutunca katılımları kendiliğinden işlenir.</p>
      </section>
      </div>
      <aside class="stack">
      <section class="card stack">
        <h2>Haftanın haberi</h2>
        <p>Sıradaki oylamanın finalistleri: <strong><?= count($havuz) ?> / 3</strong></p>
        <?php if ($havuz): ?><ul class="bullets small"><?php foreach ($havuz as $fid): $fh = haber_yukle($fid); ?><li><?= e($fh['baslik']) ?> · <?= e($adlar[$fh['yazar_id']] ?? $fh['yazar']) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <a class="btn btn-sm" href="egitmen.php?sekme=gonderiler#yayimlanan" style="align-self:flex-start">Finalist seç →</a>
        <p class="help">Oylamayı derste sunucu ekranından başlatırsınız. Finalist yalnızca tüm bilgileri kaynaklı haberlerden seçilir; bir kişi kur boyunca bir kez kazanır.</p>
      </section>
      </aside>
    </div>
  </div>

  <div class="sekme-panel stack" id="p-gonderiler"<?= $sekme === 'gonderiler' ? '' : ' hidden' ?>>
      <section class="card stack" id="haberler">
        <h2>Onay bekleyen haberler <?php if ($bekleyenHaber): ?><span class="tag tag-gold"><?= count($bekleyenHaber) ?></span><?php endif; ?></h2>
        <?php if (!$bekleyenHaber): ?><p class="muted">Bekleyen haber yok.</p><?php endif; ?>
        <ul class="list-plain"><?php foreach ($bekleyenHaber as $h) { gonderi_satiri($h, $adlar); } ?></ul>

        <div class="card-head" style="margin:12px 0 0">
          <h2>Yazılı ödevler <?php if ($bekleyenOdev): ?><span class="tag tag-gold"><?= count($bekleyenOdev) ?></span><?php endif; ?></h2>
          <?php if ($bekleyenOdev): ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="islem" value="goruldu_tumu">
              <button class="btn btn-sm" type="submit">Tümünü "Görüldü" yap</button></form>
          <?php endif; ?>
        </div>
        <?php if (!$bekleyenOdev): ?><p class="muted">Bakılmamış yazılı ödev yok.</p><?php endif; ?>
        <ul class="list-plain"><?php foreach ($bekleyenOdev as $h) { gonderi_satiri($h, $adlar); } ?></ul>

        <h2 style="margin-top:12px" id="yayimlanan">Yayımlanan haberler</h2>
        <?php if (!$yayinda): ?><p class="muted">Henüz yayımlanan haber yok.</p><?php endif; ?>
        <ul class="list-plain"><?php foreach ($yayinda as $h) { gonderi_satiri($h, $adlar, true); } ?></ul>

        <?php if ($digerleri): ?>
        <details>
          <summary style="cursor:pointer;font-weight:600">Düzeltmede, görülen ve yayımlanmayanlar (<?= count($digerleri) ?>)</summary>
          <ul class="list-plain"><?php foreach ($digerleri as $h) { gonderi_satiri($h, $adlar); } ?></ul>
        </details>
        <?php endif; ?>
      </section>
      <?php
        $takipHafta = max(1, min(8, (int)($_GET['takip'] ?? $hafta)));
        $onayli = array_values(array_filter($kullanicilar, function ($u) {
            return $u['durum'] === 'onayli';
        }));
        $ilk = [];
        foreach ($gonderiler as $g) {
            if ((int)$g['hafta'] === $takipHafta && (!isset($ilk[$g['yazar_id']]) || $g['tarih'] < $ilk[$g['yazar_id']]['tarih'])) {
                $ilk[$g['yazar_id']] = $g;
            }
        }
        $gonderen = count(array_filter($onayli, function ($u) use ($ilk) {
            return isset($ilk[$u['id']]);
        }));
        $st = son_teslim_yazi($kur, $takipHafta);
      ?>
      <section class="card stack" id="takip">
        <div class="card-head" style="margin:0">
          <h2>Ödev takibi</h2>
          <form method="get" action="egitmen.php#takip" class="row" style="gap:6px"><input type="hidden" name="sekme" value="gonderiler"><label for="takip-sec" class="hidden">Hafta</label>
            <select id="takip-sec" name="takip" style="max-width:150px;min-height:40px;padding:6px 10px" onchange="this.form.submit()"><?php for ($i = 1; $i <= 8; $i++): ?><option value="<?= $i ?>"<?= $i === $takipHafta ? ' selected' : '' ?>>Hafta <?= $i ?></option><?php endfor; ?></select>
            <noscript><button class="btn btn-sm">Göster</button></noscript></form>
        </div>
        <p><strong><?= $gonderen ?> / <?= count($onayli) ?></strong> katılımcı gönderdi<?= $st ? ' · son teslim ' . e($st) : ' · son teslim, bu haftanın dersi başlatılınca belirlenir' ?></p>
        <?php if ($onayli): ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Katılımcı</th><th>Durum</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($onayli as $u): $g = $ilk[$u['id']] ?? null; ?>
            <tr>
              <td><?= e($u['ad']) ?> <span class="help">· <?= e($u['rumuz']) ?></span></td>
              <td><?php if (!$g): ?><span class="tag tag-bad">Göndermedi</span><?php else: ?><span class="tag <?= gec_mi($kur, $g) ? 'tag-gold' : 'tag-ok' ?>"><?= gec_mi($kur, $g) ? 'Geç gönderdi' : 'Gönderdi' ?></span> <span class="help"><?= e(tr_date($g['tarih'], true)) ?></span><?php endif; ?></td>
              <td><?php if ($g): ?><a href="egitmen.php?haber=<?= e($g['id']) ?>">İncele</a><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php endif; ?>
      </section>
  </div>

  <div class="sekme-panel stack" id="p-katilimcilar"<?= $sekme === 'katilimcilar' ? '' : ' hidden' ?>>
      <section class="card stack" id="kayit">
        <div class="card-head" style="margin:0">
          <h2>Kayıt onayı <?php if ($bekleyenKayit): ?><span class="tag tag-gold"><?= count($bekleyenKayit) ?> bekliyor</span><?php endif; ?></h2>
          <?php if (count($bekleyenKayit) > 1): ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="islem" value="kayit_tumu"><button class="btn btn-sm btn-primary">Tümünü onayla</button></form>
          <?php endif; ?>
        </div>
        <?php if (!$bekleyenKayit): ?><p class="muted">Bekleyen kayıt yok.</p><?php endif; ?>
        <ul class="list-plain">
          <?php foreach ($bekleyenKayit as $u): ?>
            <li style="padding:12px 0;border-bottom:1px solid var(--line-2);display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px">
              <span><strong><?= e($u['ad']) ?></strong> · <?= e($u['rumuz']) ?><br><span class="meta"><?= e($u['eposta']) ?> · <?= e(tr_date($u['tarih'], true)) ?></span></span>
              <span class="row" style="gap:6px">
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="uid" value="<?= e($u['id']) ?>"><button class="btn btn-sm" name="islem" value="kayit_red">Reddet</button></form>
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="uid" value="<?= e($u['id']) ?>"><button class="btn btn-sm btn-primary" name="islem" value="kayit_onay">Onayla</button></form>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($bekleyenTanitim): ?>
        <div id="tanitim" class="stack" style="gap:8px;border-top:1px solid var(--line-2);padding-top:12px">
          <strong>Yazar tanıtımları</strong>
          <?php foreach ($bekleyenTanitim as $u): ?>
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px">
              <span><strong><?= e($u['rumuz']) ?></strong> <span class="help">(<?= e($u['ad']) ?>)</span><br><?= e($u['tanitim_bekleyen']) ?></span>
              <span class="row" style="gap:6px">
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="uid" value="<?= e($u['id']) ?>"><button class="btn btn-sm" name="islem" value="tanitim_red">Reddet</button></form>
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="uid" value="<?= e($u['id']) ?>"><button class="btn btn-sm btn-primary" name="islem" value="tanitim_onay">Onayla</button></form>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="row between">
          <span class="help">Kayıt formu şu an <strong><?= !empty($kur['kayit_acik']) ? 'açık' : 'kapalı' ?></strong>. İlk ders haftası açık kalır; Hafta 2 başlatılınca kendiliğinden kapanır.</span>
          <form method="post" class="inline-form"><?= csrf_field() ?><button class="btn btn-sm" name="islem" value="<?= !empty($kur['kayit_acik']) ? 'kayit_kapat' : 'kayit_ac' ?>"><?= !empty($kur['kayit_acik']) ? 'Kaydı kapat' : 'Kaydı aç' ?></button></form>
        </div>
      </section>
      <?php
        $yk = yoklama($kid);
        $dersler = array_map('intval', array_keys((array)($kur['ders_tarihleri'] ?? [])));
        $gonBy = [];
        foreach ($gonderiler as $g) {
            $gonBy[$g['yazar_id'] ?? ''][(int)$g['hafta']][] = $g;
        }
        $ogrList = array_values(array_filter($kullanicilar, function ($u) {
            return in_array($u['durum'], ['onayli', 'kapali'], true);
        }));
        usort($ogrList, function ($a, $b) {
            return strcmp(tr_lower($a['ad']), tr_lower($b['ad']));
        });
        $odevDurum = function ($liste) {
            if (!$liste) {
                return ['', '–', 'Gönderi yok'];
            }
            $d = array_column($liste, 'durum');
            if (in_array('yayinda', $d, true)) {
                return ['ok', '★', 'Yayımlandı'];
            }
            if (in_array('bekliyor', $d, true)) {
                return ['bekle', '✓', 'Teslim edildi, inceleme bekliyor'];
            }
            return ['tamam', '✓', 'Teslim edildi'];
        };
        $kart = isset($_GET['ogr']) ? kullanici_bul($kid, (string)$_GET['ogr']) : null;
      ?>
      <?php if ($kart): ?>
      <section class="card stack" id="ogrenci-kart">
        <a href="egitmen.php?sekme=katilimcilar#ogrenciler" class="crumbs">← Tüm öğrenciler</a>
        <div class="row between">
          <div><h2><?= e($kart['ad']) ?> <span class="muted" style="font-weight:400">· <?= e($kart['rumuz']) ?></span></h2>
            <p class="meta"><?= e($kart['eposta']) ?> · kayıt <?= e(tr_date($kart['tarih'])) ?><?= !empty($kart['elle']) ? ' · panelden eklendi' : '' ?> · <?= e(['onayli' => 'Onaylı', 'bekliyor' => 'Onay bekliyor', 'red' => 'Reddedildi', 'kapali' => 'Hesap kapalı'][$kart['durum']] ?? $kart['durum']) ?></p></div>
          <span class="row" style="gap:6px">
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="kart" value="1"><input type="hidden" name="uid" value="<?= e($kart['id']) ?>"><button class="btn btn-sm" name="islem" value="sifre">Yeni şifre</button></form>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="kart" value="1"><input type="hidden" name="uid" value="<?= e($kart['id']) ?>"><button class="btn btn-sm<?= $kart['durum'] === 'onayli' ? ' btn-danger' : '' ?>" name="islem" value="<?= $kart['durum'] === 'onayli' ? 'hesap_kapat' : 'hesap_ac' ?>"><?= $kart['durum'] === 'onayli' ? 'Hesabı kapat' : 'Etkinleştir' ?></button></form>
          </span>
        </div>
        <form method="post" class="row" style="gap:6px"><?= csrf_field() ?><input type="hidden" name="kart" value="1"><input type="hidden" name="islem" value="rumuz"><input type="hidden" name="uid" value="<?= e($kart['id']) ?>">
          <label for="kart-rumuz" class="help">Rumuz</label><input id="kart-rumuz" type="text" name="rumuz" value="<?= e($kart['rumuz']) ?>" maxlength="40" style="min-height:38px;padding:6px 8px;font-size:15px;max-width:220px"><button class="btn btn-sm" type="submit">Kaydet</button></form>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Hafta</th><th>Katılım</th><th>Ödev ve gönderiler</th></tr></thead>
            <tbody>
            <?php for ($i = 1; $i <= 8; $i++): $kat = $yk[(string)$i][$kart['id']] ?? null; $yapildi = in_array($i, $dersler, true); ?>
              <tr>
                <td><strong><?= $i ?></strong> <span class="help"><?= e(week($i)['baslik']) ?></span></td>
                <td>
                  <?php if ($kat): ?><span class="tag tag-ok">Katıldı</span> <span class="help"><?= $kat['yol'] === 'elle' ? 'elle' : 'QR' ?></span>
                  <?php elseif ($yapildi): ?><span class="tag">Yok</span>
                  <?php else: ?><span class="help">Ders yapılmadı</span><?php endif; ?>
                  <?php if ($yapildi || $kat): ?>
                  <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="kart" value="1"><input type="hidden" name="uid" value="<?= e($kart['id']) ?>"><input type="hidden" name="hafta" value="<?= $i ?>"><button class="btn btn-sm" name="islem" value="<?= $kat ? 'yoklama_kapat' : 'yoklama_ac' ?>"><?= $kat ? 'Kaldır' : 'Katıldı işaretle' ?></button></form>
                  <?php endif; ?>
                </td>
                <td>
                  <?php foreach ($gonBy[$kart['id']][$i] ?? [] as $g): ?>
                    <a href="egitmen.php?haber=<?= e($g['id']) ?>"><?= e($g['baslik'] ?: 'Başlıksız') ?></a> <span class="help">· <?= e(['bekliyor' => 'inceleme bekliyor', 'yayinda' => 'yayında', 'duzeltme' => 'düzeltme istendi', 'goruldu' => 'görüldü', 'red' => 'yayımlanmadı'][$g['durum']] ?? $g['durum']) ?></span><br>
                  <?php endforeach; ?>
                  <?php if (empty($gonBy[$kart['id']][$i])): ?><span class="help">–</span><?php endif; ?>
                </td>
              </tr>
            <?php endfor; ?>
            </tbody>
          </table>
        </div>
        <form method="post" class="stack" style="gap:6px"><?= csrf_field() ?><input type="hidden" name="islem" value="egitmen_notu"><input type="hidden" name="kart" value="1"><input type="hidden" name="uid" value="<?= e($kart['id']) ?>">
          <label for="egitmen-notu"><strong>Eğitmen notu</strong> <span class="help">yalnızca siz görürsünüz</span></label>
          <textarea id="egitmen-notu" name="not" rows="3"><?= e($kart['egitmen_notu'] ?? '') ?></textarea>
          <button class="btn btn-sm" type="submit" style="align-self:flex-start">Notu kaydet</button>
        </form>
      </section>
      <?php endif; ?>

      <section class="card stack" id="ogrenciler">
        <div class="card-head" style="margin:0">
          <h2>Öğrenciler (<?= count($ogrList) ?>)</h2>
          <span class="row" style="gap:6px">
            <input type="search" id="ogr-ara" placeholder="Ad, rumuz ya da e-posta" aria-label="Öğrenci ara" style="min-height:38px;padding:6px 10px;font-size:15px;max-width:240px">
            <a class="btn btn-sm" href="egitmen.php?csv=ogrenciler">Listeyi indir (CSV)</a>
          </span>
        </div>
        <p class="help">Her hafta: üstte katılım (● katıldı, ○ katılmadı), altta ödev (✓ teslim, ★ yayımlandı). Katılım, öğrenci derste QR'ı okutunca kendiliğinden işlenir; bir hücreye tıklayıp elle değiştirebilirsiniz. Öğrencinin adına tıklayınca kartı açılır.</p>
        <?php if (!$ogrList): ?><p class="muted">Henüz onaylı öğrenci yok.</p><?php else: ?>
        <div class="table-wrap">
          <table class="table ogr-tablo">
            <thead><tr><th>Öğrenci</th><?php for ($i = 1; $i <= 8; $i++): ?><th style="text-align:center" title="<?= e(week($i)['baslik']) ?>">H<?= $i ?></th><?php endfor; ?><th>Katılım</th></tr></thead>
            <tbody>
            <?php foreach ($ogrList as $u): $top = 0; ?>
              <tr data-ara="<?= e(tr_lower($u['ad'] . ' ' . $u['rumuz'] . ' ' . $u['eposta'])) ?>"<?= $u['durum'] !== 'onayli' ? ' style="opacity:.55"' : '' ?>>
                <td><a href="egitmen.php?sekme=katilimcilar&amp;ogr=<?= e($u['id']) ?>#ogrenci-kart"><strong><?= e($u['ad']) ?></strong></a><br><span class="help"><?= e($u['rumuz']) ?><?= $u['durum'] !== 'onayli' ? ' · hesap kapalı' : '' ?><?= trim($u['egitmen_notu'] ?? '') !== '' ? ' · not var' : '' ?></span></td>
                <?php for ($i = 1; $i <= 8; $i++):
                    $kat = isset($yk[(string)$i][$u['id']]); $top += $kat ? 1 : 0; $yapildi = in_array($i, $dersler, true);
                    [$od, $oi, $ot] = $odevDurum($gonBy[$u['id']][$i] ?? []); ?>
                  <td style="text-align:center;padding:4px">
                    <?php if ($yapildi || $kat): ?>
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="uid" value="<?= e($u['id']) ?>"><input type="hidden" name="hafta" value="<?= $i ?>">
                      <button class="ogr-hucre<?= $kat ? ' var' : '' ?>" name="islem" value="<?= $kat ? 'yoklama_kapat' : 'yoklama_ac' ?>" title="Hafta <?= $i ?>: <?= $kat ? 'katıldı (kaldırmak için tıklayın)' : 'katılmadı (katıldı işaretlemek için tıklayın)' ?>"><?= $kat ? '●' : '○' ?></button></form>
                    <?php else: ?><span class="help" title="Ders henüz yapılmadı">·</span><?php endif; ?>
                    <br><span class="ogr-odev <?= $od ?>" title="<?= e($ot) ?>"><?= $oi ?></span>
                  </td>
                <?php endfor; ?>
                <td><strong><?= $top ?></strong> / <?= count($dersler) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
        <details id="ogrenci-ekle">
          <summary style="cursor:pointer;font-weight:600">Öğrenci ekle</summary>
          <form method="post" class="stack" style="margin-top:10px;max-width:520px"><?= csrf_field() ?><input type="hidden" name="islem" value="ogrenci_ekle">
            <p class="help">Kayıt formunu kullanamayan biri için. Hesap onaylı açılır; geçici şifre bir kez ekranda gösterilir.</p>
            <div class="field"><label for="ye-ad">Ad soyad</label><input id="ye-ad" name="ad" type="text" required></div>
            <div class="field"><label for="ye-ep">E-posta</label><input id="ye-ep" name="eposta" type="email" required></div>
            <div class="field"><label for="ye-rz">Rumuz <span class="help">(isteğe bağlı)</span></label><input id="ye-rz" name="rumuz" type="text" maxlength="40"></div>
            <button class="btn btn-primary" type="submit" style="align-self:flex-start">Ekle</button>
          </form>
        </details>
      </section>
      <script>
      (function () {
        var a = document.getElementById("ogr-ara"); if (!a) return;
        a.addEventListener("input", function () {
          var q = a.value.toLocaleLowerCase("tr").trim();
          document.querySelectorAll(".ogr-tablo tbody tr").forEach(function (tr) { tr.hidden = q && tr.getAttribute("data-ara").indexOf(q) < 0; });
        });
      })();
      </script>
  </div>

  <div class="sekme-panel stack" id="p-icerik"<?= $sekme === 'icerik' ? '' : ' hidden' ?>>
    <?php if ($sekme === 'icerik') { icerik_paneli($kur, $kid); } ?>
  </div>

  <div class="sekme-panel stack" id="p-site"<?= $sekme === 'site' ? '' : ' hidden' ?>>
    <?php if ($sekme === 'site'): ?>
      <a href="egitmen.php">← Panele dön</a>
      <p class="help" style="margin:0">Ana sayfa yazıları, duyuru bandı, görseller ve sitenin genel bilgileri. Derslerden bağımsızdır; değişiklikler hemen yayına girer.</p>
      <?php icerik_paneli($kur, $kid, 'site'); ?>
    <?php endif; ?>
  </div>

  <div class="sekme-panel stack" id="p-ayarlar"<?= $sekme === 'ayarlar' ? '' : ' hidden' ?>>
    <div class="grid-2">
      <div class="stack">
      <section class="card stack" id="kur">
        <h2>Kur</h2>
        <div class="kv">
          <span class="muted">Ad</span><span><?= e($kur['ad']) ?></span>
          <span class="muted">Ders kodu</span><span class="mono"><?= e(ders_kodu($kid)) ?> <span class="help">(her derste yenilenir)</span></span>
          <span class="muted">Açılış</span><span><?= e(tr_date($kur['acilis'])) ?></span>
          <span class="muted">Yapay zekâ</span><span><?= ai_acik() ? 'Açık' : 'Kapalı <span class="help">(config.php › yapay_zeka)</span>' ?></span>
        </div>
        <details>
          <summary style="cursor:pointer;font-weight:600">Ders yapmadan hafta aç</summary>
          <p class="help" style="margin-top:6px">Normalde gerek yoktur: dersi başlattığınızda o hafta kendiliğinden açılır ve son teslim belirlenir. Bu ayar yalnızca dersi yapılamayan bir haftanın içeriğini ve ödevini öğrencilere açmak içindir; son teslim tarihi oluşmaz.</p>
        <form method="post" class="row" style="gap:6px"><?= csrf_field() ?><input type="hidden" name="islem" value="hafta">
          <label for="hafta-ac" class="help" style="flex-basis:100%">Öğrencilere açık son hafta</label>
          <select id="hafta-ac" name="hafta" style="max-width:140px"><?php for ($i = 1; $i <= 8; $i++): ?><option value="<?= $i ?>"<?= $i === $hafta ? ' selected' : '' ?>>Hafta <?= $i ?></option><?php endfor; ?></select>
          <button class="btn btn-sm" type="submit">Kaydet</button></form>
        </details>
        <div class="row" style="gap:8px">
          <form method="post" class="inline-form"><?= csrf_field() ?><button class="btn btn-sm" name="islem" value="ders_kodu_yenile">Ders kodunu yenile</button></form>
          <a class="btn btn-sm" href="rapor.php?kur=<?= e($kid) ?>">Kur raporu</a>
          <a class="btn btn-sm" href="egitmen.php?yedek=1">Yedeği indir</a>
        </div>
        <details>
          <summary style="cursor:pointer;color:var(--bad);font-weight:600">Kuru kapat ve arşivle</summary>
          <form method="post" class="stack" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="islem" value="kur_kapat">
            <p class="help">Kur kapanınca hesaplar kapanır, yayımlanan haberler arşivde kalır. Hesaplar ve yayımlanmamış gönderiler <?= (int)cfg('saklama_gun', 60) ?> gün sonra kendiliğinden silinir. Ardından yeni kur açabilirsiniz.</p>
            <label for="onay">Onaylamak için KAPAT yazın</label><input id="onay" name="onay" type="text" autocomplete="off">
            <button class="btn btn-danger" type="submit">Kuru kapat</button>
          </form>
        </details>
      </section>
      </div>
      <div class="stack">
      <?php $sorular = array_reverse(json_read(soru_gecmisi_path($kid), [])); ?>
      <section class="card stack" id="sorular">
        <div class="card-head" style="margin:0">
          <h2>Anlık soru sonuçları</h2>
          <?php if ($sorular): ?><a class="btn btn-sm" href="egitmen.php?csv=sorular">CSV indir</a><?php endif; ?>
        </div>
        <?php if (!$sorular): ?>
          <p class="muted">Derste sorduğunuz soruların sonuçları burada birikir. Cevaplar anonimdir.</p>
        <?php else: ?>
          <ul class="list-plain">
          <?php foreach (array_slice($sorular, 0, 12) as $r): $top = max(1, (int)$r['yanit']); ?>
            <li style="padding:10px 0;border-bottom:1px solid var(--line-2)">
              <details>
                <summary style="cursor:pointer"><strong><?= e($r['metin']) ?></strong> <span class="help">· Hafta <?= (int)$r['hafta'] ?> · <?= (int)$r['yanit'] ?> cevap</span></summary>
                <div class="stack" style="gap:6px;margin-top:8px">
                  <?php foreach ($r['secenekler'] as $i => $sec): $n = (int)($r['sayilar'][$i] ?? 0); $pct = round(100 * $n / $top); ?>
                    <div class="opt-row"><div class="small"><?= e($sec) ?></div><div class="bar"><span style="width:<?= $pct ?>%"></span><em><?= $pct ?>% · <?= $n ?></em></div></div>
                  <?php endforeach; ?>
                </div>
              </details>
            </li>
          <?php endforeach; ?>
          </ul>
          <p class="help">İlk ve son derste aynı soruları sorarsanız kurun öğrenme kazanımını karşılaştırabilirsiniz. Tüm sonuçlar CSV dosyasında.</p>
        <?php endif; ?>
      </section>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  var linkler = document.querySelectorAll(".sekmeler a");
  function ac(ad, odak) {
    if (!document.getElementById("p-" + ad)) return;
    document.querySelectorAll(".sekme-panel").forEach(function (p) { p.hidden = p.id !== "p-" + ad; });
    linkler.forEach(function (a) { if (a.getAttribute("data-sekme") === ad) a.setAttribute("aria-current", "page"); else a.removeAttribute("aria-current"); });
    if (odak) { var el = document.getElementById(odak); if (el) el.scrollIntoView(); }
  }
  linkler.forEach(function (a) {
    a.addEventListener("click", function (ev) {
      var ad = a.getAttribute("data-sekme");
      if (ad === "icerik" || ad === "site") return; // bu iki sekme sayfayı yeniden yükler
      ev.preventDefault();
      ac(ad);
      history.replaceState(null, "", "egitmen.php?sekme=" + ad);
    });
  });
  /* #kayit gibi bir bağlantıyla gelindiyse o bölümün sekmesini aç */
  function hashAc() {
    var h = location.hash.slice(1), el = h && document.getElementById(h);
    if (el) { var p = el.closest(".sekme-panel"); if (p && p.hidden) ac(p.id.slice(2), h); }
  }
  window.addEventListener("hashchange", hashAc);
  hashAc();
})();
</script>
<?php page_foot(['assets/kopyala.js', 'assets/panel.js']);