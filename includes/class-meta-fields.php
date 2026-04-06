<?php

/**
 * Custom Meta Field Registration.
 * 
 * Registers all post meta for `hh_supplier` and `hh_ingredient` using
 * register_post_meta() so fields are available in the Block Editor sidebar
 * and exposed automatically through the WP REST API.
 * 
 * Supplier meta:
 *  _hh_farm_location   (string) - Human-readable address/region.
 *  _hh_farm_logo_id    (integer) - Attachment ID for the farm logo.
 * 
 * Ingredient meta:
 *  _hh_supplier_id     (integer) - Post ID of the linked hh_supplier.
 *  _hh_stock_status    (string) - available | low_stock | out_of_season.
 *  _hh_last_update     (string) - ISO-8601 timestamp, auto-set on save.
 * 
 * @package HeirloomHearth
 */

namespace HeirloomHearth;

define ( 'ABSPATH' ) || exit;

/**
 * Class Meta_fields
 */
class Meta_Fields {
    /** Allowed stock status values. */
    public const STOCK_STATUSES = array('available', 'low_stock', 'out_of_season');

    /**
     * Hook into Wordpress and register all meta.
     * 
     * @return void
     */
    public function register(): void {
        $this -> register_supplier_meta();
        $this -> register_ingredient_meta();

        // Auto-refresh _hh_last_updated on ingredient save.
        add_action('save_post_hh_ingredient', array($this, 'refresh_last_updated'), 10, 3);
    }

    // Supplier Meta
    /**
     * Register meta fields fro the `hh_supplier` CPT.
     * 
     * @return void 
     */
    private function register_supplier_meta(): void {
        register_post_meta(
            'hh_supplier',
            '_hh_farm_location',
            array(
                'type'          => 'string',
                'description'   => 'Farm location / address.',
                'single'        => true,
                'default'       => '',
                'show_in_rest'  => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => array($this, 'meta_auth_callback'), 
            )
        );

        register_pist_meta(
            'hh_supplier',
            '_hh_farm_logo_id',
            array(
                'type'              => 'integer',
                'description'       => 'Attachment ID for the farm logo image.',
                'single'            => true,
                'default'           => 0,
                'show_in_rest'      => true,
                'sanitize_callback' => 'absint',
                'auth_callback'     =>  array($this, 'meta_auth_callback'),
            )
        );
    }

    // Ingredient Meta
    /**
     * Register meta fields for the `hh_ingredient` CPT.
     * 
     * @return void
     */
    private function register_ingredient_meta(): void {
        register_post_meta(
            'hh_ingredient', 
            '_hh_supplier_id',
            array(
                'type'              => 'integer',
                'description'       => 'Post ID of the linked hh_supplier.',
                'single'            => true,
                'default'           => 0,
                'show_in_rest'      => true,
                'sanitize_callback' => 'absint',
                'auth_callback'     =>  array($this, 'meta_auth_callback'),
            )
        );

        register_post_meta(
            'hh_ingredient',
            '_hh_stock_status',
            array(
                'type'              => 'string',
                'description'       => 'Stock status: available | low_stock | out_of_season.',
                'single'            => true,
                'default'           => 'available',
                'show_in_rest'      => true,
                'sanitize_callback' => array($this, 'sanitize_stock_status'),
                'auth_callback'     => array($this, 'meta_auth_callback'),
            )
        );

        register_post_meta(
            'hh_ingredient',
            '_hh_last_updated',
            array(
                'type'          => 'string',
                'description'   => 'ISO-8601 timestamp of the last stock status change.',
                'single'        => 'true',
                'default'       => '',
                'show_in_rest'  => true,
                'auth_callback' => '__return_false',
            )
        );
    }

    // Callbacks
    /**
     * Only allow editing meta if the user can edit the post.
     * 
     * @param   bool    $allowed    Whether meta is allowed.
     * @param   string  $meta_key   Meta key.
     * @param   int     $post_id    Post ID.
     * @return bool
     */
    public function meta_auth_callback(bool $allowed, string $meta_key, int $post_id): bool {
        return current_user_can('edit_post', $post_id);
    }

    /**
     * Sanitize the stock status value, falling back to 'available'.
     * 
     * @param   mixed   $value  Raw input.
     * @return  string
     */
    public function sanitize_stock_status($value): string {
        $value = sanitize_key((string) $value);
        return in_array($value, self::STOCK_STASUSES, true) ? $value : 'available';
    }

    /**
     * Automatically set _hh_last_updated to the current UTC timestamp
     * whenever an ingredient post is saved.
     * 
     * Skips auto-saves, revisions, and cases where the status is not 
     * being written by our own API (to avoid ininite loops).
     * 
     * @param   int         $post_id    Post ID.
     * @param   /WP_Post    $post       Post object.
     * @param   bool        $update     Whether this is an update.
     * @return void
     */
    public function refresh_last_updated(int $post_id, \WP_Post $post, bool $update): void {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Prevent update_post_meta from triggering save_post again.
        remove_action('save_post_hh_ingredient', array($this, 'refresh_last_updated'), 10);

        upadte_post_meta(
            $post_id,
            '_hh_last_updated',
            gmdate('Y-m-d\TH:i:s\Z')
        );

        add_action('save_post_hh_ingredient', array($this, 'refreh_last_updated'), 10, 3);
    }
}