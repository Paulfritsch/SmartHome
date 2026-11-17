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

    /** Produkt-ID => Anzahl, im Basis-Abo enthalten (zusaetzlich zu Hub, App und Support) */
    public const BASE_INCLUDED = ['led' => 2, 'steckdose' => 1, 'bewegung' => 1];

    /**
     * Preis = Gesamtpreis ueber die volle Laufzeit von 24 Monaten. Wer spaeter bucht, verteilt denselben
     * Gesamtpreis auf weniger Monate (siehe monthlyPrice).
     *
     * @var array<string, array{name: string, description: string, total: float}>
     */
    public const PRODUCTS = [
        'led' => ['name' => 'Smarte LED-Lampe', 'description' => 'Dimmbar, per App und Zeitplan schaltbar', 'total' => 12.00],
        'steckdose' => ['name' => 'Smarte Steckdose', 'description' => 'Schaltet Geräte und misst den Verbrauch', 'total' => 20.00],
        'bewegung' => ['name' => 'Bewegungsmelder', 'description' => 'Schaltet Licht und Geräte bei Bewegung', 'total' => 24.00],
        'temperatur' => ['name' => 'Temperatursensor', 'description' => 'Misst Temperatur und Luftfeuchte', 'total' => 14.00],
        'fenster' => ['name' => 'Tür-/Fenstersensor', 'description' => 'Meldet offene Fenster, Heizung kann reagieren', 'total' => 16.00],
        'thermostat' => ['name' => 'Heizkörperthermostat', 'description' => 'Regelt die Heizung nach Zeitplan', 'total' => 48.00],
    ];

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
}
