<?php
declare(strict_types=1);

require_once __DIR__ . '/KnowledgeBase.php';
require_once __DIR__ . '/IntentAnalyzer.php';

class ConversationEngine {
    private $knowledge;
    private $sessionsPath;
    private $analyzer;

    public function __construct(string $knowledgePath, string $sessionsPath) {
        $this->knowledge = new KnowledgeBase($knowledgePath);
        $this->sessionsPath = $sessionsPath;
        $this->analyzer = new IntentAnalyzer();
    }

    public function reply(string $sessionId, string $message): array {
        $file = $this->sessionsPath . '/dinners_chat_' . $sessionId . '.json';
        $state = $this->load($file);
        $analysis = $this->analyzer->analyze($message);
        $this->mergeMemory($state, $analysis['entities']);

        if (in_array('reject_firm', $analysis['intents'], true)) {
            $state['stage'] = 'closed';
            $reply = 'Entiendo. Gracias por tu tiempo; no insistire. Si en otro momento deseas informacion, estare disponible.';
        } elseif ($state['stage'] === 'closed') {
            $reply = 'La conversacion quedo cerrada para respetar tu decision. Si deseas retomarla, escribe "quiero informacion".';
        } elseif (in_array('request_human', $analysis['intents'], true)) {
            $state['stage'] = 'human_handoff';
            $reply = 'Claro. Registrare que prefieres continuar con un asesor humano. Mientras tanto, puedo responder cualquier consulta puntual del producto.';
        } elseif (in_array('start_application', $analysis['intents'], true) || $state['stage'] === 'application') {
            $state['stage'] = 'application';
            $reply = $this->continueApplication($state, $message);
        } elseif (in_array('ask_rates', $analysis['intents'], true)) {
            $state['stage'] = 'informing';
            $reply = $this->answerRates($state);
        } elseif (in_array('ask_fees', $analysis['intents'], true) || in_array('ask_product', $analysis['intents'], true)) {
            $state['stage'] = 'informing';
            $reply = $this->answerFromKnowledge($message, $state);
        } elseif (in_array('objection', $analysis['intents'], true)) {
            $state['objections']++;
            $state['stage'] = 'discovery';
            $reply = $this->answerObjection($message);
        } else {
            $state['stage'] = 'discovery';
            $reply = $this->nextDiscoveryQuestion($state);
        }

        $state['history'][] = ['role' => 'user', 'content' => $message, 'time' => time(), 'intents' => $analysis['intents']];
        $state['history'][] = ['role' => 'assistant', 'content' => $reply, 'time' => time()];
        $state['history'] = array_slice($state['history'], -30);
        $state['summary'] = $this->summary($state);
        file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

        return ['response' => $reply, 'state' => $this->publicState($state), 'analysis' => ['intents' => $analysis['intents'], 'entities' => $analysis['entities']]];
    }

    private function load(string $file): array {
        $initial = ['stage' => 'new', 'profile' => ['interest' => null, 'travel_purpose' => null], 'application' => ['card' => null, 'dni' => null, 'phone' => null, 'email' => null], 'objections' => 0, 'history' => [], 'summary' => ''];
        if (!is_file($file)) return $initial;
        $saved = json_decode((string) file_get_contents($file), true);
        return is_array($saved) ? array_replace_recursive($initial, $saved) : $initial;
    }

    private function mergeMemory(array &$state, array $entities): void {
        foreach (['interest', 'travel_purpose'] as $field) if (!empty($entities[$field])) $state['profile'][$field] = $entities[$field];
        if (!empty($entities['card'])) $state['application']['card'] = $entities['card'];
    }

    private function answerRates(array $state): string {
        $summary = $this->knowledge->rateSummary();
        if (!$summary) return 'No encuentro tasas vigentes en la base de conocimiento. Prefiero no darte un dato que no pueda confirmar.';
        $bridge = $state['profile']['travel_purpose'] === 'pleasure' ? ' Como indicaste que viajas por placer, despues podemos comparar Gold y Platinum por sus beneficios de viaje.' : ' Si quieres, despues comparo la tasa con los beneficios de cada tarjeta.';
        return $summary . $bridge;
    }

