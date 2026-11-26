<?php
namespace OfferteWizard;

final class Rest
{
    const NS = 'offerte-wizard/v1';

    public static function register(): void
    {
        register_rest_route(self::NS, '/config', ['methods' => 'GET', 'callback' => [self::class, 'config'], 'permission_callback' => '__return_true']);
        register_rest_route(self::NS, '/upload', ['methods' => 'POST', 'callback' => [self::class, 'upload'], 'permission_callback' => '__return_true']);
        register_rest_route(self::NS, '/submit', ['methods' => 'POST', 'callback' => [self::class, 'submit'], 'permission_callback' => '__return_true']);
    }

    public static function config(): \WP_REST_Response
    {
        $s = Settings::get();
        $logo = (string) $s['logo_url'];
        if ($logo !== '' && $logo[0] === '/') {
            $logo = home_url($logo);
        }
        $r = new \WP_REST_Response([
            'steps' => Flow::steps(),
            'texts' => [
                'choice' => Flow::ERR_CHOICE, 'number' => Flow::ERR_NUMBER, 'upload' => Flow::ERR_UPLOAD,
                'contact' => Validator::MSG_ALL, 'email' => Validator::MSG_EMAIL, 'phone' => Validator::MSG_PHONE,
                'postcode' => Validator::MSG_POSTCODE, 'period' => Validator::MSG_PERIOD,
            ],
            'brand' => ['name' => $s['brand_name'], 'logo' => $logo, 'accent' => $s['color_accent'], 'dark' => $s['color_dark'], 'soft' => $s['color_soft']],
            'today' => wp_date('Y-m-d'),
            'session' => SessionToken::issue(self::secret(), time()),
            'ai_possible' => $s['ai_key'] !== '',
            'default_mode' => $s['mode'],
            'max_mb' => (int) $s['max_upload_mb'],
        ]);
        $r->header('Cache-Control', 'no-store');
        return $r;
    }

    /** @return \WP_REST_Response|\WP_Error */
    public static function upload(\WP_REST_Request $req)
    {
        $guard = self::guard((string) $req->get_param('session'), 'up', 10);
        if ($guard) {
            return $guard;
        }
        $s = Settings::get();
        $files = $req->get_file_params();
        if (empty($files['plan']) || !is_array($files['plan'])) {
            return new \WP_Error('ow_nofile', 'Kies eerst een bestand.', ['status' => 400]);
        }
        try {
            $stored = Uploads::store($files['plan'], (int) $s['max_upload_mb'] * 1048576);
        } catch (\RuntimeException $e) {
            return new \WP_Error('ow_upload', $e->getMessage(), ['status' => 400]);
        }

        $mode = (string) $req->get_param('mode');
        if (!in_array($mode, Settings::MODES, true)) {
            $mode = $s['mode'];
        }
        $chain = ModeResolver::chain($mode, $s['ai_key'] !== '', $stored['ext']);
        if ((string) $req->get_param('consent') !== '1') {
            $chain = array_values(array_diff($chain, ['ai']));
        }
        $result = Extraction::run($chain, ExtractorFactory::make($s), $stored['path'], $stored['ext']);
        $totals = AreaCalculator::totals($result['rooms'], (float) $s['ceiling_height']);

        return new \WP_REST_Response([
            'token' => $stored['token'],
            'source' => $result['source'],
            'rooms' => $totals['rooms'],
            'totals' => ['ceiling_m2' => $totals['ceiling_m2'], 'wall_m2' => $totals['wall_m2']],
        ]);
    }

