<?php
declare(strict_types=1);

/**
 * Chat de ventas DINNERS.
 * El flujo es deliberado: informar, descubrir, responder una objecion y solo
 * pedir datos cuando el cliente confirma que desea iniciar una solicitud.
 */
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
if (!is_array($body) || !is_string($body['user_message'] ?? null)) {
    respond(['ok' => false, 'error' => 'Mensaje invalido'], 400);
}

$message = trim($body['user_message']);
if ($message === '') {
    respond(['ok' => false, 'error' => 'El mensaje no puede estar vacio'], 400);
}

$sessionId = preg_replace('/[^a-z0-9_-]/i', '', (string) ($body['session_id'] ?? 'default'));
$sessionId = $sessionId ?: 'default';
$sessionFile = sys_get_temp_dir() . '/dinners_chat_' . $sessionId . '.json';
$state = loadState($sessionFile);
$normalized = normalize($message);

if (isHardRejection($normalized)) {
    $state['stage'] = 'closed';
    $state['closed_reason'] = 'not_interested';
    $reply = 'Entiendo, gracias por tu tiempo. No te insistiré. Si más adelante quieres comparar beneficios o solicitar una tarjeta, aquí estaré. Que tengas un buen día.';
} elseif ($state['stage'] === 'closed') {
    $reply = 'La conversación quedó cerrada para respetar tu decisión. Si deseas retomarla, escribe "quiero información" o "quiero solicitar".';
} else {
    captureApplicationData($state, $message);

    if ($state['stage'] === 'application') {
        $reply = continueApplication($state);
    } elseif (isExplicitApplicationIntent($normalized)) {
        $state['stage'] = 'application';
        $reply = continueApplication($state);
    } elseif (isSoftObjection($normalized)) {
        $state['objections']++;
        $reply = answerObjection($normalized);
        $state['stage'] = 'discovery';
    } elseif (isInformationRequest($normalized) || $state['stage'] === 'new') {
        $reply = answerInformation($normalized);
        $state['stage'] = 'discovery';
    } else {
        $state['stage'] = 'discovery';
        $reply = recommendNextStep($normalized);
    }
}

$state['history'][] = ['role' => 'user', 'content' => $message, 'time' => time()];
$state['history'][] = ['role' => 'assistant', 'content' => $reply, 'time' => time()];
$state['history'] = array_slice($state['history'], -24);
$state['last_reply'] = $reply;
saveState($sessionFile, $state);

respond([
    'ok' => true,
    'response' => $reply,
    'state' => publicState($state),
]);

function loadState(string $file): array {
    $initial = [
        'stage' => 'new',
        'objections' => 0,
        'card' => null,
        'dni' => null,
        'phone' => null,
        'email' => null,
        'history' => [],
        'closed_reason' => null,
        'last_reply' => null,
    ];
    if (!is_file($file)) return $initial;
    $saved = json_decode((string) file_get_contents($file), true);
    return is_array($saved) ? array_merge($initial, $saved) : $initial;
}

function saveState(string $file, array $state): void {
    file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function publicState(array $state): array {
    return [
        'stage' => $state['stage'],
        'card' => $state['card'],
        'application_progress' => [
            'dni' => (bool) $state['dni'],
            'phone' => (bool) $state['phone'],
            'email' => (bool) $state['email'],
        ],
    ];
}

function normalize(string $value): string {
    $value = mb_strtolower(trim($value), 'UTF-8');
    return strtr($value, ['á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u']);
}

function contains(string $text, string $needle): bool {
    return strpos($text, $needle) !== false;
}

function isHardRejection(string $text): bool {
    return (bool) preg_match('/\b(no quiero nada|no me interesa|no gracias|paso|dejame|dejenme|no insistas|no insista|cancelar|olvida|adios|chau|hasta nunca)\b/u', $text);
}

function isSoftObjection(string $text): bool {
    return (bool) preg_match('/\b(ya tengo|tengo tarjeta|cuota|tasa|interes|deuda|complicado|no estoy seguro|lo pensare|mas tarde)\b/u', $text);
}

function isExplicitApplicationIntent(string $text): bool {
    return (bool) preg_match('/\b(quiero solicitar|quiero pedir|iniciar solicitud|iniciar el tramite|quiero la tarjeta|me interesa solicitar|si quiero|si, quiero|vamos a solicitar)\b/u', $text);
}

function isInformationRequest(string $text): bool {
    return (bool) preg_match('/\b(hola|informacion|informacion|cuentame|explicame|beneficio|tarjeta|punto|viaje|restaurante|lounge|como funciona|que ofrece|que es|precio|promocion)\b/u', $text);
}

function captureApplicationData(array &$state, string $message): void {
    if (!$state['dni'] && preg_match('/\b([0-8]\d{7})\b/', $message, $match)) $state['dni'] = $match[1];
    if (!$state['phone'] && preg_match('/\b(9\d{8})\b/', $message, $match)) $state['phone'] = $match[1];
    if (!$state['email'] && preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $message, $match)) $state['email'] = $match[0];
    if (!$state['card'] && preg_match('/\b(classic|clasica|gold|platinum|black)\b/i', $message, $match)) $state['card'] = ucfirst(strtolower($match[1]));
}

