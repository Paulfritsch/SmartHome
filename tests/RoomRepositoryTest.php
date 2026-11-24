<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PHPUnit\Framework\TestCase;
use SmartSave\Database;
use SmartSave\DeviceRepository;
use SmartSave\RoomRepository;
use SmartSave\UserRepository;
use SmartSave\ValidationException;

final class RoomRepositoryTest extends TestCase
{
    use RejectsInvalidInput;

    private RoomRepository $rooms;
    private DeviceRepository $devices;
    private int $anna;
    private int $ben;

    protected function setUp(): void
    {
        $pdo = Database::connect(':memory:');
        $users = new UserRepository($pdo);
        $this->rooms = new RoomRepository($pdo);
        $this->devices = new DeviceRepository($pdo);
        $this->anna = $users->register('Anna', 'anna@example.de', 'geheim123', 0.35)->id;
        $this->ben = $users->register('Ben', 'ben@example.de', 'geheim456', 0.30)->id;
    }

    public function testRoomsCanBeCreatedNamedAndRenamed(): void
    {
        $this->rooms->add($this->anna, '  Küche ', 1);
        $flur = $this->rooms->add($this->anna, 'Flur', 2);

        $list = $this->rooms->listForUser($this->anna);
        $this->assertSame(['Küche', 'Flur'], array_column($list, 'name'));
        $this->assertSame([1, 2], array_column($list, 'width'));

        $this->assertTrue($this->rooms->update($this->anna, $flur, 'Diele', 2));
        $this->assertSame(['Küche', 'Diele'], array_column($this->rooms->listForUser($this->anna), 'name'));
    }

    public function testUserSeesAndChangesOnlyOwnRooms(): void
    {
        $id = $this->rooms->add($this->anna, 'Bad');

        $this->assertSame([], $this->rooms->listForUser($this->ben));
        $this->assertFalse($this->rooms->update($this->ben, $id, 'Gehackt', 1));
        $this->assertFalse($this->rooms->delete($this->ben, $id));
        $this->assertSame('Bad', $this->rooms->listForUser($this->anna)[0]['name']);
    }

    public function testDeletingRoomDeletesItsDevices(): void
    {
        $room = $this->rooms->add($this->anna, 'Bad');
        $this->devices->add($this->anna, $room, 'Lampe', 40, 2);

        $this->assertTrue($this->rooms->delete($this->anna, $room));
        $this->assertSame([], $this->devices->listForUser($this->anna));
    }

    public function testInvalidRoomsAndTooManyRoomsAreRejected(): void
    {
        $this->assertAllRejected(self::invalidRooms(), fn (string $name, int $width) => $this->rooms->add($this->anna, $name, $width));

        for ($i = 0; $i < RoomRepository::MAX_ROOMS_PER_USER; $i++) {
            $this->rooms->add($this->anna, "Raum $i");
        }
        $this->assertAllRejected(['Raumlimit' => []], fn () => $this->rooms->add($this->anna, 'Einer zu viel'));
    }

    /** @return array<string, array{string, int}> */
    public static function invalidRooms(): array
    {
        return [
            'leerer Name' => ['   ', 1],
            'Name zu lang' => [str_repeat('x', RoomRepository::MAX_NAME_LENGTH + 1), 1],
            'ungueltige Breite' => ['Bad', 7],
        ];
    }
}
