<?php
declare(strict_types=1);

class CreditSimulator {
    private $catalog;

    public function __construct(string $catalogPath) {
        $data = is_file($catalogPath) ? json_decode((string) file_get_contents($catalogPath), true) : null;
        if (!is_array($data)) throw new RuntimeException('No se encontro el catalogo de credito.');
        $this->catalog = $data;
    }

    public function client(): array {
        return $this->catalog['client'];
    }

    public function terms(): array {
        return $this->catalog['loan_terms'];
    }

    public function simulate(float $amount, int $months): ?array {
        $client = $this->client();
        if ($amount < $client['minimum_loan_amount'] || $amount > $client['available_credit_limit']) return null;
        foreach ($this->terms() as $term) {
            if ((int) $term['months'] !== $months) continue;
            $rate = (float) $term['monthly_rate'];
            $installment = $amount * ($rate * pow(1 + $rate, $months)) / (pow(1 + $rate, $months) - 1);
            $total = $installment * $months;
            return ['amount' => $amount, 'months' => $months, 'monthly_rate' => $rate, 'installment' => round($installment, 2), 'total' => round($total, 2), 'interest' => round($total - $amount, 2)];
        }
        return null;
    }

    public function format(float $amount): string {
        return 'S/ ' . number_format($amount, 2, '.', ',');
    }
}
