<?php
/**
 * REST API Routes.
 * 
 * Namespace : hh/v1
 * 
 * Route                                Method      Auth            Description
 * /hh/v1/sourcing                      GET         Public          Ingredient list
 * /hh/v1/sourcing/(?P<id>\d+)          GET         Public          Single item
 * /hh/v1/sourcing/(?P<id>\d+)/stock    POST        Supplier JWT    Update stock
 * 
 * @package HeirloomHearth
 */

namespace HeirloomHearth;

defined ( 'ABSPATH' ) || exit;

/**
 * Class REST_API
 */

class REST_API{
    /** API namespace. */
    private const NAMESPACE = 'hh/v1';

    /** Base route for sourcing. */
    private const BASE = 'sourcing';

    /**
     * Register all REST routes.
     * 
     * @return void
     */
    public function register_routes(): void {
        // GET /hh/v1/sourcing
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE,
            array(
                'methods'               => \WP_REST_Server::READABLE,
                'callback'              => array($this, 'get_sourcing'),
                'permission_callback'   => '__return_true', 
                'args'                  => $this->get_collection_args(),
            )
        );
    }
}   