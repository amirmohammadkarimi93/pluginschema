<?php

namespace AMK\SchemaCore\Schema;

use AMK\SchemaCore\Data\DataResolver;
use AMK\SchemaCore\Repository\TemplateRepository;
use AMK\SchemaCore\Schema\Builders\ArchiveItemListBuilder;
use AMK\SchemaCore\Schema\Builders\SiteNavigationBuilder;

defined('ABSPATH') || exit;

class SchemaManager {

    /**
     * @var DataResolver
     */
    private $resolver;

    /**
     * @var TemplateRepository
     */
    private $repository;

    public function __construct() {
        $this->resolver   = new DataResolver();
        $this->repository = new TemplateRepository();
    }

    /**
     * Build all schema nodes for the current page.
     *
     * Responsibilities of this class:
     * - detect current context
     * - load and compile active templates
     * - call dedicated builders for automatic schema nodes
     * - connect related nodes at graph level
     *
     * Responsibilities intentionally left outside this class:
     * - final JSON-LD rendering
     * - script tag output
     * - final graph normalization/merge
     *
     * @return array
     */
    public function get_schemas_for_current_page() {
        $context = $this->detect_context();
        $data    = $this->resolver->resolve($context);

        $schemas = $this->compile_templates_for_context($context, $data);
        $schemas = $this->append_automatic_schemas($schemas, $context, $data);
        $schemas = $this->connect_related_nodes($schemas, $context, $data);
        $schemas = $this->remove_non_renderable_schemas($schemas);

        /**
         * Filter final schema nodes before Output normalizes and renders them.
         *
         * @param array  $schemas
         * @param string $context
         * @param array  $data
         */
        $schemas = apply_filters(
            'amk_schema_core_schemas_for_current_page',
            $schemas,
            $context,
            is_array($data) ? $data : []
        );

        return is_array($schemas) ? array_values($schemas) : [];
    }

    /**
     * Detect current schema context.
     *
     * @return string
     */
    private function detect_context() {
        $selector = new SchemaSelector();

        return SchemaTemplateContract::normalize_scope($selector->detect_context());
    }

    /**
     * Compile all active templates for the current context.
     *
     * @param string $context
     * @param array  $data
     * @return array
     */
    private function compile_templates_for_context($context, $data) {
        $templates = $this->load_templates_for_context($context);

        if (empty($templates)) {
            return [];
        }

        $schemas = [];
        $data    = is_array($data) ? $data : [];

        foreach ($templates as $template) {
            if (!$this->is_active_template($template)) {
                continue;
            }

            $compiler = new SchemaCompiler($data);
            $compiled = $compiler->compile($template);

            if (!$this->is_renderable_schema($compiled)) {
                continue;
            }

            $schemas[] = $compiled;
        }

        return $schemas;
    }

    /**
     * Load active templates matching the current context.
     *
     * @param string $context
     * @return array
     */
    private function load_templates_for_context($context) {
        $context = SchemaTemplateContract::normalize_scope($context);

        $templates = $this->repository->active_by_context($context);

        if (empty($templates) || !is_array($templates)) {
            return [];
        }

        $matched = [];

        foreach ($templates as $template) {
            if (empty($template) || !is_array($template)) {
                continue;
            }

            $scope = SchemaTemplateContract::normalize_scope($template['scope'] ?? 'default');

            $template['scope'] = $scope;
            $template['type']  = SchemaTemplateContract::normalize_type($template['type'] ?? 'custom');

            $matched[] = $template;
        }

        return $this->sort_templates($matched, $context);
    }

    /**
     * Sort templates by scope relevance, priority, and ID.
     *
     * @param array  $templates
     * @param string $context
     * @return array
     */
    private function sort_templates($templates, $context) {
        if (empty($templates) || !is_array($templates)) {
            return [];
        }

        $context = SchemaTemplateContract::normalize_scope($context);

        usort($templates, function ($a, $b) use ($context) {
            $scope_a = SchemaTemplateContract::normalize_scope($a['scope'] ?? 'default');
            $scope_b = SchemaTemplateContract::normalize_scope($b['scope'] ?? 'default');

            $rank_a = $this->get_scope_rank($scope_a, $context);
            $rank_b = $this->get_scope_rank($scope_b, $context);

            if ($rank_a !== $rank_b) {
                return $rank_a <=> $rank_b;
            }

            $priority_a = isset($a['priority']) ? intval($a['priority']) : 0;
            $priority_b = isset($b['priority']) ? intval($b['priority']) : 0;

            if ($priority_a !== $priority_b) {
                return $priority_b <=> $priority_a;
            }

            $id_a = isset($a['id']) ? absint($a['id']) : 0;
            $id_b = isset($b['id']) ? absint($b['id']) : 0;

            return $id_b <=> $id_a;
        });

        return $this->apply_override_rules($templates);
    }

