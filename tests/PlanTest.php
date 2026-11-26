<?php
use OfferteWizard\AiExtractor;
use OfferteWizard\Extraction;
use OfferteWizard\ExtractionFailed;
use OfferteWizard\ModeResolver;
use OfferteWizard\PlanExtractor;
use OfferteWizard\RateLimiter;
use OfferteWizard\SessionToken;
use OfferteWizard\TextExtractor;
use OfferteWizard\UploadPolicy;
use PHPUnit\Framework\TestCase;

final class PlanTest extends TestCase
{
    public function testModeChains(): void
    {
        $this->assertSame(['ai', 'tekst'], ModeResolver::chain('auto', true, 'pdf'));
        $this->assertSame(['tekst'], ModeResolver::chain('auto', false, 'pdf'));
        $this->assertSame(['ai'], ModeResolver::chain('auto', true, 'png'));
        $this->assertSame([], ModeResolver::chain('auto', false, 'png'));
        $this->assertSame([], ModeResolver::chain('handmatig', true, 'pdf'));
        $this->assertSame([], ModeResolver::chain('ai', false, 'pdf'));
        $this->assertSame(['tekst'], ModeResolver::chain('tekst', true, 'pdf'));
    }

    public function testExtractionFallsBackInOrder(): void
    {
        $boom = new class implements PlanExtractor {
            public function extract(string $path, string $ext): array { throw new ExtractionFailed('x'); }
        };
        $ok = new class implements PlanExtractor {
            public function extract(string $path, string $ext): array { return [['name' => 'A', 'length_m' => null, 'width_m' => null, 'floor_area_m2' => 10.0]]; }
        };
        $r = Extraction::run(['ai', 'tekst'], ['ai' => $boom, 'tekst' => $ok], 'x', 'pdf');
        $this->assertSame('tekst', $r['source']);
        $this->assertCount(1, $r['rooms']);
        $none = Extraction::run(['ai'], ['ai' => $boom], 'x', 'pdf');
        $this->assertSame(['rooms' => [], 'source' => 'handmatig'], $none);
        $this->assertSame('handmatig', Extraction::run([], [], 'x', 'pdf')['source']);
    }

    public function testTextParsing(): void
    {
        $rooms = TextExtractor::parseText('Woonkamer 24,5 m2 Slaapkamer 12 m² Keuken');
        $this->assertSame(['Woonkamer', 'Slaapkamer'], array_column($rooms, 'name'));
        $this->assertSame([24.5, 12.0], array_column($rooms, 'floor_area_m2'));
        $dims = TextExtractor::parseText('Keuken 3.50 x 2.80');
        $this->assertSame(3.5, $dims[0]['length_m']);
        $this->assertSame(2.8, $dims[0]['width_m']);
        $this->assertSame([], TextExtractor::parseText('Geen maten hier'));
    }

    public function testPdfTextFromCompressedStream(): void
    {
        $content = "BT /F1 12 Tf (Woonkamer 24,5 m2) Tj ET\nBT [(Slaap) -20 (kamer 12 m2)] TJ ET";
        $pdf = "%PDF-1.4\n1 0 obj\n<< >>\nstream\n" . gzcompress($content) . "\nendstream\nendobj\n";
        $text = TextExtractor::pdfText($pdf);
        $this->assertStringContainsString('Woonkamer 24,5 m2', $text);
        $rooms = TextExtractor::parseText($text);
        $this->assertCount(2, $rooms);
        $this->assertSame('Slaapkamer', $rooms[1]['name']);
    }

    public function testAiParseRoomsToleratesFencesAndJunk(): void
    {
        $txt = "Hier:\n```json\n{\"rooms\":[{\"name\":\"Woonkamer\",\"length_m\":5.2,\"width_m\":4.1,\"floor_area_m2\":null},"
            . "{\"name\":\"X\",\"length_m\":null,\"width_m\":null,\"floor_area_m2\":null},{\"name\":\"Hal\",\"floor_area_m2\":6}]}\n```";
        $rooms = AiExtractor::parseRooms($txt);
        $this->assertCount(2, $rooms);
        $this->assertSame('Hal', $rooms[1]['name']);
        $this->assertSame([], AiExtractor::parseRooms('geen json'));
    }

