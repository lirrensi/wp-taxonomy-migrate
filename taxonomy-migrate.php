<?php
/**
 * Plugin Name: Taxonomy Migrate
 * Description: Migrate posts from one taxonomy term to another with ease.
 * Version: 1.1.3
 * Author: lirrensi | Claude 3.7
 * Text Domain: taxonomy-migrate
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('TAXONOMY_MIGRATE_VERSION', '1.1.3');

if (!function_exists('log_to_file')) {
    /**
     * Log data to a file in the child theme directory.
     *
     * @param mixed $data The data to log.
     * @param string $filename The name of the log file.
     */
    function log_to_file($data, $filename = 'taxonomy-migrate-log.txt')
    {
        // Get the child theme directory
        $child_theme_dir = get_stylesheet_directory();

        // Construct the full path to the log file
        $log_file = $child_theme_dir . '/' . $filename;

        // Ensure the directory is writable
        if (!is_writable($child_theme_dir)) {
            error_log('Directory not writable: ' . $child_theme_dir);
            return;
        }

        // Format the data as a string
        if (is_array($data) || is_object($data)) {
            $data = print_r($data, true);
        }

        // Add a timestamp to the log entry
        $log_entry = '[' . date('Y-m-d H:i:s') . '] ' . $data . PHP_EOL;

        // Write the log entry to the file, creating the file if it doesn't exist
        if (file_put_contents($log_file, $log_entry, FILE_APPEND) === false) {
            error_log('Failed to write to log file: ' . $log_file);
        }
    }
}

include_once plugin_dir_path(__FILE__) . 'taxonomy-migrate-admin.php';

class Taxonomy_Migrate
{
    /**
     * Initialize the plugin
     */
    public function __construct()
    {
        // Register scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers
        add_action('wp_ajax_get_taxonomy_terms', [$this, 'get_taxonomy_terms']);
        add_action('wp_ajax_migrate_taxonomy_terms', [$this, 'migrate_taxonomy_terms']);

        // Action Scheduler integration
        add_action('taxonomy_migrate_scheduled_migration', [$this, 'process_scheduled_migration'], 10, 6);

        // Initialize admin panel
        new Taxonomy_Migrate_Admin();
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_assets($hook)
    {
        if ('tools_page_taxonomy-migrate' !== $hook) {
            return;
        }

        wp_enqueue_style('taxonomy-migrate-css', plugin_dir_url(__FILE__) . 'assets/css/taxonomy-migrate.css', [], TAXONOMY_MIGRATE_VERSION);

        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');

        wp_enqueue_script('taxonomy-migrate-js', plugin_dir_url(__FILE__) . 'assets/js/taxonomy-migrate.js', ['jquery', 'select2'], TAXONOMY_MIGRATE_VERSION, true);

        wp_localize_script('taxonomy-migrate-js', 'taxonomyMigrate', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('taxonomy-migrate-nonce'),
            'loading'   => __('Loading...', 'taxonomy-migrate'),
            'success'   => __('Migration completed successfully!', 'taxonomy-migrate'),
            'error'     => __('An error occurred. Please try again.', 'taxonomy-migrate'),
            'scheduled' => __('Migration has been scheduled successfully!', 'taxonomy-migrate')
        ]);
    }