function continueApplication(array &$state): string {
    if (!$state['card']) return 'Perfecto. Para orientar la solicitud, ¿prefieres una Classic sin cuota el primer año, Gold con más puntos en restaurantes o quieres que te recomiende una según tu estilo de gasto?';
    if (!$state['dni']) return 'Excelente. Para iniciar la preevaluación de la DINNERS ' . $state['card'] . ', necesito tu DNI de 8 dígitos.';
    if (!$state['phone']) return 'DNI registrado. ¿Cuál es tu celular de 9 dígitos para contactarte?';
    if (!$state['email']) return 'Casi listo. ¿Cuál es tu correo para enviarte la confirmación de la preevaluación?';
    $state['stage'] = 'completed';
    return 'Listo. Registré tu interés por la DINNERS ' . $state['card'] . '. Recibirás la confirmación de la preevaluación en 24 a 48 horas al correo indicado. Gracias por confiar en nosotros.';
}

function answerObjection(string $text): string {
    if (contains($text, 'ya tengo') || contains($text, 'tengo tarjeta')) {
        return 'Tiene sentido. DINNERS puede complementar la que ya usas: destaca en restaurantes, viajes y experiencias. Si no te aporta valor real, no tendría sentido cambiar. ¿Tus gastos se concentran más en comidas o en viajes?';
    }
    if (contains($text, 'cuota')) {
        return 'La cuota es importante y conviene verla con claridad. La Classic no tiene cuota el primer año; después puedes decidir si los beneficios justifican mantenerla. ¿Buscas una opción sin riesgo para empezar?';
    }
    if (contains($text, 'tasa') || contains($text, 'interes') || contains($text, 'deuda')) {
        return 'Es una preocupación válida. La tarjeta tiene sentido si puedes pagar el total; así evitas intereses y aprovechas los beneficios. Si buscas financiarte, revisaremos las condiciones antes de cualquier solicitud. ¿Quieres que te explique las cuotas sin interés?';
    }
    if (contains($text, 'complicado')) {
        return 'El proceso es corto: una preevaluación y luego validación de datos. Si no es buen momento, no hace falta iniciarlo ahora. ¿Prefieres conocer primero los beneficios o dejarlo para más adelante?';
    }
    return 'Claro, tómate tu tiempo. Puedo responder una duda puntual para que decidas con información completa. ¿Qué te preocupa más: cuota, tasas o beneficios?';
}

function answerInformation(string $text): string {
    if (contains($text, 'viaje') || contains($text, 'lounge')) return 'DINNERS ofrece puntos para viajes, seguros según la tarjeta y accesos a lounge desde Gold. ¿Viajas con frecuencia por trabajo o por placer?';
    if (contains($text, 'restaurante') || contains($text, 'punto')) return 'En restaurantes puedes acumular más puntos y acceder a descuentos en aliados, según la tarjeta. ¿Sueles salir a comer al menos una vez al mes?';
    if (contains($text, 'cuota') || contains($text, 'precio')) return 'La Classic no tiene cuota el primer año; Gold, Platinum y Black tienen beneficios crecientes. ¿Quieres una alternativa de bajo costo o más beneficios para viajes y restaurantes?';
    return 'Soy Carlos, asesor DINNERS. Te explico la tarjeta con transparencia y, si te interesa, te acompaño a solicitarla. ¿Te atraen más los beneficios en restaurantes, viajes o una opción sin cuota el primer año?';
}

function recommendNextStep(string $text): string {
    if (contains($text, 'viaj')) return 'Por lo que comentas, Gold o Platinum pueden encajar por los beneficios de viaje. ¿Quieres que comparemos esas dos opciones?';
    if (contains($text, 'com') || contains($text, 'rest')) return 'Si disfrutas salir a comer, Gold destaca por sus puntos y descuentos en restaurantes. ¿Quieres que te cuente qué incluye antes de decidir?';
    return 'Para recomendarte bien, dime qué te interesa más: ahorrar en restaurantes, acumular beneficios de viaje o empezar sin cuota el primer año.';
}

function respond(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
