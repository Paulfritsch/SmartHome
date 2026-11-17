<?php

declare(strict_types=1);

namespace SmartSave;

use PDO;

// Geraete eines Nutzers. Jede Abfrage ist an die Nutzer-ID gebunden: Wer fremde IDs schickt,
// aendert und sieht nichts.
final class DeviceRepository
{
    public const MAX_NAME_LENGTH = 40;
    public const MAX_DEVICES_PER_USER = 30;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @throws ValidationException
     */
    public function add(
        int $userId,
        int $roomId,
        string $name,
        float|string|null $wattage,
        float|string|null $hoursPerDay,
        bool $isOn = true,
        mixed $category = 'sonstiges',
        ?string $offTime = null,
        ?string $onTime = null
    ): int {
        [$name, $watt, $hours, $cat, $schedule] = $this->validate($name, $wattage, $hoursPerDay, $category, $offTime, $onTime);
        $this->assertRoomBelongsToUser($userId, $roomId);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM devices WHERE user_id = :u');
        $count->execute(['u' => $userId]);
        if ((int) $count->fetchColumn() >= self::MAX_DEVICES_PER_USER) {
            throw new ValidationException(['Du kannst höchstens ' . self::MAX_DEVICES_PER_USER . ' Geräte anlegen.']);
        }

        $this->pdo
            ->prepare(
                'INSERT INTO devices (user_id, room_id, name, wattage, hours_per_day, is_on, category, off_time, on_time)
                 VALUES (:u, :r, :n, :w, :h, :on, :c, :off, :ontime)'
            )
            ->execute([
                'u' => $userId, 'r' => $roomId, 'n' => $name, 'w' => $watt, 'h' => $hours, 'on' => $isOn ? 1 : 0,
                'c' => $cat->value, 'off' => $schedule?->offTime, 'ontime' => $schedule?->onTime,
            ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<int, array{id: int, roomId: int, device: Device}> Geraete des Nutzers inkl. Datenbank-ID
     */
    public function listForUser(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM devices WHERE user_id = :u ORDER BY id');
        $statement->execute(['u' => $userId]);

        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $schedule = $row['off_time'] !== null ? new Schedule((string) $row['off_time'], $row['on_time'] !== null ? (string) $row['on_time'] : null) : null;
            $device = new Device(
                (string) $row['name'],
                (float) $row['wattage'],
                (float) $row['hours_per_day'],
                DeviceCategory::tryFrom((string) $row['category']) ?? DeviceCategory::Other,
                $schedule
            );
            $row['is_on'] ? $device->turnOn() : $device->turnOff();
            $result[] = ['id' => (int) $row['id'], 'roomId' => (int) $row['room_id'], 'device' => $device];
        }

        return $result;
    }

    /**
     * @throws ValidationException
     * @return bool false, wenn das Geraet nicht existiert oder einem anderen Nutzer gehoert
     */
    public function update(
        int $userId,
        int $deviceId,
        string $name,
        float|string|null $wattage,
        float|string|null $hoursPerDay,
        mixed $category = 'sonstiges',
        ?string $offTime = null,
        ?string $onTime = null
    ): bool {
        [$name, $watt, $hours, $cat, $schedule] = $this->validate($name, $wattage, $hoursPerDay, $category, $offTime, $onTime);

        $statement = $this->pdo->prepare(
            'UPDATE devices SET name = :n, wattage = :w, hours_per_day = :h, category = :c, off_time = :off, on_time = :ontime
             WHERE id = :id AND user_id = :u'
        );
        $statement->execute([
            'n' => $name, 'w' => $watt, 'h' => $hours, 'c' => $cat->value,
            'off' => $schedule?->offTime, 'ontime' => $schedule?->onTime, 'id' => $deviceId, 'u' => $userId,
        ]);

        return $statement->rowCount() === 1;
    }

    public function setOn(int $userId, int $deviceId, bool $isOn): bool
    {
        $statement = $this->pdo->prepare('UPDATE devices SET is_on = :on WHERE id = :id AND user_id = :u');
        $statement->execute(['on' => $isOn ? 1 : 0, 'id' => $deviceId, 'u' => $userId]);

        return $statement->rowCount() === 1;
    }

    public function delete(int $userId, int $deviceId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM devices WHERE id = :id AND user_id = :u');
        $statement->execute(['id' => $deviceId, 'u' => $userId]);

        return $statement->rowCount() === 1;
    }

    private function assertRoomBelongsToUser(int $userId, int $roomId): void
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM rooms WHERE id = :r AND user_id = :u');
        $statement->execute(['r' => $roomId, 'u' => $userId]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new ValidationException(['Raum: bitte einen deiner Räume auswählen.']);
        }
    }

    /**
     * @return array{0: string, 1: float, 2: float, 3: DeviceCategory, 4: ?Schedule}
     * @throws ValidationException
     */
    private function validate(string $name, mixed $wattage, mixed $hoursPerDay, mixed $category, ?string $offTime, ?string $onTime): array
    {
        $errors = [];

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors[] = 'Gerätename: bitte 1 bis ' . self::MAX_NAME_LENGTH . ' Zeichen eingeben.';
        }

        $watt = UserRepository::parseNumber($wattage, 'Leistung (Watt)', 0.0, Dashboard::MAX_WATTAGE, $errors);
        $hours = UserRepository::parseNumber($hoursPerDay, 'Nutzung (Std./Tag)', 0.0, Dashboard::MAX_HOURS_PER_DAY, $errors);

        $cat = DeviceCategory::tryFromInput($category);
        if ($cat === null) {
            $errors[] = 'Geräteart: bitte eine der angebotenen Arten wählen.';
        }

        $schedule = null;
        $hasOff = $offTime !== null && trim($offTime) !== '';
        $hasOn = $onTime !== null && trim($onTime) !== '';
        if (!$hasOff && $hasOn) {
            $errors[] = 'Zeitplan: Für die Einschaltzeit brauchst du auch eine Ausschaltzeit.';
        } elseif ($hasOff) {
            try {
                $schedule = new Schedule($offTime, $onTime);
            } catch (ValidationException $e) {
                array_push($errors, ...$e->getErrors());
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [$name, $watt, $hours, $cat, $schedule];
    }
}