    /**
     * Lower rank means stronger match.
     *
     * @param string $scope
     * @param string $context
     * @return int
     */
    private function get_scope_rank($scope, $context) {
        $scope   = SchemaTemplateContract::normalize_scope($scope);
        $context = SchemaTemplateContract::normalize_scope($context);

        if ($scope === $context) {
            return 10;
        }

        if ($scope === 'global') {
            return 20;
        }

        if ($scope === 'page') {
            return 30;
        }

        if (in_array($scope, ['collection', 'blog_archive', 'archive'], true)) {
            return 40;
        }

        if ($scope === 'default') {
            return 90;
        }

        return 50;
    }

    /**
     * Apply template override rules by internal template type.
     *
     * @param array $templates
     * @return array
     */
    private function apply_override_rules($templates) {
        if (empty($templates) || !is_array($templates)) {
            return [];
        }

        $final         = [];
        $blocked_types = [];

        foreach ($templates as $template) {
            if (empty($template) || !is_array($template)) {
                continue;
            }

            $type = SchemaTemplateContract::normalize_type($template['type'] ?? 'custom');

            if (isset($blocked_types[$type])) {
                continue;
            }

            $template['type'] = $type;
            $final[]          = $template;

            if (!empty($template['override']) && $type !== 'custom') {
                $blocked_types[$type] = true;
            }
        }

        return $final;
    }

    /**
     * Append automatic schemas from dedicated builders.
     *
     * @param array  $schemas
     * @param string $context
     * @param array  $data
     * @return array
     */
    private function append_automatic_schemas($schemas, $context, $data) {
        $schemas = is_array($schemas) ? $schemas : [];
        $context = SchemaTemplateContract::normalize_scope($context);
        $data    = is_array($data) ? $data : [];

        $schemas = $this->append_site_navigation_schema($schemas, $context, $data);
        $schemas = $this->append_archive_item_list_schema($schemas, $context, $data);

        return $schemas;
    }

    /**
     * Append SiteNavigationElement nodes when no navigation schema exists yet.
     *
     * @param array  $schemas
     * @param string $context
     * @param array  $data
     * @return array
     */
    private function append_site_navigation_schema($schemas, $context, $data) {
        /**
         * Enable/disable automatic SiteNavigationElement builder.
         *
         * @param bool   $enabled
         * @param string $context
         * @param array  $data
         */
        $enabled = apply_filters(
            'amk_schema_core_auto_site_navigation_enabled',
            true,
            $context,
            $data
        );

        if (!$enabled) {
            return $schemas;
        }

        if ($this->has_schema_type($schemas, 'SiteNavigationElement')) {
            return $schemas;
        }

        if (!class_exists(SiteNavigationBuilder::class)) {
            return $schemas;
        }

        $builder = new SiteNavigationBuilder();
        $items   = $builder->build($context);

        if (empty($items) || !is_array($items)) {
            return $schemas;
        }

        foreach ($items as $item) {
            if ($this->is_renderable_schema($item)) {
                $schemas[] = $item;
            }
        }

        return $schemas;
    }

    /**
     * Append archive/category ItemList when no ItemList exists yet.
     *
     * @param array  $schemas
     * @param string $context
     * @param array  $data
     * @return array
     */
    private function append_archive_item_list_schema($schemas, $context, $data) {
        /**
         * Enable/disable automatic archive ItemList builder.
         *
         * @param bool   $enabled
         * @param string $context
         * @param array  $data
         */
        $enabled = apply_filters(
            'amk_schema_core_auto_archive_itemlist_enabled',
            true,
            $context,
            $data
        );

        if (!$enabled) {
            return $schemas;
        }

        if ($this->has_schema_type($schemas, 'ItemList')) {
            return $schemas;
        }

        if (!class_exists(ArchiveItemListBuilder::class)) {
            return $schemas;
        }

        $builder  = new ArchiveItemListBuilder();
        $itemlist = $builder->build($context);

        if (!$this->is_renderable_schema($itemlist)) {
            return $schemas;
        }

        $schemas[] = $itemlist;

        return $schemas;
    }

    /**
     * Connect page-level nodes to builder-generated nodes.
     *
     * @param array  $schemas
     * @param string $context
     * @param array  $data
     * @return array
     */
    private function connect_related_nodes($schemas, $context, $data) {
        $schemas = is_array($schemas) ? $schemas : [];

        $schemas = $this->connect_collection_page_to_itemlist($schemas, $context, $data);

        return $schemas;
    }

