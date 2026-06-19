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
        } elseif ($this->isRecommendedCardSelection($state, $analysis)) {
            $reply = $this->selectCard($state, $analysis['entities']['card']);
        } elseif (in_array('affirm', $analysis['intents'], true) && ($state['pending_action'] ?? null) === 'compare_options') {
            $state['stage'] = 'recommending';
            $reply = $this->compareOptions($state);
        } elseif (in_array('affirm', $analysis['intents'], true) && ($state['pending_action'] ?? null) === 'start_application') {
            $state['stage'] = 'application';
            $reply = $this->continueApplication($state, $message);
        } elseif (in_array('deny', $analysis['intents'], true) && !in_array('reject_firm', $analysis['intents'], true)) {
            $state['pending_action'] = null;
            $reply = 'No hay problema. Podemos dejar la solicitud para otro momento. Que informacion te gustaria revisar antes de decidir?';
        } elseif (in_array('start_application', $analysis['intents'], true) || $state['stage'] === 'application') {
            $state['stage'] = 'application';
            if (!empty($analysis['entities']['card'])) $state['application']['card'] = $analysis['entities']['card'];
            $reply = $this->continueApplication($state, $message);
        } elseif (in_array('compare_options', $analysis['intents'], true)) {
            $state['stage'] = 'recommending';
            $reply = $this->compareOptions($state);
        } elseif (in_array('ask_rates', $analysis['intents'], true)) {
            $state['stage'] = 'informing';
            $reply = $this->answerRates($state);
        } elseif ($this->isShortTopicSelection($message, $analysis)) {
            $state['stage'] = 'discovery';
            $reply = $this->nextDiscoveryQuestion($state);
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

        return ['response' => $reply, 'state' => $this->publicState($state), 'analysis' => ['intents' => $analysis['intents'], 'entities' => $analysis['entities'], 'provider' => $analysis['provider']]];
    }

    private function load(string $file): array {
        $initial = ['stage' => 'new', 'profile' => ['interest' => null, 'travel_purpose' => null], 'recommendation' => ['cards' => []], 'application' => ['card' => null, 'dni' => null, 'phone' => null, 'email' => null], 'pending_action' => null, 'objections' => 0, 'history' => [], 'summary' => ''];
        if (!is_file($file)) return $initial;
        $saved = json_decode((string) file_get_contents($file), true);
        return is_array($saved) ? array_replace_recursive($initial, $saved) : $initial;
    }

    private function mergeMemory(array &$state, array $entities): void {
        foreach (['interest', 'travel_purpose'] as $field) if (!empty($entities[$field])) $state['profile'][$field] = $entities[$field];
    }

    private function answerRates(array &$state): string {
        $cards = $state['recommendation']['cards'] ?: null;
        $summary = $this->knowledge->rateSummary($cards);
        if (!$summary) return 'No encuentro tasas vigentes en la base de conocimiento. Prefiero no darte un dato que no pueda confirmar.';
        if ($cards) $state['pending_action'] = 'compare_options';
        $bridge = $cards ? ' Quieres que compare estas dos opciones por cuota, seguro y lounge?' : ' Si quieres, despues comparo la tasa con los beneficios de cada tarjeta.';
        return $summary . $bridge;
    }

    private function compareOptions(array &$state): string {
        $cards = $state['recommendation']['cards'];
        if (!$cards && $state['profile']['interest'] === 'travel') $cards = ['Gold', 'Platinum'];
        if (count($cards) < 2) return 'Claro. Para comparar necesito saber que dos tarjetas te interesan. Podemos revisar Classic y Gold, o Gold y Platinum.';
        $state['recommendation']['cards'] = $cards;
        $comparison = $this->knowledge->comparison($cards);
        if (count($comparison) < 2) return 'No encuentro una comparativa vigente en la base de conocimiento. Prefiero que un asesor confirme esos datos.';
        $facts = [];
        foreach ($cards as $card) {
            $row = $comparison[$card] ?? [];
            $facts[] = $card . ': cuota ' . ($row['cuota anual'] ?? 'no disponible') . ', viajes ' . ($row['viajes'] ?? 'no disponible') . ', seguro de viaje ' . ($row['seguro viaje'] ?? 'no disponible') . ', lounge ' . ($row['lounge'] ?? 'no disponible') . ' y TEA ' . ($row['tea'] ?? 'no disponible') . '.';
        }
        $state['pending_action'] = 'select_card';
        $guide = $state['profile']['travel_purpose'] === 'pleasure' ? ' Para viajes por placer ocasionales, Gold suele ser la alternativa mas contenida; si viajas con frecuencia, Platinum entrega mas cobertura y accesos. Cual de las dos se acerca mas a lo que buscas?' : ' Cual de estas opciones quieres revisar con mas detalle?';
        return implode(' ', $facts) . $guide;
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

    private function nextDiscoveryQuestion(array &$state): string {
        if ($state['profile']['interest'] === 'travel' && !$state['profile']['travel_purpose']) return 'Para orientarte mejor, viajas principalmente por trabajo o por placer?';
        if ($state['profile']['interest'] === 'travel') {
            $state['recommendation']['cards'] = ['Gold', 'Platinum'];
            $state['pending_action'] = 'compare_options';
            return 'Como viajas por placer, Gold y Platinum son las opciones mas relevantes. Prefieres revisar tasas, seguros, lounge o quieres que las compare?';
        }
        if ($state['profile']['interest'] === 'restaurants') return 'Perfecto. Sueles salir a comer con frecuencia o buscas descuentos ocasionales?';
        return 'Puedo ayudarte a elegir sin presion. Que te interesa mas: restaurantes, viajes, ahorro en cuota o seguridad?';
    }

    private function isShortTopicSelection(string $message, array $analysis): bool {
        $words = preg_split('/\s+/', trim($message)) ?: [];
        return count($words) <= 2 && !in_array('ask_rates', $analysis['intents'], true) && !in_array('ask_fees', $analysis['intents'], true) && !in_array('compare_options', $analysis['intents'], true) && !empty($analysis['entities']['interest']);
    }

    private function isRecommendedCardSelection(array $state, array $analysis): bool {
        return !empty($analysis['entities']['card']) && in_array($analysis['entities']['card'], $state['recommendation']['cards'] ?? [], true);
    }

    private function selectCard(array &$state, string $card): string {
        $state['application']['card'] = $card;
        $state['stage'] = 'considering';
        $state['pending_action'] = 'start_application';
        return 'Buena eleccion. La DINNERS ' . $card . ' queda seleccionada como tu opcion. Si quieres, iniciamos la preevaluacion; tambien puedo resolver una duda antes de pedirte datos.';
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
