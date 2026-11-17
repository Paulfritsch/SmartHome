<?php

declare(strict_types=1);

namespace SmartSave;

use PDO;

// Dazugebuchte Technik eines Nutzers. Der Monatspreis wird bei der Buchung festgehalten.
final class BookingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @throws ValidationException
     */
    public function book(int $userId, string $productId, int $currentMonth, ?int $orderId = null): int
    {
        if (!Catalog::exists($productId)) {
            throw new ValidationException(['Produkt: bitte ein Produkt aus der Liste wählen.']);
        }
        if (!Catalog::canBook($currentMonth)) {
            throw new ValidationException([
                'Dazubuchen geht nur, wenn noch mindestens ' . Catalog::MIN_REMAINING_MONTHS . ' Monate Laufzeit übrig sind.',
            ]);
        }

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM bookings WHERE user_id = :u');
        $count->execute(['u' => $userId]);
        if ((int) $count->fetchColumn() >= Catalog::MAX_BOOKINGS_PER_USER) {
            throw new ValidationException(['Du kannst höchstens ' . Catalog::MAX_BOOKINGS_PER_USER . ' Zusatzprodukte buchen.']);
        }

        $this->pdo
            ->prepare('INSERT INTO bookings (user_id, product, start_month, monthly_price, order_id) VALUES (:u, :p, :m, :price, :o)')
            ->execute([
                'u' => $userId,
                'p' => $productId,
                'm' => $currentMonth,
                'price' => Catalog::monthlyPrice($productId, $currentMonth),
                'o' => $orderId,
            ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int, array{id: int, product: string, startMonth: int, monthlyPrice: float}> */
    public function listForUser(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT id, product, start_month, monthly_price FROM bookings WHERE user_id = :u ORDER BY id');
        $statement->execute(['u' => $userId]);

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'product' => (string) $row['product'],
                'startMonth' => (int) $row['start_month'],
                'monthlyPrice' => (float) $row['monthly_price'],
            ],
            $statement->fetchAll()
        );
    }

    /** @return bool false, wenn die Buchung nicht existiert oder einem anderen Nutzer gehoert */
    public function cancel(int $userId, int $bookingId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM bookings WHERE id = :id AND user_id = :u');
        $statement->execute(['id' => $bookingId, 'u' => $userId]);

        return $statement->rowCount() === 1;
    }
}
