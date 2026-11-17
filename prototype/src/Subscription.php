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
     * @param array<int, array{startMonth: int, monthlyPrice: float}> $bookings gebuchte Zusatztechnik
     * @param int $monthsElapsed vergangene Monate der Laufzeit (0 bis 24)
     */
    public function __construct(
        private readonly float $monthlySavings,
        private readonly array $bookings,
        private readonly int $monthsElapsed
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
        return round($this->monthlySavings - $this->currentFee(), 2);
    }

    // Seit Beginn der Laufzeit effektiv gespart (nach Abzug aller Gebuehren)
    public function effectiveSoFar(): float
    {
        $sum = 0.0;
        for ($month = 0; $month < $this->monthsElapsed(); $month++) {
            $sum += $this->monthlySavings - $this->feeForMonth($month);
        }

        return round($sum, 2);
    }

    public function totalSavings24(): float
    {
        return round($this->monthlySavings * Catalog::TERM_MONTHS, 2);
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
        return round($this->monthlySavings, 2);
    }
}
