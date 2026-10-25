<?php
/* Haber Masası araçlarının sunucu tarafı */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/openai.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
@set_time_limit(300);

function out($data)
{
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function fail($status, $message)
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail(405, 'Bu adrese yalnızca form gönderilebilir.');
}
if (!ai_acik()) {
    fail(403, 'Yapay zekâ araçları kapalı.');
}
$uid = current_user_id();
if (!$uid) {
    fail(401, 'Oturumunuz kapanmış ya da hesabınız henüz onaylanmamış. Yeniden giriş yapın.');
}
if (!csrf_ok()) {
    fail(400, 'Sayfanın süresi dolmuş. Sayfayı yenileyip tekrar deneyin.');
}
session_write_close(); // uzun yapay zekâ çağrısı sırasında diğer sayfalar beklemesin

function use_quota($uid, $kind)
{
    if (!kota_kullan($uid, $kind)) {
        fail(429, 'Bugünkü kullanım hakkınız doldu. Yarın tekrar deneyebilirsiniz.');
    }
}

function field($name, $min, $label)
{
    $v = trim((string)($_POST[$name] ?? ''));
    if (text_len($v) < $min) {
        fail(400, $label);
    }
    return cut_text($v, (int)cfg('max_chars', 12000));
}

$STATUS = ['destekleniyor', 'kismen', 'carpitilmis', 'kaynak_yok'];

/* Model çıktısındaki cümleleri temizler ve alıntıları kaynakta arar */
function clean_checks($rows, $source, $extraKey)
{
    global $STATUS;
    $out = [];
    foreach ((array)$rows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $metin = trim((string)($r['metin'] ?? ''));
        if ($metin === '') {
            continue;
        }
        $kaynak = isset($r['kaynak']) && is_string($r['kaynak']) && trim($r['kaynak']) !== '' ? trim($r['kaynak']) : null;
        $out[] = [
            'metin'      => $metin,
            'durum'      => in_array($r['durum'] ?? '', $STATUS, true) ? $r['durum'] : 'kaynak_yok',
            'kaynak'     => $kaynak,
            'dogrulandi' => $kaynak ? in_source($source, $kaynak) : false,
            $extraKey    => trim((string)($r[$extraKey] ?? '')),
        ];
    }
    return $out;
}

$action = (string)($_POST['islem'] ?? '');

