<?php

namespace AMK\SchemaCore\Data;

defined('ABSPATH') || exit;

class BindingResolver {

    /**
     * Resolved DataResolver payload.
     *
     * @var array
     */
    private $data = [];

    /**
     * @param array $data
     */
    public function __construct($data = []) {
        $this->data = is_array($data) ? $data : [];
    }

    /**
     * Resolve one binding definition.
     *
     * Supported examples:
     * "title"
     * {"data_key":"title"}
     * {"value":"static"}
     * {"source":"post_meta","key":"_yoast_wpseo_title"}
     * {"source":"product_meta","key":"_gtin"}
     * {"source":"product_attribute","key":"pa_brand"}
     * {"source":"option","key":"blogname"}
     * {"source":"global_setting","path":"organization.name"}
     *
     * @param mixed       $binding
     * @param string|null $fallback_key
     * @return mixed|null
     */
    public function resolve($binding, $fallback_key = null) {
        if (is_string($binding) || is_numeric($binding)) {
            return $this->get_data_value((string) $binding);
        }

        if (is_object($binding)) {
            $binding = json_decode(json_encode($binding), true);
        }

        if (!is_array($binding)) {
            return null;
        }

        if (array_key_exists('value', $binding)) {
            return $this->apply_transform($binding['value'], $binding);
        }

        $source = isset($binding['source']) ? $this->sanitize_source($binding['source']) : '';

        if ($source === '') {
            if (isset($binding['data_key'])) {
                return $this->apply_default(
                    $this->get_data_value($binding['data_key']),
                    $binding
                );
            }

            if (isset($binding['path'])) {
                return $this->apply_default(
                    $this->get_data_value($binding['path']),
                    $binding
                );
            }

            if ($fallback_key !== null) {
                return $this->apply_default(
                    $this->get_data_value($fallback_key),
                    $binding
                );
            }

            return null;
        }

        $value = null;

        switch ($source) {
            case 'resolver':
            case 'data':
            case 'data_key':
                $value = $this->get_data_value($this->array_get_first($binding, ['data_key', 'path', 'key'], $fallback_key));
                break;

            case 'post_meta':
                $value = $this->resolve_post_meta($binding);
                break;

            case 'product_meta':
                $value = $this->resolve_product_meta($binding);
                break;

            case 'term_meta':
                $value = $this->resolve_term_meta($binding);
                break;

            case 'user_meta':
                $value = $this->resolve_user_meta($binding);
                break;

            case 'option':
                $value = $this->resolve_option($binding);
                break;

            case 'theme_mod':
                $value = $this->resolve_theme_mod($binding);
                break;

            case 'global_setting':
                $value = $this->resolve_global_setting($binding);
                break;

            case 'product_attribute':
                $value = $this->resolve_product_attribute($binding);
                break;

            case 'taxonomy_terms':
                $value = $this->resolve_taxonomy_terms($binding);
                break;

            case 'callback':
                $value = null;
                break;
        }

        return $this->apply_default($value, $binding);
    }

    /**
     * Resolve value from DataResolver payload.
     *
     * @param string|null $path
     * @return mixed|null
     */
    public function get_data_value($path) {
        $path = $this->normalize_key($path);

        if ($path === '') {
            return null;
        }

        if (array_key_exists($path, $this->data)) {
            return $this->data[$path];
        }

        $path = preg_replace('/\[(\w+)\]/', '.$1', $path);
        $path = trim($path, '.');

        if ($path === '') {
            return null;
        }

        $segments = explode('.', $path);
        $current  = $this->data;

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if (is_object($current)) {
                $current = json_decode(json_encode($current), true);
            }

            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
                continue;
            }

