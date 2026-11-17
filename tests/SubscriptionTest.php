<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PHPUnit\Framework\TestCase;
use SmartSave\Catalog;
use SmartSave\Subscription;

final class SubscriptionTest extends TestCase
{
    public function testBaseSubscriptionWithoutExtras(): void
    {
        $subscription = new Subscription(15.0, [], 0);

        $this->assertSame(5.99, $subscription->currentFee());
        $this->assertSame(9.01, $subscription->effectiveMonthly());
        $this->assertSame(0.0, $subscription->effectiveSoFar());
        $this->assertSame(360.0, $subscription->totalSavings24());
        $this->assertSame(143.76, $subscription->totalFees24());
        $this->assertSame(216.24, $subscription->totalEffective24());
        $this->assertSame(24, $subscription->remainingMonths());
    }

    public function testEffectiveSoFarGrowsWithElapsedMonths(): void
    {
        $this->assertSame(54.06, (new Subscription(15.0, [], 6))->effectiveSoFar());   // 6 * 9,01
        $this->assertSame(216.24, (new Subscription(15.0, [], 24))->effectiveSoFar());
    }

    public function testExtrasRaiseTheFeeFromTheirStartMonth(): void
    {
        $bookings = [
            ['startMonth' => 0, 'monthlyPrice' => 0.69],   // gleich bei Start gebucht
            ['startMonth' => 12, 'monthlyPrice' => 4.98],  // in Monat 12 gebucht
        ];
        $subscription = new Subscription(15.0, $bookings, 0);

        $this->assertSame(6.68, $subscription->feeForMonth(0));
        $this->assertSame(6.68, $subscription->feeForMonth(11));
        $this->assertSame(11.66, $subscription->feeForMonth(12));
        $this->assertSame(11.66, $subscription->feeForMonth(23));
        // 12 * 6,68 + 12 * 11,66 = 220,08
        $this->assertSame(220.08, $subscription->totalFees24());
        $this->assertSame(139.92, $subscription->totalEffective24());
    }

    public function testNoFeesAfterTheTermAndEverythingBelongsToTheUser(): void
    {
        $subscription = new Subscription(15.0, [['startMonth' => 0, 'monthlyPrice' => 1.0]], 24);

        $this->assertTrue($subscription->isFinished());
        $this->assertSame(0.0, $subscription->currentFee());
        $this->assertSame(15.0, $subscription->effectiveMonthly());
        $this->assertSame(15.0, $subscription->monthlyAfterTerm());
        $this->assertSame(0, $subscription->remainingMonths());
    }

    public function testNegativeEffectiveWhenSavingsAreBelowTheFee(): void
    {
        $subscription = new Subscription(4.0, [], 3);

        $this->assertSame(-1.99, $subscription->effectiveMonthly());
        $this->assertSame(-5.97, $subscription->effectiveSoFar());
    }

    public function testMonthsElapsedIsClamped(): void
    {
        $this->assertSame(24, (new Subscription(10.0, [], 99))->monthsElapsed());
        $this->assertSame(0, (new Subscription(10.0, [], -5))->monthsElapsed());
    }

    public function testBookingBeforeItsStartMonthDoesNotCountYet(): void
    {
        // Zeitraffer zurueck auf Monat 0, obwohl die Buchung erst ab Monat 10 gilt
        $subscription = new Subscription(15.0, [['startMonth' => 10, 'monthlyPrice' => 5.0]], 0);

        $this->assertSame(5.99, $subscription->currentFee());
        $this->assertSame(0.0, $subscription->currentExtras());
    }

    public function testCatalogMonthlyPriceSpreadsTotalOverRemainingMonths(): void
    {
        $this->assertSame(0.5, Catalog::monthlyPrice('led', 0));          // 12 / 24
        $this->assertSame(1.0, Catalog::monthlyPrice('led', 12));         // 12 / 12
        $this->assertSame(4.0, Catalog::monthlyPrice('thermostat', 12));  // 48 / 12
        $this->assertSame(2.0, Catalog::monthlyPrice('thermostat', 0));
    }

    public function testCatalogBookingWindow(): void
    {
        $this->assertTrue(Catalog::canBook(0));
        $this->assertTrue(Catalog::canBook(18));  // 6 Monate uebrig
        $this->assertFalse(Catalog::canBook(19));
        $this->assertFalse(Catalog::canBook(24));
    }

    public function testCatalogRejectsUnknownProduct(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Catalog::monthlyPrice('raumschiff', 0);
    }

    public function testAllProductsHaveAPositiveTotalAndNames(): void
    {
        foreach (Catalog::PRODUCTS as $id => $product) {
            $this->assertGreaterThan(0.0, $product['total'], $id);
            $this->assertNotSame('', $product['name'], $id);
        }
        foreach (array_keys(Catalog::BASE_INCLUDED) as $id) {
            $this->assertTrue(Catalog::exists($id), "Basis-Produkt $id fehlt im Katalog");
        }
    }
}
