<?php

/**
 * Custom Post Type Registration.
 * 
 * Registers the `hh_supplier` and `hh_ingredient` post types.
 * 
 * @package HeirloomHearth
 */

namespace HeirloomHearth;

define( 'ABSPATH' ) || exit;

/**
 * Class CPT_Registration
 */

class CPT_Registation {
    /**
     * Register both CPTs.
     * 
     * @return void
     */
    public function register(): void{
        $this -> register_suppliers();
        $this -> register_ingredients();
        $this -> register_ingredient_category_taxonomy();
    }

    // Suppliers
    /**
     * Register the `hh_suppluer` CPT.
     * 
     * @return void
     */
    private function register_suppliers(): void {
        $labels = array(
            'name'                  => _x('Suppliers', 'post type general name', 'heirloom-hearth'),
            'singular_name'         => _x('Supplier', 'post type singular name', 'heirloom-hearth'),
            'menu_name'             => __('Suppliers', 'heirloom-hearth'),
            'add_new'               => __('Add New', 'heirloom-hearth'),
            'add_new_item'          => __('Add New Supplier', 'heirloom-hearth'),
            'edit_item'             => __('Edit Supplier', 'heirloom-hearth'),
            'new_item'              => __('New Supplier', 'heirloom-hearth'),
            'view_item'             => __('View Supplier', 'heirloom-hearth'),
            'search_items'          => __('Search Suppliers', 'heirloom-hearth'),
            'not_found'             => __('No Suppliers found.', 'heirloom-hearth'),
            'not_found_in_trash'    => __('No Suppliers found in Trash', 'heirloom-hearth'), 
        );

        $args = array(
            'labels'    => $labels,
            'public'    => false,
            'show_ui'   => true,
            'show_in_menu' => true,
            'show_in_rest' => true, 
            'rest_base' => 'hh-suppliers',
            'menu_icon' => 'dashicons-store',
            'supports' => array('title', 'editor', 'thumbnail'),
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'map_meta_cap' => true,             
        );

        register_post_type('hh_supplier', $args);
    }

    // Daily Ingredients
    /**
     * Register the `hh_ingredient` CPT.
     * 
     * @return void
     */
    private function register_ingredients(): void {
        $labels = array(
            'name' => _x('Daily Ingredients', 'post type general name', 'heirloom-hearth'),
            'singular_name' => _x('Ingredient', 'post type singular name', 'heirloom-hearth'),
            'menu_name' => __('Daily Ingredinets', 'heirloom-hearth'),
            'add_new' => __('Add New', 'heirloom-hearth'),
            'add_new_item' => __('Add New Ingredient', 'heirloom-hearth'),
            'edit_item' => __('Edit Ingredient', 'heirloom-hearth'),
            'new_item' => __('Add New', 'heirloom-hearth'),
            'add_new_item' => __('Add New Ingredient', 'heirloom-hearth'),
            'view_item' => __('View Ingredients', 'heirloom-hearth'),
            'search_items' => __('Search Ingredients', 'heirloom-hearth'),
            'not_found' => __('No ingredients found.', 'heirloom-hearth'),
            'not_found_in_trash' => __('No Ingredients found in Trash.', 'heirloom-hearth'),
        );

        register_post_type('hh_ingredient', $args);
    }

    // Ingredient Category Taxonomy
    /**
     * Register a `hh_ingredient_cat` taxonomy for ingredient categories
     * (e.g., Vegitables, Herbs, Dairy, Protiens).
     * 
     * @return void
     */
    private function register_ingredient_category_taxonomy(): void{
        $labels = array(
            'name'  => _x('Ingredient Categories', 'heirloom-hearth'),
            'singular_name' => _x('Ingredient Category', 'taxonomy singular name', 'heirloom-hearth'),
            'search_items' => __('Search Categories', 'heirloom-hearth'),
            'all_items' => __('All Category', 'heirloom-hearth'),
            'edit_item' => __('Edit Category', 'heirloom-hearth'),
            'update_item' => __('Update Category', 'heirloom-hearth'),
            'add_new_item' => __('Add New Category', 'heirloom-hearth'),
            'menu_name' => __('Categories', 'heirloom-hearth'),
        );

        register_taxonomy(
            'hh_ingredient_cat',
            'hh_ingredient',
            array(
                'labels'            => $labels,
                'heirarchical'      => true,
                'show_ui'           => true,
                'show_in_rest'      => true,
                'show_admin_column' => true,
                'rewrite'           => false,
            )
        );
    }
}