            return null;
        }

        return $current;
    }

    private function resolve_post_meta($binding) {
        $key = $this->array_get_first($binding, ['key', 'meta_key'], '');
        $key = is_string($key) ? trim($key) : '';

        if ($key === '' || !function_exists('get_post_meta')) {
            return null;
        }

        $post_id = absint($this->array_get_first($binding, ['post_id', 'id'], 0));

        if (!$post_id) {
            $post_id = absint($this->get_data_value('id'));
        }

        if (!$post_id && function_exists('get_the_ID')) {
            $post_id = absint(get_the_ID());
        }

        if (!$post_id) {
            return null;
        }

        return get_post_meta($post_id, $key, true);
    }

    private function resolve_product_meta($binding) {
        return $this->resolve_post_meta($binding);
    }

    private function resolve_term_meta($binding) {
        $key = $this->array_get_first($binding, ['key', 'meta_key'], '');
        $key = is_string($key) ? trim($key) : '';

        if ($key === '' || !function_exists('get_term_meta')) {
            return null;
        }

        $term_id = absint($this->array_get_first($binding, ['term_id', 'id'], 0));

        if (!$term_id && function_exists('get_queried_object')) {
            $term    = get_queried_object();
            $term_id = ($term && isset($term->term_id)) ? absint($term->term_id) : 0;
        }

        if (!$term_id) {
            return null;
        }

        return get_term_meta($term_id, $key, true);
    }

    private function resolve_user_meta($binding) {
        $key = $this->array_get_first($binding, ['key', 'meta_key'], '');
        $key = is_string($key) ? trim($key) : '';

        if ($key === '' || !function_exists('get_user_meta')) {
            return null;
        }

        $user_id = absint($this->array_get_first($binding, ['user_id', 'id'], 0));

        if (!$user_id && function_exists('get_post_field') && function_exists('get_the_ID')) {
            $post_id = get_the_ID();
            $user_id = $post_id ? absint(get_post_field('post_author', $post_id)) : 0;
        }

        if (!$user_id) {
            return null;
        }

        return get_user_meta($user_id, $key, true);
    }

    private function resolve_option($binding) {
        $key = $this->array_get_first($binding, ['key', 'option', 'option_name'], '');
        $key = is_string($key) ? trim($key) : '';

        if ($key === '' || !function_exists('get_option')) {
            return null;
        }

        return get_option($key, null);
    }

    private function resolve_theme_mod($binding) {
        $key = $this->array_get_first($binding, ['key', 'mod'], '');
        $key = is_string($key) ? trim($key) : '';

        if ($key === '' || !function_exists('get_theme_mod')) {
            return null;
        }

        return get_theme_mod($key, null);
    }

    private function resolve_global_setting($binding) {
        if (!function_exists('get_option')) {
            return null;
        }

        $settings = get_option('amk_schema_core_global_settings', []);

        if (!is_array($settings)) {
            return null;
        }

        $path = $this->array_get_first($binding, ['path', 'key', 'data_key'], '');
        $path = $this->normalize_key($path);

        if ($path === '') {
            return $settings;
        }

        $segments = explode('.', preg_replace('/\[(\w+)\]/', '.$1', $path));
        $current  = $settings;

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
                continue;
            }

            return null;
        }

        return $current;
    }

    private function resolve_product_attribute($binding) {
        $attribute = $this->array_get_first($binding, ['attribute', 'key', 'taxonomy'], '');
        $attribute = is_string($attribute) ? trim($attribute) : '';

        if ($attribute === '') {
            return null;
        }

        $product_id = absint($this->array_get_first($binding, ['product_id', 'post_id', 'id'], 0));

        if (!$product_id) {
            $product_id = absint($this->get_data_value('id'));
        }

        if (!$product_id && function_exists('get_the_ID')) {
            $product_id = absint(get_the_ID());
        }

        if (!$product_id || !function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($product_id);

        if (!$product || !method_exists($product, 'get_attribute')) {
            return null;
        }

        return wp_strip_all_tags($product->get_attribute($attribute));
    }

    private function resolve_taxonomy_terms($binding) {
        $taxonomy = $this->array_get_first($binding, ['taxonomy', 'key'], '');
        $taxonomy = is_string($taxonomy) ? trim($taxonomy) : '';

        if ($taxonomy === '' || !function_exists('get_the_terms')) {
            return null;
        }

        $post_id = absint($this->array_get_first($binding, ['post_id', 'id'], 0));

        if (!$post_id) {
            $post_id = absint($this->get_data_value('id'));
        }

        if (!$post_id && function_exists('get_the_ID')) {
            $post_id = absint(get_the_ID());
        }

        if (!$post_id) {
            return null;
        }

        $terms = get_the_terms($post_id, $taxonomy);

        if (empty($terms) || is_wp_error($terms)) {
            return null;
        }

        $field = $this->array_get_first($binding, ['field', 'return'], 'names');
        $field = is_string($field) ? sanitize_key($field) : 'names';

        $values = [];

        foreach ($terms as $term) {
            if ($field === 'ids') {
                $values[] = absint($term->term_id);
            } elseif ($field === 'slugs') {
                $values[] = $term->slug;
            } elseif ($field === 'urls' || $field === 'links') {
                $link = get_term_link($term);

                if (!is_wp_error($link)) {
                    $values[] = $link;
                }
            } else {
                $values[] = $term->name;
            }
        }

        if (!empty($binding['single'])) {
            return reset($values);
        }

        return $values;
    }

    private function apply_default($value, $binding) {
        if ($this->is_empty($value) && is_array($binding) && array_key_exists('default', $binding)) {
            $value = $binding['default'];
        }

        return $this->apply_transform($value, $binding);
    }

    private function apply_transform($value, $binding) {
        if (!is_array($binding) || empty($binding['transform'])) {
            return $value;
        }

        $transform = is_array($binding['transform']) ? $binding['transform'] : [$binding['transform']];

        foreach ($transform as $step) {
            $step = is_string($step) ? sanitize_key($step) : '';

            switch ($step) {
                case 'string':
                    $value = is_scalar($value) ? (string) $value : '';
                    break;

                case 'strip_tags':
                    $value = is_scalar($value) ? wp_strip_all_tags((string) $value) : '';
                    break;

                case 'sanitize_text':
                    $value = is_scalar($value) ? sanitize_text_field((string) $value) : '';
                    break;

                case 'url':
                case 'esc_url':
                    $value = is_scalar($value) ? esc_url_raw((string) $value) : '';
                    break;

                case 'absint':
                case 'int':
                    $value = absint($value);
                    break;

                case 'float':
                    $value = is_numeric($value) ? (float) $value : null;
                    break;

                case 'bool':
                case 'boolean':
                    $value = (bool) $value;
                    break;

                case 'csv_array':
                    if (is_string($value)) {
                        $value = array_values(array_filter(array_map('trim', explode(',', $value))));
                    }
                    break;

                case 'first':
                    if (is_array($value)) {
                        $value = reset($value);
                    }
                    break;
            }
        }

        return $value;
    }

    private function is_empty($value) {
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

    private function sanitize_source($source) {
        $source = is_string($source) ? sanitize_key($source) : '';

        $allowed = [
            'resolver',
            'data',
            'data_key',
            'post_meta',
            'product_meta',
            'term_meta',
            'user_meta',
            'option',
            'theme_mod',
            'global_setting',
            'product_attribute',
            'taxonomy_terms',
        ];

        return in_array($source, $allowed, true) ? $source : '';
    }

    private function normalize_key($key) {
        if (!is_string($key) && !is_numeric($key)) {
            return '';
        }

        $key = trim((string) $key);
        $key = preg_replace('/^\{\{\s*/', '', $key);
        $key = preg_replace('/\s*\}\}$/', '', $key);

        return trim($key);
    }

    private function array_get_first($array, $keys, $default = '') {
        if (!is_array($array)) {
            return $default;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                return $array[$key];
            }
        }

        return $default;
    }
}
