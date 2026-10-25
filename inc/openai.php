<?php
/* OpenAI API çağrıları */

class AiError extends Exception
{
}

function openai_key()
{
    $key = (string)cfg('openai_api_key', '');
    if ($key === '' || strpos($key, 'BURAYA') === 0) {
        throw new AiError('Sunucuda OpenAI API anahtarı tanımlı değil. config.php dosyasını doldurun.');
    }
    return $key;
}

function openai_post($url, $body, $multipart = false, $timeout = 120)
{
    if (!function_exists('curl_init')) {
        throw new AiError('Sunucuda PHP curl eklentisi etkin değil.');
    }
    $headers = ['Authorization: Bearer ' . openai_key()];
    if (!$multipart) {
        $headers[] = 'Content-Type: application/json';
        $body = json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body,
    ]);
    $resp   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new AiError('Yapay zekâ hizmetine bağlanılamadı. Tekrar deneyin.');
    }
    $data = json_decode($resp, true);
    if ($status !== 200 || !is_array($data)) {
        $code = is_array($data) ? (string)($data['error']['code'] ?? '') : '';
        $msg  = is_array($data) ? (string)($data['error']['message'] ?? '') : '';
        if ($status === 401) {
            throw new AiError('OpenAI API anahtarı geçersiz. config.php dosyasını kontrol edin.');
        }
        if ($code === 'insufficient_quota') {
            throw new AiError('OpenAI hesabında kredi kalmamış. Hesaba kredi yükleyin.');
        }
        if ($status === 429) {
            throw new AiError('Yapay zekâ hizmeti şu an yoğun. Bir dakika sonra tekrar deneyin.');
        }
        if ($code === 'model_not_found' || stripos($msg, 'model') !== false && $status === 404) {
            throw new AiError('Model adı geçersiz ya da hesabınızda erişim yok. config.php dosyasında model adını güncelleyin.');
        }
        if ($status === 413) {
            throw new AiError('Dosya çok büyük. Daha kısa bir kayıt yükleyin.');
        }
        throw new AiError('Yapay zekâ hizmeti yanıt vermedi. Tekrar deneyin.');
    }
    return $data;
}

/* Metin modeline bir istem gönderir, JSON nesnesi döndürür */
function ai_json($prompt)
{
    $data = openai_post('https://api.openai.com/v1/chat/completions', [
        'model'           => (string)cfg('model_text'),
        'response_format' => ['type' => 'json_object'],
        'messages'        => [
            ['role' => 'system', 'content' => 'Sen Türkçe çalışan, titiz bir haber editörü yardımcısısın. Yalnızca istenen JSON nesnesini döndürürsün.'],
            ['role' => 'user', 'content' => $prompt],
        ],
    ]);
    $text = (string)($data['choices'][0]['message']['content'] ?? '');
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    $json = ($start !== false && $end !== false && $end > $start) ? json_decode(substr($text, $start, $end - $start + 1), true) : null;
    if (!is_array($json)) {
        throw new AiError('Yanıt beklenen biçimde gelmedi. Tekrar deneyin.');
    }
    return $json;
}

/* Ses dosyasını yazıya döker: ['metin' => ..., 'satirlar' => [['zaman' => '00:12', 'metin' => ...], ...]] */
function ai_transcribe($path, $name, $mime)
{
    $model = (string)cfg('model_audio', 'whisper-1');
    $withTimes = ($model === 'whisper-1');
    $fields = [
        'file'            => new CURLFile($path, $mime ?: 'application/octet-stream', $name),
        'model'           => $model,
        'language'        => 'tr',
        'response_format' => $withTimes ? 'verbose_json' : 'json',
    ];
    $data = openai_post('https://api.openai.com/v1/audio/transcriptions', $fields, true, 300);
    $lines = [];
    if ($withTimes && !empty($data['segments']) && is_array($data['segments'])) {
        foreach ($data['segments'] as $seg) {
            $t = trim((string)($seg['text'] ?? ''));
            if ($t === '') {
                continue;
            }
            $sec = (int)floor((float)($seg['start'] ?? 0));
            $lines[] = ['zaman' => sprintf('%02d:%02d', intdiv($sec, 60), $sec % 60), 'metin' => $t];
        }
    } else {
        foreach (preg_split('/(?<=[.!?])\s+/u', trim((string)($data['text'] ?? ''))) as $sentence) {
            if (trim($sentence) !== '') {
                $lines[] = ['zaman' => null, 'metin' => trim($sentence)];
            }
        }
    }
    if (!$lines) {
        throw new AiError('Kayıtta anlaşılır bir konuşma bulunamadı.');
    }
    return $lines;
}
