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
            'type_woning' => 'nieuwbouw', 'onderdelen' => 'beide', 'm2' => 'ja', 'm2_wand' => '200m2', 'm2_plafond' => '200', 'daktype' => 'plat',
            'schilderen' => 'ja', 'periode_keuze' => 'weet', 'periode_start' => '2026-11-27', 'periode_eind' => '2026-12-05',
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
        $this->assertSame('Stucwerk (wanden en plafonds) en Schilderwerk', $s['werk']);
    }

    public function testOnlyWalls(): void
    {
        $a = $this->jaAnswers();
        $a['onderdelen'] = 'wanden';
        unset($a['m2_plafond'], $a['daktype']);
        $r = SubmissionBuilder::build($a, '2026-11-26', Settings::defaults());
        $this->assertSame([], $r['errors']);
        $this->assertSame(200.0, $r['data']['wand']);
        $this->assertNull($r['data']['plafond']);
        $this->assertSame('', $r['data']['daktype']);
        $this->assertSame(['min' => 3200, 'max' => 4400], $r['data']['pricing']['stucwerk']);
        $this->assertSame(['min' => 3514, 'max' => 4100], $r['data']['pricing']['schilderwerk']);
        $s = SubmissionBuilder::summary($r['data']);
        $this->assertSame('Stucwerk (wanden) en Schilderwerk', $s['werk']);
        $this->assertSame(200.0, $s['totaal']);
        $this->assertStringContainsString('Te stucen: wanden', Mailer::compose($r['data'])['body']);
        $this->assertStringNotContainsString('m² plafonds', Mailer::compose($r['data'])['body']);
        unset($a['m2_wand']);
        $this->assertArrayHasKey('m2-wand', SubmissionBuilder::build($a, '2026-11-26', Settings::defaults())['errors']);
    }

    public function testOnlyCeilings(): void
    {
        $a = $this->jaAnswers();
        $a['onderdelen'] = 'plafonds';
        $a['m2_plafond'] = '50';
        unset($a['m2_wand']);
        $r = SubmissionBuilder::build($a, '2026-11-26', Settings::defaults());
        $this->assertSame([], $r['errors']);
        $this->assertNull($r['data']['wand']);
        $this->assertSame(50.0, $r['data']['plafond']);
        $this->assertSame('plat', $r['data']['daktype']);
        $this->assertSame(['min' => 700, 'max' => 1000], $r['data']['pricing']['stucwerk']);
        $this->assertSame('Stucwerk (plafonds) en Schilderwerk', SubmissionBuilder::summary($r['data'])['werk']);
        unset($a['m2_plafond']);
        $this->assertArrayHasKey('m2-plafond', SubmissionBuilder::build($a, '2026-11-26', Settings::defaults())['errors']);
        $b = $this->jaAnswers();
        $b['onderdelen'] = 'plafonds';
        unset($b['daktype']);
        $this->assertArrayHasKey('type-plafond', SubmissionBuilder::build($b, '2026-11-26', Settings::defaults())['errors']);
    }

    public function testBouwplanOnlyWallsIgnoresCeilingMeasures(): void
    {
        $a = $this->jaAnswers();
        $a['m2'] = 'bouwplan';
        $a['onderdelen'] = 'wanden';
        $a['plan_token'] = str_repeat('c', 32);
        $a['rooms'] = [['name' => 'Woonkamer', 'floor_m2' => '20', 'wall_m2' => '50']];
        $a['m2_wand'] = '50';
        $a['m2_plafond'] = '20'; // stond nog in de antwoorden van de uitlezing
        unset($a['daktype']);
        $r = SubmissionBuilder::build($a, '2026-11-26', Settings::defaults());
        $this->assertSame([], $r['errors']);
        $this->assertSame(50.0, $r['data']['wand']);
        $this->assertNull($r['data']['plafond']);
        $this->assertSame(['min' => 800, 'max' => 1100], $r['data']['pricing']['stucwerk']);
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

    /** @dataProvider termijnen */
    public function testPeriodChoiceWithoutDates(string $keuze, string $tekst): void
    {
        $a = $this->jaAnswers();
        $a['periode_keuze'] = $keuze;
        unset($a['periode_start'], $a['periode_eind']);
        $r = SubmissionBuilder::build($a, '2026-11-26', Settings::defaults());
        $this->assertSame([], $r['errors']);
        $this->assertSame('', $r['data']['start']);
        $this->assertSame($tekst, SubmissionBuilder::summary($r['data'])['periode']);
        $this->assertStringContainsString('Gewenste periode: ' . $tekst, Mailer::compose($r['data'])['body']);
        $this->assertStringContainsString('Periode: ' . $tekst, Mailer::composeConfirmation($r['data'], 'X')['body']);
    }

    public static function termijnen(): array
    {
        return [['kort', 'Op korte termijn'], ['3m', 'Binnen 3 maanden'], ['6m', '3-6 maanden']];
    }

    public function testPeriodChoiceIsRequiredAndDatesIgnoredWhenNotChosen(): void
    {
        $a = $this->jaAnswers();
        unset($a['periode_keuze']);
        $this->assertArrayHasKey('periode', SubmissionBuilder::build($a, '2026-11-26', Settings::defaults())['errors']);
        $a['periode_keuze'] = 'kort'; // oude datums van een eerdere keuze mogen niet meegaan
        $d = SubmissionBuilder::build($a, '2026-11-26', Settings::defaults())['data'];
        $this->assertSame('', $d['start']);
        $this->assertSame('', $d['eind']);
        $a['periode_keuze'] = 'weet';
        $a['periode_start'] = '2020-01-01';
        $this->assertArrayHasKey('kalender', SubmissionBuilder::build($a, '2026-11-26', Settings::defaults())['errors']);
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
