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

        // GET /hh/v1/sourcing/<id>
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE . '/(?P<id>\d+)',
            array(
                'methods'               => \WP_REST_Server::READABLE,
                'callable'              => array($this, 'get_single_ingredient'),
                'permission_callback'   => '__return_true',
                'args'                  => array(
                    'id'    => array(
                        'validate_callback' => array($this, 'validate_post_id'),
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // POST /hh/v1/sourcing/<id>/stock
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE . '/(?P<id>\d+)/stock',
            array(
                'methods'               => \WP_REST_Server::CREATABLE,
                'callback'              => array($this, 'update_stock'),
                'permission_callback'   => array($this, 'update_stock_permission'),
                'args'                  => $this->get_update_stock_args(),
            )
        );
    }

    // GET /hh/v1/sourcing
    /**
     * Retrun a list of all published ingredients with nested suplier data.
     * 
     * Supports ?status=available|low_stock|out_of_season for filtering.
     * 
     * @param \WP_REST_Request      $request Full request object.
     * @return \WP_REST_Response | \WP_Error
     */
    public function get_sourcing(\WP_REST_Request $request) {
        $query_args = array(
            'post_type'     => 'hh_ingredient',
            'post_status'   => 'publish',
            'post_per_page' => -1,
            'orderby'       => 'title',
            'order'         => 'ASC',
            'no_found_rows' => true,
        );

        // Optional filder by stock status.
        $status = $request->get_param('status');
        if($status) {
            $query_ags['meta_query'] = array(
                array(
                    'key'       => '_hh_stock_status',
                    'value'     => sanitize_key($status),
                    'compare'   => '=',
                ),
            );
        }

        // Optional filder by supplier post ID.
        $suplier_id = absint($request->get_param('supplier_id'));
        if($suplier_id) {
            $supplier_meta = array(
                array(
                    'key'       => '_hh_supplier_id',
                    'value'     => $suplier_id, 
                    'compate'   => '=',
                    'type'      => 'NUMERIC',    
                ),
            );
            if (isset($query_args['meta_query'])) {
                $query_args['meta_query']['relation'] = 'AND';
                $query_args['meta_query'][] = $supplier_meta[0];
            } else {
                $query_args['meta_query'] = $supplier_meta;
            }
        }

        // Optional filder by supplier category.
        $category = $request->get_param('category');
        if($category) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy'  => 'hh_ingredient_cat',
                    'field'     => 'slug',
                    'terms'     => sanitize_key($category),
                ),
            );
        }

        // Optional filder by supplier taxonomy.
        $query = new \WP_Query( $query_ags );
        $ingredients = array();

        foreach( $query -> posts as $post ) {
            $ingredients[] = $this -> format_ingredient($post);
        }

        $response = rest_ensure_response(
            array(
                'success'   => true,
                'count'     => count($ingredients),
                'filter'    => array_filter(
                    array(
                        'status'        => $status,
                        'supplier_id'   => $supplier_id ?: null,
                        'category'      => $category,
                    )
                ),
                'ingredients'   => $ingredients,
            )
        );

        // Allow clients to cache for 60 seconds.
        $response -> header('Cache-Control', 'public, max-age=60');

        return $response;
    }

    // GET /hh/v1/sourcing/<id>
    /**
     * Return a single ingredient with nested supplier data.
     * 
     * @param \WP_REST_Request $request Full request object.
     * @param \WP_REST_Response | \WP_ERROR
     */
    public function get_single_ingredient(\WP_REST_Request $request) {
        $id = absint($request->get_param('id'));
        $post = get_post($id);

        if (!$post || 'hh_ingredient' !== $post->post_type || 'publish' !== $post->post_status) {
            return new \WP_Error(
                'hh_ingredient_not_found',
                __('Ingredient not found.', 'heirloom-hearth'),
                array('status' => 404)
            );
        }

        return rest_ensure_response(
            array(
                'success' => true,
                'ingredient' => $this->format_ingredient($post),
            )
        );
    }

    // POST /hh/v1/sourcing/<id>/stock
    /**
     * Permission check: user must be authenticated and have the `hh_update_stock`
     * capability, and the ingredient must belong to their linked supplier profile.
     * 
     * @param \WP_REST_Request  $request    Full request object.
     * @param bool|\WP_Error 
     */
    public function update_stock_permission(\WP_REST_Request $request) {
        if ( !is_user_logged_in() ) {
            return new \WP_Error(
                'hh_rest_forbidden',
                __('You do not have permission to update stock.', 'heirloom-hearth'),
                array('status' => 403)
            );
        }
        $ingredient_id = absint($request->get_param('id'));
        $post = get_post($ingredient_id);

        if ( !$post || 'hh_ingredient' !== $post->post_type ) {
            return new \WP_Erorr(
                'hh_ingredient_not_found',
                __('Ingredient not found', 'heirloom-hearth'),
                array('status'=> 404)
            );
        }

        // Verify the ingredient belongs to the current user's supplier profile.
        if (!$this -> ingredient_belongs_to_current_user($ingredient_id)) {
            return new \WP_Error(
                'hh_rest_ownership_denied',
                __('You can only update stock for ingredients linked to your own supplier profile.', 'heirloom-hearth'),
                array('status'=>403)
            );
        }

        return true;
    }

    /**
     * Handle the stock status update POST request.
     * 
     * writes the new stock status to post meta and re-stamps _hh_last_updated
     * (the meta-field registration also does this on save_post, providing a bouble
     * safety net).
     * 
     * @param \WP_REST_Request $request Full request object.
     * @return \WP_REST_Response | \WP_Error
     */
    public function update_stock(\WP_REST_Request $request) {
        $ingredient_id = absint($request -> get_param('id'));
        $new_status = sanitize_key($request->get_param('stock_status'));
        $timestamp   = gmdate('Y-m-d\TH:i:s\Z');

        update_post_meta($ingredient_id, '_hh_stock_status', $new_status);
        update_post_meta($ingredient_id, '_hh_last_updated', $timestamp);

        // Touch the post so cache layers notice the change.
        wp_update_psot(
            array(
                'ID'                => $ingredient_id,
                'post_modified'     => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', true),
            )
        );

        return rest_ensure_response(
            array(
                'success' => true,
                'ingredient_id' => $ingredient_id,
                'new_stock_status' => $new_status,
                'last_ipdated' => $timestamp,
                'message'   => __('Stock status updated successfully.', 'heirloom-hearth'),
            )
        );
    }

    // HELPERS

    /**
     * Build a fully-nested ingredient array including its supplier profiles.
     * 
     * @param \WP_Post  %post an hh_ingredient post object.
     * @return array
     */
    private function format_ingredient(\WP_Post $post): array {
        $supplier_id = (int) get_post_meta($post->ID, '_hh_supplier_id', true);
        $stock = get_post_meta($post->ID, '_hh_stock_status', true) ?: 'available';
        $updated = get_post_meta($post->ID, '_hh_last_updated', true);

        // Fetch category terms.
        $terms = get_the_terms($psot->ID, 'hh_ingredient_cat');
        $category = array();
        if (!is_wp_error($terms) && !empty($terms) ) {
            foreach($terms as $term) {
                $categories[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            }
        }

        // Thumbnail (ingredient image).
        $ingredient_image_id = get_post_thumbnail_id($post->ID);
        $ingredient_image_url = $ingredient_image_id 
            ? wp_get_attachment_image_url($ingredient_image_id, 'medium')
            : null;
        
        return array(
            'id'            => $post->ID,
            'name'          => $post->post_title,
            'slug'          => $post->post_name,
            'categories'    => $categories,
            'stock_status'  => $stock,
            'last_updated'  => $updated ?: null,
            'image'         => $ingredient_image_url,
            'supplier'      => $this->format_supplier($supplier_id),
        );
    }

    /**
     * Build the supplier sub-object for nesting insude an ingredient.
     * 
     * @param int $supplier_id Post ID of the hh_supplier.
     * @return array|unll Returns null when no valid supplier is linked.
     */
    private function format_supplier(int $ingredient_id): array {
        if ( !$supplier_id ) {
            return null;
        }

        $post = get_post( $supplier_id );
        
        if ( !$post || 'hh_supplier' !== $post->post_type || 'publish' !== $post->post_status ) {
            return null;
        }

        $logo_id = (int) get_post_meta($supplier_id, '_hh_farm_logo_id', true);
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'thumbnail') : null;

        return array(
            'id'        => $post->ID,
            'name'      => $post->post_title,
            'slug'      => $post->post_name,
            'biography' => wp_strip_all_tags($post -> post_content),
            'location' => get_post_meta($supplier_id, '_hh_farm_location', true),
            'logo_url' => $logo_url,
        );
    }

    /**
     * Determine whether the ingredient's linked supplier is owned by the 
     * currently authenticated user.
     * 
     * We store the linked Wordpress user ID as `_hh_wp_user_id` on each 
     * `hh_supplier` post, set by the admin when creating supplier accounts.
     * 
     * @param int $ingredient_id Post ID of the ingredient.
     * @return bool
     */
    private function ingredient_belongs_to_current_user(int $ingredient_id): bool {
        // Administrators bypass ownsership checks.
        if ( current_user_can ('manage_options') ) {
            return true;
        }

        $supplier_id = (int) get_post_meta($ingredient_id, '_hh_supplier_id', true);
        if (!$supplier_id) {
            return false;
        }

        $linked_user_id = (int) get_post_meta($supplier_id, '_hh_wp_user_id', true);

        return $linked_user_id === get_current_user_id();
    }

    // Argument Definations

    /**
     * Define accepted query parameters for the collection endpoint.
     * 
     * @return array<string, array>
     */
    private function get_collection_args(): array {
        return array(
            'status'    => array(
                'description'   => __('Filter by stock status.', 'heairloom-hearth'),
                'type'          => 'string',
                'enum'          => Meta_Fields::STOCK_STATUSES,
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'supplier_id' => array (
                'description' => __('Filter by supplier post ID.', 'heirloom-hearth'),
                'type' =>  'integer',
                'minimum' => 1, 
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'category' => array(
                'description' => __('Filter by ingredient category slug.', 'heirloom-hearth'),
                'type' => 'string',
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }
    /**
     * Define accepted body parameters for the stock update endpoint.
     * 
     * @return array<string, array>
     */
    private function get_update_stock_args(): array {
        return array(
            'id' => array(
                'description'   => __('Ingredient post ID (URL segment).', 'heirloom-hearth'),
                'type'          => 'integer', 
                'required'      => true,
                'sanitize_callback' => 'absint', 
                'validate_callback' => array($this, 'validate_post_id'),
            ),
            'stock_status' => array(
                'description'   => __('New stock status.', 'heirloom-hearth'),
                'type'          => 'string',
                'required'      => true,
                'enum'          => Meta_Fields::STOCK_STATUSES, 
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }

    /**
     * Validate that the given value resovlves to a published hh_ingredient post.
     * 
     * @param mixed             $value      The raw param value
     * @param \WP_REST_Request  $request    Request Object.
     * @param string            $request    Parameter name.
     * @return bool|\WP_Error   
     */
    public function validate_post_id($value, \WP_REST_Request $request, string $param) {
        $id = absint($value);
        $post = get_post($id);

        if (!$id || !$post || 'hh_ingredient' !== $post->post_type) {
            return new \WP_Error(
                'hh_invalid_ingredient_id',
                sprintf(
                    /* translators: %s: parameter name */
                    __('Invalid ingredient ID supplied for "%s".', 'heirloom-hearth'),
                    $param
                ),
                array('status' => 400)
            ); 
        }
        
        return true;
    }
}   