    /**
     * If a CollectionPage and ItemList both exist, connect them with mainEntity.
     *
     * @param array  $schemas
     * @param string $context
     * @param array  $data
     * @return array
     */
    private function connect_collection_page_to_itemlist($schemas, $context, $data) {
        if (empty($schemas) || !is_array($schemas)) {
            return [];
        }

        $itemlist_id = $this->find_first_schema_id_by_type($schemas, 'ItemList');

        if ($itemlist_id === '') {
            return $schemas;
        }

        foreach ($schemas as $index => $schema) {
            if (!is_array($schema)) {
                continue;
            }

            if (!$this->schema_has_any_type($schema, ['CollectionPage', 'SearchResultsPage'])) {
                continue;
            }

            if (empty($schema['mainEntity'])) {
                $schema['mainEntity'] = [
                    '@id' => $itemlist_id,
                ];

                $schemas[$index] = $schema;
            }
        }

        return $schemas;
    }

    /**
     * Remove schemas that cannot be rendered as root nodes.
     *
     * @param array $schemas
     * @return array
     */
    private function remove_non_renderable_schemas($schemas) {
        if (empty($schemas) || !is_array($schemas)) {
            return [];
        }

        $clean = [];

        foreach ($schemas as $schema) {
            if ($this->is_renderable_schema($schema)) {
                $clean[] = $schema;
            }
        }

        return $clean;
    }

    /**
     * Check whether a template row is active.
     *
     * @param mixed $template
     * @return bool
     */
    private function is_active_template($template) {
        if (empty($template) || !is_array($template)) {
            return false;
        }

        $status = isset($template['status']) ? sanitize_key($template['status']) : 'inactive';

        return $status === 'active';
    }

    /**
     * Check whether a schema node is safe to return to Output.
     *
     * @param mixed $schema
     * @return bool
     */
    private function is_renderable_schema($schema) {
        if (empty($schema) || !is_array($schema)) {
            return false;
        }

        if (isset($schema['_disabled']) && $schema['_disabled']) {
            return false;
        }

        if (isset($schema['@graph']) && is_array($schema['@graph'])) {
            return !empty($schema['@graph']);
        }

        if (empty($schema['@type'])) {
            return false;
        }

        if (isset($schema['itemListElement']) && is_array($schema['itemListElement']) && empty($schema['itemListElement'])) {
            return false;
        }

        return true;
    }

    /**
     * Check if any schema node has a given Schema.org type.
     *
     * @param array  $schemas
     * @param string $type
     * @return bool
     */
    private function has_schema_type($schemas, $type) {
        if (empty($schemas) || !is_array($schemas)) {
            return false;
        }

        foreach ($schemas as $schema) {
            if (!is_array($schema)) {
                continue;
            }

            if ($this->schema_has_type($schema, $type)) {
                return true;
            }

            if (!empty($schema['@graph']) && is_array($schema['@graph'])) {
                foreach ($schema['@graph'] as $graph_item) {
                    if (is_array($graph_item) && $this->schema_has_type($graph_item, $type)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Find first @id for a schema node by type.
     *
     * @param array  $schemas
     * @param string $type
     * @return string
     */
    private function find_first_schema_id_by_type($schemas, $type) {
        if (empty($schemas) || !is_array($schemas)) {
            return '';
        }

        foreach ($schemas as $schema) {
            if (!is_array($schema)) {
                continue;
            }

            if ($this->schema_has_type($schema, $type) && !empty($schema['@id']) && is_string($schema['@id'])) {
                return trim($schema['@id']);
            }

            if (!empty($schema['@graph']) && is_array($schema['@graph'])) {
                foreach ($schema['@graph'] as $graph_item) {
                    if (
                        is_array($graph_item)
                        && $this->schema_has_type($graph_item, $type)
                        && !empty($graph_item['@id'])
                        && is_string($graph_item['@id'])
                    ) {
                        return trim($graph_item['@id']);
                    }
                }
            }
        }

        return '';
    }

    /**
     * Check if schema has a specific @type.
     *
     * @param array  $schema
     * @param string $type
     * @return bool
     */
    private function schema_has_type($schema, $type) {
        if (!is_array($schema) || empty($schema['@type'])) {
            return false;
        }

        if (is_string($schema['@type'])) {
            return $schema['@type'] === $type;
        }

        if (is_array($schema['@type'])) {
            return in_array($type, $schema['@type'], true);
        }

        return false;
    }

    /**
     * Check if schema has any given type.
     *
     * @param array $schema
     * @param array $types
     * @return bool
     */
    private function schema_has_any_type($schema, $types) {
        if (empty($types) || !is_array($types)) {
            return false;
        }

        foreach ($types as $type) {
            if ($this->schema_has_type($schema, $type)) {
                return true;
            }
        }

        return false;
    }
}