<?php
declare(strict_types=1);

require_once __DIR__ . '/../web/lib/ConversationEngine.php';

$sessionPath = sys_get_temp_dir() . '/ivr_engine_tests';
if (!is_dir($sessionPath)) mkdir($sessionPath, 0770, true);

$engine = new ConversationEngine(__DIR__ . '/../rag_dinners', $sessionPath);
$sessionId = 'conversation_' . uniqid('', true);

$first = $engine->reply($sessionId, 'viaje');
assertTrue($first['state']['profile']['interest'] === 'travel', 'Debe guardar interes en viajes.');

$profile = $engine->reply($sessionId, 'por placer');
assertTrue($profile['state']['profile']['travel_purpose'] === 'pleasure', 'Debe guardar viaje por placer con respuesta corta.');

$second = $engine->reply($sessionId, 'tasas');
assertTrue(in_array('ask_rates', $second['analysis']['intents'], true), 'Debe detectar consulta de tasas.');
assertTrue(strpos($second['response'], 'Gold 39.9%') !== false, 'Debe responder TEA de las opciones recomendadas desde tasas.md.');
assertTrue(strpos($second['response'], '3.19%') === false, 'No debe mezclar TEM cuando se pide TEA.');

$comparison = $engine->reply($sessionId, 'comparalo');
assertTrue(in_array('compare_options', $comparison['analysis']['intents'], true), 'Debe entender comparalo como comparacion contextual.');
assertTrue(strpos($comparison['response'], 'Gold: cuota') !== false, 'Debe comparar las tarjetas recomendadas.');

$selection = $engine->reply($sessionId, 'gold');
assertTrue($selection['state']['stage'] === 'considering', 'Debe entender una tarjeta como seleccion contextual.');

$confirmation = $engine->reply($sessionId, 'si');
assertTrue($confirmation['state']['stage'] === 'application', 'Debe entender si como confirmacion de solicitud contextual.');

$loanSession = 'loan_' . uniqid('', true);
$limit = $engine->reply($loanSession, 'cuanto prestan');
assertTrue(strpos($limit['response'], '50,000.00') !== false, 'Debe informar la linea de credito ficticia.');

$loan = $engine->reply($loanSession, 'quiero simular S/ 10,000');
assertTrue(strpos($loan['response'], '6 meses') !== false, 'Debe ofrecer plazos de simulacion.');

$loanTerm = $engine->reply($loanSession, '12 meses');
assertTrue(strpos($loanTerm['response'], '12 meses:') !== false, 'Debe calcular la cuota del plazo solicitado.');

$infoSession = 'info_' . uniqid('', true);
$engine->reply($infoSession, 'viajes');
$engine->reply($infoSession, 'por placer');
$info = $engine->reply($infoSession, 'informame sobre platinum');
assertTrue($info['state']['stage'] === 'informing', 'Una consulta sobre Platinum no debe iniciar solicitud.');
assertTrue(!$info['state']['application_progress']['card'], 'Mencionar Platinum no debe seleccionarla automaticamente.');

$engine->reply($infoSession, 'comparalo');
$engine->reply($infoSession, 'gold');
$infoAfterSelection = $engine->reply($infoSession, 'si dame informacion');
assertTrue($infoAfterSelection['state']['stage'] === 'informing', 'Una pregunta con si no debe pedir DNI.');

$third = $engine->reply($sessionId, 'no me interesa');
assertTrue($third['state']['stage'] === 'closed', 'Debe cerrar ante rechazo firme.');

echo "PASS: memoria, intencion multiple, RAG y cierre.\n";

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . "\n");
        exit(1);
    }
}
