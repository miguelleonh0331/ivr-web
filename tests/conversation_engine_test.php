<?php
declare(strict_types=1);

require_once __DIR__ . '/../web/lib/ConversationEngine.php';

$sessionPath = sys_get_temp_dir() . '/ivr_engine_tests';
if (!is_dir($sessionPath)) mkdir($sessionPath, 0770, true);

$engine = new ConversationEngine(__DIR__ . '/../rag_dinners', $sessionPath);
$sessionId = 'conversation_' . uniqid('', true);

$first = $engine->reply($sessionId, 'viaje');
assertTrue($first['state']['profile']['interest'] === 'travel', 'Debe guardar interes en viajes.');

$second = $engine->reply($sessionId, 'por placer, pero dime las tasas');
assertTrue($second['state']['profile']['travel_purpose'] === 'pleasure', 'Debe guardar viajes por placer.');
assertTrue(in_array('ask_rates', $second['analysis']['intents'], true), 'Debe detectar consulta de tasas.');
assertTrue(strpos($second['response'], 'Classic 45.9%') !== false, 'Debe responder TEA desde tasas.md.');
assertTrue(strpos($second['response'], '3.19%') === false, 'No debe mezclar TEM cuando se pide TEA.');

$third = $engine->reply($sessionId, 'no me interesa');
assertTrue($third['state']['stage'] === 'closed', 'Debe cerrar ante rechazo firme.');

echo "PASS: memoria, intencion multiple, RAG y cierre.\n";

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . "\n");
        exit(1);
    }
}
