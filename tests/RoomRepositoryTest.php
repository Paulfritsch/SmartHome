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

    public function testRoomsCanBeCreatedAndNamed(): void
    {
        $this->rooms->add($this->anna, '  Küche ', 1);
        $this->rooms->add($this->anna, 'Wohnzimmer', 2);

        $list = $this->rooms->listForUser($this->anna);

        $this->assertSame(['Küche', 'Wohnzimmer'], array_column($list, 'name'));
        $this->assertSame([1, 2], array_column($list, 'width'));
    }

    public function testRoomCanBeRenamed(): void
    {
        $id = $this->rooms->add($this->anna, 'Flur');

        $this->assertTrue($this->rooms->update($this->anna, $id, 'Diele', 2));
        $this->assertSame('Diele', $this->rooms->listForUser($this->anna)[0]['name']);
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

    public function testInvalidRoomsAreRejected(): void
    {
        $this->assertAllRejected(self::invalidRooms(), fn (string $name, int $width) => $this->rooms->add($this->anna, $name, $width));
    }

    public static function invalidRooms(): array
    {
        return [
            'leerer Name' => ['   ', 1],
            'Name zu lang' => [str_repeat('x', RoomRepository::MAX_NAME_LENGTH + 1), 1],
            'ungueltige Breite' => ['Bad', 7],
        ];
    }

    public function testRoomLimitPerUser(): void
    {
        for ($i = 0; $i < RoomRepository::MAX_ROOMS_PER_USER; $i++) {
            $this->rooms->add($this->anna, "Raum $i");
        }

        $this->expectException(ValidationException::class);
        $this->rooms->add($this->anna, 'Einer zu viel');
    }
}
