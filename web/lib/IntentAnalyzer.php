<?php
declare(strict_types=1);

class IntentAnalyzer {
    public function analyze(string $message): array {
        $text = $this->normalize($message);
        $local = $this->localAnalysis($text);
        $remote = $this->analyzeWithGroq($message);

        if (is_array($remote)) {
            $local['intents'] = array_values(array_unique(array_merge($local['intents'], $remote['intents'] ?? [])));
            $local['entities'] = array_merge($local['entities'], $remote['entities'] ?? []);
            $local['sentiment'] = $remote['sentiment'] ?? $local['sentiment'];
        }
        return $local;
    }

    private function localAnalysis(string $text): array {
        $intents = [];
        $entities = [];
        $matches = function (string $pattern) use ($text): bool { return (bool) preg_match($pattern, $text); };

        if ($matches('/\b(no me interesa|no quiero nada|no gracias|paso|dejame|no insistas|cancelar|adios|chau)\b/u')) $intents[] = 'reject_firm';
        if ($matches('/\b(tasa|tasas|tea|tem|interes|intereses)\b/u')) $intents[] = 'ask_rates';
        if ($matches('/\b(cuota|comision|costo|precio)\b/u')) $intents[] = 'ask_fees';
        if ($matches('/\b(beneficio|punto|lounge|seguro|restaurante|viaje|promocion)\b/u')) $intents[] = 'ask_product';
        if ($matches('/\b(quiero solicitar|iniciar solicitud|iniciar el tramite|quiero la tarjeta|me interesa solicitar|vamos a solicitar)\b/u')) $intents[] = 'start_application';
        if ($matches('/\b(asesor|persona|humano|llamar)\b/u')) $intents[] = 'request_human';
        if ($matches('/\b(ya tengo|tengo tarjeta|deuda|complicado|lo pensare|mas tarde|no estoy seguro)\b/u')) $intents[] = 'objection';

        if ($matches('/\b(viajo|viaje|viajes|lounge|hotel|aerolinea)\b/u')) $entities['interest'] = 'travel';
        if ($matches('/\b(restaurante|comer|cena|gastronomia)\b/u')) $entities['interest'] = 'restaurants';
        if ($matches('/\b(sin cuota|ahorrar|economica|barata)\b/u')) $entities['interest'] = 'savings';
        if ($matches('/\b(por placer|vacaciones|turismo)\b/u')) $entities['travel_purpose'] = 'pleasure';
        if ($matches('/\b(trabajo|negocio|laboral)\b/u')) $entities['travel_purpose'] = 'work';
        if (preg_match('/\b(classic|clasica|gold|platinum|black)\b/u', $text, $m)) $entities['card'] = ucfirst($m[1]);

        if (!$intents) $intents[] = 'general';
        return ['intents' => $intents, 'entities' => $entities, 'sentiment' => in_array('reject_firm', $intents, true) ? 'negative' : 'neutral'];
    }

    private function analyzeWithGroq(string $message): ?array {
        $apiKey = getenv('GROQ_API_KEY');
        if (!$apiKey || !function_exists('curl_init')) return null;

        $safeMessage = preg_replace(['/\b\d{8}\b/', '/\b9\d{8}\b/', '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i'], ['[DNI]', '[CELULAR]', '[EMAIL]'], $message);
        $prompt = 'Clasifica el mensaje de un cliente de tarjetas. Devuelve JSON estricto con intents (array), entities (objeto) y sentiment. Intents permitidos: ask_rates, ask_fees, ask_product, start_application, request_human, objection, reject_firm, general. Extrae interest (travel, restaurants, savings), travel_purpose (pleasure, work) y card cuando existan. Mensaje: ' . $safeMessage;
        $payload = ['model' => 'llama-3.1-8b-instant', 'messages' => [['role' => 'system', 'content' => 'Responde solo JSON valido.'], ['role' => 'user', 'content' => $prompt]], 'temperature' => 0.1, 'response_format' => ['type' => 'json_object']];
        $curl = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]]);
        $raw = curl_exec($curl);
        curl_close($curl);
        $data = json_decode((string) $raw, true);
        return isset($data['choices'][0]['message']['content']) ? json_decode($data['choices'][0]['message']['content'], true) : null;
    }

    private function normalize(string $value): string {
        return strtr(strtolower($value), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