    /** @return \WP_REST_Response|\WP_Error */
    public static function submit(\WP_REST_Request $req)
    {
        $body = $req->get_json_params();
        if (!is_array($body)) {
            return new \WP_Error('ow_bad', 'Ongeldige aanvraag.', ['status' => 400]);
        }
        if (!empty($body['hp'])) {
            // Honeypot ingevuld: doe alsof het gelukt is.
            return new \WP_REST_Response(['ok' => true, 'summary' => new \stdClass()]);
        }
        $guard = self::guard((string) ($body['session'] ?? ''), 'sub', 5);
        if ($guard) {
            return $guard;
        }
        $s = Settings::get();
        $answers = is_array($body['answers'] ?? null) ? $body['answers'] : [];
        $built = SubmissionBuilder::build($answers, wp_date('Y-m-d'), $s);
        if ($built['errors']) {
            return new \WP_Error('ow_invalid', reset($built['errors']), ['status' => 422, 'errors' => $built['errors']]);
        }
        $data = $built['data'];
        $showPrice = !empty($s['show_price']);
        $id = Storage::save($data, Mailer::compose($data, [], $showPrice)['body']);

        // Knoppen onderaan de mail aan de eigenaar (instelbaar).
        $links = [];
        $hasPrice = $data['pricing'] !== null && ($data['pricing']['stucwerk'] || $data['pricing']['schilderwerk']);
        if ($id) {
            if (!empty($s['owner_link_send']) && $hasPrice) {
                $links['send'] = Inbox::sendLink($id);
            }
            if (!empty($s['owner_link_admin'])) {
                // Niet get_edit_post_link(): die geeft een lege link voor een anonieme bezoeker.
                $links['admin'] = admin_url('post.php?post=' . $id . '&action=edit');
            }
        }
        $mail = Mailer::compose($data, $links, $showPrice);

        $to = $s['recipient'] !== '' ? $s['recipient'] : (string) get_option('admin_email');
        $name = trim($data['first_name'] . ' ' . $data['last_name']);
        $headers = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . self::cleanHeader($name) . ' <' . $data['email'] . '>'];
        $attachments = [];
        $tmp = '';
        if ($data['plan_token'] !== '' && ($file = Uploads::find($data['plan_token'])) !== null) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $tmp = trailingslashit(get_temp_dir()) . 'bouwplan-' . sanitize_file_name($name !== '' ? $name : 'klant') . '.' . $ext;
            if (@copy($file, $tmp)) {
                $attachments[] = $tmp;
            }
        }
        $sent = wp_mail($to, $mail['subject'], $mail['body'], $headers, $attachments);
        if ($tmp !== '' && is_file($tmp)) {
            @unlink($tmp);
        }
        if (!$sent) {
            error_log('[offerte-wizard] Mail naar ontvanger kon niet worden verzonden; aanvraag is wel bewaard.');
        }
        // Wat de klant per mail krijgt: niets, een ontvangstbevestiging (zonder prijs) of meteen de offerte.
        $mode = (string) $s['customer_mail'];
        $quoted = false;
        if ($mode === 'direct' && $id && $hasPrice) {
            $quoted = QuoteSender::send($id, [])[0];
        }
        if ($mode !== 'uit' && !$quoted) {
            $c = Mailer::composeConfirmation($data, (string) $s['brand_name']);
            wp_mail($data['email'], $c['subject'], $c['body'], ['Content-Type: text/plain; charset=UTF-8']);
        }
        return new \WP_REST_Response(['ok' => true, 'summary' => SubmissionBuilder::summary($data, $showPrice)]);
    }

    /** @return \WP_Error|null */
    private static function guard(string $session, string $kind, int $limit)
    {
        if (!SessionToken::verify($session, self::secret(), time())) {
            return new \WP_Error('ow_session', 'Je sessie is verlopen. Ververs de pagina; je antwoorden blijven bewaard.', ['status' => 403]);
        }
        $key = 'ow_rl_' . md5($kind . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . self::secret());
        $bucket = get_transient($key);
        $bucket = is_array($bucket) ? $bucket : [];
        $ok = RateLimiter::allow($bucket, time(), $limit, HOUR_IN_SECONDS);
        set_transient($key, $bucket, HOUR_IN_SECONDS);
        return $ok ? null : new \WP_Error('ow_rate', 'Je hebt te vaak geprobeerd. Probeer het over een uur opnieuw.', ['status' => 429]);
    }

    private static function secret(): string
    {
        return wp_salt('auth');
    }

    private static function cleanHeader(string $s): string
    {
        return trim(preg_replace('/[\r\n<>",]+/', ' ', $s));
    }
}
