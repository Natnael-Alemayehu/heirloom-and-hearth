<?php
/**
 * Supplier User Role.
 * 
 * Registers a minimal "supplier" Wordpress user role that grants only the
 * capabilities required to update ingredient stock statuses via our custom
 * REST endpoint. No wp-admin dashbaord access is intended.
 * 
 * The role is added on plugin activation and removed on deactication.
 * 
 * @package HeirloomHearth
 */

namespace HeirloomHearth;

define( 'ABSPATH' ) || exit;

/**
 * Class Supplier_Role
 */
class Supplier_Role{
    /** Slug for the custom role. */
    public const ROLE_SLUG = 'hh_supplier';

    /**
     * Add the custom role. Called on plugin activation
     * 
     * Capabilities granded:
     *  read    - Required by WP for any authenticated REST call.
     *  hh_update_stock - Our own custom cap checked in the API endpoint.
     * 
     * @return void
     */
    public static function add_role(): void {
        add_role(
            self::ROLE_SLUG,
            __('Supplier', 'heirloom-hearth'),
            array(
                'read'              => true,
                'hh_update_stock'   => true,
            )
        );
    }

    /**
     * Remove the custom role. Called on Plugin deactivation.
     * 
     * Now: Users who had the role will be demoted to no role, not deleted.
     * 
     * @return void
     */
    public static function remove_role(): void {
        remove_role(self::ROLE_SLUG);
    }
}