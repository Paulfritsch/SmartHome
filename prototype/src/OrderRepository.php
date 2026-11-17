<?php

declare(strict_types=1);

namespace SmartSave;

use PDO;

// Bestellungen von Zusatztechnik: Lieferadresse, Zahlungsart und Status (offen, geliefert, storniert).
// Beim Bestellen entstehen auch die Buchungen, damit die Abo-Gebuehr sofort stimmt.
final class OrderRepository
{
    public const STATUS_OPEN = 'offen';
    public const STATUS_DELIVERED = 'geliefert';
    public const STATUS_CANCELLED = 'storniert';

    public const MAX_QUANTITY = 5;

    /** Zahlungsart => Text. Im Schulprojekt wird online nichts bezahlt. */
    public const PAYMENT_METHODS = [
        'vor_ort' => 'Bezahlung vor Ort bei der Lieferung',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly BookingRepository $bookings
    ) {
    }

    /**
     * @param array{name?: string, street?: string, zip?: string, city?: string} $address
     * @throws ValidationException
     */
    public function place(User $user, string $productId, int|string $quantity, array $address, string $paymentMethod = 'vor_ort'): int
    {
        $errors = [];

        if (!Catalog::exists($productId)) {
            $errors[] = 'Produkt: bitte ein Produkt aus der Liste wählen.';
        } elseif (!Catalog::canBook($user->monthsElapsed)) {
            $errors[] = 'Dazubuchen geht nur, wenn noch mindestens ' . Catalog::MIN_REMAINING_MONTHS . ' Monate Laufzeit übrig sind.';
        }

        $count = filter_var($quantity, FILTER_VALIDATE_INT);
        if ($count === false || $count < 1 || $count > self::MAX_QUANTITY) {
            $errors[] = 'Anzahl: bitte 1 bis ' . self::MAX_QUANTITY . ' wählen.';
            $count = 0;
        }

        if (!isset(self::PAYMENT_METHODS[$paymentMethod])) {
            $errors[] = 'Zahlungsart: bitte eine der angebotenen Arten wählen.';
        }

        $ship = $this->validateAddress($address, $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo
                ->prepare(
                    'INSERT INTO orders (user_id, product, quantity, monthly_price, start_month, payment_method,
                                         ship_name, ship_street, ship_zip, ship_city)
                     VALUES (:u, :p, :q, :price, :m, :pay, :n, :s, :z, :c)'
                )
                ->execute([
                    'u' => $user->id, 'p' => $productId, 'q' => $count,
                    'price' => Catalog::monthlyPrice($productId, $user->monthsElapsed),
                    'm' => $user->monthsElapsed, 'pay' => $paymentMethod,
                    'n' => $ship['name'], 's' => $ship['street'], 'z' => $ship['zip'], 'c' => $ship['city'],
                ]);
            $orderId = (int) $this->pdo->lastInsertId();

            for ($i = 0; $i < $count; $i++) {
                $this->bookings->book($user->id, $productId, $user->monthsElapsed, $orderId);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $orderId;
    }

    /** @return array<int, array<string, mixed>> Bestellungen eines Nutzers, neueste zuerst */
    public function listForUser(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM orders WHERE user_id = :u ORDER BY id DESC');
        $statement->execute(['u' => $userId]);

        return array_map([self::class, 'toOrder'], $statement->fetchAll());
    }

    /**
     * Nur fuer den Admin-Bereich: alle Bestellungen eines Status mit Kundendaten.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByStatus(string $status, int $limit = 100): array
    {
        $order = $status === self::STATUS_OPEN ? 'o.id ASC' : 'o.id DESC';
        $statement = $this->pdo->prepare(
            "SELECT o.*, u.name AS customer_name, u.email AS customer_email
             FROM orders o JOIN users u ON u.id = o.user_id
             WHERE o.status = :s ORDER BY $order LIMIT :l"
        );
        $statement->bindValue('s', $status);
        $statement->bindValue('l', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map([self::class, 'toOrder'], $statement->fetchAll());
    }

    /** @return array{offen: int, geliefert: int, storniert: int} */
    public function countByStatus(): array
    {
        $counts = [self::STATUS_OPEN => 0, self::STATUS_DELIVERED => 0, self::STATUS_CANCELLED => 0];
        foreach ($this->pdo->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status')->fetchAll() as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** @return array<string, int> Produkt-ID => Stueckzahl aller offenen Bestellungen (zum Packen) */
    public function packingList(): array
    {
        $statement = $this->pdo->prepare('SELECT product, SUM(quantity) AS n FROM orders WHERE status = :s GROUP BY product ORDER BY product');
        $statement->execute(['s' => self::STATUS_OPEN]);

        $list = [];
        foreach ($statement->fetchAll() as $row) {
            $list[(string) $row['product']] = (int) $row['n'];
        }

        return $list;
    }

    /** @return bool false, wenn es keine offene Bestellung mit dieser ID gibt */
    public function markDelivered(int $orderId): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE orders SET status = :done, delivered_at = CURRENT_TIMESTAMP WHERE id = :id AND status = :open"
        );
        $statement->execute(['done' => self::STATUS_DELIVERED, 'id' => $orderId, 'open' => self::STATUS_OPEN]);

        return $statement->rowCount() === 1;
    }

    /**
     * Storniert eine eigene, noch nicht gelieferte Bestellung und entfernt ihre Buchungen.
     *
     * @return bool false, wenn die Bestellung nicht existiert, einem anderen Nutzer gehoert oder schon geliefert ist
     */
    public function cancelOpen(int $userId, int $orderId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'UPDATE orders SET status = :cancelled WHERE id = :id AND user_id = :u AND status = :open'
            );
            $statement->execute([
                'cancelled' => self::STATUS_CANCELLED, 'id' => $orderId, 'u' => $userId, 'open' => self::STATUS_OPEN,
            ]);
            $cancelled = $statement->rowCount() === 1;

            if ($cancelled) {
                $this->pdo
                    ->prepare('DELETE FROM bookings WHERE order_id = :id AND user_id = :u')
                    ->execute(['id' => $orderId, 'u' => $userId]);
            }

            $this->pdo->commit();

            return $cancelled;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $address
     * @param string[] $errors
     * @return array{name: string, street: string, zip: string, city: string}
     */
    private function validateAddress(array $address, array &$errors): array
    {
        $read = static fn (string $key): string => is_string($address[$key] ?? null) ? trim($address[$key]) : '';
        $name = $read('name');
        $street = $read('street');
        $zip = $read('zip');
        $city = $read('city');

        if ($name === '' || mb_strlen($name) > 60) {
            $errors[] = 'Name: bitte 1 bis 60 Zeichen eingeben.';
        }
        if ($street === '' || mb_strlen($street) > 80) {
            $errors[] = 'Straße und Hausnummer: bitte 1 bis 80 Zeichen eingeben.';
        }
        if (preg_match('/^\d{4,5}$/', $zip) !== 1) {
            $errors[] = 'Postleitzahl: bitte 4 oder 5 Ziffern eingeben.';
        }
        if ($city === '' || mb_strlen($city) > 60) {
            $errors[] = 'Ort: bitte 1 bis 60 Zeichen eingeben.';
        }

        return ['name' => $name, 'street' => $street, 'zip' => $zip, 'city' => $city];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function toOrder(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'product' => (string) $row['product'],
            'quantity' => (int) $row['quantity'],
            'monthlyPrice' => (float) $row['monthly_price'],
            'startMonth' => (int) $row['start_month'],
            'status' => (string) $row['status'],
            'paymentMethod' => (string) $row['payment_method'],
            'name' => (string) $row['ship_name'],
            'street' => (string) $row['ship_street'],
            'zip' => (string) $row['ship_zip'],
            'city' => (string) $row['ship_city'],
            'createdAt' => (string) $row['created_at'],
            'deliveredAt' => $row['delivered_at'] !== null ? (string) $row['delivered_at'] : null,
            'customerName' => isset($row['customer_name']) ? (string) $row['customer_name'] : null,
            'customerEmail' => isset($row['customer_email']) ? (string) $row['customer_email'] : null,
        ];
    }
}
