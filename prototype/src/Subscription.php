<?php

declare(strict_types=1);

namespace SmartSave;

// Rechnet das Abo eines Nutzers: Grundgebuehr plus gebuchte Zusatztechnik gegen die Energieersparnis.
// Annahme: Die monatliche Ersparnis bleibt ueber die Laufzeit so, wie sie heute im Dashboard steht.
// Nach TERM_MONTHS endet die Gebuehr, und die Technik gehoert dem Nutzer.
final class Subscription
{
    /**
     * @param float $monthlySavings Energieersparnis pro Monat (aus dem Dashboard)
     * @param array<int, array{startMonth: int, monthlyPrice: float, monthlySaving?: float}> $bookings gebuchte Zusatztechnik
     *        (monthlySaving: Ersparnis dieser Technik pro Monat, siehe Catalog::withSavings)
     * @param int $monthsElapsed vergangene Monate der Laufzeit (0 bis 24)
     * @param float|null $maxMonthlySavings Obergrenze fuer die gesamte Ersparnis pro Monat (null = keine)
     */
    public function __construct(
        private readonly float $monthlySavings,
        private readonly array $bookings,
        private readonly int $monthsElapsed,
        private readonly ?float $maxMonthlySavings = null
    ) {
    }

    public function monthsElapsed(): int
    {
        return min(max($this->monthsElapsed, 0), Catalog::TERM_MONTHS);
    }

    public function remainingMonths(): int
    {
        return Catalog::TERM_MONTHS - $this->monthsElapsed();
    }

    public function isFinished(): bool
    {
        return $this->monthsElapsed() >= Catalog::TERM_MONTHS;
    }

    // Gebuehr fuer den Monat mit dem Index $month (0 = erster Monat); nach der Laufzeit 0
    public function feeForMonth(int $month): float
    {
        if ($month >= Catalog::TERM_MONTHS) {
            return 0.0;
        }

        return round(Catalog::BASE_MONTHLY_FEE + $this->extrasForMonth($month), 2);
    }

    public function extrasForMonth(int $month): float
    {
        if ($month >= Catalog::TERM_MONTHS) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($this->bookings as $booking) {
            if ($booking['startMonth'] <= $month) {
                $sum += $booking['monthlyPrice'];
            }
        }

        return round($sum, 2);
    }

    // Ersparnis der gebuchten Zusatztechnik im Monat $month (ab ihrem Startmonat)
    public function extraSavingsForMonth(int $month): float
    {
        // Auch nach der Laufzeit: die Technik gehoert dem Nutzer und spart weiter
        $sum = 0.0;
        foreach ($this->bookings as $booking) {
            if ($booking['startMonth'] <= $month) {
                $sum += $booking['monthlySaving'] ?? 0.0;
            }
        }

        return round($sum, 2);
    }

    // Gesamte Ersparnis im Monat $month: Strom-Ersparnis aus dem Dashboard plus Zusatztechnik
    public function savingsForMonth(int $month): float
    {
        $sum = $this->monthlySavings + $this->extraSavingsForMonth($month);
        if ($this->maxMonthlySavings !== null) {
            $sum = min($sum, $this->maxMonthlySavings);
        }

        return round($sum, 2);
    }

    // Wie viel Ersparnis die Obergrenze im laufenden Monat abschneidet (0, wenn sie nicht greift)
    public function currentCapReduction(): float
    {
        return round($this->monthlySavings + $this->currentExtraSavings() - $this->currentSavings(), 2);
    }

    public function currentExtraSavings(): float
    {
        return $this->extraSavingsForMonth($this->monthsElapsed());
    }

    public function currentSavings(): float
    {
        return $this->savingsForMonth($this->monthsElapsed());
    }

    // Gebuehr im laufenden (naechsten) Monat
    public function currentFee(): float
    {
        return $this->feeForMonth($this->monthsElapsed());
    }

    public function currentExtras(): float
    {
        return $this->extrasForMonth($this->monthsElapsed());
    }

    public function monthlySavings(): float
    {
        return $this->monthlySavings;
    }

    // Unterm Strich pro Monat: Ersparnis minus Abo-Gebuehr (negativ = der Nutzer zahlt drauf)
    public function effectiveMonthly(): float
    {
        return round($this->currentSavings() - $this->currentFee(), 2);
    }

    // Wie viel Prozent der aktuellen Abo-Gebuehr die Ersparnis deckt (100 = Komfort ist kostenlos)
    public function coveragePercent(): int
    {
        $fee = $this->currentFee();
        if ($fee <= 0.0) {
            return 100;
        }

        return (int) max(0, floor($this->currentSavings() / $fee * 100));
    }

    // Seit Beginn der Laufzeit effektiv gespart (nach Abzug aller Gebuehren)
    public function effectiveSoFar(): float
    {
        $sum = 0.0;
        for ($month = 0; $month < $this->monthsElapsed(); $month++) {
            $sum += $this->savingsForMonth($month) - $this->feeForMonth($month);
        }

        return round($sum, 2);
    }

    public function totalSavings24(): float
    {
        $sum = 0.0;
        for ($month = 0; $month < Catalog::TERM_MONTHS; $month++) {
            $sum += $this->savingsForMonth($month);
        }

        return round($sum, 2);
    }

    // Alle Gebuehren ueber die gesamte Laufzeit, bei den heute gebuchten Produkten
    public function totalFees24(): float
    {
        $sum = 0.0;
        for ($month = 0; $month < Catalog::TERM_MONTHS; $month++) {
            $sum += $this->feeForMonth($month);
        }

        return round($sum, 2);
    }

    public function totalEffective24(): float
    {
        return round($this->totalSavings24() - $this->totalFees24(), 2);
    }

    // Nach Ende der Laufzeit entfallen die Gebuehren: die volle Ersparnis bleibt
    public function monthlyAfterTerm(): float
    {
        return $this->savingsForMonth(Catalog::TERM_MONTHS - 1);
    }
}
