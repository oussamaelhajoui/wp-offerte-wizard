<?php
use OfferteWizard\Mailer;
use OfferteWizard\Settings;
use OfferteWizard\SubmissionBuilder;
use PHPUnit\Framework\TestCase;

final class SubmissionTest extends TestCase
{
    private function jaAnswers(): array
    {
        return [
            'type_woning' => 'nieuwbouw', 'm2' => 'ja', 'm2_wand' => '200m2', 'm2_plafond' => '200', 'daktype' => 'plat',
            'schilderen' => 'ja', 'periode_start' => '2026-11-27', 'periode_eind' => '2026-12-05',
            'first_name' => 'Jan', 'last_name' => 'Alleman', 'email' => 'jan@example.nl', 'phone_number' => '0612548798',
            'address_line' => 'Straat 1', 'locality' => 'Eindhoven', 'post_code' => '5611ab', 'beschrijving' => '',
        ];
    }

    public function testFullJaBranch(): void
    {
        $r = SubmissionBuilder::build($this->jaAnswers(), '2026-11-26', Settings::defaults());
        $this->assertSame([], $r['errors']);
        $this->assertSame(200.0, $r['data']['wand']);
        $this->assertSame(7028, $r['data']['pricing']['schilderwerk']['min']);
        $this->assertSame('5611AB', $r['data']['post_code']);
        $s = SubmissionBuilder::summary($r['data']);
        $this->assertSame('27 november - 5 december', $s['periode']);
        $this->assertSame(400.0, $s['totaal']);
        $this->assertSame('Stucwerk en Schilderwerk', $s['werk']);
    }

    public function testNeeBranchHasNoPrice(): void
    {
        $a = $this->jaAnswers();
        unset($a['m2_wand'], $a['m2_plafond'], $a['daktype']);
        $a['m2'] = 'nee';
        $a['ondergrond'] = ['beton', 'hack'];
        $this->assertSame(['wand-ondergrond' => 'Maak een keuze om door te gaan!'], SubmissionBuilder::build($a, '2026-11-26', Settings::defaults())['errors']);
        $a['ondergrond'] = ['beton', 'gips'];
        $r = SubmissionBuilder::build($a, '2026-11-26', Settings::defaults());
        $this->assertSame([], $r['errors']);
        $this->assertNull($r['data']['pricing']);
        $this->assertSame(['beton', 'gips'], $r['data']['ondergrond']);
        $this->assertNull(SubmissionBuilder::summary($r['data'])['totaal']);
    }

    public function testFirstBrokenStepIsReported(): void
    {
        $a = $this->jaAnswers();
        $a['email'] = '';
        $this->assertSame(['contactgegevens' => 'Vul alle velden in'], SubmissionBuilder::build($a, '2026-11-26', Settings::defaults())['errors']);
        $a = $this->jaAnswers();
        $a['periode_start'] = '2020-01-01';
        $this->assertArrayHasKey('kalender', SubmissionBuilder::build($a, '2026-11-26', Settings::defaults())['errors']);
        $this->assertArrayHasKey('type-woning', SubmissionBuilder::build([], '2026-11-26', Settings::defaults())['errors']);
    }

    public function testBouwplanBranchWithRooms(): void
    {
        $a = $this->jaAnswers();
        $a['m2'] = 'bouwplan';
        $a['daktype'] = 'punt';
        $a['plan_token'] = str_repeat('a', 32);
        $a['rooms'] = [['name' => '<b>Woonkamer</b>', 'floor_m2' => '20', 'wall_m2' => '50'], ['name' => 'leeg']];
        $r = SubmissionBuilder::build($a, '2026-11-26', Settings::defaults());
        $this->assertSame([], $r['errors']);
        $this->assertSame(str_repeat('a', 32), $r['data']['plan_token']);
        $this->assertSame([['name' => 'Woonkamer', 'floor_m2' => 20.0, 'wall_m2' => 50.0]], $r['data']['rooms']);
        $a['rooms'] = [['name' => 'x', 'floor_m2' => '20', 'wall_m2' => 'abc']];
        $bad = SubmissionBuilder::build($a, '2026-11-26', Settings::defaults());
        $this->assertNull($bad['data']['rooms'][0]['wall_m2']);
        $a['m2_wand'] = 'abc';
        $this->assertArrayHasKey('bouwplan-controle', SubmissionBuilder::build($a, '2026-11-26', Settings::defaults())['errors']);
    }

    public function testBouwplanWithoutRoomsAllowsMissingM2(): void
    {
        $a = $this->jaAnswers();
        $a['m2'] = 'bouwplan';
        unset($a['m2_wand'], $a['m2_plafond']);
        $a['plan_token'] = str_repeat('b', 32);
        $r = SubmissionBuilder::build($a, '2026-11-26', Settings::defaults());
        $this->assertSame([], $r['errors']);
        $this->assertNull($r['data']['pricing']);
    }

    public function testMailContents(): void
    {
        $r = SubmissionBuilder::build($this->jaAnswers(), '2026-11-26', Settings::defaults());
        $mail = Mailer::compose($r['data']);
        $this->assertSame('Nieuwe offerte-aanvraag: Jan Alleman (Eindhoven)', $mail['subject']);
        $this->assertStringContainsString('jan@example.nl', $mail['body']);
        $this->assertStringContainsString('€7.028 - €8.200', $mail['body']);
        $this->assertStringContainsString('m² wanden: 200', $mail['body']);
        $d = $r['data'];
        $d['first_name'] = "Jan\r\nBcc: x@y.nl";
        $this->assertStringNotContainsString("\n", Mailer::compose($d)['subject']);
        $conf = Mailer::composeConfirmation($r['data'], 'Afbouw Megens');
        $this->assertStringContainsString('Hallo Jan', $conf['body']);
    }
}
