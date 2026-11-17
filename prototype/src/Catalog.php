<?php

declare(strict_types=1);

namespace SmartSave;

// Preise und Technik des Abos. Alle Werte sind Vorschlaege fuer das Schulprojekt und hier zentral aenderbar.
//
// Kalkulation (Ladenpreise Stand 2026, gerundet): Hub ca. 45 EUR, smarte LED ca. 12 EUR, smarte Steckdose
// ca. 18 EUR, Bewegungsmelder ca. 25 EUR, Temperatursensor ca. 13 EUR, Tuer-/Fenstersensor ca. 15 EUR,
// Heizkoerperthermostat ca. 45 EUR. Wir kaufen guenstiger ein und rechnen fuer Support, App, Versand und
// Ausfaelle einen Aufschlag von etwa 30 bis 40 % auf den Ladenpreis.
final class Catalog
{
    public const TERM_MONTHS = 24;

    // Grundgebuehr pro Monat: Hub, App, Support und die Basis-Technik (siehe BASE_INCLUDED)
    public const BASE_MONTHLY_FEE = 5.99;

    // Weniger als so viele Monate Restlaufzeit: nichts mehr dazubuchbar
    public const MIN_REMAINING_MONTHS = 6;

    public const MAX_BOOKINGS_PER_USER = 20;

    /**
     * Ersparnis durch Verbrauchsanzeige (Hub und App) in Prozent der Energiekosten. Der Wert stammt aus dem
     * Feldversuch Intelliekon (Fraunhofer ISE/ISI, ueber 2.000 Haushalte in Deutschland und Oesterreich):
     * 3,7 % weniger Stromverbrauch durch zeitnahe Verbrauchsinformation. Konservativer Ansatz fuer die Demo.
     */
    public const BASE_SAVING_PERCENT = 3.7;

    /** Obergrenze fuer die gesamte geschaetzte Ersparnis in Prozent der bisherigen Energiekosten */
    public const MAX_SAVINGS_PERCENT = 30.0;

    /** Produkt-ID => Anzahl, im Basis-Abo enthalten (zusaetzlich zu Hub, App und Support) */
    // Kennung der Bestellung des Basis-Sets (Hub plus BASE_INCLUDED); kein Produkt zum Dazubuchen
    public const BASE_PRODUCT_ID = 'basis';

    /**
     * Pakete, aus denen man bei der Registrierung waehlt (wie die Fertigpakete auf der Website).
     * Beide enthalten das Basis-Set; extras sind Zusatztechnik oben drauf (Produkt => Stueckzahl).
     *
     * @var array<string, array{name: string, description: string, extras: array<string, int>}>
     */
    public const MODELS = [
        'starter' => ['name' => 'Starter', 'description' => 'Das Basis-Set, nicht mehr und nicht weniger', 'extras' => []],
        'komfort' => ['name' => 'Komfort', 'description' => 'Basis-Set plus 2 weitere Lampen, 1 weiterer Bewegungsmelder und 1 Temperatursensor', 'extras' => ['led' => 2, 'bewegung' => 1, 'temperatur' => 1]],
    ];

    public const BASE_INCLUDED = ['led' => 2, 'steckdose' => 1, 'bewegung' => 1];

    /**
     * Preis = Gesamtpreis ueber die volle Laufzeit von 24 Monaten. Wer spaeter bucht, verteilt denselben
     * Gesamtpreis auf weniger Monate (siehe monthlyPrice).
     *
     * Zusatz:
     *  - saving: geschaetzte Ersparnis pro Stueck in Prozent der bisherigen monatlichen Energiekosten
     *  - maxUseful: ab dieser Stueckzahl bringt ein weiteres Stueck nichts mehr (Raeume und Heizkoerper sind begrenzt)
     * Die Prozentwerte sind Schaetzungen fuer das Schulprojekt, keine Messwerte.
     *
     * @var array<string, array{name: string, description: string, total: float, saving: float, maxUseful: int}>
     */
    public const PRODUCTS = [
        'led' => ['name' => 'Smarte LED-Lampe', 'description' => 'Dimmbar, per App und Zeitplan schaltbar', 'total' => 12.00, 'saving' => 0.4, 'maxUseful' => 4],
        'steckdose' => ['name' => 'Smarte Steckdose', 'description' => 'Schaltet Geräte und misst den Verbrauch', 'total' => 20.00, 'saving' => 0.5, 'maxUseful' => 2],
        'bewegung' => ['name' => 'Bewegungsmelder', 'description' => 'Schaltet Licht und Geräte bei Bewegung', 'total' => 24.00, 'saving' => 1.0, 'maxUseful' => 3],
        'temperatur' => ['name' => 'Temperatursensor', 'description' => 'Misst Temperatur und Luftfeuchte', 'total' => 14.00, 'saving' => 0.8, 'maxUseful' => 3],
        'fenster' => ['name' => 'Tür-/Fenstersensor', 'description' => 'Meldet offene Fenster, Heizung kann reagieren', 'total' => 16.00, 'saving' => 1.0, 'maxUseful' => 4],
        'thermostat' => ['name' => 'Heizkörperthermostat', 'description' => 'Regelt die Heizung nach Zeitplan', 'total' => 48.00, 'saving' => 3.0, 'maxUseful' => 4],
    ];

