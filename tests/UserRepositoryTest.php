<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use SmartSave\Database;
use SmartSave\UserRepository;
use SmartSave\ValidationException;

final class UserRepositoryTest extends TestCase
{
    use RejectsInvalidInput;

    private PDO $pdo;
    private UserRepository $users;

    protected function setUp(): void
    {
        $this->pdo = Database::connect(':memory:');
        $this->users = new UserRepository($this->pdo);
    }

    public function testRegisterCreatesUserWithPriceAndDefaultOldCost(): void
    {
        $user = $this->users->register('Anna', 'Anna@Example.de ', 'geheim123', '0,35');

        $this->assertSame('anna@example.de', $user->email);
        $this->assertSame(0.35, $user->pricePerKwh);
        $this->assertSame(150.0, $user->oldMonthlyCost);

        $this->users->updateSettings($user->id, '0,28', '200');
        $reloaded = $this->users->findById($user->id);
        $this->assertSame(0.28, $reloaded->pricePerKwh);
        $this->assertSame(200.0, $reloaded->oldMonthlyCost);
    }

    public function testPasswordIsStoredAsHashNotPlaintext(): void
    {
        $this->users->register('Anna', 'anna@example.de', 'geheim123', 0.35);

        $hash = $this->pdo->query('SELECT password_hash FROM users')->fetchColumn();

        $this->assertNotSame('geheim123', $hash);
        $this->assertTrue(password_verify('geheim123', $hash));
    }

    public function testLoginOnlyWorksWithCorrectCredentials(): void
    {
        $registered = $this->users->register('Anna', 'anna@example.de', 'geheim123', 0.35);

        $user = $this->users->authenticate('ANNA@example.de', 'geheim123');
        $this->assertNotNull($user);
        $this->assertSame($registered->id, $user->id);

        $this->assertNull($this->users->authenticate('anna@example.de', 'falsch12345'));
        $this->assertNull($this->users->authenticate('niemand@example.de', 'geheim123'));
        $this->assertNull($this->users->authenticate("' OR '1'='1", "' OR '1'='1"), 'SQL-Injection');
    }

    public function testInvalidRegistrationsAndDuplicateEmailsAreRejected(): void
    {
        $this->users->register('Anna', 'anna@example.de', 'geheim123', 0.35);

        $this->assertAllRejected(
            self::invalidRegistrations() + ['doppelte E-Mail' => ['Anna 2', 'ANNA@example.de', 'anderes123', 0.30]],
            fn (string $name, string $email, string $password, mixed $price) => $this->users->register($name, $email, $password, $price)
        );
    }

    /** @return array<string, array{string, string, string, mixed}> */
    public static function invalidRegistrations(): array
    {
        return [
            'leerer Name' => ['  ', 'a@example.de', 'geheim123', 0.35],
            'ungueltige E-Mail' => ['Anna', 'keine-mail', 'geheim123', 0.35],
            'Passwort zu kurz' => ['Anna', 'a@example.de', 'kurz', 0.35],
            'Strompreis keine Zahl' => ['Anna', 'a@example.de', 'geheim123', 'viel'],
            'Strompreis negativ' => ['Anna', 'a@example.de', 'geheim123', -0.1],
            'Strompreis unrealistisch hoch' => ['Anna', 'a@example.de', 'geheim123', 50],
            'Strompreis leer' => ['Anna', 'a@example.de', 'geheim123', null],
        ];
    }
}
