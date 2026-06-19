<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Archivo de audio requerido']]);
    exit;
}

$apiKey = getenv('GROQ_API_KEY');
if (!$apiKey) {
    http_response_code(503);
    echo json_encode(['error' => ['message' => 'El servicio de transcripcion no esta configurado']]);
    exit;
}

$audio = $_FILES['file'];
if (($audio['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'No se pudo recibir el audio']]);
    exit;
}

$payload = [
    'file' => new CURLFile($audio['tmp_name'], $audio['type'] ?: 'audio/webm', $audio['name'] ?: 'audio.webm'),
    'model' => 'whisper-large-v3',
    'language' => 'es',
    'response_format' => 'json',
];
$curl = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 45,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
]);
$raw = curl_exec($curl);
$status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

if ($raw === false || $status < 200 || $status >= 300) {
    http_response_code($status ?: 502);
    echo json_encode(['error' => ['message' => $error ?: 'No fue posible transcribir el audio']]);
    exit;
}

echo $raw;
