<?php
declare(strict_types=1);

class IntentAnalyzer {
    public function analyze(string $message, array $context = []): array {
        $text = $this->normalize($message);
        $local = $this->localAnalysis($text);
        $local['provider'] = 'local';
        $remote = $this->analyzeWithKimi($message, $context);

        if (is_array($remote)) {
            $local['intents'] = array_values(array_unique(array_merge($local['intents'], $this->allowedIntents($remote['intents'] ?? [], $text))));
            $local['entities'] = array_merge($local['entities'], $this->allowedEntities($remote['entities'] ?? [], $text));
            $local['sentiment'] = $remote['sentiment'] ?? $local['sentiment'];
            $local['provider'] = 'kimi';
        }
        return $local;
    }

    private function localAnalysis(string $text): array {
        $intents = [];
        $entities = [];
        $matches = function (string $pattern) use ($text): bool { return (bool) preg_match($pattern, $text); };

        if ($matches('/\b(no me interesa|no quiero nada|no gracias|paso|dejame|no insistas|cancelar|adios|chau)\b/u')) $intents[] = 'reject_firm';
        if ($matches('/\b(tasa|tasas|tea|tem|interes|intereses)\b/u')) $intents[] = 'ask_rates';
        if ($matches('/\b(cuota|cuotas|comision|costo|precio)\b/u')) $intents[] = 'ask_fees';
        if ($matches('/\b(linea de credito|monto disponible|fondos|cuanto prestan|monto.*prestamo|prestamo actual|credito disponible)\b/u')) $intents[] = 'ask_credit_limit';
        if ($matches('/\b(simular|simulacion|prestamo|credito|cuanto pagaria|cuanto pago|\d+\s*(mes|meses)|cuotas?\s+(a|de))\b/u')) $intents[] = 'loan_simulation';
        if ($matches('/\b(compara|comparalo|comparala|compare|diferencia|cual conviene|mejor opcion)\b/u')) $intents[] = 'compare_options';
        if ($matches('/\b(si|claro|dale|ok|perfecto|adelante|vamos)\b/u')) $intents[] = 'affirm';
        if ($matches('/\b(no|mejor no|ahora no)\b/u')) $intents[] = 'deny';
        if ($matches('/\b(beneficio|punto|lounge|seguro|restaurante|viaje|promocion|informacion|informame|cuentame|explicame|dame info)\b/u')) $intents[] = 'ask_product';
        if ($matches('/\b(quiero solicitar|iniciar solicitud|iniciar el tramite|quiero la tarjeta|me interesa solicitar|vamos a solicitar)\b/u')) $intents[] = 'start_application';
        if ($matches('/\b(asesor|persona|humano|llamar)\b/u')) $intents[] = 'request_human';
        if ($matches('/\b(ya tengo|tengo tarjeta|deuda|complicado|lo pensare|mas tarde|no estoy seguro)\b/u')) $intents[] = 'objection';
        if ($matches('/\b(te pregunte|te dije|no me refiero|me refiero|eso no|no era|corrige|equivocaste)\b/u')) $intents[] = 'correction';
        if ($matches('/\b(repite|repetir|otra vez|de nuevo|no entendi|me quedaron dudas|tengo dudas)\b/u')) $intents[] = 'repeat_request';

        if ($matches('/\b(viajo|viaje|viajes|lounge|hotel|aerolinea)\b/u')) $entities['interest'] = 'travel';
        if ($matches('/\b(restaurante|comer|cena|gastronomia)\b/u')) $entities['interest'] = 'restaurants';
        if ($matches('/\b(sin cuota|ahorrar|economica|barata)\b/u')) $entities['interest'] = 'savings';
        if ($matches('/\b(por placer|vacaciones|turismo)\b/u')) $entities['travel_purpose'] = 'pleasure';
        if ($matches('/\b(trabajo|negocio|laboral)\b/u')) $entities['travel_purpose'] = 'work';
        if (preg_match('/\b(classic|clasica|gold|platinum|black)\b/u', $text, $m)) $entities['card'] = ucfirst($m[1]);
        if (preg_match('/\b(6|9|12)\s*(mes|meses)\b/u', $text, $m)) $entities['term_months'] = (int) $m[1];
        if (preg_match('/\b(diez|veinte|treinta|cuarenta|cincuenta)\s+mil\b/u', $text, $m)) {
            $amounts = ['diez' => 10000, 'veinte' => 20000, 'treinta' => 30000, 'cuarenta' => 40000, 'cincuenta' => 50000];
            $entities['loan_amount'] = $amounts[$m[1]];
        } elseif (preg_match('/\b(\d{1,2})\s*mil\b/u', $text, $m)) {
            $entities['loan_amount'] = (int) $m[1] * 1000;
        } elseif (preg_match('/s\/?\s*(\d[\d,.]*)/u', $text, $m)) {
            $entities['loan_amount'] = (float) str_replace([',', '.'], '', $m[1]);
        }

        if (!$intents) $intents[] = 'general';
        return ['intents' => $intents, 'entities' => $entities, 'sentiment' => in_array('reject_firm', $intents, true) ? 'negative' : 'neutral'];
    }

