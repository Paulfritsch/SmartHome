<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PHPUnit\Framework\TestCase;
use SmartSave\Dashboard;
use SmartSave\SavingsCalculator;

final class DashboardTest extends TestCase
{
    public function testFirstLoadUsesDefaultsAndSwitchesAllDevicesOn(): void
    {
        $dashboard = Dashboard::fromRequest([], false);

        $this->assertSame(Dashboard::DEFAULT_OLD_MONTHLY_COST, $dashboard->getOldMonthlyCost());
        $this->assertSame(SavingsCalculator::DEFAULT_PRICE_PER_KWH, $dashboard->getPricePerKwh());
        $this->assertCount(count(Dashboard::DEVICE_DEFAULTS), $dashboard->getDevices());
        foreach ($dashboard->getDevices() as $device) {
            $this->assertTrue($device->isOn());
        }
        $this->assertSame([], $dashboard->getErrors());
    }

    public function testDefaultsGiveSensibleSavings(): void
    {
        $dashboard = Dashboard::fromRequest([], false);

        // 1.8 kW*6h*30 + 0.09 kW*4h*30 + 1.5 kW*1.5h*30 = 402.3 kWh
        $this->assertEqualsWithDelta(402.3, $dashboard->getMonthlyConsumptionKwh(), 0.0001);
        $this->assertEqualsWithDelta(140.805, $dashboard->getNewMonthlyCost(), 0.0001);
        $this->assertEqualsWithDelta(9.195, $dashboard->getSavings(), 0.0001);
    }

    public function testPostWithoutCheckboxesSwitchesDeviceOff(): void
    {
        $dashboard = Dashboard::fromRequest(['old_cost' => '150'], true);

        foreach ($dashboard->getDevices() as $device) {
            $this->assertFalse($device->isOn());
        }
        $this->assertSame(0.0, $dashboard->getMonthlyConsumptionKwh());
        $this->assertSame(150.0, $dashboard->getSavings());
    }

    public function testUserInputOverridesWattageAndHours(): void
    {
        $dashboard = Dashboard::fromRequest([
            'device_on' => ['Steckdose Kueche' => '1'],
            'device_watt' => ['Steckdose Kueche' => '1000'],
            'device_hours' => ['Steckdose Kueche' => '2'],
        ], true);

        $kitchen = $this->deviceByName($dashboard, 'Steckdose Kueche');
        $this->assertTrue($kitchen->isOn());
        $this->assertSame(1000.0, $kitchen->getWattage());
        $this->assertSame(2.0, $kitchen->getHoursPerDay());
        // nur die Kueche ist an: 1 kW * 2 h * 30 Tage
        $this->assertEqualsWithDelta(60.0, $dashboard->getMonthlyConsumptionKwh(), 0.0001);
    }

    public function testCustomPriceIsUsed(): void
    {
        $dashboard = Dashboard::fromRequest([
            'old_cost' => '100',
            'price_per_kwh' => '0.5',
            'device_on' => ['Steckdose Kueche' => '1'],
            'device_watt' => ['Steckdose Kueche' => '1000'],
            'device_hours' => ['Steckdose Kueche' => '2'],
        ], true);

        // 60 kWh * 0.50 EUR = 30 EUR neue Kosten
        $this->assertEqualsWithDelta(30.0, $dashboard->getNewMonthlyCost(), 0.0001);
        $this->assertEqualsWithDelta(70.0, $dashboard->getSavings(), 0.0001);
    }

    public function testEmptyFieldFallsBackToDefaultWithoutError(): void
    {
        $dashboard = Dashboard::fromRequest([
            'device_watt' => ['Steckdose Kueche' => ''],
            'device_hours' => ['Steckdose Kueche' => ''],
        ], true);

        $kitchen = $this->deviceByName($dashboard, 'Steckdose Kueche');
        $this->assertSame(Dashboard::DEVICE_DEFAULTS['Steckdose Kueche']['watt'], $kitchen->getWattage());
        $this->assertSame(Dashboard::DEVICE_DEFAULTS['Steckdose Kueche']['hours'], $kitchen->getHoursPerDay());
        $this->assertSame([], $dashboard->getErrors());
    }

    /**
     * @dataProvider invalidInputProvider
     * @param array<string, mixed> $post
     */
    public function testInvalidInputIsRejectedAndFallsBackToDefault(array $post): void
    {
        $dashboard = Dashboard::fromRequest($post, true);

        $this->assertNotEmpty($dashboard->getErrors());
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function invalidInputProvider(): array
    {
        return [
            'negative Stunden' => [['device_hours' => ['Steckdose Kueche' => '-1']]],
            'mehr als 24 Stunden' => [['device_hours' => ['Steckdose Kueche' => '25']]],
            'negative Watt' => [['device_watt' => ['Steckdose Kueche' => '-100']]],
            'Watt kein Zahlenwert' => [['device_watt' => ['Steckdose Kueche' => 'abc']]],
            'Watt unrealistisch hoch' => [['device_watt' => ['Steckdose Kueche' => '99999']]],
        ];
    }

    public function testInvalidHoursFallBackToDefaultHours(): void
    {
        $dashboard = Dashboard::fromRequest(['device_hours' => ['Steckdose Kueche' => '25']], true);

        $kitchen = $this->deviceByName($dashboard, 'Steckdose Kueche');
        $this->assertSame(Dashboard::DEVICE_DEFAULTS['Steckdose Kueche']['hours'], $kitchen->getHoursPerDay());
    }

    public function testArrayInsteadOfNumberIsRejectedWithoutPhpWarning(): void
    {
        $dashboard = Dashboard::fromRequest(['device_watt' => ['Steckdose Kueche' => ['1', '2']]], true);

        $this->assertNotEmpty($dashboard->getErrors());
        $this->assertSame(
            Dashboard::DEVICE_DEFAULTS['Steckdose Kueche']['watt'],
            $this->deviceByName($dashboard, 'Steckdose Kueche')->getWattage()
        );
    }

    public function testNegativeOldCostIsRejected(): void
    {
        $dashboard = Dashboard::fromRequest(['old_cost' => '-5'], true);

        $this->assertSame(Dashboard::DEFAULT_OLD_MONTHLY_COST, $dashboard->getOldMonthlyCost());
        $this->assertNotEmpty($dashboard->getErrors());
    }

    public function testNonNumericPriceIsRejected(): void
    {
        $dashboard = Dashboard::fromRequest(['price_per_kwh' => 'teuer'], true);

        $this->assertSame(SavingsCalculator::DEFAULT_PRICE_PER_KWH, $dashboard->getPricePerKwh());
        $this->assertNotEmpty($dashboard->getErrors());
    }

    public function testMonitorTracksCurrentPowerOfSwitchedOnDevices(): void
    {
        $dashboard = Dashboard::fromRequest([
            'device_on' => ['Steckdose Kueche' => '1'],
            'device_watt' => ['Steckdose Kueche' => '1500'],
        ], true);

        $this->assertEqualsWithDelta(1.5, $dashboard->getCurrentPowerKw(), 0.0001);
    }

    private function deviceByName(Dashboard $dashboard, string $name): \SmartSave\Device
    {
        foreach ($dashboard->getDevices() as $device) {
            if ($device->getName() === $name) {
                return $device;
            }
        }
        $this->fail("Geraet $name nicht gefunden");
    }
}
