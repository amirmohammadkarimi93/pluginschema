<?php

namespace AMK\SchemaCore\Frontend;

use AMK\SchemaCore\Schema\SchemaManager;

defined('ABSPATH') || exit;

class Output {

    /**
     * @var SchemaManager
     */
    private $manager;

    public function __construct() {
        $this->manager = new SchemaManager();

        add_action('wp_head', [$this, 'render_schema'], 10);
    }

    /**
     * Print the final JSON-LD graph on the frontend.
     *
     * This class is intentionally a renderer/normalizer only.
     * It must not build page-specific schemas such as Product, ProductGroup,
     * archive ItemList, SiteNavigationElement, or WooCommerce Offer data.
     * Those belong in SchemaManager/DataResolver/dedicated schema builders.
     *
     * @return void
     */
    public function render_schema() {
        if (!$this->should_render()) {
            return;
        }

        $schemas = $this->manager->get_schemas_for_current_page();
        $schemas = $this->normalize_schemas($schemas);

        if (empty($schemas)) {
            return;
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@graph'   => array_values($schemas),
        ];

        $json = wp_json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if (!is_string($json) || $json === '') {
            return;
        }

        $json = $this->escape_json_ld_for_script_tag($json);

        echo "\n" . '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    /**
     * Check if JSON-LD should be rendered for the current request.
     *
     * @return bool
     */
    private function should_render() {
        if (is_admin()) {
            return false;
        }

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return false;
        }

        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return false;
        }

        if (function_exists('is_feed') && is_feed()) {
            return false;
        }

