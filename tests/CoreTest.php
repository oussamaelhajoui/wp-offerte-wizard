<?php
use OfferteWizard\AreaCalculator;
use OfferteWizard\Flow;
use OfferteWizard\NumberParser;
use OfferteWizard\Pricing;
use OfferteWizard\Settings;
use OfferteWizard\Validator;
use PHPUnit\Framework\TestCase;

final class CoreTest extends TestCase
{
    /** @dataProvider numbers */
    public function testNumberParser($in, $expected): void
    {
        $this->assertSame($expected, NumberParser::parse($in));
    }

    public static function numbers(): array
    {
        return [
            ['200m2', 200.0], ['12,5 m²', 12.5], ['1.200', 1200.0], ['1.5', 1.5], ['  80 ', 80.0],
            ['abc', null], ['', null], ['0', null], ['abc 12,5m2', null], ['1,200.5', null], [null, null], ['-5', null],
        ];
    }

    public function testAreaRoomFromDimensions(): void
    {
        $r = AreaCalculator::room(['name' => 'Woonkamer', 'length_m' => 4, 'width_m' => 3], 2.6);
        $this->assertSame(12.0, $r['floor_m2']);
        $this->assertSame(36.4, $r['wall_m2']);
    }

    public function testAreaRoomFromAreaOnlyAndTotals(): void
    {
        $t = AreaCalculator::totals([
            ['name' => 'A', 'length_m' => 4, 'width_m' => 3],
            ['name' => 'B', 'floor_area_m2' => 16],
            ['name' => 'leeg'],
        ], 2.6);
        $this->assertSame(28.0, $t['ceiling_m2']);
        $this->assertSame(78.0, $t['wall_m2']);
        $this->assertSame(1, $t['skipped']);
        $this->assertCount(2, $t['rooms']);
    }

    public function testPricingMatchesObservedSchilderwerk(): void
    {
        $p = Pricing::compute(200, 200, true, Settings::defaults());
        $this->assertSame(['min' => 7028, 'max' => 8200], $p['schilderwerk']);
        $this->assertSame(['min' => 6000, 'max' => 8400], $p['stucwerk']);
        $this->assertSame(['min' => 4, 'max' => 5], $p['dagen']);
    }

    public function testPricingWithoutSchilderenOrArea(): void
    {
        $this->assertNull(Pricing::compute(50, 50, false, Settings::defaults())['schilderwerk']);
        $this->assertNull(Pricing::compute(0, 0, true, Settings::defaults())['stucwerk']);
    }

    public function testContactValidation(): void
    {
        $ok = ['first_name' => 'Jan', 'last_name' => 'Alleman', 'email' => 'jan@example.nl', 'phone_number' => '0612548798',
            'address_line' => 'Straat 1', 'locality' => 'Eindhoven', 'post_code' => '5611 AB'];
        $this->assertSame([], Validator::contact($ok));
        $this->assertSame(Validator::MSG_ALL, Validator::contact(array_merge($ok, ['last_name' => '']))['last_name']);
        $this->assertArrayHasKey('email', Validator::contact(array_merge($ok, ['email' => 'nope'])));
        $this->assertArrayHasKey('phone_number', Validator::contact(array_merge($ok, ['phone_number' => '123'])));
        $this->assertSame([], Validator::contact(array_merge($ok, ['phone_number' => '+31 6 12548798'])));
        $this->assertArrayHasKey('post_code', Validator::contact(array_merge($ok, ['post_code' => '0123AB'])));
    }

    public function testPeriodValidation(): void
    {
        $this->assertNull(Validator::period('2026-11-27', '2026-12-05', '2026-11-26'));
        $this->assertNull(Validator::period('2026-11-26', '2026-11-26', '2026-11-26'));
        $this->assertSame(Validator::MSG_PERIOD, Validator::period('2026-11-20', '2026-11-30', '2026-11-26'));
        $this->assertSame(Validator::MSG_PERIOD, Validator::period('2026-12-05', '2026-11-30', '2026-11-26'));
        $this->assertSame(Validator::MSG_PERIOD, Validator::period('', '', '2026-11-26'));
        $this->assertSame(Validator::MSG_PERIOD, Validator::period('2026-02-30', '2026-03-01', '2026-01-01'));
    }

    public function testFlowBranches(): void
    {
        $this->assertSame('m2-wand', Flow::next('m2', ['m2' => 'ja']));
        $this->assertSame('wand-ondergrond', Flow::next('m2', ['m2' => 'nee']));
        $this->assertSame('bouwplan-upload', Flow::next('m2', ['m2' => 'bouwplan']));
        $this->assertSame('type-plafond', Flow::next('bouwplan-controle', []));
        $this->assertSame('schilderen', Flow::next('wand-ondergrond', []));
        $this->assertSame('kalender', Flow::next('schilderen', ['schilderen' => 'nee']));
        $this->assertNull(Flow::next('overzicht', []));
    }

    public function testFlowValidation(): void
    {
        $today = '2026-11-26';
        $this->assertSame(Flow::ERR_CHOICE, Flow::validate('type-woning', [], $today));
        $this->assertNull(Flow::validate('type-woning', ['type_woning' => 'renovatie'], $today));
        $this->assertSame(Flow::ERR_CHOICE, Flow::validate('type-woning', ['type_woning' => 'kasteel'], $today));
        $this->assertSame(Flow::ERR_NUMBER, Flow::validate('m2-wand', ['m2_wand' => 'abc'], $today));
        $this->assertSame(Flow::ERR_NUMBER, Flow::validate('m2-wand', ['m2_wand' => ''], $today));
        $this->assertNull(Flow::validate('m2-wand', ['m2_wand' => '12,5m2'], $today));
        $this->assertSame(Flow::ERR_CHOICE, Flow::validate('wand-ondergrond', ['ondergrond' => []], $today));
        $this->assertNull(Flow::validate('wand-ondergrond', ['ondergrond' => ['beton']], $today));
        $this->assertSame(Flow::ERR_UPLOAD, Flow::validate('bouwplan-upload', [], $today));
        $this->assertNull(Flow::validate('bouwplan-controle', [], $today));
        $this->assertSame(Flow::ERR_NUMBER, Flow::validate('bouwplan-controle', ['rooms' => [['floor_m2' => 1]]], $today));
    }

    public function testSettingsSanitize(): void
    {
        $old = Settings::defaults();
        $old['recipient'] = 'a@b.nl';
        $old['ai_key'] = 'sk-1';
        $s = Settings::sanitize(['recipient' => 'bad', 'ai_key' => '', 'mode' => 'evil', 'ceiling_height' => '9',
            'price_schilder_min' => '30', 'price_schilder_max' => '20', 'color_accent' => 'red', 'max_upload_mb' => '99',
            'logo_url' => 'javascript:alert(1)'], $old);
        $this->assertSame('a@b.nl', $s['recipient']);
        $this->assertSame('sk-1', $s['ai_key']);
        $this->assertSame('auto', $s['mode']);
        $this->assertSame(2.6, $s['ceiling_height']);
        $this->assertSame(20.0, $s['price_schilder_min']);
        $this->assertSame(30.0, $s['price_schilder_max']);
        $this->assertSame('#c39455', $s['color_accent']);
        $this->assertSame(15, $s['max_upload_mb']);
        $this->assertSame($old['logo_url'], $s['logo_url']);
        $cleared = Settings::sanitize(['ai_key_clear' => '1'], $old);
        $this->assertSame('', $cleared['ai_key']);
        $this->assertSame(17.57, Settings::sanitize(['price_schilder_min' => '17,57'], $old)['price_schilder_min']);
    }
}
