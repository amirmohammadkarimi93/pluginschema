<?php

namespace AMK\SchemaCore\Admin;

use AMK\SchemaCore\Schema\SchemaValidator;
use AMK\SchemaCore\Schema\SchemaTemplateContract;
use AMK\SchemaCore\Data\ResolverVariableCatalog;


defined('ABSPATH') || exit;

class TemplateEditorPage {

    /**
     * @var array
     */
    private $notices = [];

    /**
     * @var array
     */
    private $errors = [];

    /**
     * Render template editor page.
     *
     * @return void
     */
    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'amk-schema-core'));
        }

        $this->maybe_handle_request();

        $template_id = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;

        if (!$template_id && isset($_GET['id'])) {
            $template_id = absint($_GET['id']);
        }

        $template = $template_id ? $this->get_template($template_id) : $this->default_template();

        if ($template_id && !$template) {
            $this->errors[] = __('The requested schema was not found.', 'amk-schema-core');
            $template       = $this->default_template();
        }

        $conditions = $template_id ? $this->get_conditions($template_id) : [];

        $this->render_styles();

        ?>
        <div class="wrap amk-schema-editor-wrap">
            <h1 class="wp-heading-inline">
                <?php echo $template_id ? esc_html__('Edit Schema', 'amk-schema-core') : esc_html__('Add Schema', 'amk-schema-core'); ?>
            </h1>

            <?php $this->render_notices(); ?>

            <div class="amk-schema-editor-grid">
                <div class="amk-schema-editor-main">
                    <form method="post" action="">
                        <?php wp_nonce_field('amk_schema_core_save_template', 'amk_schema_core_template_nonce'); ?>

                        <input type="hidden" name="template_id" value="<?php echo esc_attr($template_id); ?>">

                        <?php $this->render_basic_box($template); ?>
                        <?php $this->render_schema_json_box($template); ?>
                        <?php $this->render_bindings_box($template); ?>
                        <?php $this->render_conditions_box($conditions); ?>

                        <p class="submit">
                            <button type="submit" name="amk_schema_core_save_template" value="1" class="button button-primary button-large">
                                <?php esc_html_e('Save Schema', 'amk-schema-core'); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <div class="amk-schema-editor-sidebar">
                    <?php $this->render_help_box(); ?>
                    <?php $this->render_placeholder_box(); ?>
                    <?php $this->render_condition_help_box(); ?>
                </div>
            </div>
        </div>

        <?php

        $this->render_conditions_script();
        $this->render_editor_script();
    }

    /**
     * Handle save/delete requests.
     *
     * @return void
     */
    private function maybe_handle_request() {
        if (!isset($_POST['amk_schema_core_save_template'])) {
            return;
        }

        if (
            !isset($_POST['amk_schema_core_template_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['amk_schema_core_template_nonce'])), 'amk_schema_core_save_template')
        ) {
            $this->errors[] = __('The request is invalid. Refresh the page and try again.', 'amk-schema-core');
            return;
        }

        $this->save_template();
    }

    /**
     * Save template and conditions.
     *
     * @return void
     */
    private function save_template() {
        global $wpdb;

        $template_table   = $this->templates_table();
        $conditions_table = $this->conditions_table();

        if (!$this->table_exists($template_table)) {
            $this->errors[] = __('The schema templates table does not exist.', 'amk-schema-core');
            return;
        }

        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        $name        = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $type        = isset($_POST['type']) ? SchemaTemplateContract::normalize_type(wp_unslash($_POST['type'])) : '';
        $scope       = isset($_POST['scope']) ? SchemaTemplateContract::normalize_scope(wp_unslash($_POST['scope'])) : '';
        $status      = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'active';
        $priority    = isset($_POST['priority']) ? absint($_POST['priority']) : 10;
        $override    = isset($_POST['override']) ? 1 : 0;
        $schema_json = isset($_POST['schema_json']) ? trim(wp_unslash($_POST['schema_json'])) : '';
        $bindings    = isset($_POST['bindings']) ? trim(wp_unslash($_POST['bindings'])) : '';

        if ($name === '') {
            $this->errors[] = __('Schema name is required.', 'amk-schema-core');
        }

        if ($type === '') {
            $this->errors[] = __('Schema type is required.', 'amk-schema-core');
        }

        if ($scope === '') {
            $this->errors[] = __('Display scope is required.', 'amk-schema-core');
        }

        if ($schema_json === '') {
            $this->errors[] = __('Schema JSON cannot be empty.', 'amk-schema-core');
        }

        $schema_json = $this->normalize_json_text($schema_json, __('Schema JSON', 'amk-schema-core'));
        $bindings    = $this->normalize_json_text($bindings, __('Bindings', 'amk-schema-core'), true);

        if ($schema_json !== '') {
            $this->validate_schema_json($schema_json);
        }

        if (!empty($this->errors)) {
            return;
        }

        $columns = $this->get_table_columns($template_table);

        $data = [
            'name'        => $name,
            'type'        => $type,
            'scope'       => $scope,
            'status'      => $status,
            'priority'    => $priority,
            'override'    => $override,
            'schema_json' => $schema_json,
            'bindings'    => $bindings,
        ];

        if (in_array('updated_at', $columns, true)) {
            $data['updated_at'] = current_time('mysql');
        }

        if ($template_id > 0) {
            $updated = $wpdb->update(
                $template_table,
                $this->filter_data_by_columns($data, $columns),
                ['id' => $template_id],
                null,
                ['%d']
            );

            if ($updated === false) {
                $this->errors[] = __('Schema was not saved. A database error occurred.', 'amk-schema-core');
                return;
            }
        } else {
            if (in_array('created_at', $columns, true)) {
                $data['created_at'] = current_time('mysql');
            }

            $inserted = $wpdb->insert(
                $template_table,
                $this->filter_data_by_columns($data, $columns)
            );

            if (!$inserted) {
                $this->errors[] = __('Schema was not created. A database error occurred.', 'amk-schema-core');
                return;
            }

            $template_id = absint($wpdb->insert_id);
            $_GET['template_id'] = $template_id;
        }

        if ($this->table_exists($conditions_table)) {
            $this->save_conditions($template_id);
        }

        $this->notices[] = __('Schema saved successfully.', 'amk-schema-core');
    }

    /**
     * Validate schema JSON with SchemaValidator if available.
     *
     * @param string $schema_json
     * @return void
     */
    private function validate_schema_json($schema_json) {
        if (!class_exists(SchemaValidator::class)) {
            return;
        }

        $validator = new SchemaValidator();
        $report    = method_exists($validator, 'validate_with_report')
            ? $validator->validate_with_report($schema_json)
            : ['valid' => $validator->validate($schema_json), 'errors' => $validator->get_errors(), 'warnings' => $validator->get_warnings()];

        if (empty($report['valid'])) {
            if (!empty($report['errors'])) {
                foreach ($report['errors'] as $error) {
                    $message = is_array($error) && isset($error['message']) ? $error['message'] : $error;
                    $path    = is_array($error) && isset($error['path']) ? $error['path'] : '';

                    $this->errors[] = $path ? $path . ': ' . $message : $message;
                }
            } else {
                $this->errors[] = __('Schema JSON is not valid according to the validator.', 'amk-schema-core');
            }

            return;
        }

        if (!empty($report['warnings'])) {
            foreach ($report['warnings'] as $warning) {
                $message = is_array($warning) && isset($warning['message']) ? $warning['message'] : $warning;
                $path    = is_array($warning) && isset($warning['path']) ? $warning['path'] : '';

                $this->notices[] = $path ? __('Validator warning - ', 'amk-schema-core') . $path . ': ' . $message : __('Validator warning: ', 'amk-schema-core') . $message;
            }
        }
    }

    /**
     * Save template conditions.
     *
     * @param int $template_id
     * @return void
     */
    private function save_conditions($template_id) {
        global $wpdb;

        $table = $this->conditions_table();

        if (!$this->table_exists($table)) {
            return;
        }

        $wpdb->delete($table, ['template_id' => $template_id], ['%d']);

        $conditions = isset($_POST['conditions']) && is_array($_POST['conditions'])
            ? wp_unslash($_POST['conditions'])
            : [];

        if (empty($conditions)) {
            return;
        }

        $columns = $this->get_table_columns($table);
        $order   = 0;

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            $data_key = isset($condition['data_key']) ? sanitize_text_field($condition['data_key']) : '';
            $operator = isset($condition['operator']) ? sanitize_text_field($condition['operator']) : '';
            $expected = isset($condition['expected']) ? sanitize_text_field($condition['expected']) : '';
            $action   = isset($condition['action']) ? sanitize_text_field($condition['action']) : 'remove';
            $path     = isset($condition['path']) ? sanitize_text_field($condition['path']) : '';

            if ($data_key === '' || $operator === '') {
                continue;
            }

            if ($action === 'remove' && $path === '') {
                continue;
            }

            $row = [
                'template_id' => $template_id,
                'data_key'    => $data_key,
                'field'       => $data_key,
                'key'         => $data_key,
                'operator'    => $operator,
                'expected'    => $expected,
                'value'       => $expected,
                'action'      => $action,
                'path'        => $path,
                'target_path' => $path,
                'sort_order'  => $order,
                'priority'    => $order,
                'created_at'  => current_time('mysql'),
                'updated_at'  => current_time('mysql'),
            ];

            $insert = $this->filter_condition_row_by_columns($row, $columns);

            if (!empty($insert)) {
                $wpdb->insert($table, $insert);
                $order++;
            }
        }
    }

    /**
     * Render basic settings box.
     *
     * @param array $template
     * @return void
     */
    private function render_basic_box($template) {
        ?>
        <div class="amk-card">
            <div class="amk-card-header">
                <h2><?php esc_html_e('Main Schema Information', 'amk-schema-core'); ?></h2>
                <p><?php esc_html_e('Set the type, display scope, and execution priority for this template.', 'amk-schema-core'); ?></p>
            </div>

            <div class="amk-fields-grid">
                <div class="amk-field">
                    <label for="amk-name"><?php esc_html_e('Schema name', 'amk-schema-core'); ?></label>
                    <input type="text" id="amk-name" name="name" value="<?php echo esc_attr($template['name'] ?? ''); ?>" class="regular-text" required>
                </div>

                <div class="amk-field">
                    <label for="amk-type"><?php esc_html_e('Schema type', 'amk-schema-core'); ?></label>
                    <select id="amk-type" name="type" required>
                        <?php foreach ($this->schema_type_options() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($template['type'] ?? '', $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="amk-field">
                    <label for="amk-scope"><?php esc_html_e('Display scope', 'amk-schema-core'); ?></label>
                    <select id="amk-scope" name="scope" required>
                        <?php foreach ($this->scope_options() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($template['scope'] ?? '', $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="amk-field">
                    <label for="amk-status"><?php esc_html_e('Status', 'amk-schema-core'); ?></label>
                    <select id="amk-status" name="status">
                        <option value="active" <?php selected($template['status'] ?? 'active', 'active'); ?>><?php esc_html_e('Active', 'amk-schema-core'); ?></option>
                        <option value="inactive" <?php selected($template['status'] ?? '', 'inactive'); ?>><?php esc_html_e('Inactive', 'amk-schema-core'); ?></option>
                    </select>
                </div>

                <div class="amk-field">
                    <label for="amk-priority"><?php esc_html_e('Priority', 'amk-schema-core'); ?></label>
                    <input type="number" id="amk-priority" name="priority" value="<?php echo esc_attr($template['priority'] ?? 10); ?>" min="0" step="1">
                    <p class="description"><?php esc_html_e('A higher number means higher priority when selecting the schema.', 'amk-schema-core'); ?></p>
                </div>

                <div class="amk-field amk-checkbox-field">
                    <label>
                        <input type="checkbox" name="override" value="1" <?php checked(!empty($template['override'])); ?>>
                        <?php esc_html_e('Enable override', 'amk-schema-core'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('If multiple similar templates match, this option can override weaker templates.', 'amk-schema-core'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render schema JSON editor.
     *
     * @param array $template
     * @return void
     */
    private function render_schema_json_box($template) {
        $schema_json = $template['schema_json'] ?? '';
        ?>
        <div class="amk-card">
            <div class="amk-card-header">
                <h2><?php esc_html_e('Schema JSON', 'amk-schema-core'); ?></h2>
                <p><?php esc_html_e('Enter the main Schema JSON-LD structure here. Placeholders using the {{data_key}} format are supported.', 'amk-schema-core'); ?></p>
            </div>

            <textarea name="schema_json" id="amk-schema-json" class="amk-code-textarea" rows="22" spellcheck="false"><?php echo esc_textarea($schema_json); ?></textarea>

            <div class="amk-editor-actions">
                <button type="button" class="button" data-amk-format-json="#amk-schema-json">
                    <?php esc_html_e('Format JSON', 'amk-schema-core'); ?>
                </button>

                <button type="button" class="button" data-amk-insert-template>
                    <?php esc_html_e('Insert sample by type', 'amk-schema-core'); ?>
                </button>

                <button type="button" id="preview-schema" class="button button-secondary amk-preview-schema" data-amk-action="preview">
                    <?php esc_html_e('Preview dynamic output', 'amk-schema-core'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    
    /**
     * Render bindings editor.
     *
     * @param array $template
     * @return void
     */
    private function render_bindings_box($template) {
        $bindings = $template['bindings'] ?? '';

        if (is_array($bindings) || is_object($bindings)) {
            $bindings = wp_json_encode($bindings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (!is_string($bindings) || trim($bindings) === '') {
            $bindings = "{}";
        }

        ?>
        <div class="amk-card">
            <div class="amk-card-header">
                <h2><?php esc_html_e('Bindings / custom variables', 'amk-schema-core'); ?></h2>
                <p>
                    <?php esc_html_e('Use this section to create custom variables. For example, you can create a variable named', 'amk-schema-core'); ?>
                    <code>{{custom_gtin}}</code>
                    <?php esc_html_e('and read its value from product meta such as', 'amk-schema-core'); ?>
                    <code>_gtin</code>.
                </p>
            </div>

            <div id="amk-binding-builder" class="amk-binding-builder">
                <div class="amk-binding-toolbar">
                    <button type="button" id="amk-add-binding" class="button button-secondary">
                        <?php esc_html_e('Add Binding', 'amk-schema-core'); ?>
                    </button>

                    <button type="button" id="amk-format-bindings-json" class="button">
                        <?php esc_html_e('Format JSON', 'amk-schema-core'); ?>
                    </button>

                    <button type="button" id="amk-sync-bindings-from-json" class="button">
                        <?php esc_html_e('Reload form from JSON', 'amk-schema-core'); ?>
                    </button>
                </div>

                <div id="amk-binding-rows" class="amk-binding-rows"></div>

                <details class="amk-binding-json-details">
                    <summary><?php esc_html_e('View / edit raw bindings JSON', 'amk-schema-core'); ?></summary>

                    <textarea
                        name="bindings"
                        id="amk-bindings-json"
                        class="amk-code-textarea"
                        rows="10"
                        spellcheck="false"
                    ><?php echo esc_textarea($bindings); ?></textarea>
                </details>
            </div>

            <div class="amk-binding-examples">
                <strong><?php esc_html_e('Example usage:', 'amk-schema-core'); ?></strong>

                <pre>{
    "custom_gtin": {
        "source": "product_meta",
        "key": "_gtin",
        "default": "",
        "transform": "sanitize_text"
    }
    }</pre>

                <p>
                    <?php esc_html_e('Then use it inside the main JSON:', 'amk-schema-core'); ?>
                    <code>"gtin": "{{custom_gtin}}"</code>
                </p>
            </div>
        </div>

        <style>
            .amk-binding-builder {
                padding: 20px;
            }

            .amk-binding-toolbar {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
                margin-bottom: 14px;
            }

            .amk-binding-rows {
                display: grid;
                gap: 12px;
            }

            .amk-binding-row {
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                background: #ffffff;
                padding: 14px;
            }

            .amk-binding-grid {
                display: grid;
                grid-template-columns: 1.1fr 1.1fr 1.4fr 1fr 1fr auto;
                gap: 12px;
                align-items: end;
            }

            .amk-binding-grid label {
                display: block;
            }

            .amk-binding-grid strong {
                display: block;
                margin-bottom: 6px;
                color: #111827;
            }

            .amk-binding-grid input,
            .amk-binding-grid select {
                width: 100%;
                border-radius: 9px;
            }

            .amk-binding-help {
                margin: 10px 0 0;
                color: #6b7280;
            }

            .amk-binding-json-details {
                margin-top: 16px;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                background: #f9fafb;
            }

            .amk-binding-json-details summary {
                cursor: pointer;
                padding: 12px 14px;
                font-weight: 700;
            }

            .amk-binding-json-details .amk-code-textarea {
                margin-top: 0;
            }

            .amk-binding-examples {
                margin: 0 20px 20px;
                padding: 14px;
                background: #f8fafc;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                line-height: 1.8;
            }

            .amk-binding-examples pre {
                direction: ltr;
                text-align: left;
                background: #0f172a;
                color: #e5e7eb;
                padding: 12px;
                border-radius: 10px;
                overflow: auto;
            }

            .amk-binding-examples code {
                direction: ltr;
                display: inline-block;
            }

            @media (max-width: 1200px) {
                .amk-binding-grid {
                    grid-template-columns: 1fr 1fr;
                }
            }

            @media (max-width: 782px) {
                .amk-binding-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }

    /**
     * Render conditions box.
     *
     * @param array $conditions
     * @return void
     */
    private function render_conditions_box($conditions) {
        ?>
        <div class="amk-card">
            <div class="amk-card-header">
                <h2><?php esc_html_e('Conditions', 'amk-schema-core'); ?></h2>
                <p><?php esc_html_e('Conditions are used to remove unnecessary parts from the final output, such as removing rating when a product has no rating.', 'amk-schema-core'); ?></p>
            </div>

            <div id="amk-conditions-list">
                <?php
                if (empty($conditions)) {
                    $this->render_condition_row(0, []);
                } else {
                    foreach (array_values($conditions) as $index => $condition) {
                        $this->render_condition_row($index, $condition);
                    }
                }
                ?>
            </div>

            <p>
                <button type="button" class="button button-secondary" id="amk-add-condition">
                    <?php esc_html_e('Add condition', 'amk-schema-core'); ?>
                </button>
            </p>

            <script type="text/html" id="amk-condition-template">
                <?php $this->render_condition_row('__INDEX__', [], true); ?>
            </script>
        </div>
        <?php
    }

    /**
     * Render one condition row.
     *
     * @param int|string $index
     * @param array      $condition
     * @param bool       $template
     * @return void
     */
    private function render_condition_row($index, $condition = [], $template = false) {
        $data_key = $condition['data_key'] ?? $condition['field'] ?? $condition['key'] ?? '';
        $operator = $condition['operator'] ?? '';
        $expected = $condition['expected'] ?? $condition['value'] ?? '';
        $action   = $condition['action'] ?? 'remove';
        $path     = $condition['path'] ?? $condition['target_path'] ?? '';

        ?>
        <div class="amk-condition-row <?php echo $template ? 'amk-condition-row-template' : ''; ?>">
            <div class="amk-condition-grid">
                <div class="amk-field">
                    <label><?php esc_html_e('Data key', 'amk-schema-core'); ?></label>
                    <input type="text" name="conditions[<?php echo esc_attr($index); ?>][data_key]" value="<?php echo esc_attr($data_key); ?>" placeholder="<?php echo esc_attr__('For example rating_value', 'amk-schema-core'); ?>">
                </div>

                <div class="amk-field">
                    <label><?php esc_html_e('Operator', 'amk-schema-core'); ?></label>
                    <select name="conditions[<?php echo esc_attr($index); ?>][operator]">
                        <?php foreach ($this->condition_operator_options() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($operator, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="amk-field">
                    <label><?php esc_html_e('Expected value', 'amk-schema-core'); ?></label>
                    <input type="text" name="conditions[<?php echo esc_attr($index); ?>][expected]" value="<?php echo esc_attr($expected); ?>" placeholder="<?php echo esc_attr__('Optional', 'amk-schema-core'); ?>">
                </div>

                <div class="amk-field">
                    <label><?php esc_html_e('Action', 'amk-schema-core'); ?></label>
                    <select name="conditions[<?php echo esc_attr($index); ?>][action]">
                        <option value="remove" <?php selected($action, 'remove'); ?>><?php esc_html_e('Remove path', 'amk-schema-core'); ?></option>
                    </select>
                </div>

                <div class="amk-field amk-condition-path">
                    <label><?php esc_html_e('Path to remove', 'amk-schema-core'); ?></label>
                    <input type="text" name="conditions[<?php echo esc_attr($index); ?>][path]" value="<?php echo esc_attr($path); ?>" placeholder="<?php echo esc_attr__('For example aggregateRating', 'amk-schema-core'); ?>">
                </div>

                <div class="amk-condition-remove">
                    <button type="button" class="button-link-delete amk-remove-condition"><?php esc_html_e('Remove', 'amk-schema-core'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render sidebar help.
     *
     * @return void
     */
    private function render_help_box() {
        ?>
        <div class="amk-side-card">
            <h3><?php esc_html_e('Quick guide', 'amk-schema-core'); ?></h3>
            <p><?php esc_html_e('This page is for building a raw schema template. Real data is later inserted into placeholders by DataResolver.', 'amk-schema-core'); ?></p>

            <ul>
                <li><strong><?php esc_html_e('Schema JSON:', 'amk-schema-core'); ?></strong> <?php esc_html_e('The final Schema.org structure.', 'amk-schema-core'); ?></li>
                <li><strong><?php esc_html_e('Binding:', 'amk-schema-core'); ?></strong> <?php esc_html_e('Connects a placeholder to data.', 'amk-schema-core'); ?></li>
                <li><strong><?php esc_html_e('Condition:', 'amk-schema-core'); ?></strong> <?php esc_html_e('Removes empty or invalid sections.', 'amk-schema-core'); ?></li>
            </ul>

            <p class="amk-warning-text">
                <?php esc_html_e('The validator should not treat placeholders as errors. Before compile, {{...}} placeholders are normal.', 'amk-schema-core'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render placeholder list.
     *
     * @return void
     */
    /**

    /**
     * Render dynamic resolver variables.
     *
     * @return void
     */
    private function render_placeholder_box() {
        $groups = class_exists(ResolverVariableCatalog::class)
            ? ResolverVariableCatalog::for_admin()
            : [];

        ?>
        <div class="amk-side-card amk-variable-catalog-card">
            <h3><?php esc_html_e('Dynamic variables', 'amk-schema-core'); ?></h3>

            <p>
                <?php esc_html_e('Click a variable to insert it into the active textarea and make the JSON dynamic.', 'amk-schema-core'); ?>
            </p>

            <input
                type="search"
                id="amk-variable-search"
                class="regular-text"
                placeholder="<?php echo esc_attr__('Search: price, home_url, organization...', 'amk-schema-core'); ?>"
            >

            <?php if (empty($groups)) : ?>

                <p class="amk-warning-text">
                    <?php esc_html_e('Variable catalog was not found. Check the ResolverVariableCatalog.php file.', 'amk-schema-core'); ?>
                </p>

            <?php else : ?>

                <div class="amk-variable-scroll-area">
                    <?php foreach ($groups as $group_key => $group) : ?>
                        <details class="amk-variable-group" <?php echo $group_key === 'global' ? 'open' : ''; ?> data-group="<?php echo esc_attr($group_key); ?>">
                            <summary>
                                <?php echo esc_html($group['label'] ?? $group_key); ?>
                            </summary>

                            <div class="amk-variable-list">
                                <?php foreach (($group['variables'] ?? []) as $variable) : ?>
                                    <?php
                                    $key         = $variable['key'] ?? '';
                                    $placeholder = $variable['placeholder'] ?? '{{' . $key . '}}';
                                    $label       = $variable['label'] ?? $key;
                                    $type        = $variable['type'] ?? 'mixed';
                                    $description = $variable['description'] ?? '';
                                    $contexts    = !empty($variable['contexts']) && is_array($variable['contexts'])
                                        ? implode(', ', $variable['contexts'])
                                        : 'global';

                                    $search_text = strtolower(
                                        $key . ' ' .
                                        $label . ' ' .
                                        $type . ' ' .
                                        $description . ' ' .
                                        $contexts . ' ' .
                                        $placeholder
                                    );
                                    ?>

                                    <button
                                        type="button"
                                        class="amk-variable-item"
                                        data-placeholder="<?php echo esc_attr($placeholder); ?>"
                                        data-variable-key="<?php echo esc_attr($key); ?>"
                                        data-search-text="<?php echo esc_attr($search_text); ?>"
                                        title="<?php echo esc_attr($description); ?>"
                                    >
                                        <span class="amk-variable-title">
                                            <?php echo esc_html($label); ?>
                                        </span>

                                        <code class="amk-variable-placeholder">
                                            <?php echo esc_html($placeholder); ?>
                                        </code>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

            <div class="amk-variable-note">
                <?php esc_html_e('Object/array variables such as', 'amk-schema-core'); ?>
                <code>{{organization_address}}</code>
                <?php esc_html_e('and', 'amk-schema-core'); ?>
                <code>{{breadcrumb_items}}</code>
                <?php esc_html_e('must be used as the complete value of a property.', 'amk-schema-core'); ?>
            </div>
        </div>

        <style>
            .amk-variable-catalog-card {
                overflow: hidden;
            }

            .amk-variable-catalog-card h3 {
                margin-bottom: 8px;
            }

            #amk-variable-search {
                width: 100%;
                margin: 8px 0 12px;
                border-radius: 9px;
            }

            .amk-variable-scroll-area {
                max-height: 560px;
                overflow-y: auto;
                padding-left: 4px;
            }

            .amk-variable-group {
                margin-bottom: 10px;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                background: #f9fafb;
                overflow: hidden;
            }

            .amk-variable-group summary {
                cursor: pointer;
                padding: 10px 12px;
                font-weight: 700;
                color: #111827;
                background: #f3f4f6;
            }

            .amk-variable-list {
                display: grid;
                gap: 8px;
                padding: 10px;
            }

            .amk-variable-item {
                width: 100%;
                display: block;
                text-align: right;
                border: 1px solid #d1d5db;
                background: #ffffff;
                border-radius: 12px;
                padding: 10px 12px;
                cursor: pointer;
                transition: all 0.15s ease;
            }

            .amk-variable-item:hover {
                background: #eef2ff;
                border-color: #818cf8;
                transform: translateY(-1px);
            }

            .amk-variable-title {
                display: block;
                font-size: 14px;
                font-weight: 700;
                color: #111827;
                margin-bottom: 5px;
                line-height: 1.6;
            }

            .amk-variable-placeholder {
                display: inline-block;
                direction: ltr;
                text-align: left;
                font-size: 12px;
                color: #1d4ed8;
                background: #eff6ff;
                border: 1px solid #bfdbfe;
                border-radius: 999px;
                padding: 3px 8px;
            }

            .amk-variable-note {
                margin-top: 12px;
                padding: 10px;
                background: #fff7ed;
                border: 1px solid #fed7aa;
                border-radius: 10px;
                color: #7c2d12;
                line-height: 1.8;
                font-size: 12px;
            }

            .amk-variable-note code {
                direction: ltr;
                display: inline-block;
            }
        </style>
        <?php
    }

    /**
     * Render condition help.
     *
     * @return void
     */
    private function render_condition_help_box() {
        ?>
        <div class="amk-side-card">
            <h3><?php esc_html_e('Condition examples', 'amk-schema-core'); ?></h3>

            <p><?php esc_html_e('If the product has no rating, remove this section:', 'amk-schema-core'); ?></p>

            <pre>{
  "data_key": "rating_value",
  "operator": "empty",
  "action": "remove",
  "path": "aggregateRating"
}</pre>

            <p><?php esc_html_e('If the price is empty, remove offers:', 'amk-schema-core'); ?></p>

            <pre>{
  "data_key": "price",
  "operator": "empty",
  "action": "remove",
  "path": "offers"
}</pre>
        </div>
        <?php
    }

    /**
     * Get template by ID.
     *
     * @param int $id
     * @return array|null
     */
    private function get_template($id) {
        global $wpdb;

        $table = $this->templates_table();

        if (!$this->table_exists($table)) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );

        $row['type']  = SchemaTemplateContract::normalize_type($row['type'] ?? 'custom');
        $row['scope'] = SchemaTemplateContract::normalize_scope($row['scope'] ?? 'global');

        return $row ?: null;
    }

    /**
     * Get conditions for template.
     *
     * @param int $template_id
     * @return array
     */
    private function get_conditions($template_id) {
        global $wpdb;

        $table = $this->conditions_table();

        if (!$this->table_exists($table)) {
            return [];
        }

        $columns = $this->get_table_columns($table);

        $order_column = 'id';

        if (in_array('sort_order', $columns, true)) {
            $order_column = 'sort_order';
        } elseif (in_array('priority', $columns, true)) {
            $order_column = 'priority';
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE template_id = %d ORDER BY {$order_column} ASC", $template_id),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Default empty template.
     *
     * @return array
     */
    private function default_template() {
        return [
            'name'        => '',
            'type'        => 'organization',
            'scope'       => 'global',
            'status'      => 'active',
            'priority'    => 10,
            'override'    => 0,
            'schema_json' => $this->default_schema_json('organization'),
            'bindings'    => "{}",
        ];
    }

    /**
     * Schema type options.
     *
     * @return array
     */
    private function schema_type_options() {
        return SchemaTemplateContract::type_options();
    }

    /**
     * Scope options.
     *
     * @return array
     */
     private function scope_options() {
        return SchemaTemplateContract::scope_options();
    }

    /**
     * Condition operator options.
     *
     * @return array
     */
    private function condition_operator_options() {
        return [
            'empty'        => __('Is empty', 'amk-schema-core'),
            'not_empty'    => __('Is not empty', 'amk-schema-core'),
            'equals'       => __('Equals', 'amk-schema-core'),
            'not_equals'   => __('Does not equal', 'amk-schema-core'),
            'contains'     => __('Contains', 'amk-schema-core'),
            'not_contains' => __('Does not contain', 'amk-schema-core'),
            'greater_than' => __('Greater than', 'amk-schema-core'),
            'less_than'    => __('Less than', 'amk-schema-core'),
        ];
    }

    /**
     * Get default schema JSON by type.
     *
     * These samples must stay aligned with config/default-schemas.php.
     * They are used by the "insert sample by type" button in the admin editor.
     *
     * @param string $type
     * @return string
     */
    private function default_schema_json($type) {
        $type = SchemaTemplateContract::normalize_type($type);

        $organization_schema_type = SchemaTemplateContract::schema_org_type($type);

        if ($type === 'organization') {
            $organization_schema_type = '{{organization_types}}';
        }

        $organization_schema = [
            '@context'                => 'https://schema.org',
            '@type'                   => $organization_schema_type,
            '@id'                     => '{{organization_id}}',
            'name'                    => '{{organization_name}}',
            'legalName'               => '{{organization_legal_name}}',
            'alternateName'           => '{{organization_alternate_name}}',
            'url'                     => '{{organization_url}}',
            'description'             => '{{organization_description}}',
            'logo'                    => '{{organization_logo}}',
            'image'                   => '{{organization_image}}',
            'telephone'               => '{{organization_telephone}}',
            'email'                   => '{{organization_email}}',
            'address'                 => '{{organization_address}}',
            'contactPoint'            => '{{organization_contact_point}}',
            'sameAs'                  => '{{organization_same_as}}',
            'priceRange'              => '{{organization_price_range}}',
            'currenciesAccepted'      => '{{organization_currencies_accepted}}',
            'acceptedPaymentMethod'   => '{{organization_payment_accepted}}',
            'foundingDate'            => '{{organization_founding_date}}',
            'taxID'                   => '{{organization_tax_id}}',
            'vatID'                   => '{{organization_vat_id}}',
            'geo'                     => '{{organization_geo}}',
            'hasMap'                  => '{{organization_has_map}}',
            'openingHoursSpecification' => '{{organization_opening_hours_specification}}',
            'hasMerchantReturnPolicy' => '{{merchant_return_policy}}',
            'hasShippingService'      => '{{shipping_service}}',
        ];

        $schemas = [
            'organization'  => $organization_schema,
            'onlinestore'   => $organization_schema,
            'store'         => $organization_schema,
            'localbusiness' => $organization_schema,

            'website' => [
                '@context'        => 'https://schema.org',
                '@type'           => 'WebSite',
                '@id'             => '{{website_id}}',
                'name'            => '{{site_name}}',
                'url'             => '{{site_url}}',
                'description'     => '{{site_description}}',
                'publisher'       => [
                    '@id' => '{{organization_id}}',
                ],
                'inLanguage'      => '{{language}}',
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => '{{website_search_target}}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],

            'webpage' => [
                '@context'    => 'https://schema.org',
                '@type'       => '{{webpage_type}}',
                '@id'         => '{{webpage_id}}',
                'name'        => '{{title}}',
                'description' => '{{description}}',
                'url'         => '{{url}}',
                'isPartOf'    => [
                    '@id' => '{{website_id}}',
                ],
                'publisher'   => [
                    '@id' => '{{organization_id}}',
                ],
                'about'       => '{{webpage_about}}',
                'mainEntity'  => '{{webpage_main_entity}}',
                'breadcrumb'  => [
                    '@id' => '{{breadcrumb_id}}',
                ],
                'inLanguage'  => '{{language}}',
            ],

            'article' => [
                '@context'         => 'https://schema.org',
                '@type'            => 'Article',
                '@id'              => '{{url}}#article',
                'headline'         => '{{title}}',
                'description'      => '{{description}}',
                'image'            => '{{image}}',
                'datePublished'    => '{{date_published}}',
                'dateModified'     => '{{date_modified}}',
                'author'           => [
                    '@type' => 'Person',
                    'name'  => '{{author_name}}',
                    'url'   => '{{author_url}}',
                ],
                'publisher'        => [
                    '@id' => '{{organization_id}}',
                ],
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id'   => '{{webpage_id}}',
                ],
                'inLanguage'       => '{{language}}',
            ],

            'product' => [
                '@context'        => 'https://schema.org',
                '@type'           => '{{product_schema_type}}',
                '@id'             => '{{product_entity_id}}',
                'name'            => '{{name}}',
                'description'     => '{{description}}',
                'image'           => '{{images}}',
                'sku'             => '{{sku}}',
                'gtin'            => '{{gtin}}',
                'mpn'             => '{{mpn}}',
                'brand'           => '{{brand_schema}}',
                'offers'          => '{{product_offer}}',
                'productGroupID'  => '{{product_group_id_value}}',
                'variesBy'        => '{{product_varies_by}}',
                'hasVariant'      => '{{product_variants}}',
                'aggregateRating' => [
                    '@type'       => 'AggregateRating',
                    'ratingValue' => '{{rating_value}}',
                    'reviewCount' => '{{review_count}}',
                ],
            ],

            'collection' => [
                '@context'    => 'https://schema.org',
                '@type'       => 'CollectionPage',
                '@id'         => '{{url}}#collection',
                'name'        => '{{title}}',
                'description' => '{{description}}',
                'url'         => '{{url}}',
                'isPartOf'    => [
                    '@id' => '{{website_id}}',
                ],
                'publisher'   => [
                    '@id' => '{{organization_id}}',
                ],
                'breadcrumb'  => [
                    '@id' => '{{breadcrumb_id}}',
                ],
                'inLanguage'  => '{{language}}',
            ],

            'breadcrumb' => [
                '@context'        => 'https://schema.org',
                '@type'           => 'BreadcrumbList',
                '@id'             => '{{breadcrumb_id}}',
                'itemListElement' => '{{breadcrumb_items}}',
            ],

            'custom' => [
                '@context' => 'https://schema.org',
                '@type'    => 'Thing',
                'name'     => '{{title}}',
                'url'      => '{{url}}',
            ],
        ];

        $schema = $schemas[$type] ?? $schemas['custom'];

        return wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Normalize JSON textarea.
     *
     * @param string $json
     * @param string $label
     * @param bool   $allow_empty
     * @return string
     */
    private function normalize_json_text($json, $label, $allow_empty = false) {
        $json = trim((string) $json);

        if ($json === '') {
            if ($allow_empty) {
                return '{}';
            }

            $this->errors[] = $label . __(' is empty.', 'amk-schema-core');
            return '';
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = $label . __(' is invalid: ', 'amk-schema-core') . json_last_error_msg();
            return '';
        }

        return wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Render notices.
     *
     * @return void
     */
    private function render_notices() {
        foreach ($this->errors as $error) {
            ?>
            <div class="notice notice-error">
                <p><?php echo esc_html($error); ?></p>
            </div>
            <?php
        }

        foreach ($this->notices as $notice) {
            ?>
            <div class="notice notice-success">
                <p><?php echo esc_html($notice); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Templates table name.
     *
     * @return string
     */
    private function templates_table() {
        global $wpdb;

        return $wpdb->prefix . 'amk_schema_templates';
    }

    /**
     * Conditions table name.
     *
     * @return string
     */
    private function conditions_table() {
        global $wpdb;

        return $wpdb->prefix . 'amk_schema_conditions';
    }

    /**
     * Check table exists.
     *
     * @param string $table
     * @return bool
     */
    private function table_exists($table) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /**
     * Get table columns.
     *
     * @param string $table
     * @return array
     */
    private function get_table_columns($table) {
        global $wpdb;

        $columns = $wpdb->get_col("DESC {$table}", 0);

        return is_array($columns) ? $columns : [];
    }

    /**
     * Filter data by available columns.
     *
     * @param array $data
     * @param array $columns
     * @return array
     */
    private function filter_data_by_columns($data, $columns) {
        return array_intersect_key($data, array_flip($columns));
    }

    /**
     * Filter condition row with column aliases.
     *
     * @param array $row
     * @param array $columns
     * @return array
     */
    private function filter_condition_row_by_columns($row, $columns) {
        $allowed = [];

        foreach ($columns as $column) {
            if (array_key_exists($column, $row)) {
                $allowed[$column] = $row[$column];
            }
        }

        return $allowed;
    }

    /**
     * Render styles.
     *
     * @return void
     */
    private function render_styles() {
        ?>
        <style>
            .amk-schema-editor-wrap {
                direction: rtl;
            }

            .amk-schema-editor-grid {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 340px;
                gap: 20px;
                margin-top: 20px;
            }

            .amk-card,
            .amk-side-card {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 14px;
                box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
                margin-bottom: 18px;
            }

            .amk-card {
                padding: 0;
            }

            .amk-side-card {
                padding: 18px;
            }

            .amk-card-header {
                padding: 18px 20px;
                border-bottom: 1px solid #eef0f3;
                background: linear-gradient(135deg, #f8fafc, #ffffff);
                border-radius: 14px 14px 0 0;
            }

            .amk-card-header h2,
            .amk-side-card h3 {
                margin: 0 0 8px;
                color: #111827;
            }

            .amk-card-header p,
            .amk-side-card p,
            .amk-side-card li {
                color: #4b5563;
                line-height: 1.8;
            }

            .amk-fields-grid,
            .amk-condition-grid {
                display: grid;
                gap: 16px;
                padding: 20px;
            }

            .amk-fields-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .amk-condition-grid {
                grid-template-columns: 1.3fr 1fr 1fr 0.8fr 1.4fr auto;
                align-items: end;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                background: #f9fafb;
                margin-bottom: 12px;
            }

            .amk-field label {
                display: block;
                margin-bottom: 7px;
                font-weight: 600;
                color: #1f2937;
            }

            .amk-field input[type="text"],
            .amk-field input[type="number"],
            .amk-field select,
            .amk-code-textarea {
                width: 100%;
                border-radius: 9px;
                border-color: #cfd6df;
            }

            .amk-code-textarea {
                display: block;
                width: calc(100% - 40px);
                margin: 20px;
                font-family: Consolas, Monaco, monospace;
                direction: ltr;
                text-align: left;
                line-height: 1.6;
                background: #0f172a;
                color: #e5e7eb;
                border: 1px solid #1f2937;
                padding: 16px;
                border-radius: 12px;
                box-sizing: border-box;
            }

            .amk-checkbox-field {
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .amk-editor-actions {
                padding: 0 20px 20px;
                display: flex;
                gap: 8px;
            }

            .amk-placeholder-group {
                margin-bottom: 16px;
            }

            .amk-placeholder-group strong {
                display: block;
                margin-bottom: 8px;
            }

            .amk-placeholder-chip {
                display: inline-block;
                margin: 0 0 6px 4px;
                padding: 5px 8px;
                border-radius: 999px;
                border: 1px solid #d1d5db;
                background: #f9fafb;
                color: #374151;
                cursor: pointer;
                font-size: 12px;
                direction: ltr;
            }

            .amk-placeholder-chip:hover {
                background: #eef2ff;
                border-color: #818cf8;
            }

            .amk-warning-text {
                background: #fff7ed;
                border: 1px solid #fed7aa;
                border-radius: 10px;
                padding: 10px;
            }

            .amk-side-card pre {
                direction: ltr;
                text-align: left;
                white-space: pre-wrap;
                background: #111827;
                color: #e5e7eb;
                padding: 12px;
                border-radius: 10px;
                overflow: auto;
            }

            .amk-condition-remove {
                padding-bottom: 7px;
            }

            @media (max-width: 1200px) {
                .amk-schema-editor-grid {
                    grid-template-columns: 1fr;
                }

                .amk-condition-grid {
                    grid-template-columns: 1fr 1fr;
                }
            }

            @media (max-width: 782px) {
                .amk-fields-grid,
                .amk-condition-grid {
                    grid-template-columns: 1fr;
                }

                .amk-code-textarea {
                    width: calc(100% - 24px);
                    margin: 12px;
                }
            }
        </style>
        <?php
    }

    /**
     * Render conditions JS.
     *
     * @return void
     */
    private function render_conditions_script() {
        ?>
        <script>
            jQuery(function ($) {
                var $list = $('#amk-conditions-list');
                var template = $('#amk-condition-template').html();

                $('#amk-add-condition').on('click', function () {
                    var index = $list.find('.amk-condition-row').length;
                    var html = template.replace(/__INDEX__/g, index);
                    $list.append(html);
                });

                $list.on('click', '.amk-remove-condition', function () {
                    var $rows = $list.find('.amk-condition-row');

                    if ($rows.length <= 1) {
                        var $row = $(this).closest('.amk-condition-row');
                        $row.find('input[type="text"]').val('');
                        $row.find('select').prop('selectedIndex', 0);
                        return;
                    }

                    $(this).closest('.amk-condition-row').remove();
                });
            });
        </script>
        <?php
    }

    /**
     * Render editor JS.
     *
     * @return void
     */
    /**
 * Render editor JS.
 *
 * @return void
 */
    private function render_editor_script() {
        $templates = [];

        foreach (array_keys($this->schema_type_options()) as $type) {
            $normalized_type = SchemaTemplateContract::normalize_type($type);
            $template_json   = $this->default_schema_json($normalized_type);
            $decoded         = json_decode($template_json, true);

            $templates[$normalized_type] = is_array($decoded) ? $decoded : [];
        }

        ?>
        <script>
            jQuery(function ($) {
                var schemaTemplates = <?php echo wp_json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                var $lastActiveTextarea = $('#amk-schema-json');

                function getActiveTextarea() {
                    if ($lastActiveTextarea && $lastActiveTextarea.length) {
                        return $lastActiveTextarea;
                    }

                    var $schema = $('#amk-schema-json');

                    if ($schema.length) {
                        return $schema;
                    }

                    return $('.amk-code-textarea').first();
                }

                function insertAtCursor(textarea, text) {
                    if (!textarea) {
                        return;
                    }

                    var start = textarea.selectionStart || 0;
                    var end = textarea.selectionEnd || 0;
                    var value = textarea.value || '';

                    textarea.value = value.substring(0, start) + text + value.substring(end);
                    textarea.focus();

                    var cursorPosition = start + text.length;
                    textarea.selectionStart = cursorPosition;
                    textarea.selectionEnd = cursorPosition;
                }

                function normalizeType(type) {
                    type = String(type || '').toLowerCase().trim();
                    type = type.replace(/-/g, '_');

                    var aliases = {
                        'organization': 'organization',
                        'org': 'organization',
                        'onlinestore': 'onlinestore',
                        'online_store': 'onlinestore',
                        'store': 'store',
                        'localbusiness': 'localbusiness',
                        'local_business': 'localbusiness',
                        'website': 'website',
                        'web_site': 'website',
                        'webpage': 'webpage',
                        'web_page': 'webpage',
                        'article': 'article',
                        'post': 'article',
                        'product': 'product',
                        'collection': 'collection',
                        'collection_page': 'collection',
                        'collectionpage': 'collection',
                        'breadcrumb': 'breadcrumb',
                        'breadcrumb_list': 'breadcrumb',
                        'breadcrumblist': 'breadcrumb',
                        'custom': 'custom'
                    };

                    return aliases[type] || 'custom';
                }

                function filterVariables() {
                    var query = String($('#amk-variable-search').val() || '').toLowerCase().trim();

                    $('.amk-variable-item').each(function () {
                        var haystack = String($(this).data('search-text') || '').toLowerCase();

                        if (!query || haystack.indexOf(query) !== -1) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });

                    $('.amk-variable-group').each(function () {
                        var $group = $(this);
                        var visibleItems = $group.find('.amk-variable-item:visible').length;

                        if (!query || visibleItems > 0) {
                            $group.show();

                            if (query) {
                                $group.prop('open', true);
                            }
                        } else {
                            $group.hide();
                        }
                    });
                }

                $('.amk-code-textarea, textarea[name="schema_json"], textarea[name="bindings"]').on('focus click', function () {
                    $lastActiveTextarea = $(this);
                });

                $('[data-amk-format-json]').on('click', function () {
                    var target = $(this).data('amk-format-json');
                    var $textarea = $(target);

                    try {
                        var parsed = JSON.parse($textarea.val());
                        $textarea.val(JSON.stringify(parsed, null, 2)).trigger('input');
                    } catch (e) {
                        alert(__('JSON is invalid: ', 'amk-schema-core') + e.message);
                    }
                });

                $('[data-amk-insert-template]').on('click', function () {
                    var type = normalizeType($('#amk-type').val());
                    var template = schemaTemplates[type] || schemaTemplates.custom;

                    if (!template) {
                        alert(__('No sample is defined for this schema type.', 'amk-schema-core'));
                        return;
                    }

                    var confirmed = confirm(__('Replace the current content with the sample JSON for the selected type?', 'amk-schema-core'));

                    if (!confirmed) {
                        return;
                    }

                    $('#amk-schema-json').val(JSON.stringify(template, null, 2)).trigger('input').focus();
                });

                $(document).on('click', '.amk-placeholder-chip, .amk-variable-item', function () {
                    var placeholder = $(this).data('placeholder');

                    if (!placeholder) {
                        return;
                    }

                    var $target = getActiveTextarea();

                    if (!$target.length) {
                        return;
                    }

                    insertAtCursor($target.get(0), placeholder);
                });

                $('#amk-variable-search').on('input', filterVariables);
            });
        </script>
        <?php
    }
}