    /**
     * AJAX handler to get taxonomy terms
     */
    public function get_taxonomy_terms()
    {
        check_ajax_referer('taxonomy-migrate-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'taxonomy-migrate'));
        }

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';

        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            wp_send_json_error(__('Invalid taxonomy.', 'taxonomy-migrate'));
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false
        ]);

        $formatted_terms = [];

        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                // Get post count
                $post_count = $term->count;

                // Get term hierarchy display
                $ancestors = get_ancestors($term->term_id, $taxonomy, 'taxonomy');
                $hierarchy = '';

                if (!empty($ancestors)) {
                    $hierarchy = str_repeat('— ', count($ancestors));
                }

                $formatted_terms[] = [
                    'id'         => $term->term_id,
                    // 'text'       => $hierarchy . $term->name . ' (' . $post_count . ' posts)',
                    'text'       => "{$hierarchy} {$term->name} [{$term->slug}] [{$post_count} posts]",
                    'name'       => $term->name,
                    'slug'       => $term->slug,
                    'post_count' => $post_count
                ];
            }
        }

        wp_send_json_success($formatted_terms);
    }

    /**
     * AJAX handler to migrate taxonomy terms
     */
    public function migrate_taxonomy_terms()
    {
        check_ajax_referer('taxonomy-migrate-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'taxonomy-migrate'));
        }

        $source_taxonomy      = isset($_POST['source_taxonomy']) ? sanitize_text_field($_POST['source_taxonomy']) : '';
        $source_terms         = isset($_POST['source_terms']) ? array_map('intval', $_POST['source_terms']) : [];
        $destination_taxonomy = isset($_POST['destination_taxonomy']) ? sanitize_text_field($_POST['destination_taxonomy']) : '';
        $destination_term     = isset($_POST['destination_term']) ? intval($_POST['destination_term']) : 0;
        $delete_source_term   = isset($_POST['delete_source_term']) ? (bool) $_POST['delete_source_term'] : false;
        $use_scheduler        = isset($_POST['use_scheduler']) ? (bool) $_POST['use_scheduler'] : false;
        $operation_type       = isset($_POST['operation_type']) ? sanitize_text_field($_POST['operation_type']) : 'move';

        if (empty($source_taxonomy) || empty($source_terms)) {
            wp_send_json_error(__('Source taxonomy and terms are required.', 'taxonomy-migrate'));
        }

        if (($operation_type === 'move' || $operation_type === 'add') && (empty($destination_taxonomy) || empty($destination_term))) {
            wp_send_json_error(__('Destination taxonomy and term are required for move and add operations.', 'taxonomy-migrate'));
        }

        if ($use_scheduler) {
            foreach ($source_terms as $source_term) {
                $args = [
                    'source_taxonomy'      => $source_taxonomy,
                    'source_term_id'       => $source_term,
                    'destination_taxonomy' => $destination_taxonomy,
                    'destination_term_id'  => $destination_term,
                    'delete_source_term'   => $delete_source_term,
                    'operation_type'       => $operation_type
                ];

                if (class_exists('ActionScheduler')) {
                    // Use Action Scheduler
                    as_schedule_single_action(time(), 'taxonomy_migrate_scheduled_migration', $args);
                } else {
                    // Use WordPress Cron
                    $timestamp = time() + 60; // Schedule 1 minute from now
                    wp_schedule_single_event($timestamp, 'taxonomy_migrate_scheduled_migration', $args);
                }
            }

            wp_send_json_success([
                'message' => __('Migration has been scheduled successfully!', 'taxonomy-migrate')
            ]);
        } else {
            // Perform immediate migration
            $results = [];
            foreach ($source_terms as $source_term) {
                $args = [
                    'source_taxonomy'      => $source_taxonomy,
                    'source_term_id'       => $source_term,
                    'destination_taxonomy' => $destination_taxonomy,
                    'destination_term_id'  => $destination_term,
                    'delete_source_term'   => $delete_source_term,
                    'operation_type'       => $operation_type
                ];

                $result = $this->perform_migration($args);

                if (is_wp_error($result)) {
                    wp_send_json_error($result->get_error_message());
                }

                $results[] = $result;
            }

            wp_send_json_success([
                'message'       => __('Operation completed successfully!', 'taxonomy-migrate'),
                'results'       => $results,
                'redirect_code' => ''
            ]);
        }
    }

    /**
     * Process the scheduled migration
     */
    public function process_scheduled_migration(
        $source_taxonomy,
        $source_term_id,
        $destination_taxonomy,
        $destination_term_id,
        $delete_source_term,
        $operation_type = 'move'
    ) {
        $args = [
            'source_taxonomy'      => $source_taxonomy,
            'source_term_id'       => $source_term_id,
            'destination_taxonomy' => $destination_taxonomy,
            'destination_term_id'  => $destination_term_id,
            'delete_source_term'   => $delete_source_term,
            'operation_type'       => $operation_type
        ];

        $this->perform_migration($args);
    }

    /**
     * Perform the actual migration based on operation type
     */
    private function perform_migration($args)
    {
        global $wpdb;

        // Extract arguments
        $source_taxonomy      = $args['source_taxonomy'];
        $source_term_id       = $args['source_term_id'];
        $destination_taxonomy = $args['destination_taxonomy'];
        $destination_term_id  = $args['destination_term_id'];
        $delete_source_term   = $args['delete_source_term'];
        $operation_type       = $args['operation_type'];

        // Start a transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Get posts with the source term
            $posts = get_posts([
                'numberposts' => -1,
                'post_type'   => 'any',
                'tax_query'   => [
                    [
                        'taxonomy' => $source_taxonomy,
                        'field'    => 'term_id',
                        'terms'    => $source_term_id,
                        'operator' => 'IN'
                    ]
                ]
            ]);

            // // Enhanced debug logging
            // if (empty($posts)) {
            //     log_to_file("No posts found for taxonomy: {$source_taxonomy}, term_id: {$source_term_id}");

            //     // Double check term exists and get direct count from database
            //     $term = get_term($source_term_id, $source_taxonomy);
            //     if ($term) {
            //         log_to_file("Term exists: " . print_r($term, true));

            //         // Direct database query to count relationships
            //         $count = $wpdb->get_var($wpdb->prepare("
            //             SELECT COUNT(DISTINCT tr.object_id)
            //             FROM {$wpdb->term_relationships} tr
            //             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            //             WHERE tt.term_id = %d AND tt.taxonomy = %s
            //         ", $source_term_id, $source_taxonomy));

            //         log_to_file("Direct database count of posts for term: {$count}");
            //     } else {
            //         log_to_file("Term does not exist!");
            //     }
            // }

            // // Log the list of posts found
            // log_to_file('Found ' . count($posts) . ' posts | Args: ' . print_r($args, true));

            $posts_affected = 0;

            // Process each post based on operation type
            switch ($operation_type) {
                case 'move':
                    $posts_affected = $this->perform_move_operation($posts, $source_taxonomy, $source_term_id, $destination_taxonomy, $destination_term_id);
                    break;

                case 'add':
                    $posts_affected = $this->perform_add_operation($posts, $destination_taxonomy, $destination_term_id);
                    break;

                case 'remove':
                    $posts_affected = $this->perform_remove_operation($posts, $destination_taxonomy, $destination_term_id);
                    break;

                default:
                    throw new Exception(__('Invalid operation type.', 'taxonomy-migrate'));
            }

            clean_term_cache($destination_term_id, $destination_taxonomy);

            $source_term_deleted = false;

            // Delete source term if requested
            if ($delete_source_term) {
                $delete_result       = wp_delete_term($source_term_id, $source_taxonomy);
                $source_term_deleted = (!is_wp_error($delete_result) && $delete_result);
            }

            // Commit the transaction
            $wpdb->query('COMMIT');

            return [
                'posts_affected'      => $posts_affected,
                'source_term_deleted' => $source_term_deleted
            ];

        } catch (Exception $e) {
            // Rollback the transaction
            $wpdb->query('ROLLBACK');

            return new WP_Error('operation_failed', $e->getMessage());
        }
    }

    /**
     * Perform MOVE operation - removes term from source and adds to destination
     */
    private function perform_move_operation($posts, $source_taxonomy, $source_term_id, $destination_taxonomy, $destination_term_id)
    {
        $posts_affected = 0;

        foreach ($posts as $post) {
            $post_id = $post->ID; // Access the post ID from the post object

            // Remove the source term
            wp_remove_object_terms($post_id, $source_term_id, $source_taxonomy);

            // Add the destination term
            wp_set_object_terms($post_id, $destination_term_id, $destination_taxonomy, true);

            // Clear caches
            clean_post_cache($post_id);

            $posts_affected++;
        }

        return $posts_affected;
    }

    /**
     * Perform ADD operation - for all in source, append term/taxonomy of destination
     */
    private function perform_add_operation($posts, $destination_taxonomy, $destination_term_id)
    {
        $posts_affected = 0;

        foreach ($posts as $post) {
            $post_id = $post->ID; // Access the post ID from the post object

            // Add the destination term (keeping existing terms)
            wp_set_object_terms($post_id, $destination_term_id, $destination_taxonomy, true);

            // Clear caches
            clean_post_cache($post_id);

            $posts_affected++;
        }

        return $posts_affected;
    }

    /**
     * Perform REMOVE operation - for all in source, remove that term
     */
    private function perform_remove_operation($posts, $destination_taxonomy, $destination_term_id)
    {
        $posts_affected = 0;

        foreach ($posts as $post) {
            $post_id = $post->ID; // Access the post ID from the post object

            // Remove the destination term
            wp_remove_object_terms($post_id, $destination_term_id, $destination_taxonomy);

            // Clear caches
            clean_post_cache($post_id);

            $posts_affected++;
        }

        return $posts_affected;
    }
}

// Initialize the plugin
new Taxonomy_Migrate();

// Hook into WordPress Cron
add_action('taxonomy_migrate_scheduled_migration', function ($args) {
    $taxonomy_migrate = new Taxonomy_Migrate();
    $taxonomy_migrate->perform_migration($args);
}, 10, 1);
