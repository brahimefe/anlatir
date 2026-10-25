<?php
/* Otomatik dipnot kontrolü: haberdeki her cümle kaynak listesinden birine dayanıyor mu? */
require_once __DIR__ . '/openai.php';

function dipnot_kontrol($metin, $kaynaklar)
{
    $liste = '';
    $i = 0;
    foreach ($kaynaklar as $k) {
        $i++;
        $liste .= $i . '. Bilgi: ' . ($k['bilgi'] !== '' ? $k['bilgi'] : '(belirtilmemiş)') . ' — Kaynak: ' . ($k['kaynak'] !== '' ? $k['kaynak'] : '(kaynak yazılmamış)') . "\n";
    }
    if ($liste === '') {
        $liste = "(Öğrenci hiç kaynak yazmamış.)\n";
    }
    $prompt = "Bir seminer öğrencisinin HABER metnini ve yazdığı KAYNAK LİSTESİ'ni inceliyorsun. Metni yeniden yazma.\n\n"
        . "HABER metnini cümlelere ayır. Her cümle için bir durum seç:\n"
        . "- \"kaynakli\": cümledeki bilgi kaynak listesindeki bir maddeye dayanıyor (kaynağı yazılmış olmalı)\n"
        . "- \"kaynak_yok\": cümle bir bilgi veriyor ama listede buna karşılık gelen, kaynağı yazılmış bir madde yok\n"
        . "- \"yorum\": cümle bilgi değil yorum, değerlendirme ya da abartı içeriyor (ör. \"dev\", \"müjde\", \"herkes çok sevindi\")\n\n"
        . "\"kaynak_no\": dayandığı maddenin numarası; yoksa null.\n"
        . "\"soru\": durum \"kaynakli\" değilse öğrenciye tek, kısa ve somut bir soru yaz (ör. \"Mart açılışını kimden öğrendin?\"); kaynakliysa boş bırak.\n"
        . "\"ozet\": tek cümlelik, cesaretlendirici genel değerlendirme.\n\n"
        . "Yalnızca şu JSON'u döndür:\n{\"ozet\": \"...\", \"cumleler\": [{\"metin\": \"...\", \"durum\": \"...\", \"kaynak_no\": 1, \"soru\": \"...\"}]}\n\n"
        . "KAYNAK LİSTESİ:\n" . $liste . "\nHABER:\n\"\"\"\n" . $metin . "\n\"\"\"";
    $res = ai_json($prompt);
    $out = [];
    foreach ((array)($res['cumleler'] ?? []) as $c) {
        if (!is_array($c) || trim((string)($c['metin'] ?? '')) === '') {
            continue;
        }
        $durum = in_array($c['durum'] ?? '', ['kaynakli', 'kaynak_yok', 'yorum'], true) ? $c['durum'] : 'kaynak_yok';
        $no = isset($c['kaynak_no']) && is_numeric($c['kaynak_no']) && $c['kaynak_no'] >= 1 && $c['kaynak_no'] <= $i ? (int)$c['kaynak_no'] : null;
        if ($durum === 'kaynakli' && ($no === null || trim((string)$kaynaklar[$no - 1]['kaynak']) === '')) {
            $durum = 'kaynak_yok';
        }
        $out[] = ['metin' => trim((string)$c['metin']), 'durum' => $durum, 'kaynak_no' => $no, 'soru' => trim((string)($c['soru'] ?? ''))];
    }
    return ['ozet' => (string)($res['ozet'] ?? ''), 'cumleler' => $out, 'tarih' => date('c')];
}