    private function analyzeWithKimi(string $message, array $context): ?array {
        $apiKey = $this->kimiApiKey();
        if (!$apiKey || !function_exists('curl_init')) return null;

        $safeMessage = preg_replace(['/\b\d{8}\b/', '/\b9\d{8}\b/', '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i'], ['[DNI]', '[CELULAR]', '[EMAIL]'], $message);
        $prompt = 'Clasifica el mensaje de un cliente de tarjetas y credito en espanol. Usa el contexto solo para resolver referencias como "comparalo", "esa opcion" o "te pregunte". El mensaje actual tiene prioridad. No inventes preferencias, tarjetas ni datos que no aparezcan en el mensaje o contexto. Devuelve SOLO JSON valido con intents (array), entities (objeto) y sentiment. Intents permitidos: ask_rates, ask_fees, ask_product, ask_credit_limit, loan_simulation, compare_options, start_application, request_human, objection, reject_firm, affirm, deny, correction, repeat_request, general. Entities permitidas: interest (travel, restaurants, savings), travel_purpose (pleasure, work), card (Classic, Gold, Platinum, Black), loan_amount (numero), term_months (6, 9 o 12). Contexto: ' . json_encode($context, JSON_UNESCAPED_UNICODE) . '. Mensaje: ' . $safeMessage;
        $payload = ['model' => 'kimi-for-coding', 'max_tokens' => 240, 'temperature' => 0, 'system' => 'Responde solo JSON valido, sin markdown ni explicaciones.', 'messages' => [['role' => 'user', 'content' => $prompt]]];
        $curl = curl_init('https://api.kimi.com/coding/v1/messages');
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01']]);
        $raw = curl_exec($curl);
        curl_close($curl);
        $data = json_decode((string) $raw, true);
        $content = $data['content'][0]['text'] ?? '';
        if (preg_match('/\{.*\}/s', $content, $match)) $content = $match[0];
        return $content ? json_decode($content, true) : null;
    }

    private function kimiApiKey(): ?string {
        $fromEnv = getenv('KIMI_API_KEY');
        if ($fromEnv) return $fromEnv;
        $configPath = '/etc/ivr-web/kimi.php';
        if (!is_file($configPath)) return null;
        $config = include $configPath;
        return is_array($config) && !empty($config['api_key']) ? (string) $config['api_key'] : null;
    }

    private function allowedIntents($intents, string $message): array {
        $allowed = ['ask_rates', 'ask_fees', 'ask_product', 'ask_credit_limit', 'loan_simulation', 'compare_options', 'start_application', 'request_human', 'objection', 'reject_firm', 'affirm', 'deny', 'correction', 'repeat_request', 'general'];
        if (!is_array($intents)) return [];
        $sensitive = ['ask_credit_limit' => '/\b(linea|monto|fondos|prestan|credito)\b/u', 'loan_simulation' => '/\b(simul|prestamo|credito|\d+\s*mes|cuotas?\s+(a|de)|pagar)\b/u', 'start_application' => '/\b(solicitar|iniciar|tramite)\b/u', 'request_human' => '/\b(asesor|humano|persona|llamar)\b/u'];
        return array_values(array_filter(array_intersect($intents, $allowed), function ($intent) use ($sensitive, $message) {
            return !isset($sensitive[$intent]) || (bool) preg_match($sensitive[$intent], $message);
        }));
    }

    private function allowedEntities($entities, string $message): array {
        if (!is_array($entities)) return [];
        $clean = [];
        if (in_array($entities['interest'] ?? null, ['travel', 'restaurants', 'savings'], true) && $this->hasInterestClue($message, $entities['interest'])) $clean['interest'] = $entities['interest'];
        if (in_array($entities['travel_purpose'] ?? null, ['pleasure', 'work'], true) && preg_match('/\b(placer|vacaciones|turismo|trabajo|negocio|laboral)\b/u', $message)) $clean['travel_purpose'] = $entities['travel_purpose'];
        if (in_array($entities['card'] ?? null, ['Classic', 'Gold', 'Platinum', 'Black'], true) && preg_match('/\b(classic|clasica|gold|platinum|black)\b/u', $message)) $clean['card'] = $entities['card'];
        if (isset($entities['loan_amount']) && is_numeric($entities['loan_amount']) && $entities['loan_amount'] >= 1000 && $entities['loan_amount'] <= 50000) $clean['loan_amount'] = (float) $entities['loan_amount'];
        if (in_array((int) ($entities['term_months'] ?? 0), [6, 9, 12], true)) $clean['term_months'] = (int) $entities['term_months'];
        return $clean;
    }

    private function hasInterestClue(string $message, string $interest): bool {
        $patterns = ['travel' => '/\b(viaje|viajes|viajo|lounge|hotel|aerolinea)\b/u', 'restaurants' => '/\b(restaurante|comer|cena|gastronomia)\b/u', 'savings' => '/\b(sin cuota|ahorrar|economica|barata)\b/u'];
        return (bool) preg_match($patterns[$interest], $message);
    }

    private function normalize(string $value): string {
        return strtr(strtolower($value), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
