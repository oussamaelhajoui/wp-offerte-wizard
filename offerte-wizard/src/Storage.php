<?php
namespace OfferteWizard;

/** Bewaart elke aanvraag als privé item in wp-admin ("Offerte-aanvragen"). */
final class Storage
{
    const POST_TYPE = 'ow_aanvraag';
    const STATUS_NEW = 'nieuw';
    const STATUS_SENT = 'verstuurd';
    const STATUS_DONE = 'afgehandeld';

    public static function register(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Offerte-aanvragen',
                'singular_name' => 'Offerte-aanvraag',
                'menu_name' => 'Offerte-aanvragen',
                'edit_item' => 'Aanvraag bekijken',
                'not_found' => 'Nog geen aanvragen',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-clipboard',
            'menu_position' => 58,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'capabilities' => ['create_posts' => 'do_not_allow'],
        ]);
    }

    /** @return int post-id of 0 bij mislukken */
    public static function save(array $data, string $body): int
    {
        $title = trim($data['first_name'] . ' ' . $data['last_name']) . ' – ' . $data['locality'];
        $id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'post_title' => wp_strip_all_tags($title),
            'post_content' => $body,
        ], true);
        if (is_wp_error($id)) {
            return 0;
        }
        update_post_meta($id, '_ow_data', wp_slash(wp_json_encode($data)));
        update_post_meta($id, '_ow_status', self::STATUS_NEW);
        return (int) $id;
    }

    /** @return array|null de schone aanvraag zoals SubmissionBuilder die maakte */
    public static function data(int $id): ?array
    {
        if ($id <= 0 || get_post_type($id) !== self::POST_TYPE) {
            return null;
        }
        $d = json_decode((string) get_post_meta($id, '_ow_data', true), true);
        return is_array($d) ? $d : null;
    }

    public static function status(int $id): string
    {
        $s = (string) get_post_meta($id, '_ow_status', true);
        return in_array($s, [self::STATUS_NEW, self::STATUS_SENT, self::STATUS_DONE], true) ? $s : self::STATUS_NEW;
    }

    public static function setStatus(int $id, string $status): void
    {
        update_post_meta($id, '_ow_status', $status);
    }
}
