<?php
use OfferteWizard\Settings;
use OfferteWizard\UpdateInfo;
use PHPUnit\Framework\TestCase;

final class UpdateTest extends TestCase
{
    private const SHA = 'a3f1c2d4e5b6a7980123456789abcdef0123456789abcdef0123456789abcdef';

    public function testUrlAllowed(): void
    {
        $this->assertTrue(UpdateInfo::urlAllowed('https://example.com/x.json'));
        $this->assertTrue(UpdateInfo::urlAllowed('http://localhost:8099/x.json'));
        $this->assertTrue(UpdateInfo::urlAllowed('http://updates.test/x.json'));
        $this->assertFalse(UpdateInfo::urlAllowed('http://example.com/x.json'));
        $this->assertFalse(UpdateInfo::urlAllowed('ftp://example.com/x.zip'));
        $this->assertFalse(UpdateInfo::urlAllowed('javascript:alert(1)'));
        $this->assertFalse(UpdateInfo::urlAllowed('niet een url'));
        $this->assertFalse(UpdateInfo::urlAllowed(''));
    }

    public function testParseValid(): void
    {
        $info = UpdateInfo::parse(json_encode(['version' => '1.3.0', 'download_url' => 'https://x.nl/offerte-wizard.zip', 'sha256' => strtoupper(self::SHA), 'changelog' => '<b>Nieuw</b> ding']));
        $this->assertSame('1.3.0', $info['version']);
        $this->assertSame(self::SHA, $info['sha256']);
        $this->assertSame('Nieuw ding', $info['changelog']);
        $this->assertSame('', UpdateInfo::parse(json_encode(['version' => '1.0', 'download_url' => 'https://x.nl/a.zip']))['sha256']);
    }

    /** @dataProvider invalid */
    public function testParseRejects(string $json): void
    {
        $this->assertNull(UpdateInfo::parse($json));
    }

    public static function invalid(): array
    {
        return [
            ['niet json'],
            ['[]'],
            [json_encode(['version' => 'latest', 'download_url' => 'https://x.nl/a.zip'])],
            [json_encode(['version' => '1.0.0', 'download_url' => 'http://x.nl/a.zip'])],
            [json_encode(['version' => '1.0.0'])],
            [json_encode(['version' => '1.0.0', 'download_url' => 'https://x.nl/a.zip', 'sha256' => 'kort'])],
        ];
    }

    public function testIsNewer(): void
    {
        $this->assertTrue(UpdateInfo::isNewer('1.1.0', ['version' => '1.2.0']));
        $this->assertTrue(UpdateInfo::isNewer('1.9.0', ['version' => '1.10.0']));
        $this->assertFalse(UpdateInfo::isNewer('1.2.0', ['version' => '1.2.0']));
        $this->assertFalse(UpdateInfo::isNewer('2.0.0', ['version' => '1.9.9']));
    }

    public function testUpdateUrlSetting(): void
    {
        $old = Settings::defaults();
        $this->assertSame(Settings::DEFAULT_UPDATE_URL, $old['update_url']);
        $this->assertTrue(UpdateInfo::urlAllowed($old['update_url']));
        $this->assertSame('https://x.nl/o.json', Settings::sanitize(['update_url' => ' https://x.nl/o.json '], $old)['update_url']);
        $this->assertSame(Settings::DEFAULT_UPDATE_URL, Settings::sanitize(['update_url' => 'http://evil.com/o.json'], $old)['update_url']);
        $this->assertSame('', Settings::sanitize(['update_url' => ''], $old)['update_url']);
        $kept = Settings::sanitize(['update_url' => 'javascript:x'], array_merge($old, ['update_url' => 'https://ok.nl/o.json']));
        $this->assertSame('https://ok.nl/o.json', $kept['update_url']);
    }
}