    private function answerFromKnowledge(string $message, array $state): string {
        $results = $this->knowledge->search($message);
        if (!$results) return 'No encuentro una respuesta verificada en la base de conocimiento. Prefiero no inventar informacion; puedo registrar tu consulta para un asesor.';
        $excerpt = $this->knowledge->readableExcerpt($results[0]);
        $bridge = $state['profile']['interest'] === 'travel' ? ' Como te interesan los viajes, puedo ayudarte a comparar las opciones que incluyen beneficios de viaje.' : ' Que aspecto te interesa revisar despues: beneficios, tasas o requisitos?';
        return 'Segun la informacion vigente de ' . $results[0]['title'] . ': ' . $excerpt . ' ' . $bridge;
    }

    private function answerObjection(string $message): string {
        $text = strtolower($message);
        if (strpos($text, 'tengo tarjeta') !== false || strpos($text, 'ya tengo') !== false) return 'Es razonable. No necesitas cambiar si tu tarjeta actual ya cubre lo que buscas. Puedo mostrarte solo las diferencias en viajes, restaurantes o cuota para que compares sin compromiso.';
        if (strpos($text, 'deuda') !== false || strpos($text, 'interes') !== false) return 'Es una preocupacion valida. Una tarjeta conviene solo si puedes manejar el pago total o conoces las condiciones de financiamiento. Puedo explicarte las tasas vigentes antes de hablar de una solicitud.';
        return 'Entiendo. Podemos resolver una sola duda concreta y tu decides con tranquilidad. Que te preocupa mas: tasas, cuota o beneficios?';
    }

    private function nextDiscoveryQuestion(array $state): string {
        if ($state['profile']['interest'] === 'travel' && !$state['profile']['travel_purpose']) return 'Para orientarte mejor, viajas principalmente por trabajo o por placer?';
        if ($state['profile']['interest'] === 'travel') return 'Con ese perfil puedo comparar opciones de viaje. Prefieres revisar tasas, seguros o accesos a lounge?';
        if ($state['profile']['interest'] === 'restaurants') return 'Perfecto. Sueles salir a comer con frecuencia o buscas descuentos ocasionales?';
        return 'Puedo ayudarte a elegir sin presion. Que te interesa mas: restaurantes, viajes, ahorro en cuota o seguridad?';
    }

    private function continueApplication(array &$state, string $message): string {
        if (!$state['application']['card']) return 'Excelente. Antes de iniciar, quieres Classic sin cuota el primer ano, Gold, Platinum o Black? Tambien puedo recomendarte una segun tus prioridades.';
        if (!$state['application']['dni'] && preg_match('/\b([0-8]\d{7})\b/', $message, $m)) $state['application']['dni'] = $m[1];
        if (!$state['application']['dni']) return 'Para iniciar la preevaluacion de la DINNERS ' . $state['application']['card'] . ', necesito tu DNI de 8 digitos.';
        if (!$state['application']['phone'] && preg_match('/\b(9\d{8})\b/', $message, $m)) $state['application']['phone'] = $m[1];
        if (!$state['application']['phone']) return 'DNI registrado. Cual es tu celular de 9 digitos para contactarte?';
        if (!$state['application']['email'] && preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $message, $m)) $state['application']['email'] = $m[0];
        if (!$state['application']['email']) return 'Casi listo. Cual es tu correo para enviarte la confirmacion de la preevaluacion?';
        $state['stage'] = 'completed';
        return 'Listo. Tu interes por la DINNERS ' . $state['application']['card'] . ' quedo registrado. Recibiras la confirmacion de la preevaluacion en 24 a 48 horas.';
    }

    private function summary(array $state): string {
        $items = [];
        if ($state['profile']['interest']) $items[] = 'interes: ' . $state['profile']['interest'];
        if ($state['profile']['travel_purpose']) $items[] = 'viaje: ' . $state['profile']['travel_purpose'];
        if ($state['application']['card']) $items[] = 'tarjeta: ' . $state['application']['card'];
        return implode('; ', $items);
    }

    private function publicState(array $state): array {
        return ['stage' => $state['stage'], 'profile' => $state['profile'], 'application_progress' => ['card' => (bool) $state['application']['card'], 'dni' => (bool) $state['application']['dni'], 'phone' => (bool) $state['application']['phone'], 'email' => (bool) $state['application']['email']]];
    }
}
