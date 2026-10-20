<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/booking_return.php';
require_once dirname(__DIR__, 2) . '/includes/public_login_destination.php';

final class BookingReturnTest extends TestCase
{
    public function testSpecificVariantSurvivesLoginRegistrationAndChildRequest(): void
    {
        $purchase = 'produkt.php?id=143&variant_id=671#varianta-671';
        foreach ([$purchase, 'moje_osoby.php?return=' . rawurlencode($purchase),
            'registrace_sportovce.php?return=' . rawurlencode($purchase)] as $destination) {
            self::assertSame($destination, \publicLoginDestination($destination, false));
            self::assertSame($destination, \bookingRegistrationDestination($destination, 'rodina'));
            self::assertSame($destination, \bookingReturnDestination($destination));
        }
        self::assertSame($purchase, \bookingPurchaseReturn($purchase));
        self::assertSame('moje_osoby.php', \bookingRegistrationDestination(null, 'rodina'));
        self::assertSame('sportovni_prehled.php', \bookingRegistrationDestination('', 'sport'));
    }

    public function testReservationTimesAndExistingEventAnchorsStillWork(): void
    {
        foreach (['rezervovat.php?lekce_id=2&slot_od=15%3A30&slot_do=16%3A00&waitlist=1',
            'klubovy_kalendar.php?zobrazeni=register#akce-12', 'eshop.php#kosik',
            'eshop.php?kategorie=Krou%C5%BEky&q=D%C4%9Btsk%C3%A9+kolo'] as $destination) {
            self::assertSame($destination, \bookingReturnDestination($destination));
        }
    }

    public function testUntrustedReturnsFailClosedIncludingEncodedAndNestedInput(): void
    {
        foreach (['https://example.test', '//example.test', '../admin.php', 'odhlaseni.php',
            'potvrdit.php?akce=approve', 'neexistuje.php', "eshop.php\r\nLocation: https://example.test",
            'eshop.php?x=%0D%0ALocation%3Aevil', 'eshop.php?x=%250A', 'eshop.php?x=%252F%252Fevil',
            'eshop.php?redirect=https%3A%2F%2Fevil.test', 'produkt.php?id=1&id=2',
            'moje_osoby.php?return=' . rawurlencode('//evil.test'),
            'moje_osoby.php?return=' . rawurlencode(rawurlencode('produkt.php?id=2')),
            'moje_osoby.php?return=' . rawurlencode('moje_osoby.php?return=eshop.php'),
            ['produkt.php?id=2']] as $unsafe) {
            self::assertSame('', \bookingReturnDestination($unsafe));
            self::assertSame('moje_osoby.php', \bookingRegistrationDestination($unsafe, 'rodina'));
        }
    }
}