    // Ersparnis durch Hub und App (Verbrauchsanzeige), unabhaengig von Geraeten und Zeitplaenen
    public static function baseSaving(float $oldMonthlyCost): float
    {
        return $oldMonthlyCost <= 0.0 ? 0.0 : round($oldMonthlyCost * self::BASE_SAVING_PERCENT / 100, 2);
    }

    public static function maxSavings(float $oldMonthlyCost): float
    {
        return $oldMonthlyCost <= 0.0 ? 0.0 : round($oldMonthlyCost * self::MAX_SAVINGS_PERCENT / 100, 2);
    }

    // Anzeigename fuer Produkte und das Basis-Set (Bestellungen koennen beides enthalten)
    public static function productName(string $productId): string
    {
        if ($productId === self::BASE_PRODUCT_ID) {
            return 'Basis-Set';
        }

        return self::PRODUCTS[$productId]['name'] ?? $productId;
    }

    // Was im Basis-Set steckt, zum Packen: Hub plus BASE_INCLUDED
    public static function baseSetContents(): string
    {
        $parts = ['Hub'];
        foreach (self::BASE_INCLUDED as $productId => $count) {
            $parts[] = $count . '× ' . self::PRODUCTS[$productId]['name'];
        }

        return implode(', ', $parts);
    }

    public static function modelExists(string $modelId): bool
    {
        return isset(self::MODELS[$modelId]);
    }

    // Monatspreis eines Pakets bei Abo-Start: Grundgebuehr plus Zusatztechnik
    public static function modelMonthlyPrice(string $modelId): float
    {
        $sum = self::BASE_MONTHLY_FEE;
        foreach (self::MODELS[$modelId]['extras'] ?? [] as $productId => $count) {
            $sum += self::monthlyPrice($productId, 0) * $count;
        }

        return round($sum, 2);
    }

    public static function exists(string $productId): bool
    {
        return isset(self::PRODUCTS[$productId]);
    }

    public static function remainingMonths(int $currentMonth): int
    {
        return max(0, self::TERM_MONTHS - $currentMonth);
    }

    public static function canBook(int $currentMonth): bool
    {
        return self::remainingMonths($currentMonth) >= self::MIN_REMAINING_MONTHS;
    }

    /**
     * Monatspreis eines Produkts, wenn es nach $currentMonth vergangenen Monaten gebucht wird:
     * Gesamtpreis geteilt durch die Restlaufzeit.
     */
    public static function monthlyPrice(string $productId, int $currentMonth): float
    {
        if (!self::exists($productId)) {
            throw new \InvalidArgumentException("Unbekanntes Produkt: $productId");
        }
        $remaining = self::remainingMonths($currentMonth);
        if ($remaining < 1) {
            throw new \InvalidArgumentException('Die Laufzeit ist abgelaufen.');
        }

        return round(self::PRODUCTS[$productId]['total'] / $remaining, 2);
    }

    /**
     * Ersparnis in Euro pro Monat fuer $add neue Stuecke, wenn der Nutzer schon $owned Stueck gebucht hat.
     * Nur die ersten maxUseful Stuecke zaehlen.
     */
    public static function savingForUnits(string $productId, int $owned, int $add, float $oldMonthlyCost): float
    {
        if (!self::exists($productId) || $oldMonthlyCost <= 0.0) {
            return 0.0;
        }
        $product = self::PRODUCTS[$productId];
        $counted = max(0, min($add, $product['maxUseful'] - $owned));

        return round($oldMonthlyCost * $product['saving'] / 100 * $counted, 2);
    }

    /**
     * Ergaenzt jede Buchung um 'monthlySaving'. Die Stuecke werden in Buchungsreihenfolge gezaehlt,
     * Stuecke ueber maxUseful bringen 0 Euro.
     *
     * @param array<int, array{id: int, product: string, startMonth: int, monthlyPrice: float}> $bookings
     * @return array<int, array{id: int, product: string, startMonth: int, monthlyPrice: float, monthlySaving: float}>
     */
    public static function withSavings(array $bookings, float $oldMonthlyCost): array
    {
        usort($bookings, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        $owned = [];
        foreach ($bookings as &$booking) {
            $count = $owned[$booking['product']] ?? 0;
            $booking['monthlySaving'] = self::savingForUnits($booking['product'], $count, 1, $oldMonthlyCost);
            $owned[$booking['product']] = $count + 1;
        }

        return $bookings;
    }
}
