<?php

namespace AMK\SchemaCore\Admin;

use AMK\SchemaCore\Repository\TemplateRepository;
use AMK\SchemaCore\Schema\SchemaTemplateContract;

defined('ABSPATH') || exit;

class SchemaBuilderPage {

    /**
     * @var TemplateRepository
     */
    private $repository;

    public function __construct() {
        $this->repository = new TemplateRepository();
    }

    /**
     * Render schema templates list page.
     *
     * @return void
     */
    public function render() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'amk-schema-core'));
        }

        $normalized_count = $this->maybe_normalize_existing_rows();
        $templates        = $this->get_templates();

        ?>
        <div class="wrap amk-schema-wrap amk-schema-list-page">

            <div class="amk-schema-list-shell">

                <div class="amk-schema-list-hero">

                    <div class="amk-schema-list-hero-content">
                        <span class="amk-schema-list-badge">AMK Schema Core</span>

                        <h1><?php esc_html_e('Schema Template Management', 'amk-schema-core'); ?></h1>

                        <p>
                            <?php esc_html_e('Manage the plugin JSON-LD templates, change their execution priority, and review each schema status.', 'amk-schema-core'); ?>
                        </p>

                        <?php if ($normalized_count > 0) : ?>
                            <div class="notice notice-info inline amk-inline-notice">
                                <p>
                                    <?php echo esc_html($normalized_count); ?>
                                    <?php esc_html_e('Template synchronized with the new type/scope contract.', 'amk-schema-core'); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="amk-schema-list-hero-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=amk_schema_template_editor')); ?>" class="button button-primary amk-schema-list-primary">
                            <?php esc_html_e('Add New Template', 'amk-schema-core'); ?>
                        </a>

                        <button type="button" id="save-priority" class="button amk-schema-list-secondary">
                            <?php esc_html_e('Save Priorities', 'amk-schema-core'); ?>
                        </button>
                    </div>

                    <div class="amk-schema-list-hero-mark">
                        <span>{ }</span>
                    </div>

                </div>

                <div class="amk-schema-list-card">

                    <div class="amk-schema-list-card-header">
                        <div>
                            <h2><?php esc_html_e('Registered Templates', 'amk-schema-core'); ?></h2>
                            <p>
                                <?php esc_html_e('Only active templates output on the frontend. Draft and inactive templates should not be printed on the site.', 'amk-schema-core'); ?>
                            </p>
                        </div>

                        <div class="amk-schema-list-count">
                            <span><?php echo esc_html(count($templates)); ?></span>
                            <strong><?php esc_html_e('Template', 'amk-schema-core'); ?></strong>
                        </div>
                    </div>

                    <div class="amk-schema-list-summary">
                        <?php $this->render_summary_cards($templates); ?>
                    </div>

                    <div class="amk-schema-table-wrap">

                        <table class="widefat fixed striped amk-schema-table">

                            <thead>
                                <tr>
                                    <th class="amk-col-id"><?php esc_html_e('ID', 'amk-schema-core'); ?></th>
                                    <th><?php esc_html_e('Template name', 'amk-schema-core'); ?></th>
                                    <th><?php esc_html_e('Internal type', 'amk-schema-core'); ?></th>
                                    <th>Schema.org</th>
                                    <th><?php esc_html_e('Execution scope', 'amk-schema-core'); ?></th>
                                    <th><?php esc_html_e('Status', 'amk-schema-core'); ?></th>
                                    <th class="amk-col-priority"><?php esc_html_e('Priority', 'amk-schema-core'); ?></th>
                                    <th class="amk-col-override"><?php esc_html_e('Override', 'amk-schema-core'); ?></th>
                                    <th><?php esc_html_e('Last updated', 'amk-schema-core'); ?></th>
                                    <th class="amk-col-actions"><?php esc_html_e('Actions', 'amk-schema-core'); ?></th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (empty($templates)) : ?>

                                    <tr>
                                        <td colspan="10">
                                            <div class="amk-schema-empty-state">
                                                <strong><?php esc_html_e('No schema templates have been registered yet.', 'amk-schema-core'); ?></strong>
                                                <p>
                                                    <?php esc_html_e('Create a new template to get started. Without an active template, the plugin prints nothing on the frontend.', 'amk-schema-core'); ?>
                                                </p>

                                                <a href="<?php echo esc_url(admin_url('admin.php?page=amk_schema_template_editor')); ?>" class="button button-primary amk-schema-list-primary">
                                                    <?php esc_html_e('Add first template', 'amk-schema-core'); ?>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>

                                <?php else : ?>

                                    <?php foreach ($templates as $template) : ?>

                                        <tr
                                            data-template-id="<?php echo esc_attr($template['id']); ?>"
                                            data-priority="<?php echo esc_attr((int) $template['priority']); ?>"
                                            data-override="<?php echo !empty($template['override']) ? '1' : '0'; ?>"
                                            data-type="<?php echo esc_attr($template['type']); ?>"
                                            data-scope="<?php echo esc_attr($template['scope']); ?>"
                                        >

                                            <td class="amk-col-id">
                                                <span class="amk-schema-id">
                                                    <?php echo esc_html($template['id']); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <div class="amk-schema-title">
                                                    <strong>
                                                        <?php echo esc_html($template['name']); ?>
                                                    </strong>

                                                    <small>
                                                        <?php echo esc_html($this->get_template_subtitle($template)); ?>
                                                    </small>
                                                </div>
                                            </td>

                                            <td>
                                                <span class="amk-schema-type-badge">
                                                    <?php echo esc_html($this->get_type_label($template['type'])); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <code><?php echo esc_html(SchemaTemplateContract::schema_org_type($template['type'])); ?></code>
                                            </td>

                                            <td>
                                                <span class="amk-schema-scope-badge">
                                                    <?php echo esc_html($this->get_scope_label($template['scope'])); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span class="<?php echo esc_attr($this->get_status_class($template['status'])); ?>">
                                                    <?php echo esc_html($this->get_status_label($template['status'])); ?>
                                                </span>
                                            </td>

                                            <td class="amk-col-priority">
                                                <?php echo esc_html((int) $template['priority']); ?>
                                            </td>

                                            <td class="amk-col-override">
                                                <?php echo !empty($template['override']) ? __('Active', 'amk-schema-core') : __('Inactive', 'amk-schema-core'); ?>
                                            </td>

                                            <td>
                                                <span class="amk-schema-date">
                                                    <?php echo esc_html($this->format_datetime($template['updated_at'] ?? '')); ?>
                                                </span>
                                            </td>

                                            <td class="amk-col-actions">
                                                <a class="button button-small amk-schema-edit-btn" href="<?php echo esc_url($this->get_edit_url($template['id'])); ?>">
                                                    <?php esc_html_e('Edit', 'amk-schema-core'); ?>
                                                </a>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>
                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

        <?php $this->render_inline_styles(); ?>

        <?php
    }

    /**
     * Load templates through repository, not raw DB.
     *
     * @return array
     */
    private function get_templates() {

        if (!$this->repository->table_exists()) {
            return [];
        }

        $templates = $this->repository->all([
            'limit'   => 500,
            'orderby' => 'priority',
            'order'   => 'DESC',
        ]);

        if (empty($templates) || !is_array($templates)) {
            return [];
        }

        return array_map([$this, 'normalize_template_for_list'], $templates);
    }

    /**
     * Normalize old database rows once when page opens.
     *
     * @return int
     */
    private function maybe_normalize_existing_rows() {

        if (!$this->repository->table_exists()) {
            return 0;
        }

        if (!method_exists($this->repository, 'normalize_existing_rows')) {
            return 0;
        }

        return (int) $this->repository->normalize_existing_rows(500);
    }

    /**
     * Normalize one template row for display.
     *
     * @param array $template
     * @return array
     */
    private function normalize_template_for_list($template) {

        $template = is_array($template) ? $template : [];

        $template['id']       = isset($template['id']) ? absint($template['id']) : 0;
        $template['name']     = isset($template['name']) ? sanitize_text_field($template['name']) : '';
        $template['type']     = SchemaTemplateContract::normalize_type($template['type'] ?? 'custom');
        $template['scope']    = SchemaTemplateContract::normalize_scope($template['scope'] ?? 'global');
        $template['status']   = $this->sanitize_status($template['status'] ?? 'inactive');
        $template['priority'] = isset($template['priority']) ? intval($template['priority']) : 0;
        $template['override'] = !empty($template['override']) ? 1 : 0;

        if (empty($template['updated_at'])) {
            $template['updated_at'] = '';
        }

        return $template;
    }

    /**
     * Render small statistics cards.
     *
     * @param array $templates
     * @return void
     */
    private function render_summary_cards($templates) {

        $total    = count($templates);
        $active   = 0;
        $inactive = 0;
        $draft    = 0;

        foreach ($templates as $template) {
            $status = $template['status'] ?? '';

            if ($status === 'active') {
                $active++;
            } elseif ($status === 'draft') {
                $draft++;
            } else {
                $inactive++;
            }
        }

        ?>
        <div class="amk-schema-summary-card">
            <span><?php echo esc_html($total); ?></span>
            <strong><?php esc_html_e('Total templates', 'amk-schema-core'); ?></strong>
        </div>

        <div class="amk-schema-summary-card">
            <span><?php echo esc_html($active); ?></span>
            <strong><?php esc_html_e('Active', 'amk-schema-core'); ?></strong>
        </div>

        <div class="amk-schema-summary-card">
            <span><?php echo esc_html($inactive); ?></span>
            <strong><?php esc_html_e('Inactive', 'amk-schema-core'); ?></strong>
        </div>

        <div class="amk-schema-summary-card">
            <span><?php echo esc_html($draft); ?></span>
            <strong><?php esc_html_e('Draft', 'amk-schema-core'); ?></strong>
        </div>
        <?php
    }

    /**
     * Get edit URL.
     *
     * @param int $template_id
     * @return string
     */
    private function get_edit_url($template_id) {

        return admin_url(
            'admin.php?page=amk_schema_template_editor&template_id=' . absint($template_id)
        );
    }

    /**
     * Type label from central contract.
     *
     * @param string $type
     * @return string
     */
    private function get_type_label($type) {

        return SchemaTemplateContract::type_label($type);
    }

    /**
     * Scope label from central contract.
     *
     * @param string $scope
     * @return string
     */
    private function get_scope_label($scope) {

        return SchemaTemplateContract::scope_label($scope);
    }

    /**
     * Status label.
     *
     * @param string $status
     * @return string
     */
    private function get_status_label($status) {

        $status = $this->sanitize_status($status);

        $labels = [
            'active'   => __('Active', 'amk-schema-core'),
            'inactive' => __('Inactive', 'amk-schema-core'),
            'draft'    => __('Draft', 'amk-schema-core'),
        ];

        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    /**
     * Status class.
     *
     * @param string $status
     * @return string
     */
    private function get_status_class($status) {

        $status = $this->sanitize_status($status);

        $classes = [
            'active'   => 'amk-status-badge amk-status-active',
            'inactive' => 'amk-status-badge amk-status-inactive',
            'draft'    => 'amk-status-badge amk-status-draft',
        ];

        return isset($classes[$status]) ? $classes[$status] : 'amk-status-badge amk-status-inactive';
    }

    /**
     * Template subtitle.
     *
     * @param array $template
     * @return string
     */
    private function get_template_subtitle($template) {

        $type  = SchemaTemplateContract::normalize_type($template['type'] ?? 'custom');
        $scope = SchemaTemplateContract::normalize_scope($template['scope'] ?? 'global');

        return $type . ' / ' . $scope;
    }

    /**
     * Format date.
     *
     * @param string $datetime
     * @return string
     */
    private function format_datetime($datetime) {

        if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
            return '—';
        }

        $timestamp = strtotime($datetime);

        if (!$timestamp) {
            return $datetime;
        }

        return date_i18n('Y/m/d - H:i', $timestamp);
    }

    /**
     * Sanitize status.
     *
     * @param string $status
     * @return string
     */
    private function sanitize_status($status) {

        $status = sanitize_key($status);

        $allowed = [
            'active',
            'inactive',
            'draft',
        ];

        return in_array($status, $allowed, true) ? $status : 'inactive';
    }

    /**
     * Extra inline CSS for the updated list UI.
     *
     * @return void
     */
    private function render_inline_styles() {
        ?>
        <style>
            .amk-schema-list-summary {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 12px;
                padding: 16px 20px 0;
            }

            .amk-schema-summary-card {
                background: #f8fafc;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 14px;
            }

            .amk-schema-summary-card span {
                display: block;
                font-size: 24px;
                font-weight: 700;
                color: #111827;
                margin-bottom: 4px;
            }

            .amk-schema-summary-card strong {
                color: #4b5563;
            }

            .amk-inline-notice {
                margin: 12px 0 0;
                border-radius: 8px;
            }

            .amk-schema-table code {
                direction: ltr;
                display: inline-block;
            }

            @media (max-width: 960px) {
                .amk-schema-list-summary {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 600px) {
                .amk-schema-list-summary {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }
}