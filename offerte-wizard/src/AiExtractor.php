<?php
namespace OfferteWizard;

/** Laat een AI-model (Claude of OpenAI) de ruimtes en maten uit het bouwplan lezen. */
final class AiExtractor implements PlanExtractor
{
    const MIME = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    const PROMPT = 'Dit is een bouwtekening of plattegrond. Lees alle binnenruimtes (woonkamer, keuken, slaapkamers, '
        . 'badkamer, hal, overloop, toilet, etc.) af met de maten die op de tekening staan. Verzin niets: gebruik alleen '
        . 'afmetingen of oppervlaktes die zichtbaar zijn of direct uit de maatvoering volgen. Geef ALLEEN geldige JSON terug, '
        . 'zonder uitleg, in dit formaat: {"rooms":[{"name":"Woonkamer","length_m":5.2,"width_m":4.1,"floor_area_m2":21.3}]}. '
        . 'Gebruik null voor wat je niet kunt aflezen. Maten in meters, oppervlak in m².';

    /** @var callable(string,array,string):array{status:int,body:string} */
    private $http;
    private string $provider;
    private string $model;
    private string $key;

    public function __construct(string $provider, string $model, string $key, callable $http)
    {
        $this->provider = $provider === 'openai' ? 'openai' : 'claude';
        $this->model = $model;
        $this->key = $key;
        $this->http = $http;
    }

    /** @return array{url:string,headers:array<string,string>,body:array} */
    public function buildRequest(string $bytes, string $ext): array
    {
        $mime = self::MIME[$ext] ?? 'application/octet-stream';
        $b64 = base64_encode($bytes);
        if ($this->provider === 'openai') {
            $part = $ext === 'pdf'
                ? ['type' => 'file', 'file' => ['filename' => 'bouwplan.pdf', 'file_data' => 'data:application/pdf;base64,' . $b64]]
                : ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . $b64]];
            return [
                'url' => 'https://api.openai.com/v1/chat/completions',
                'headers' => ['Authorization' => 'Bearer ' . $this->key, 'Content-Type' => 'application/json'],
                'body' => ['model' => $this->model, 'messages' => [['role' => 'user', 'content' => [['type' => 'text', 'text' => self::PROMPT], $part]]]],
            ];
        }
        $block = ['type' => $ext === 'pdf' ? 'document' : 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $b64]];
        return [
            'url' => 'https://api.anthropic.com/v1/messages',
            'headers' => ['x-api-key' => $this->key, 'anthropic-version' => '2023-06-01', 'Content-Type' => 'application/json'],
            'body' => ['model' => $this->model, 'max_tokens' => 2000, 'messages' => [['role' => 'user', 'content' => [$block, ['type' => 'text', 'text' => self::PROMPT]]]]],
        ];
    }

    public function extract(string $path, string $ext): array
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new ExtractionFailed('Bestand niet te lezen.');
        }
        $req = $this->buildRequest($bytes, $ext);
        $resp = ($this->http)($req['url'], $req['headers'], (string) json_encode($req['body']));
        if (($resp['status'] ?? 0) !== 200) {
            throw new ExtractionFailed('AI-dienst gaf status ' . (int) ($resp['status'] ?? 0));
        }
        $json = json_decode((string) $resp['body'], true);
        $text = $this->provider === 'openai'
            ? ($json['choices'][0]['message']['content'] ?? '')
            : ($json['content'][0]['text'] ?? '');
        return self::parseRooms((string) $text);
    }

    /** @return array<int,array{name:string,length_m:?float,width_m:?float,floor_area_m2:?float}> */
    public static function parseRooms(string $text): array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            return [];
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        if (!is_array($data) || !isset($data['rooms']) || !is_array($data['rooms'])) {
            return [];
        }
        $rooms = [];
        foreach (array_slice($data['rooms'], 0, 50) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $room = [
                'name' => mb_substr(trim(strip_tags((string) ($r['name'] ?? ''))), 0, 60),
                'length_m' => self::pos($r['length_m'] ?? null),
                'width_m' => self::pos($r['width_m'] ?? null),
                'floor_area_m2' => self::pos($r['floor_area_m2'] ?? null),
            ];
            if ($room['floor_area_m2'] !== null || ($room['length_m'] !== null && $room['width_m'] !== null)) {
                $rooms[] = $room;
            }
        }
        return $rooms;
    }

    private static function pos($v): ?float
    {
        return (is_numeric($v) && (float) $v > 0 && (float) $v < 1000) ? (float) $v : null;
    }
}
