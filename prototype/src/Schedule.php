<?php

declare(strict_types=1);

namespace SmartSave;

use DateTimeInterface;

// Zeitplan eines Geraets: um $offTime ausschalten, optional um $onTime wieder einschalten.
// Ohne $onTime rechnen wir mit 8 Stunden Pause. Zeiten im Format "HH:MM".
//
// Annahme fuer die Ersparnis: Nur wer abends ausschaltet (Schlaf, ca. 8 Stunden) oder morgens
// (Arbeit, ca. 8 Stunden) spart wirklich etwas. Tagsueber ausschalten zaehlt nicht als Ersparnis.
final class Schedule
{
    private const MINUTES_PER_DAY = 1440;

    public const CREDITED_HOURS_MAX = 8.0;
    public const ASSUMED_OFF_HOURS = 8;
    public const EVENING_START = '18:00'; // ab hier bis 03:59 gilt "abends/nachts"
    public const MORNING_START = '04:00'; // von hier bis 10:59 gilt "morgens"
    public const MORNING_END = '11:00';

    public readonly string $offTime;
    public readonly ?string $onTime;

    /** @throws ValidationException */
    public function __construct(string $offTime, ?string $onTime = null)
    {
        $errors = [];
        $off = self::normalize($offTime);
        $on = ($onTime === null || trim($onTime) === '') ? null : self::normalize($onTime);

        if ($off === null) {
            $errors[] = 'Ausschaltzeit: bitte eine Uhrzeit wie 22:30 angeben.';
        }
        if ($onTime !== null && trim($onTime) !== '' && $on === null) {
            $errors[] = 'Einschaltzeit: bitte eine Uhrzeit wie 06:30 angeben oder leer lassen.';
        }
        if ($off !== null && $on !== null && $off === $on) {
            $errors[] = 'Ausschalt- und Einschaltzeit dürfen nicht gleich sein.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->offTime = $off;
        $this->onTime = $on;
    }

    /** Wandelt "22:30" oder "22:30:00" in "22:30" um; null bei ungueltiger Eingabe. */
    public static function normalize(string $time): ?string
    {
        $time = trim($time);
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $time, $m) !== 1) {
            return null;
        }

        return $m[1] . ':' . $m[2];
    }

    // Dauer, in der das Geraet pro Tag ausgeschaltet ist (ueber Mitternacht moeglich)
    public function offMinutesPerDay(): int
    {
        $off = self::toMinutes($this->offTime);
        if ($this->onTime === null) {
            return self::ASSUMED_OFF_HOURS * 60;
        }
        $on = self::toMinutes($this->onTime);

        return $on > $off ? $on - $off : self::MINUTES_PER_DAY - $off + $on;
    }

    public function offHoursPerDay(): float
    {
        return $this->offMinutesPerDay() / 60;
    }

    public function isOffAt(DateTimeInterface $moment): bool
    {
        $now = (int) $moment->format('G') * 60 + (int) $moment->format('i');
        $off = self::toMinutes($this->offTime);
        $length = $this->offMinutesPerDay();

        return (($now - $off + self::MINUTES_PER_DAY) % self::MINUTES_PER_DAY) < $length;
    }

    // "Schlaf" (abends/nachts ausgeschaltet), "Arbeit" (morgens ausgeschaltet) oder null (tagsueber)
    public function savingReason(): ?string
    {
        $off = self::toMinutes($this->offTime);

        if ($off >= self::toMinutes(self::EVENING_START) || $off < self::toMinutes(self::MORNING_START)) {
            return 'Schlaf';
        }

        return $off < self::toMinutes(self::MORNING_END) ? 'Arbeit' : null;
    }

    // Stunden pro Tag, die als Ersparnis zaehlen: hoechstens 8, nur abends oder morgens, nie mehr als die Pause selbst
    public function creditedHoursPerDay(): float
    {
        return $this->savingReason() === null
            ? 0.0
            : min(self::CREDITED_HOURS_MAX, $this->offHoursPerDay());
    }

    public function describe(): string
    {
        return $this->onTime === null
            ? "aus ab {$this->offTime} (ca. " . self::ASSUMED_OFF_HOURS . ' Std.)'
            : "aus {$this->offTime} bis {$this->onTime}";
    }

    private static function toMinutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }
}
