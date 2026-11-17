<?php

declare(strict_types=1);

namespace SmartSave;

use PDO;

// Raeume (Zimmer) einer Wohnung. Auch hier ist jede Abfrage an die Nutzer-ID gebunden.
final class RoomRepository
{
    public const MAX_NAME_LENGTH = 30;
    public const MAX_ROOMS_PER_USER = 12;
    public const WIDTHS = [1, 2]; // 1 = kleiner Raum, 2 = grosser Raum (doppelte Breite)

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @throws ValidationException
     */
    public function add(int $userId, string $name, int $width = 1): int
    {
        $name = $this->validateName($name);
        $width = $this->validateWidth($width);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM rooms WHERE user_id = :u');
        $count->execute(['u' => $userId]);
        if ((int) $count->fetchColumn() >= self::MAX_ROOMS_PER_USER) {
            throw new ValidationException(['Du kannst höchstens ' . self::MAX_ROOMS_PER_USER . ' Räume anlegen.']);
        }

        $this->pdo
            ->prepare('INSERT INTO rooms (user_id, name, width) VALUES (:u, :n, :w)')
            ->execute(['u' => $userId, 'n' => $name, 'w' => $width]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int, array{id: int, name: string, width: int}> */
    public function listForUser(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT id, name, width FROM rooms WHERE user_id = :u ORDER BY id');
        $statement->execute(['u' => $userId]);

        return array_map(
            static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'width' => (int) $row['width']],
            $statement->fetchAll()
        );
    }

    /**
     * @throws ValidationException
     * @return bool false, wenn der Raum nicht existiert oder einem anderen Nutzer gehoert
     */
    public function update(int $userId, int $roomId, string $name, int $width): bool
    {
        $name = $this->validateName($name);
        $width = $this->validateWidth($width);

        $statement = $this->pdo->prepare('UPDATE rooms SET name = :n, width = :w WHERE id = :id AND user_id = :u');
        $statement->execute(['n' => $name, 'w' => $width, 'id' => $roomId, 'u' => $userId]);

        return $statement->rowCount() === 1;
    }

    /** Loescht den Raum samt seiner Geraete. */
    public function delete(int $userId, int $roomId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM rooms WHERE id = :id AND user_id = :u');
        $statement->execute(['id' => $roomId, 'u' => $userId]);

        return $statement->rowCount() === 1;
    }

    /** @throws ValidationException */
    private function validateName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new ValidationException(['Raumname: bitte 1 bis ' . self::MAX_NAME_LENGTH . ' Zeichen eingeben.']);
        }

        return $name;
    }

    /** @throws ValidationException */
    private function validateWidth(int $width): int
    {
        if (!in_array($width, self::WIDTHS, true)) {
            throw new ValidationException(['Raumgröße: bitte klein oder groß wählen.']);
        }

        return $width;
    }
}