    public function testAiRequestShapes(): void
    {
        $claude = new AiExtractor('claude', 'm', 'KEY', static function () { return ['status' => 200, 'body' => '']; });
        $r = $claude->buildRequest('data', 'pdf');
        $this->assertSame('https://api.anthropic.com/v1/messages', $r['url']);
        $this->assertSame('KEY', $r['headers']['x-api-key']);
        $this->assertSame('document', $r['body']['messages'][0]['content'][0]['type']);
        $this->assertSame('image', $claude->buildRequest('data', 'png')['body']['messages'][0]['content'][0]['type']);
        $openai = new AiExtractor('openai', 'm', 'KEY', static function () { return ['status' => 200, 'body' => '']; });
        $o = $openai->buildRequest('data', 'jpg');
        $this->assertSame('Bearer KEY', $o['headers']['Authorization']);
        $this->assertSame('image_url', $o['body']['messages'][0]['content'][1]['type']);
        $this->assertSame('file', $openai->buildRequest('data', 'pdf')['body']['messages'][0]['content'][1]['type']);
    }

    public function testAiExtractThroughFakeHttp(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ow');
        file_put_contents($file, 'x');
        $reply = json_encode(['content' => [['type' => 'text', 'text' => '{"rooms":[{"name":"A","length_m":4,"width_m":3,"floor_area_m2":null}]}']]]);
        $ai = new AiExtractor('claude', 'm', 'K', static function () use ($reply) { return ['status' => 200, 'body' => $reply]; });
        $this->assertCount(1, $ai->extract($file, 'png'));
        $bad = new AiExtractor('claude', 'm', 'K', static function () { return ['status' => 401, 'body' => '{}']; });
        $this->expectException(ExtractionFailed::class);
        $bad->extract($file, 'png');
    }

    public function testUploadPolicy(): void
    {
        $this->assertSame('pdf', UploadPolicy::sniff('%PDF-1.7 xx'));
        $this->assertSame('jpg', UploadPolicy::sniff("\xFF\xD8\xFF\xE0abc"));
        $this->assertSame('png', UploadPolicy::sniff("\x89PNG\r\n\x1a\nxxxx"));
        $this->assertSame('webp', UploadPolicy::sniff('RIFF1234WEBPVP8 '));
        $this->assertNull(UploadPolicy::sniff('<?php echo 1;'));
        $this->assertNotNull(UploadPolicy::check(10, 100, null));
        $this->assertNotNull(UploadPolicy::check(0, 100, 'pdf'));
        $this->assertStringContainsString('te groot', UploadPolicy::check(200, 100, 'pdf'));
        $this->assertNull(UploadPolicy::check(50, 100, 'pdf'));
    }

    public function testRateLimiter(): void
    {
        $b = [];
        $this->assertTrue(RateLimiter::allow($b, 1000, 2, 60));
        $this->assertTrue(RateLimiter::allow($b, 1001, 2, 60));
        $this->assertFalse(RateLimiter::allow($b, 1002, 2, 60));
        $this->assertTrue(RateLimiter::allow($b, 1100, 2, 60));
    }

    public function testSessionToken(): void
    {
        $t = SessionToken::issue('secret', 1000);
        $this->assertFalse(SessionToken::verify($t, 'secret', 1001));
        $this->assertTrue(SessionToken::verify($t, 'secret', 1010));
        $this->assertFalse(SessionToken::verify($t, 'other', 1010));
        $this->assertFalse(SessionToken::verify($t, 'secret', 1000 + 86401));
        $this->assertFalse(SessionToken::verify('rommel', 'secret', 1010));
        $this->assertFalse(SessionToken::verify('1000.abc', 'secret', 1010));
    }
}
