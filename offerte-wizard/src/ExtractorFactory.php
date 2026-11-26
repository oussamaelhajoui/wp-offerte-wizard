<?php
namespace OfferteWizard;

final class ExtractorFactory
{
    /** @return array<string,PlanExtractor> */
    public static function make(array $settings): array
    {
        $extractors = ['tekst' => new TextExtractor()];
        if ($settings['ai_key'] !== '') {
            $extractors['ai'] = new AiExtractor(
                $settings['ai_provider'],
                $settings['ai_model'],
                $settings['ai_key'],
                [self::class, 'http']
            );
        }
        return $extractors;
    }

    /** @return array{status:int,body:string} */
    public static function http(string $url, array $headers, string $body): array
    {
        $resp = wp_remote_post($url, ['headers' => $headers, 'body' => $body, 'timeout' => 60]);
        if (is_wp_error($resp)) {
            return ['status' => 0, 'body' => ''];
        }
        return ['status' => (int) wp_remote_retrieve_response_code($resp), 'body' => (string) wp_remote_retrieve_body($resp)];
    }
}