try {
    openai_key(); // anahtar yoksa kullanım hakkı düşmeden hata ver
    switch ($action) {

        case 'karsilastir':
            $src = field('kaynak', 40, 'Önce kaynak metni yapıştırın.');
            $draft = field('taslak', 20, 'Karşılaştırılacak haberinizi yazın.');
            use_quota($uid, 'text');
            $prompt = "Bir öğrencinin yazdığı TASLAK haberi KAYNAK metinle cümle cümle karşılaştır. Taslağı yeniden yazma; öğrencinin kendisinin düzeltmesi için soru sor.\n\n"
                . "Her cümle için bir durum seç:\n"
                . "- \"destekleniyor\": kaynakla tam uyumlu\n"
                . "- \"kismen\": özünde doğru ama önemli bir ayrıntı eksik ya da bağlamından koparılmış\n"
                . "- \"carpitilmis\": kaynaktaki bilgi değiştirilmiş, abartılmış veya yanlış aktarılmış\n"
                . "- \"kaynak_yok\": kaynakta karşılığı yok\n\n"
                . "\"kaynak\": ilgili pasajı KAYNAK'tan harfi harfine kopyala; yoksa null.\n"
                . "\"soru\": cümle destekleniyorsa boş bırak; değilse öğrenciye yönelik tek, kısa ve somut bir soru yaz (ör. \"200 rakamını hangi belgeden aldınız?\").\n"
                . "\"ozet\": tek cümlelik, dürüst ve cesaretlendirici genel değerlendirme.\n\n"
                . "Yalnızca şu JSON'u döndür:\n{\"ozet\": \"...\", \"cumleler\": [{\"metin\": \"...\", \"durum\": \"...\", \"kaynak\": \"... veya null\", \"soru\": \"...\"}]}\n\n"
                . "KAYNAK:\n\"\"\"\n$src\n\"\"\"\n\nTASLAK:\n\"\"\"\n$draft\n\"\"\"";
            $res = ai_json($prompt);
            out(['ozet' => (string)($res['ozet'] ?? ''), 'cumleler' => clean_checks($res['cumleler'] ?? [], $src, 'soru')]);

        case 'denetle':
            $src = field('kaynak', 40, 'Önce kaynak metni yapıştırın.');
            $news = field('haber', 20, 'Denetlenecek haberi yapıştırın.');
            use_quota($uid, 'text');
            $prompt = "HABER metnindeki her cümleyi KAYNAK metinle karşılaştır.\n\n"
                . "Her cümle için bir durum seç:\n"
                . "- \"destekleniyor\": kaynakla tam uyumlu\n"
                . "- \"kismen\": özünde doğru ama önemli bir ayrıntı eksik ya da bağlamından koparılmış\n"
                . "- \"carpitilmis\": kaynaktaki bilgi değiştirilmiş, abartılmış veya yanlış aktarılmış\n"
                . "- \"kaynak_yok\": kaynakta karşılığı yok\n\n"
                . "\"kaynak\": ilgili pasajı KAYNAK'tan harfi harfine kopyala; yoksa null.\n"
                . "\"aciklama\": farkı tek kısa cümleyle açıkla; cümle destekleniyorsa boş bırak.\n"
                . "\"ozet\": tek cümlelik genel değerlendirme.\n\n"
                . "Yalnızca şu JSON'u döndür:\n{\"ozet\": \"...\", \"cumleler\": [{\"metin\": \"...\", \"durum\": \"...\", \"kaynak\": \"... veya null\", \"aciklama\": \"...\"}]}\n\n"
                . "KAYNAK:\n\"\"\"\n$src\n\"\"\"\n\nHABER:\n\"\"\"\n$news\n\"\"\"";
            $res = ai_json($prompt);
            out(['ozet' => (string)($res['ozet'] ?? ''), 'cumleler' => clean_checks($res['cumleler'] ?? [], $src, 'aciklama')]);

        case 'dokum':
            if (($_POST['izin'] ?? '') !== '1') {
                fail(400, 'Kaydı konuşan kişinin izniyle aldığınızı onaylayın.');
            }
            $f = $_FILES['ses'] ?? null;
            if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                fail(400, 'Bir ses dosyası seçin.');
            }
            if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
                fail(400, 'Dosya sunucunun yükleme sınırını aşıyor. Daha kısa bir kayıt deneyin.');
            }
            if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                fail(400, 'Dosya yüklenemedi. Tekrar deneyin.');
            }
            $maxBytes = (int)cfg('max_audio_mb', 25) * 1024 * 1024;
            if ($f['size'] > $maxBytes) {
                fail(400, 'Dosya ' . (int)cfg('max_audio_mb', 25) . ' MB sınırını aşıyor.');
            }
            $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
            $allowed = ['mp3', 'm4a', 'wav', 'webm', 'mp4', 'mpeg', 'mpga', 'ogg', 'oga', 'aac', 'flac'];
            if (!in_array($ext, $allowed, true)) {
                fail(400, 'Bu dosya türü desteklenmiyor. MP3, M4A ya da WAV yükleyin.');
            }
            use_quota($uid, 'audio');
            $lines = ai_transcribe($f['tmp_name'], 'kayit.' . $ext, (string)($f['type'] ?? ''));
            @unlink($f['tmp_name']);
            out(['satirlar' => $lines]);

        default:
            fail(400, 'Geçersiz işlem.');
    }
} catch (AiError $e) {
    fail(502, $e->getMessage());
}