        return true;
    }

    /**
     * Normalize schema nodes before rendering.
     *
     * @param mixed $schemas
     * @return array
     */
    private function normalize_schemas($schemas) {
        if (empty($schemas) || !is_array($schemas)) {
            return [];
        }

        $schemas = $this->flatten_graphs($schemas);
        $schemas = $this->remove_invalid_schema_items($schemas);
        $schemas = $this->normalize_schema_items($schemas);
        $schemas = $this->merge_duplicate_schemas_by_id($schemas);
        $schemas = $this->normalize_schema_references($schemas);
        $schemas = $this->remove_homepage_breadcrumb($schemas);
        $schemas = $this->normalize_breadcrumb_language($schemas);
        $schemas = $this->normalize_item_lists($schemas);
        $schemas = $this->remove_invalid_schema_items($schemas);

        return array_values($schemas);
    }

    /**
     * Flatten root arrays that contain @graph.
     *
     * @param array $schemas
     * @return array
     */
    private function flatten_graphs($schemas) {
        $flat = [];

        foreach ($schemas as $schema) {
            if (!is_array($schema)) {
                continue;
            }

            if (isset($schema['@graph']) && is_array($schema['@graph'])) {
                foreach ($schema['@graph'] as $graph_item) {
                    if (is_array($graph_item)) {
                        $flat[] = $graph_item;
                    }
                }

                continue;
            }

            $flat[] = $schema;
        }

        return $flat;
    }

    /**
     * Remove invalid root schema nodes.
     *
     * @param array $schemas
     * @return array
     */
    private function remove_invalid_schema_items($schemas) {
        $clean = [];

        foreach ($schemas as $schema) {
            if (!is_array($schema)) {
                continue;
            }

            if (!empty($schema['_disabled'])) {
                continue;
            }

            $schema = $this->deep_cleanup($schema);

            if (empty($schema) || !is_array($schema)) {
                continue;
            }

            if (empty($schema['@type'])) {
                continue;
            }

            if (isset($schema['itemListElement']) && is_array($schema['itemListElement']) && empty($schema['itemListElement'])) {
                continue;
            }

            $clean[] = $schema;
        }

        return $clean;
    }

    /**
     * Normalize each schema node.
     *
     * @param array $schemas
     * @return array
     */
    private function normalize_schema_items($schemas) {
        $normalized = [];

        foreach ($schemas as $schema) {
            if (!is_array($schema)) {
                continue;
            }

            unset($schema['@context']);

            $schema = $this->normalize_schema_id($schema);
            $schema = $this->normalize_schema_type($schema);
            $schema = $this->normalize_logo_schema($schema);
            $schema = $this->normalize_contact_points($schema);
            $schema = $this->normalize_website_search_action($schema);
            $schema = $this->normalize_breadcrumb_node($schema);
            $schema = $this->deep_cleanup($schema);

            if (!empty($schema)) {
                $normalized[] = $schema;
            }
        }

        return $normalized;
    }

    /**
     * Normalize @id value.
     *
     * @param array $schema
     * @return array
     */

    /**
     * Remove invalid generic Country values from final output.
     *
     * @param mixed $schema
     * @return mixed
     */
    private function remove_invalid_country_nodes($schema) {

        if (!is_array($schema)) {
            return $schema;
        }

        if (
            isset($schema['@type']) &&
            $schema['@type'] === 'Country' &&
            isset($schema['name'])
        ) {

            $value = strtolower(trim((string) $schema['name']));

            if (in_array($value, [
                'worldwide',
                'world wide',
                'all countries',
                'global',
            ], true)) {
                return [];
            }
        }

        foreach ($schema as $key => $value) {

            if (is_array($value)) {
                $schema[$key] = $this->remove_invalid_country_nodes($value);
            }

        }

        return $schema;
    }


    private function normalize_schema_id($schema) {
        if (!empty($schema['@id']) && is_string($schema['@id'])) {
            $schema['@id'] = esc_url_raw(trim($schema['@id']));
        }

        return $schema;
    }

    /**
     * Normalize @type value.
     *
     * @param array $schema
     * @return array
     */
    private function normalize_schema_type($schema) {
        if (empty($schema['@type'])) {
            return $schema;
        }

        if (is_string($schema['@type'])) {
            $schema['@type'] = trim($schema['@type']);
            return $schema;
        }

        if (!is_array($schema['@type'])) {
            unset($schema['@type']);
            return $schema;
        }

        $types = [];

        foreach ($schema['@type'] as $type) {
            if (!is_string($type)) {
                continue;
            }

            $type = trim($type);

            if ($type !== '') {
                $types[] = $type;
            }
        }

        $types = array_values(array_unique($types));

        if (empty($types)) {
            unset($schema['@type']);
            return $schema;
        }

        $schema['@type'] = count($types) === 1 ? $types[0] : $types;

        return $schema;
    }

    /**
     * Convert logo URL to ImageObject for organization-like schemas.
     *
     * @param array $schema
     * @return array
     */
    private function normalize_logo_schema($schema) {
        if (!$this->schema_has_any_type($schema, ['Organization', 'OnlineStore', 'Store', 'LocalBusiness'])) {
            return $schema;
        }

        if (!empty($schema['logo']) && is_string($schema['logo'])) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url'   => esc_url_raw($schema['logo']),
            ];
        }

        if (!empty($schema['logo']) && is_array($schema['logo'])) {
            if (empty($schema['logo']['@type'])) {
                $schema['logo']['@type'] = 'ImageObject';
            }

            if (!empty($schema['logo']['url']) && is_string($schema['logo']['url'])) {
                $schema['logo']['url'] = esc_url_raw($schema['logo']['url']);
            }
        }

        return $schema;
    }

    /**
     * Normalize contactPoint values.
     *
     * @param array $schema
     * @return array
     */
    private function normalize_contact_points($schema) {
        if (empty($schema['contactPoint'])) {
            return $schema;
        }

        $points = $schema['contactPoint'];

        if (is_array($points) && $this->is_assoc($points)) {
            $points = [$points];
        }

        if (!is_array($points)) {
            unset($schema['contactPoint']);
            return $schema;
        }

        $clean = [];

        foreach ($points as $point) {
            if (!is_array($point)) {
                continue;
            }

            if (empty($point['@type'])) {
                $point['@type'] = 'ContactPoint';
            }

            if (!empty($point['telephone']) && is_string($point['telephone'])) {
                $point['telephone'] = trim($point['telephone']);
            }

            if (!empty($point['email']) && is_string($point['email'])) {
                $point['email'] = sanitize_email($point['email']);
            }

            if (!empty($point['availableLanguage']) && is_string($point['availableLanguage'])) {
                $point['availableLanguage'] = [$point['availableLanguage']];
            }

            if (!empty($point['areaServed']) && is_string($point['areaServed'])) {
                $point['areaServed'] = [$point['areaServed']];
            }

            $point = $this->deep_cleanup($point);

            if (!empty($point)) {
                $clean[] = $point;
            }
        }

        if (empty($clean)) {
            unset($schema['contactPoint']);
            return $schema;
        }

        $schema['contactPoint'] = $clean;

        return $schema;
    }

    /**
     * Normalize WebSite SearchAction target to EntryPoint.
     *
     * @param array $schema
     * @return array
     */
    private function normalize_website_search_action($schema) {
        if (!$this->schema_has_type($schema, 'WebSite')) {
            return $schema;
        }

        if (empty($schema['potentialAction']) || !is_array($schema['potentialAction'])) {
            return $schema;
        }

        $action = $schema['potentialAction'];

        if (($action['@type'] ?? '') !== 'SearchAction') {
            return $schema;
        }

        if (!empty($action['target']) && is_string($action['target'])) {
            $action['target'] = [
                '@type'       => 'EntryPoint',
                'urlTemplate' => $action['target'],
            ];
        }

        if (empty($action['query-input'])) {
            $action['query-input'] = 'required name=search_term_string';
        }

        $schema['potentialAction'] = $action;

        return $schema;
    }

    /**
     * Normalize BreadcrumbList node.
     *
     * @param array $schema
     * @return array
     */
    private function normalize_breadcrumb_node($schema) {
        if (!$this->schema_has_type($schema, 'BreadcrumbList')) {
            return $schema;
        }

        if (empty($schema['itemListElement']) || !is_array($schema['itemListElement'])) {
            return $schema;
        }

        foreach ($schema['itemListElement'] as $index => $item) {
            if (!is_array($item)) {
                unset($schema['itemListElement'][$index]);
                continue;
            }

            if (empty($item['@type'])) {
                $item['@type'] = 'ListItem';
            }

            if (empty($item['position'])) {
                $item['position'] = $index + 1;
            }

            if (!empty($item['item']) && is_array($item['item']) && !empty($item['item']['@id'])) {
                $item['item'] = $item['item']['@id'];
            }

            $schema['itemListElement'][$index] = $this->deep_cleanup($item);
        }

        $schema['itemListElement'] = array_values($schema['itemListElement']);

        return $schema;
    }

    /**
     * Merge duplicate root nodes by @id.
     *
     * @param array $schemas
     * @return array
     */
    private function merge_duplicate_schemas_by_id($schemas) {
        $by_id = [];
        $without_id = [];

        foreach ($schemas as $schema) {
            if (!is_array($schema)) {
                continue;
            }

            $id = !empty($schema['@id']) && is_string($schema['@id']) ? trim($schema['@id']) : '';

            if ($id === '') {
                $without_id[] = $schema;
                continue;
            }

            if (!isset($by_id[$id])) {
                $by_id[$id] = $schema;
                continue;
            }

            $by_id[$id] = $this->merge_schema_nodes($by_id[$id], $schema);
        }

        return array_merge(array_values($by_id), $without_id);
    }

    /**
     * Normalize common @id reference fields.
     *
     * @param array $schemas
     * @return array
     */
    private function normalize_schema_references($schemas) {
        $reference_fields = [
            'isPartOf',
            'publisher',
            'breadcrumb',
            'mainEntity',
            'about',
            'author',
            'creator',
            'brand',
            'seller',
            'manufacturer',
            'parentOrganization',
            'isVariantOf',
        ];

        foreach ($schemas as $index => $schema) {
            if (!is_array($schema)) {
                continue;
            }

            foreach ($reference_fields as $field) {
                $schema = $this->normalize_id_reference($schema, $field);
            }

            $schemas[$index] = $schema;
        }

        return $schemas;
    }

    /**
     * Normalize a single reference field only when it is clearly an @id reference.
     *
     * @param array  $schema
     * @param string $field
     * @return array
     */
    private function normalize_id_reference($schema, $field) {
        if (empty($schema[$field])) {
            return $schema;
        }

        if (is_string($schema[$field]) && $this->looks_like_schema_id($schema[$field])) {
            $schema[$field] = ['@id' => $schema[$field]];
            return $schema;
        }

        if (is_array($schema[$field]) && !empty($schema[$field]['@id'])) {
            $schema[$field] = ['@id' => $schema[$field]['@id']];
        }

        return $schema;
    }

    /**
     * Remove homepage breadcrumb and homepage breadcrumb reference.
     *
     * @param array $schemas
     * @return array
     */
    private function remove_homepage_breadcrumb($schemas) {
        if (!(function_exists('is_front_page') && is_front_page())) {
            return $schemas;
        }

        $clean = [];

        foreach ($schemas as $schema) {
            if (!is_array($schema)) {
                continue;
            }

            if ($this->schema_has_type($schema, 'BreadcrumbList')) {
                continue;
            }

            if ($this->schema_is_webpage_like($schema)) {
                unset($schema['breadcrumb']);
            }

            $clean[] = $schema;
        }

        return $clean;
    }

    /**
     * Make Breadcrumb home label match the final schema language.
     *
     * @param array $schemas
     * @return array
     */
    private function normalize_breadcrumb_language($schemas) {
        $language = $this->get_primary_language($schemas);
        $home_label = $this->get_home_label($language);

        foreach ($schemas as $schema_index => $schema) {
            if (!is_array($schema) || !$this->schema_has_type($schema, 'BreadcrumbList')) {
                continue;
            }

            if (empty($schema['itemListElement']) || !is_array($schema['itemListElement'])) {
                continue;
            }

            foreach ($schema['itemListElement'] as $item_index => $item) {
                if (!is_array($item)) {
                    continue;
                }

                if ((int) ($item['position'] ?? 0) === 1) {
                    $item['name'] = $home_label;
                }

                $schema['itemListElement'][$item_index] = $item;
            }

            $schemas[$schema_index] = $schema;
        }

        return $schemas;
    }

    /**
     * Normalize existing ItemList nodes without creating new ones.
     *
     * @param array $schemas
     * @return array
     */
    private function normalize_item_lists($schemas) {
        foreach ($schemas as $schema_index => $schema) {
            if (!is_array($schema) || !$this->schema_has_type($schema, 'ItemList')) {
                continue;
            }

            if (empty($schema['itemListElement']) || !is_array($schema['itemListElement'])) {
                continue;
            }

            foreach ($schema['itemListElement'] as $item_index => $item) {
                if (!is_array($item)) {
                    unset($schema['itemListElement'][$item_index]);
                    continue;
                }

                if (empty($item['@type'])) {
                    $item['@type'] = 'ListItem';
                }

                if (empty($item['position'])) {
                    $item['position'] = $item_index + 1;
                }

                $schema['itemListElement'][$item_index] = $this->deep_cleanup($item);
            }

            $schema['itemListElement'] = array_values($schema['itemListElement']);
            $schema['numberOfItems'] = count($schema['itemListElement']);
            $schemas[$schema_index] = $schema;
        }

        return $schemas;
    }

    /**
     * Get primary language from WebPage/WebSite or WordPress.
     *
     * @param array $schemas
     * @return string
     */
    private function get_primary_language($schemas) {
        foreach ($schemas as $schema) {
            if (is_array($schema) && $this->schema_is_webpage_like($schema) && !empty($schema['inLanguage'])) {
                return (string) $schema['inLanguage'];
            }
        }

        foreach ($schemas as $schema) {
            if (is_array($schema) && $this->schema_has_type($schema, 'WebSite') && !empty($schema['inLanguage'])) {
                return (string) $schema['inLanguage'];
            }
        }

        $language = function_exists('get_bloginfo') ? get_bloginfo('language') : '';

        return $language ? (string) $language : 'en-US';
    }

    /**
     * Get home label by language code.
     *
     * @param string $language
     * @return string
     */
    private function get_home_label($language) {
        $language = strtolower(str_replace('_', '-', (string) $language));

        if (strpos($language, 'fa') === 0) {
            return __('Home', 'amk-schema-core');
        }

        if (strpos($language, 'ar') === 0) {
            return __('Home', 'amk-schema-core');
        }

        return 'Home';
    }

    /**
     * Merge two schema nodes recursively.
     *
     * @param array $old
     * @param array $new
     * @return array
     */
    private function merge_schema_nodes($old, $new) {
        foreach ($new as $key => $new_value) {
            if (!array_key_exists($key, $old)) {
                $old[$key] = $new_value;
                continue;
            }

            $old_value = $old[$key];

            if (is_array($old_value) && is_array($new_value)) {
                if ($this->is_assoc($old_value) && $this->is_assoc($new_value)) {
                    $old[$key] = $this->merge_schema_nodes($old_value, $new_value);
                } else {
                    $old[$key] = $this->merge_list_values($old_value, $new_value);
                }

                continue;
            }

            if (!$this->is_empty_value($new_value)) {
                $old[$key] = $new_value;
            }
        }

        return $this->deep_cleanup($old);
    }

    /**
     * Merge list values and remove duplicates.
     *
     * @param array $old
     * @param array $new
     * @return array
     */
    private function merge_list_values($old, $new) {
        $merged = array_merge(array_values($old), array_values($new));
        $seen = [];
        $clean = [];

        foreach ($merged as $item) {
            $hash = is_array($item)
                ? md5((string) wp_json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                : md5((string) $item);

            if (isset($seen[$hash])) {
                continue;
            }

            $seen[$hash] = true;
            $clean[] = $item;
        }

        return $clean;
    }

    /**
     * Check whether a schema node is a WebPage-like node.
     *
     * @param array $schema
     * @return bool
     */
    private function schema_is_webpage_like($schema) {
        return $this->schema_has_any_type($schema, [
            'WebPage',
            'CollectionPage',
            'ItemPage',
            'ContactPage',
            'AboutPage',
            'ProfilePage',
            'SearchResultsPage',
            'FAQPage',
        ]);
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
     * Check if schema has any of the given @types.
     *
     * @param array $schema
     * @param array $types
     * @return bool
     */
    private function schema_has_any_type($schema, $types) {
        foreach ($types as $type) {
            if ($this->schema_has_type($schema, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deep cleanup for empty values. false and 0 are valid values.
     *
     * @param mixed $value
     * @return mixed
     */
    private function deep_cleanup($value) {
        if (!is_array($value)) {
            return $value;
        }

        $clean = [];

        foreach ($value as $key => $item) {
            $item = $this->deep_cleanup($item);

            if ($this->is_empty_value($item)) {
                continue;
            }

            $clean[$key] = $item;
        }

        return $clean;
    }

    /**
     * Check empty values.
     *
     * @param mixed $value
     * @return bool
     */
    private function is_empty_value($value) {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return empty($value);
        }

        return false;
    }

    /**
     * Check associative array.
     *
     * @param mixed $array
     * @return bool
     */
    private function is_assoc($array) {
        if (!is_array($array) || $array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Check if a string looks like a schema @id/URL reference.
     *
     * @param string $value
     * @return bool
     */
    private function looks_like_schema_id($value) {
        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        return strpos($value, 'http://') === 0
            || strpos($value, 'https://') === 0
            || strpos($value, '#') === 0;
    }

    /**
     * Escape JSON safely inside a script tag.
     *
     * @param string $json
     * @return string
     */
    private function escape_json_ld_for_script_tag($json) {
        return str_replace(
            ['</script', '<!--', '-->'],
            ['<\/script', '<\!--', '--\>'],
            $json
        );
    }
}
