<?php
declare(strict_types=1);

class KnowledgeBase {
    private $path;

    public function __construct(string $path) {
        $this->path = $path;
    }

    public function search(string $query, int $limit = 3): array {
        $terms = $this->tokens($query);
        $results = [];

        foreach (glob($this->path . '/*.md') ?: [] as $file) {
            $content = (string) file_get_contents($file);
            $sections = preg_split('/(?=^#{1,3} .+$)/m', $content) ?: [];
            foreach ($sections as $section) {
                $score = $this->score($section, $terms);
                if ($score === 0) continue;
                $lines = preg_split('/\R/', trim($section)) ?: [];
                $results[] = [
                    'source' => basename($file),
                    'title' => isset($lines[0]) ? ltrim($lines[0], '# ') : basename($file),
                    'text' => trim($section),
                    'score' => $score,
                ];
            }
        }

        usort($results, function (array $a, array $b): int { return $b['score'] <=> $a['score']; });
        return array_slice($results, 0, $limit);
    }

    public function rateSummary(?array $cards = null): ?string {
        $file = $this->path . '/tasas.md';
        if (!is_file($file)) return null;
        $content = (string) file_get_contents($file);
        $teaSection = preg_split('/^### TEM/m', $content)[0];
        $rows = [];
        foreach (preg_split('/\R/', $teaSection) ?: [] as $line) {
            if (preg_match('/\|\s*\*\*(Classic|Gold|Platinum|Black)\*\*\s*\|\s*([0-9.]+%)\s*\|\s*([0-9.]+%)/i', $line, $m)) {
                if ($cards && !in_array($m[1], $cards, true)) continue;
                $rows[] = $m[1] . ' ' . $m[2] . ' en compras y ' . $m[3] . ' en retiro';
            }
        }
        return $rows ? 'TEA vigente segun el documento de tasas: ' . implode('; ', $rows) . '.' : null;
    }

    public function comparison(array $cards): array {
        $file = $this->path . '/beneficios.md';
        if (!is_file($file)) return [];
        $content = (string) file_get_contents($file);
        $start = strpos($content, '## Comparativa Completa');
        if ($start === false) return [];
        $section = substr($content, $start);
        $end = strpos($section, '## Beneficios Adicionales');
        if ($end !== false) $section = substr($section, 0, $end);

        $headers = [];
        $rows = [];
        foreach (preg_split('/\R/', $section) ?: [] as $line) {
            if (strpos($line, '|') === false || strpos($line, '---') !== false) continue;
            $cells = array_values(array_filter(array_map('trim', explode('|', $line)), function ($cell) { return $cell !== ''; }));
            if (count($cells) < 5) continue;
            if (!$headers) {
                $headers = $cells;
                continue;
            }
            $criterion = $this->normalize($cells[0]);
            foreach ($cards as $card) {
                $index = array_search($card, $headers, true);
                if ($index !== false && isset($cells[$index])) $rows[$card][$criterion] = $cells[$index];
            }
        }
        return $rows;
    }

    public function readableExcerpt(array $result, int $max = 360): string {
        $lines = preg_split('/\R/', trim($result['text'])) ?: [];
        array_shift($lines);
        $text = preg_replace('/^#{1,3}\s+/m', '', implode("\n", $lines));
        $text = preg_replace('/\*\*/', '', $text);
        $text = preg_replace('/\|[-| ]+\|/', '', $text);
        $text = preg_replace('/\s+/', ' ', trim($text));
        if (strlen($text) > $max) $text = substr($text, 0, $max - 3) . '...';
        return $text;
    }

    private function score(string $text, array $terms): int {
        $haystack = $this->normalize($text);
        $score = 0;
        foreach ($terms as $term) {
            if (strlen($term) < 3) continue;
            $hits = substr_count($haystack, $term);
            $score += $hits * (strlen($term) > 5 ? 3 : 1);
        }
        return $score;
    }

    private function tokens(string $value): array {
        $tokens = preg_split('/[^a-z0-9]+/u', $this->normalize($value)) ?: [];
        return array_values(array_unique(array_filter($tokens, function ($term) {
            return strlen($term) > 2 && !in_array($term, ['para', 'como', 'quiero', 'dime', 'sobre', 'tiene', 'tienen'], true);
        })));
    }

    private function normalize(string $value): string {
        $value = strtolower($value);
        return strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
