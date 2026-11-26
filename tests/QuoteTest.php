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
            'type_woning' => 'nieuwbouw', 'onderdelen' => 'beide', 'm2' => 'ja', 'm2_wand' => '200', 'm2_plafond' => '200', 'daktype' => 'plat',
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

    public function testHiddenPriceMessageIsConfigurable(): void
    {
        $d = $this->data();
        $msg = "Je offerte ontvang je binnen 24 uur.\nTot snel!";
        $notice = ['title' => 'Bijna daar', 'message' => $msg];
        $hidden = SubmissionBuilder::summary($d, false, $notice);
        $this->assertTrue($hidden['price_hidden']);
        $this->assertSame($notice, $hidden['price_notice']);
        $this->assertNull(SubmissionBuilder::summary($d, true, $notice)['price_notice']);
        $c = Mailer::composeConfirmation($d, 'Afbouw Megens', $msg);
        $this->assertStringContainsString('binnen 24 uur', $c['body']);
        $this->assertStringContainsString('Met vriendelijke groet,', $c['body']);
        $this->assertStringNotContainsString('€', $c['body']);
        $this->assertStringNotContainsString('binnen 24 uur', Mailer::composeConfirmation($d, 'Afbouw Megens')['body']);
        $old = Settings::defaults();
        $this->assertNotSame('', $old['hidden_price_message']);
        $this->assertSame('Binnen 24 uur.', Settings::sanitize(['hidden_price_message' => '<b>Binnen 24 uur.</b>'], $old)['hidden_price_message']);
        $this->assertSame($old['hidden_price_message'], Settings::sanitize(['hidden_price_message' => '  '], $old)['hidden_price_message']);
        $this->assertSame('Bijna daar', Settings::sanitize(['hidden_price_title' => '<i>Bijna daar</i>'], $old)['hidden_price_title']);
        $this->assertSame($old['hidden_price_title'], Settings::sanitize(['hidden_price_title' => ''], $old)['hidden_price_title']);
        // wie de eerste standaardtekst (1.6.0) nooit aanpaste, krijgt de nieuwe
        $legacy = array_merge($old, ['hidden_price_message' => Settings::LEGACY_HIDDEN_MESSAGE]);
        $this->assertSame($old['hidden_price_message'], Settings::migrate($legacy)['hidden_price_message']);
        $custom = array_merge($old, ['hidden_price_message' => 'Mijn eigen tekst']);
        $this->assertSame('Mijn eigen tekst', Settings::migrate($custom)['hidden_price_message']);
        $this->assertSame($old['hidden_price_message'], Settings::sanitize(['show_price' => '0'], $legacy)['hidden_price_message']);
        $this->assertSame(0, Settings::sanitize(['show_price' => '0'], $old)['show_price']);
        $this->assertSame(1, Settings::sanitize(['show_price' => '1'], $old)['show_price']);
    }

    public function testNewSettings(): void
    {
        $old = Settings::defaults();
        $s = Settings::sanitize(['customer_mail' => 'aan', 'mail_price' => '1', 'show_price' => '1', 'quote_intro' => '<i>Hallo</i> daar'], $old);
        $this->assertSame('aan', $s['customer_mail']);
        $this->assertSame(1, $s['mail_price']);
        $this->assertSame(1, $s['show_price']);
        $this->assertSame(0, $s['owner_link_send']);
        $this->assertSame('Hallo daar', $s['quote_intro']);
        $this->assertSame('aan', Settings::sanitize(['customer_mail' => 'raar'], $old)['customer_mail']);
        $this->assertSame('uit', Settings::sanitize(['customer_mail' => 'uit'], $old)['customer_mail']);
        $this->assertSame(0, Settings::defaults()['show_price']);
        $this->assertSame(0, Settings::defaults()['mail_price']);
        $this->assertSame(0, Settings::sanitize([], $old)['show_price']);
        $this->assertSame($old['quote_intro'], Settings::sanitize(['quote_intro' => ''], $old)['quote_intro']);
    }

    public function testMailPriceIsIndependentOfScreenAndMigratesOldChoices(): void
    {
        $old = Settings::defaults();
        // Prijs op het scherm verbergen maar wel mailen, en andersom.
        $a = Settings::sanitize(['show_price' => '0', 'customer_mail' => 'aan', 'mail_price' => '1'], $old);
        $this->assertSame([0, 'aan', 1], [$a['show_price'], $a['customer_mail'], $a['mail_price']]);
        $b = Settings::sanitize(['show_price' => '1', 'customer_mail' => 'aan', 'mail_price' => '0'], $old);
        $this->assertSame([1, 'aan', 0], [$b['show_price'], $b['customer_mail'], $b['mail_price']]);
        // Oude keuzelijst (1.2-1.6): "direct" = met prijs, "ontvangst" = zonder, bij opgeslagen instellingen...
        $m1 = Settings::migrate(array_merge($old, ['customer_mail' => 'direct', 'mail_price' => 0]));
        $this->assertSame(['aan', 1], [$m1['customer_mail'], $m1['mail_price']]);
        $m2 = Settings::migrate(array_merge($old, ['customer_mail' => 'ontvangst']));
        $this->assertSame(['aan', 0], [$m2['customer_mail'], $m2['mail_price']]);
        $m3 = Settings::migrate(array_merge($old, ['customer_mail' => 'uit']));
        $this->assertSame('uit', $m3['customer_mail']);
        // ... en bij formulierinvoer van een oude keuze.
        $fromForm = Settings::sanitize(['customer_mail' => 'direct'], $old);
        $this->assertSame(['aan', 1], [$fromForm['customer_mail'], $fromForm['mail_price']]);
    }

    public function testPriceTextsOnScreenAndInQuoteMail(): void
    {
        $d = $this->data();
        $notice = ['disclaimer' => 'Richtprijs, inclusief materiaal.', 'extra' => "Deze maand geen voorrijkosten.\nTot snel!", 'title' => 'x', 'message' => 'y'];
        $shown = SubmissionBuilder::summary($d, true, $notice);
        $this->assertSame($notice['disclaimer'], $shown['price_disclaimer']);
        $this->assertSame($notice['extra'], $shown['price_extra']);
        $this->assertNull($shown['price_notice']);
        $hidden = SubmissionBuilder::summary($d, false, $notice);
        $this->assertSame('', $hidden['price_disclaimer']);
        $this->assertSame('', $hidden['price_extra']);
        $this->assertNotNull($hidden['price_notice']);
        $prices = QuotePrices::resolve($d['pricing'], []);
        $with = Mailer::composeQuote($d, $prices, 'Afbouw Megens', 'Hallo!', 'Richtprijs, inclusief materiaal.');
        $this->assertStringContainsString('Richtprijs, inclusief materiaal.', $with['body']);
        $this->assertStringNotContainsString('Richtprijs', Mailer::composeQuote($d, $prices, 'Afbouw Megens', 'Hallo!')['body']);
        $old = Settings::defaults();
        $s = Settings::sanitize(['price_disclaimer' => '<b>Eigen toelichting</b>', 'shown_price_extra' => '<i>Extra</i> tekst'], $old);
        $this->assertSame('Eigen toelichting', $s['price_disclaimer']);
        $this->assertSame('Extra tekst', $s['shown_price_extra']);
        $this->assertSame($old['price_disclaimer'], Settings::sanitize(['price_disclaimer' => ' '], $old)['price_disclaimer']);
        $this->assertSame('', Settings::sanitize(['shown_price_extra' => ''], array_merge($old, ['shown_price_extra' => 'oud']))['shown_price_extra']);
        $this->assertSame('', $old['shown_price_extra']);
    }

    public function testLegacyDefaultTextsAreReplaced(): void
    {
        $d = Settings::defaults();
        foreach (Settings::LEGACY_HIDDEN_MESSAGES as $legacy) {
            $this->assertSame($d['hidden_price_message'], Settings::migrate(array_merge($d, ['hidden_price_message' => $legacy]))['hidden_price_message']);
        }
        $this->assertSame($d['hidden_price_title'], Settings::migrate(array_merge($d, ['hidden_price_title' => Settings::LEGACY_HIDDEN_TITLE]))['hidden_price_title']);
        $this->assertStringContainsString('contact met je op', $d['hidden_price_message']);
        $this->assertStringContainsString('contact met je op', $d['hidden_price_title']);
    }
}
