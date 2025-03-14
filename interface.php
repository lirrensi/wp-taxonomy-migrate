<div class="wrap">
    <h1><?php _e('Taxonomy Migrate', 'taxonomy-migrate'); ?></h1>

    <div class="taxonomy-migrate-container">
        <div class="taxonomy-migrate-operation">
            <h2><?php _e('Operation Type', 'taxonomy-migrate'); ?></h2>

            <div class="operation-selector">

                <div class="operation-radio-group">
                    <div class="operation-radio-option">
                        <input type="radio" id="operation_move" name="operation_type" value="move" checked>
                        <label for="operation_move"><?php _e('Move', 'taxonomy-migrate'); ?></label>
                        <div class="operation-description"><?php _e('MOVE posts from source term to destination term', 'taxonomy-migrate'); ?></div>
                    </div>

                    <div class="operation-radio-option">
                        <input type="radio" id="operation_add" name="operation_type" value="add">
                        <label for="operation_add"><?php _e('Add', 'taxonomy-migrate'); ?></label>
                        <div class="operation-description"><?php _e('ADD destination term to posts (keep source term)', 'taxonomy-migrate'); ?></div>
                    </div>

                    <div class="operation-radio-option">
                        <input type="radio" id="operation_delete" name="operation_type" value="remove">
                        <label for="operation_delete"><?php _e('Delete', 'taxonomy-migrate'); ?></label>
                        <div class="operation-description"><?php _e('REMOVE destination term to posts (use same to remove)', 'taxonomy-migrate'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="taxonomy-migrate-form">
            <div class="taxonomy-migrate-box">
                <h2><?php _e('Source', 'taxonomy-migrate'); ?></h2>

                <div class="form-field">
                    <label for="source_taxonomy"><?php _e('Source Taxonomy', 'taxonomy-migrate'); ?></label>
                    <select id="source_taxonomy" name="source_taxonomy" class="taxonomy-select">
                        <option value=""><?php _e('Select a taxonomy', 'taxonomy-migrate'); ?></option>
                        <?php
$taxonomies = get_taxonomies(['public' => true], 'objects');
foreach ($taxonomies as $taxonomy) {
    echo '<option value="' . esc_attr($taxonomy->name) . '">' . esc_html($taxonomy->label) . ' [' . esc_html($taxonomy->name) . ']</option>';
}
?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="source_term"><?php _e('Source Term', 'taxonomy-migrate'); ?></label>
                    <select id="source_term" name="source_terms[]" class="term-select" multiple disabled>
                        <option value=""><?php _e('Select a taxonomy first', 'taxonomy-migrate'); ?></option>
                    </select>
                </div>
            </div>

            <div class="taxonomy-migrate-arrow">
                <span class="dashicons dashicons-arrow-right-alt"></span>
            </div>

            <div class="taxonomy-migrate-box destination-box">
                <h2><?php _e('Destination', 'taxonomy-migrate'); ?></h2>

                <div class="form-field">
                    <label for="destination_taxonomy"><?php _e('Destination Taxonomy', 'taxonomy-migrate'); ?></label>
                    <select id="destination_taxonomy" name="destination_taxonomy" class="taxonomy-select">
                        <option value=""><?php _e('Select a taxonomy', 'taxonomy-migrate'); ?></option>
                        <?php
foreach ($taxonomies as $taxonomy) {
    echo '<option value="' . esc_attr($taxonomy->name) . '">' . esc_html($taxonomy->label) . ' [' . esc_html($taxonomy->name) . ']</option>';
}
?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="destination_term"><?php _e('Destination Term', 'taxonomy-migrate'); ?></label>
                    <select id="destination_term" name="destination_term" class="term-select" disabled>
                        <option value=""><?php _e('Select a taxonomy first', 'taxonomy-migrate'); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div class="taxonomy-migrate-options">
            <h2><?php _e('Options', 'taxonomy-migrate'); ?></h2>

            <div class="form-field">
                <label>
                    <input type="checkbox" id="delete_source_term" name="delete_source_term">
                    <?php _e('Delete source term after migration', 'taxonomy-migrate'); ?>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <input type="checkbox" id="use_scheduler" name="use_scheduler">
                    <?php _e('Use Action Scheduler for large migrations', 'taxonomy-migrate'); ?>
                </label>
            </div>
        </div>

        <div class="taxonomy-migrate-actions">
            <button id="migrate-button" class="button button-primary" disabled>
                <?php _e('Run Migration', 'taxonomy-migrate'); ?>
            </button>
            <div id="migration-status" class="hidden"></div>
        </div>
    </div>

    <div class="taxonomy-migrate-results">
        <div id="result-container" class="hidden">
            <h2><?php _e('Migration Results', 'taxonomy-migrate'); ?></h2>
            <div id="result-content"></div>
        </div>
    </div>
</div>