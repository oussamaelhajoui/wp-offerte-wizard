<?php
use OfferteWizard\ActionToken;
use OfferteWizard\Mailer;
use OfferteWizard\QuotePrices;
use OfferteWizard\Settings;
use OfferteWizard\SubmissionBuilder;
use PHPUnit\Framework\TestCase;

final class QuoteTest extends TestCase
{
    private function data(): array
    {
        $a = [
            'type_woning' => 'nieuwbouw', 'm2' => 'ja', 'm2_wand' => '200', 'm2_plafond' => '200', 'daktype' => 'plat',
            'schilderen' => 'ja', 'periode_keuze' => 'weet', 'periode_start' => '2026-11-27', 'periode_eind' => '2026-12-05',
            'first_name' => 'Jan', 'last_name' => 'Alleman', 'email' => 'jan@example.nl', 'phone_number' => '0612548798',
            'address_line' => 'Straat 1', 'locality' => 'Eindhoven', 'post_code' => '5611AB',
        ];
        return SubmissionBuilder::build($a, '2026-11-26', Settings::defaults())['data'];
    }

    public function testActionToken(): void
    {
        $exp = 2000;
        $sig = ActionToken::sign(7, 'send', $exp, 'secret');
        $this->assertTrue(ActionToken::verify(7, 'send', $exp, $sig, 'secret', 1000));
        $this->assertFalse(ActionToken::verify(8, 'send', $exp, $sig, 'secret', 1000));
        $this->assertFalse(ActionToken::verify(7, 'other', $exp, $sig, 'secret', 1000));
        $this->assertFalse(ActionToken::verify(7, 'send', $exp, $sig, 'wrong', 1000));
        $this->assertFalse(ActionToken::verify(7, 'send', $exp, $sig, 'secret', 2001));
        $this->assertFalse(ActionToken::verify(7, 'send', 9999, $sig, 'secret', 1000));
        $this->assertFalse(ActionToken::verify(0, 'send', $exp, $sig, 'secret', 1000));
    }

    public function testQuotePricesFallsBackToCalculated(): void
    {
        $d = $this->data();
        $p = QuotePrices::resolve($d['pricing'], []);
        $this->assertSame(['min' => 6000, 'max' => 8400], $p['stucwerk']);
        $this->assertSame(['min' => 7028, 'max' => 8200], $p['schilderwerk']);
        $this->assertSame('', $p['note']);
    }

    public function testQuotePricesCustomOverrides(): void
    {
        $d = $this->data();
        $p = QuotePrices::resolve($d['pricing'], ['st_min' => '5.500', 'st_max' => '', 'sc_min' => '3000', 'sc_max' => '2500', 'note' => '<b>Incl. steiger</b>']);
        $this->assertSame(['min' => 5500, 'max' => 5500], $p['stucwerk']);
        $this->assertSame(['min' => 2500, 'max' => 3000], $p['schilderwerk']);
        $this->assertSame('Incl. steiger', $p['note']);
        $this->assertNull(QuotePrices::resolve(null, [])['stucwerk']);
        $this->assertSame(['min' => 1200, 'max' => 1200], QuotePrices::resolve(null, ['st_min' => '1200'])['stucwerk']);
    }

    public function testOwnerMailLinks(): void
    {
        $d = $this->data();
        $none = Mailer::compose($d)['body'];
        $this->assertStringNotContainsString('WAT WIL JE', $none);
        $mail = Mailer::compose($d, ['send' => 'https://x.nl/?s=1', 'admin' => 'https://x.nl/wp-admin/post.php?post=5'], false);
        $this->assertStringContainsString('https://x.nl/?s=1', $mail['body']);
        $this->assertStringContainsString('post=5', $mail['body']);
        $this->assertStringContainsString('NIET getoond aan de klant', $mail['body']);
        $this->assertStringContainsString('Niets doen?', $mail['body']);
        $onlyAdmin = Mailer::compose($d, ['admin' => 'https://x.nl/a'])['body'];
        $this->assertStringNotContainsString('Offerte sturen naar klant', $onlyAdmin);
    }

    public function testConfirmationHasNoPrice(): void
    {
        $c = Mailer::composeConfirmation($this->data(), 'Afbouw Megens');
        $this->assertStringNotContainsString('€', $c['body']);
        $this->assertStringContainsString('Hallo Jan', $c['body']);
    }

    public function testQuoteMail(): void
    {
        $d = $this->data();
        $prices = QuotePrices::resolve($d['pricing'], ['st_min' => '5500', 'note' => 'Incl. steiger.']);
        $m = Mailer::composeQuote($d, $prices, 'Afbouw Megens', "Bedankt!\nHierbij de offerte.");
        $this->assertSame('Je offerte van Afbouw Megens', $m['subject']);
        $this->assertStringContainsString('Stucwerk: €5.500', $m['body']);
        $this->assertStringNotContainsString('€5.500 -', $m['body']);
        $this->assertStringContainsString('Schilderwerk: €7.028 - €8.200', $m['body']);
        $this->assertStringContainsString('Incl. steiger.', $m['body']);
        $this->assertStringContainsString('Bedankt!', $m['body']);
    }

    public function testSummaryCanHidePrices(): void
    {
        $d = $this->data();
        $shown = SubmissionBuilder::summary($d, true);
        $this->assertNotNull($shown['stucwerk']);
        $this->assertFalse($shown['price_hidden']);
        $hidden = SubmissionBuilder::summary($d, false);
        $this->assertNull($hidden['stucwerk']);
        $this->assertNull($hidden['schilderwerk']);
        $this->assertTrue($hidden['price_hidden']);
        $this->assertSame(400.0, $hidden['totaal']);
        $this->assertNotNull($hidden['dagen']);
    }

    public function testNewSettings(): void
    {
        $old = Settings::defaults();
        $s = Settings::sanitize(['customer_mail' => 'direct', 'show_price' => '1', 'quote_intro' => '<i>Hallo</i> daar'], $old);
        $this->assertSame('direct', $s['customer_mail']);
        $this->assertSame(1, $s['show_price']);
        $this->assertSame(0, $s['owner_link_send']);
        $this->assertSame('Hallo daar', $s['quote_intro']);
        $this->assertSame('ontvangst', Settings::sanitize(['customer_mail' => 'raar'], $old)['customer_mail']);
        $this->assertSame(0, Settings::defaults()['show_price']);
        $this->assertSame(0, Settings::sanitize([], $old)['show_price']);
        $this->assertSame($old['quote_intro'], Settings::sanitize(['quote_intro' => ''], $old)['quote_intro']);
    }
}
