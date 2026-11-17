<?php

declare(strict_types=1);

namespace SmartSave;

use PDO;
use PDOException;

// Registrierung, Anmeldung und Kontoeinstellungen. Passwoerter werden nie im Klartext gespeichert.
final class UserRepository
{
    public const MIN_PASSWORD_LENGTH = 8;
    public const MAX_NAME_LENGTH = 60;
    public const MAX_PRICE_PER_KWH = 5.0;
    public const MAX_OLD_MONTHLY_COST = 100000.0;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @throws ValidationException bei ungueltigen Eingaben oder bereits vergebener E-Mail
     */
    public function register(
        string $name,
        string $email,
        string $password,
        float|string|null $pricePerKwh,
        float|string|null $oldMonthlyCost = null
    ): User {
        $errors = [];

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors[] = 'Name: bitte 1 bis ' . self::MAX_NAME_LENGTH . ' Zeichen eingeben.';
        }

        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
            $errors[] = 'E-Mail: bitte eine gueltige Adresse eingeben.';
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = 'Passwort: mindestens ' . self::MIN_PASSWORD_LENGTH . ' Zeichen.';
        }

        $price = self::parseNumber($pricePerKwh, 'Strompreis (EUR/kWh)', 0.01, self::MAX_PRICE_PER_KWH, $errors);
        $oldCost = $oldMonthlyCost === null || $oldMonthlyCost === ''
            ? Dashboard::DEFAULT_OLD_MONTHLY_COST
            : self::parseNumber($oldMonthlyCost, 'Bisherige Energiekosten (EUR/Monat)', 0.0, self::MAX_OLD_MONTHLY_COST, $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO users (name, email, password_hash, price_per_kwh, old_monthly_cost)
                 VALUES (:name, :email, :hash, :price, :old)'
            );
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'price' => $price,
                'old' => $oldCost,
            ]);
        } catch (PDOException $e) {
            // SQLSTATE 23000 = UNIQUE-Verletzung (E-Mail existiert schon)
            if ($e->getCode() === '23000') {
                throw new ValidationException(['Mit dieser E-Mail gibt es schon ein Konto.']);
            }
            throw $e;
        }

        return new User((int) $this->pdo->lastInsertId(), $name, $email, $price, $oldCost);
    }

    /** @return User|null null bei unbekannter E-Mail oder falschem Passwort (bewusst nicht unterscheidbar) */
    public function authenticate(string $email, string $password): ?User
    {
        $row = $this->fetchRow('email', strtolower(trim($email)));

        // Auch bei unbekannter E-Mail einen Hash pruefen, damit die Antwortzeit nicht verraet,
        // ob es das Konto gibt.
        $hash = $row['password_hash'] ?? password_hash('dummy-passwort', PASSWORD_DEFAULT);
        $valid = password_verify($password, $hash);

        return ($row !== null && $valid) ? self::toUser($row) : null;
    }

    public function findById(int $id): ?User
    {
        $row = $this->fetchRow('id', $id);

        return $row === null ? null : self::toUser($row);
    }

    /**
     * @throws ValidationException
     */
    public function updateSettings(int $userId, float|string|null $pricePerKwh, float|string|null $oldMonthlyCost): void
    {
        $errors = [];
        $price = self::parseNumber($pricePerKwh, 'Strompreis (EUR/kWh)', 0.01, self::MAX_PRICE_PER_KWH, $errors);
        $oldCost = self::parseNumber($oldMonthlyCost, 'Bisherige Energiekosten (EUR/Monat)', 0.0, self::MAX_OLD_MONTHLY_COST, $errors);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->pdo
            ->prepare('UPDATE users SET price_per_kwh = :price, old_monthly_cost = :old WHERE id = :id')
            ->execute(['price' => $price, 'old' => $oldCost, 'id' => $userId]);
    }

    /**
     * Macht ein Konto zum Admin. Gedacht fuer das Skript prototype/bin/make-admin.php,
     * nicht fuer eine Webseite: Admin wird man nur mit Zugriff auf den Server.
     *
     * @return bool false, wenn es kein Konto mit dieser E-Mail gibt
     */
    public function setAdmin(string $email, bool $isAdmin = true): bool
    {
        $statement = $this->pdo->prepare('UPDATE users SET is_admin = :a WHERE email = :e');
        $statement->execute(['a' => $isAdmin ? 1 : 0, 'e' => strtolower(trim($email))]);

        return $statement->rowCount() === 1;
    }

    /**
     * Demo-Zeitraffer: so tun, als waeren schon $months Monate der Laufzeit vergangen.
     *
     * @throws ValidationException
     */
    public function setMonthsElapsed(int $userId, int $months): void
    {
        if ($months < 0 || $months > Catalog::TERM_MONTHS) {
            throw new ValidationException(['Laufzeit: bitte einen Wert zwischen 0 und ' . Catalog::TERM_MONTHS . ' Monaten wählen.']);
        }

        $this->pdo
            ->prepare('UPDATE users SET months_elapsed = :m WHERE id = :id')
            ->execute(['m' => $months, 'id' => $userId]);
    }

    /** @return array<string, mixed>|null */
    private function fetchRow(string $column, string|int $value): ?array
    {
        // $column kommt nur aus dem Code oben, nie aus Nutzereingaben
        $statement = $this->pdo->prepare("SELECT * FROM users WHERE $column = :value");
        $statement->execute(['value' => $value]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $row */
    private static function toUser(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            (float) $row['price_per_kwh'],
            (float) $row['old_monthly_cost'],
            (int) ($row['months_elapsed'] ?? 0),
            (int) ($row['is_admin'] ?? 0) === 1
        );
    }

    /**
     * Akzeptiert auch ein Komma als Dezimalzeichen ("0,35"), weil Nutzer das so tippen.
     *
     * @param string[] $errors
     */
    public static function parseNumber(mixed $raw, string $label, float $min, float $max, array &$errors): float
    {
        if (is_string($raw)) {
            $raw = str_replace(',', '.', trim($raw));
        }
        if (!is_int($raw) && !is_float($raw) && !(is_string($raw) && is_numeric($raw))) {
            $errors[] = "$label: bitte eine Zahl eingeben.";
            return 0.0;
        }

        $value = (float) $raw;
        if (!is_finite($value) || $value < $min || $value > $max) {
            $errors[] = "$label: Wert muss zwischen $min und $max liegen.";
            return 0.0;
        }

        return $value;
    }
}
