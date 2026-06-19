<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'Metodo no permitido'], 405);
}

$body = json_decode((string) file_get_contents('php://input'), true);
$message = is_array($body) ? trim((string) ($body['user_message'] ?? '')) : '';
if ($message === '') {
    respond(['ok' => false, 'error' => 'Mensaje invalido'], 400);
}

require_once __DIR__ . '/lib/ConversationEngine.php';

$sessionId = preg_replace('/[^a-z0-9_-]/i', '', (string) ($body['session_id'] ?? 'default'));
$engine = new ConversationEngine(__DIR__ . '/../rag_dinners', sys_get_temp_dir());
$result = $engine->reply($sessionId ?: 'default', $message);

respond(['ok' => true] + $result);

function respond(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
