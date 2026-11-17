<?php

declare(strict_types=1);

namespace SmartSave;

// Dummy-Wohnung fuer die Demo: dieselben Geraete wie im Standard-Dashboard, auf drei Raeume verteilt.
final class ExampleApartment
{
    /** Raumname => [Breite, [Geraetename aus Dashboard::DEVICE_DEFAULTS]] */
    public const ROOMS = [
        'Wohnzimmer' => [2, ['Heizungsthermostat']],
        'Küche' => [1, ['Steckdose Kueche']],
        'Schlafzimmer' => [1, ['Beleuchtung Schlafzimmer']],
    ];

    /** Geraetename => Geraeteart */
    private const CATEGORIES = [
        'Heizungsthermostat' => DeviceCategory::Heating,
        'Steckdose Kueche' => DeviceCategory::Socket,
        'Beleuchtung Schlafzimmer' => DeviceCategory::Light,
    ];

    public static function create(int $userId, RoomRepository $rooms, DeviceRepository $devices): void
    {
        foreach (self::ROOMS as $roomName => [$width, $deviceNames]) {
            $roomId = $rooms->add($userId, $roomName, $width);
            foreach ($deviceNames as $deviceName) {
                $default = Dashboard::DEVICE_DEFAULTS[$deviceName];
                $devices->add(
                    $userId,
                    $roomId,
                    $deviceName,
                    $default['watt'],
                    $default['hours'],
                    true,
                    (self::CATEGORIES[$deviceName] ?? DeviceCategory::Other)->value
                );
            }
        }
    }
}
