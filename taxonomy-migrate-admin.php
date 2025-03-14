<?php
/**
 * Admin Panel for Taxonomy Migrate
 */
class Taxonomy_Migrate_Admin
{
    /**
     * Initialize the admin panel
     */
    public function __construct()
    {
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    /**
     * Add menu item to the WordPress admin
     */
    public function add_admin_menu()
    {
        add_management_page(
            __('Taxonomy Migrate', 'taxonomy-migrate'),
            __('Taxonomy Migrate', 'taxonomy-migrate'),
            'manage_options',
            'taxonomy-migrate',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Render the admin page
     */
    public function render_admin_page()
    {
        include plugin_dir_path(__FILE__) . 'interface.php';
    }
}