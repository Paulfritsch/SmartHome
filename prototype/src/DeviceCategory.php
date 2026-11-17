<?php

declare(strict_types=1);

namespace SmartSave;

// Geraeteart: bestimmt, wie viel ein Zeitplan (Ausschalten) wirklich spart.
enum DeviceCategory: string
{
    case Light = 'licht';
    case Heating = 'heizung';
    case Socket = 'steckdose';
    case Other = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Licht',
            self::Heating => 'Heizung',
            self::Socket => 'Steckdose',
            self::Other => 'Sonstiges Gerät',
        };
    }

    // Anteil der gesparten Stunden (siehe Schedule), der als Ersparnis zaehlt (Annahmen fuer das Schulprojekt)
    public function savingsFactor(): float
    {
        return match ($this) {
            self::Light, self::Heating => 1.0,
            self::Socket => 0.0,
            self::Other => 0.1,
        };
    }

    public function savingsNote(): string
    {
        return match ($this) {
            self::Light => 'Licht, das aus ist, verbraucht nichts.',
            self::Heating => 'Weniger Heizzeit spart Energie (vereinfacht gerechnet).',
            self::Socket => 'Was an der Steckdose hängt, braucht in der Nutzungszeit gleich viel. Der Zeitplan spart hier nichts.',
            self::Other => 'Nur der Standby-Anteil (10 %) wird als Ersparnis gerechnet.',
        };
    }

    public static function tryFromInput(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom($value) : null;
    }
}
