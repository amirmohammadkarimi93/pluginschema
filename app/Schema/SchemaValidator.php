<?php

namespace AMK\SchemaCore\Schema;

defined('ABSPATH') || exit;

class SchemaValidator {

    /**
     * @var array
     */
    private $errors = [];

    /**
     * @var array
     */
    private $warnings = [];

    /**
     * Schema.org types supported by this plugin.
     *
     * @var array
     */
    private $supported_types = [
        'Organization',
        'OnlineStore',
        'Store',
        'LocalBusiness',

        'WebSite',
        'WebPage',
        'ContactPage',
        'AboutPage',
        'CollectionPage',
        'Article',
        'Product',
        'ProductGroup',

        'Offer',
        'Brand',
        'AggregateRating',

        'BreadcrumbList',
        'ListItem',

        'PostalAddress',
        'ContactPoint',
        'GeoCoordinates',

        'MerchantReturnPolicy',
        'ShippingService',
        'ShippingConditions',
        'DefinedRegion',
        'MonetaryAmount',
        'ShippingDeliveryTime',
        'QuantitativeValue',

        'SearchAction',
        'SiteNavigationElement',
        'ItemList',
        'Person',

        // Safe extra types used by Schema.org templates.
        'Thing',
        'EntryPoint',
        'ImageObject',
        'Review',
        'Rating',
        'PropertyValue',
        'OpeningHoursSpecification',
    ];

    /**
     * Common properties allowed on most Schema.org nodes.
     *
     * @var array
     */
    private $common_properties = [
        '@context',
        '@type',
        '@id',
        '@graph',

        'name',
        'description',
        'url',
        'image',
        'logo',
        'sameAs',
        'mainEntity',
        'mainEntityOfPage',
        'inLanguage',
    ];

    /**
     * Allowed properties per type.
     *
     * This is intentionally practical, not a full Schema.org mirror.
     * Unknown properties are warnings, not fatal errors.
     *
     * @var array
     */
    private $properties_by_type = [
        'Organization' => [
            'legalName',
            'alternateName',
            'telephone',
            'email',
            'address',
            'contactPoint',
            'priceRange',
            'acceptedPaymentMethod',
            'foundingDate',
            'taxID',
            'vatID',
            'hasMerchantReturnPolicy',
            'hasShippingService',
            'areaServed',
            'department',
            'parentOrganization',
            'branchOf',
        ],

        'OnlineStore' => [
            'legalName',
            'alternateName',
            'telephone',
            'email',
            'address',
            'contactPoint',
            'priceRange',
            'acceptedPaymentMethod',
            'foundingDate',
            'taxID',
            'vatID',
            'hasMerchantReturnPolicy',
            'hasShippingService',
            'areaServed',
        ],

        'Store' => [
            'legalName',
            'alternateName',
            'telephone',
            'email',
            'address',
            'contactPoint',
            'priceRange',
            'currenciesAccepted',
            'acceptedPaymentMethod',
            'foundingDate',
            'taxID',
            'vatID',
            'geo',
            'hasMap',
            'openingHoursSpecification',
            'hasMerchantReturnPolicy',
            'hasShippingService',
            'areaServed',
            'department',
            'parentOrganization',
            'branchOf',
        ],

        'LocalBusiness' => [
            'legalName',
            'alternateName',
            'telephone',
            'email',
            'address',
            'contactPoint',
            'priceRange',
            'currenciesAccepted',
            'acceptedPaymentMethod',
            'foundingDate',
            'taxID',
            'vatID',
            'geo',
            'hasMap',
            'openingHoursSpecification',
            'hasMerchantReturnPolicy',
            'hasShippingService',
            'areaServed',
            'department',
            'parentOrganization',
            'branchOf',
        ],

        'WebSite' => [
            'publisher',
            'potentialAction',
        ],

        'WebPage' => [
            'headline',
            'isPartOf',
            'publisher',
            'breadcrumb',
            'primaryImageOfPage',
            'datePublished',
            'dateModified',
            'about',
        ],

        'ContactPage' => [
            'headline',
            'isPartOf',
            'publisher',
            'breadcrumb',
            'primaryImageOfPage',
            'datePublished',
            'dateModified',
            'about',
            'mainEntity',
        ],

        'AboutPage' => [
            'headline',
            'isPartOf',
            'publisher',
            'breadcrumb',
            'primaryImageOfPage',
            'datePublished',
            'dateModified',
            'about',
            'mainEntity',
        ],

        'CollectionPage' => [
            'headline',
            'isPartOf',
            'publisher',
            'breadcrumb',
            'primaryImageOfPage',
            'datePublished',
            'dateModified',
            'about',
        ],

        'Article' => [
            'headline',
            'articleSection',
            'wordCount',
            'datePublished',
            'dateModified',
            'author',
            'publisher',
        ],

        'Product' => [
            'sku',
            'gtin',
            'gtin8',
            'gtin12',
            'gtin13',
            'gtin14',
            'mpn',
            'brand',
            'offers',
            'aggregateRating',
            'review',
            'category',
            'additionalProperty',
            'isVariantOf',
            'color',
            'size',
            'material',
            'pattern',
        ],

        'ProductGroup' => [
            'sku',
            'gtin',
            'gtin8',
            'gtin12',
            'gtin13',
            'gtin14',
            'mpn',
            'brand',
            'aggregateRating',
            'review',
            'category',
            'additionalProperty',
            'productGroupID',
            'variesBy',
            'hasVariant',
        ],

        'Offer' => [
            'price',
            'priceCurrency',
            'availability',
            'itemCondition',
            'seller',
            'priceValidUntil',
            'shippingDetails',
            'hasMerchantReturnPolicy',
            'availabilityStarts',
            'availabilityEnds',
        ],

        'Brand' => [],

        'AggregateRating' => [
            'ratingValue',
            'reviewCount',
            'ratingCount',
            'bestRating',
            'worstRating',
        ],

        'BreadcrumbList' => [
            'itemListElement',
        ],

        'ListItem' => [
            'position',
            'item',
        ],

        'PostalAddress' => [
            'streetAddress',
            'addressLocality',
            'addressRegion',
            'postalCode',
            'addressCountry',
        ],

        'ContactPoint' => [
            'contactType',
            'telephone',
            'email',
            'availableLanguage',
            'areaServed',
            'contactOption',
        ],

        'GeoCoordinates' => [
            'latitude',
            'longitude',
            'elevation',
        ],

        'MerchantReturnPolicy' => [
            'applicableCountry',
            'returnPolicyCategory',
            'merchantReturnDays',
            'returnMethod',
            'returnFees',
            'refundType',
            'returnShippingFeesAmount',
            'restockingFee',
            'returnPolicyCountry',
        ],

        'ShippingService' => [
            'provider',
            'areaServed',
            'shippingConditions',
            'shippingRate',
            'deliveryTime',
            'fulfillmentType',
        ],

        'ShippingConditions' => [
            'shippingDestination',
            'shippingRate',
            'deliveryTime',
            'doesNotShip',
            'orderValue',
            'currency',
        ],

        'DefinedRegion' => [
            'addressCountry',
            'addressRegion',
            'postalCodeRange',
            'geoMidpoint',
            'geoRadius',
        ],

        'MonetaryAmount' => [
            'value',
            'currency',
            'minValue',
            'maxValue',
        ],

        'ShippingDeliveryTime' => [
            'handlingTime',
            'transitTime',
            'businessDays',
            'cutoffTime',
        ],

        'QuantitativeValue' => [
            'value',
            'minValue',
            'maxValue',
            'unitCode',
            'unitText',
        ],

        'SearchAction' => [
            'target',
            'query-input',
            'queryInput',
            'object',
            'result',
        ],

        'SiteNavigationElement' => [
            'position',
            'isPartOf',
            'about',
        ],

        'ItemList' => [
            'itemListElement',
            'numberOfItems',
            'itemListOrder',
        ],

        'Person' => [
            'email',
            'telephone',
            'jobTitle',
            'worksFor',
        ],

        'Thing' => [],
        'EntryPoint' => [
            'urlTemplate',
            'actionPlatform',
            'contentType',
            'encodingType',
        ],
        'ImageObject' => [
            'contentUrl',
            'caption',
            'width',
            'height',
        ],
        'Review' => [
            'author',
            'reviewRating',
            'reviewBody',
            'datePublished',
            'publisher',
        ],
        'Rating' => [
            'ratingValue',
            'bestRating',
            'worstRating',
        ],
        'PropertyValue' => [
            'propertyID',
            'value',
            'unitCode',
            'unitText',
        ],
        'OpeningHoursSpecification' => [
            'dayOfWeek',
            'opens',
            'closes',
            'validFrom',
            'validThrough',
        ],
    ];

    /**
     * Main validator.
     *
     * @param string|array|object $schema
     * @return bool
     */
    public function validate($schema) {
        $this->errors   = [];
        $this->warnings = [];

        $schema = $this->normalize_schema_input($schema);

        if ($schema === null) {
            return false;
        }

        $this->validate_root($schema);

        return empty($this->errors);
    }

    /**
     * Detailed validation report.
     *
     * @param string|array|object $schema
     * @return array
     */
    public function validate_with_report($schema) {
        $valid = $this->validate($schema);

        return [
            'valid'    => $valid,
            'errors'   => $this->get_errors(),
            'warnings' => $this->get_warnings(),
        ];
    }

    /**
     * Alias for older/newer call sites.
     *
     * @param string|array|object $schema
     * @return bool
     */
    public function validate_json($schema) {
        return $this->validate($schema);
    }

    /**
     * Alias for editor pages.
     *
     * @param string|array|object $schema
     * @return bool
     */
    public function validate_schema_json($schema) {
        return $this->validate($schema);
    }

    /**
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * @return array
     */
    public function get_warnings() {
        return $this->warnings;
    }

    /**
     * @return array
     */
    public function get_supported_types() {
        return $this->supported_types;
    }

    /**
     * @return array
     */
    public function get_allowed_types() {
        return $this->supported_types;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function supports_type($type) {
        $type = $this->normalize_type($type);

        if ($type === '' || $this->is_placeholder_string($type)) {
            return true;
        }

        return in_array($type, $this->supported_types, true);
    }

    /**
     * @param mixed $schema
     * @return array|null
     */
    private function normalize_schema_input($schema) {
        if (is_string($schema)) {
            $schema = trim($schema);

            if ($schema === '') {
                $this->add_error('$', __('Schema JSON is empty.', 'amk-schema-core'));
                return null;
            }

            $decoded = json_decode($schema, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->add_error('$', __('Invalid JSON: ', 'amk-schema-core') . json_last_error_msg());
                return null;
            }

            return $decoded;
        }

        if (is_object($schema)) {
            $schema = json_decode(json_encode($schema), true);
        }

        if (!is_array($schema)) {
            $this->add_error('$', __('Schema structure must be a JSON object or array.', 'amk-schema-core'));
            return null;
        }

        return $schema;
    }

    /**
     * @param array $schema
     * @return void
     */
    private function validate_root($schema) {
        if (!$this->is_assoc($schema)) {
            foreach ($schema as $index => $item) {
                $this->validate_node($item, '$[' . $index . ']', true);
            }

            return;
        }

        if (isset($schema['@graph'])) {
            if (!is_array($schema['@graph'])) {
                $this->add_error('$.@graph', __('`@graph` must be an array.', 'amk-schema-core'));
                return;
            }

            foreach ($schema['@graph'] as $index => $item) {
                $this->validate_node($item, '$.@graph[' . $index . ']', true);
            }

            return;
        }

        $this->validate_node($schema, '$', true);
    }

    /**
     * @param mixed  $node
     * @param string $path
     * @param bool   $require_type
     * @return void
     */
    private function validate_node($node, $path, $require_type = false) {
        if ($this->is_placeholder_value($node)) {
            return;
        }

        if (is_object($node)) {
            $node = json_decode(json_encode($node), true);
        }

        if (!is_array($node)) {
            return;
        }

        if (!$this->is_assoc($node)) {
            foreach ($node as $index => $item) {
                $this->validate_node($item, $path . '[' . $index . ']', false);
            }

            return;
        }

        if (isset($node['@graph'])) {
            if (!is_array($node['@graph'])) {
                $this->add_error($path . '.@graph', __('`@graph` must be an array.', 'amk-schema-core'));
                return;
            }

            foreach ($node['@graph'] as $index => $item) {
                $this->validate_node($item, $path . '.@graph[' . $index . ']', true);
            }

            return;
        }

        $types = $this->extract_types($node, $path);
        $has_dynamic_type = $this->has_dynamic_type($node);

        if ($require_type && empty($types) && !$has_dynamic_type && !isset($node['@id'])) {
            $this->add_error($path, __('Every main schema object must have `@type`.', 'amk-schema-core'));
        }

        $allowed_properties = $has_dynamic_type
            ? $this->get_all_known_properties()
            : $this->get_allowed_properties_for_types($types);

        if (in_array('OnlineStore', $types, true) && !in_array('Store', $types, true) && !in_array('LocalBusiness', $types, true)) {
            foreach (['geo', 'hasMap', 'openingHoursSpecification', 'openingHours', 'currenciesAccepted'] as $local_property) {
                if (array_key_exists($local_property, $node)) {
                    $this->add_warning(
                        $path . '.' . $local_property,
                        'property `' . $local_property . __('` is not suitable for `OnlineStore` alone. If the store has a physical location, set multiple types such as `Organization + Store + OnlineStore`.', 'amk-schema-core')
                    );
                }
            }
        }

        if (in_array('SiteNavigationElement', $types, true) && array_key_exists('itemListElement', $node)) {
            $this->add_warning(
                $path . '.itemListElement',
                __('`itemListElement` is not a clean structure for `SiteNavigationElement`. For a site menu, create each link as a separate `SiteNavigationElement` or change the type to `ItemList`.', 'amk-schema-core')
            );
        }

        foreach ($node as $property => $value) {
            if ($property === 'paymentAccepted') {
                $this->add_warning(
                    $path . '.' . $property,
                    __('The `paymentAccepted` property is deprecated or less compatible. Use `acceptedPaymentMethod` in newer templates.', 'amk-schema-core')
                );
            }

            if ($property === 'openingHours') {
                $this->add_warning(
                    $path . '.' . $property,
                    __('Raw `openingHours` is not recommended for newer templates. Use `openingHoursSpecification`.', 'amk-schema-core')
                );
            }

            if (!$this->is_allowed_property($property, $allowed_properties)) {
                $this->add_warning(
                    $path . '.' . $property,
                    'property `' . $property . __('` is not in the plugin support list for the current type. If it was added intentionally, this is not critical.', 'amk-schema-core')
                );
            }

            $this->validate_property_value($property, $value, $path . '.' . $property);
        }
    }

    /**
     * @param array  $node
     * @param string $path
     * @return array
     */
    private function extract_types($node, $path) {
        if (!isset($node['@type'])) {
            return [];
        }

        $type_value = $node['@type'];
        $types      = [];

        if ($this->is_placeholder_value($type_value)) {
            return [];
        }

        if (is_string($type_value)) {
            $types[] = $this->normalize_type($type_value);
        } elseif (is_array($type_value)) {
            foreach ($type_value as $index => $type) {
                if ($this->is_placeholder_value($type)) {
                    continue;
                }

                if (!is_string($type)) {
                    $this->add_error($path . '.@type[' . $index . ']', __('`@type` must be a string.', 'amk-schema-core'));
                    continue;
                }

                $types[] = $this->normalize_type($type);
            }
        } else {
            $this->add_error($path . '.@type', __('`@type` must be a string or an array of strings.', 'amk-schema-core'));
            return [];
        }

        foreach ($types as $type) {
            if ($type === '') {
                $this->add_error($path . '.@type', __('`@type` cannot be empty.', 'amk-schema-core'));
                continue;
            }

            if (!$this->supports_type($type)) {
                $this->add_error($path . '.@type', 'type `' . $type . __('` is not supported by the plugin validator.', 'amk-schema-core'));
            }
        }

        return array_values(array_unique(array_filter($types)));
    }

    /**
     * @param array $types
     * @return array
     */
    private function get_allowed_properties_for_types($types) {
        $allowed = $this->common_properties;

        foreach ($types as $type) {
            if (isset($this->properties_by_type[$type])) {
                $allowed = array_merge($allowed, $this->properties_by_type[$type]);
            }
        }

        return array_values(array_unique($allowed));
    }

    /**
     * When @type is a resolver placeholder, the concrete Schema.org type is unknown
     * at template-validation time. In that situation we should not warn about
     * properties that are valid for any supported type.
     *
     * @return array
     */
    private function get_all_known_properties() {
        $allowed = $this->common_properties;

        foreach ($this->properties_by_type as $properties) {
            $allowed = array_merge($allowed, $properties);
        }

        return array_values(array_unique($allowed));
    }

    /**
     * @param array $node
     * @return bool
     */
    private function has_dynamic_type($node) {
        if (!is_array($node) || !isset($node['@type'])) {
            return false;
        }

        $type_value = $node['@type'];

        if ($this->is_placeholder_value($type_value)) {
            return true;
        }

        if (!is_array($type_value)) {
            return false;
        }

        foreach ($type_value as $type) {
            if ($this->is_placeholder_value($type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $property
     * @param array  $allowed_properties
     * @return bool
     */
    private function is_allowed_property($property, $allowed_properties) {
        if (in_array($property, $allowed_properties, true)) {
            return true;
        }

        // Schema.org allows extension-like properties. Warn only.
        if (strpos($property, '@') === 0) {
            return in_array($property, ['@context', '@type', '@id', '@graph'], true);
        }

        return false;
    }

    /**
     * @param string $property
     * @param mixed  $value
     * @param string $path
     * @return void
     */
    private function validate_property_value($property, $value, $path) {
        if ($this->is_placeholder_value($value)) {
            return;
        }

        switch ($property) {
            case '@context':
                if (!is_string($value) && !is_array($value)) {
                    $this->add_error($path, __('`@context` must be a string or object.', 'amk-schema-core'));
                }
                return;

            case '@id':
            case 'url':
            case 'contentUrl':
            case 'hasMap':
            case 'productGroupID':
                $this->validate_string_like($value, $path, $property);
                return;

            case 'sameAs':
            case 'image':
            case 'logo':
            case 'availableLanguage':
            case 'openingHours':
            case 'acceptedPaymentMethod':
            case 'currenciesAccepted':
            case 'areaServed':
            case 'variesBy':
            case 'color':
            case 'size':
            case 'material':
            case 'pattern':
                $this->validate_string_or_array($value, $path, $property);
                return;

            case 'address':
            case 'contactPoint':
            case 'geo':
            case 'publisher':
            case 'author':
            case 'brand':
            case 'offers':
            case 'hasVariant':
            case 'isVariantOf':
            case 'aggregateRating':
            case 'review':
            case 'additionalProperty':
            case 'potentialAction':
            case 'hasMerchantReturnPolicy':
            case 'hasShippingService':
            case 'shippingDetails':
            case 'shippingConditions':
            case 'shippingDestination':
            case 'shippingRate':
            case 'deliveryTime':
            case 'handlingTime':
            case 'transitTime':
            case 'seller':
            case 'provider':
            case 'isPartOf':
            case 'breadcrumb':
            case 'primaryImageOfPage':
            case 'mainEntity':
            case 'mainEntityOfPage':
            case 'item':
            case 'target':
            case 'object':
            case 'result':
            case 'reviewRating':
            case 'worksFor':
                $this->validate_schema_reference_or_node($value, $path, $property);
                return;

            case 'itemListElement':
                $this->validate_item_list_element($value, $path);
                return;

            case 'price':
            case 'ratingValue':
            case 'reviewCount':
            case 'ratingCount':
            case 'bestRating':
            case 'worstRating':
            case 'position':
            case 'latitude':
            case 'longitude':
            case 'elevation':
            case 'merchantReturnDays':
            case 'value':
            case 'minValue':
            case 'maxValue':
            case 'wordCount':
            case 'width':
            case 'height':
            case 'geoRadius':
            case 'restockingFee':
                $this->validate_number_like($value, $path, $property);
                return;

            default:
                $this->validate_nested_values($value, $path);
                return;
        }
    }

    /**
     * @param mixed  $value
     * @param string $path
     * @param string $property
     * @return void
     */
    private function validate_string_like($value, $path, $property) {
        if ($this->is_placeholder_value($value)) {
            return;
        }

        if (!is_string($value) && !is_numeric($value)) {
            $this->add_warning($path, '`' . $property . __('` should be a string.', 'amk-schema-core'));
        }
    }

    /**
     * @param mixed  $value
     * @param string $path
     * @param string $property
     * @return void
     */
    private function validate_string_or_array($value, $path, $property) {
        if ($this->is_placeholder_value($value)) {
            return;
        }

        if (is_string($value) || is_numeric($value)) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $index => $item) {
                if ($this->is_placeholder_value($item)) {
                    continue;
                }

                if (is_array($item)) {
                    $this->validate_node($item, $path . '[' . $index . ']', false);
                    continue;
                }

                if (!is_string($item) && !is_numeric($item)) {
                    $this->add_warning($path . '[' . $index . ']', '`' . $property . __('` must contain string values.', 'amk-schema-core'));
                }
            }

            return;
        }

        $this->add_warning($path, '`' . $property . __('` must be a string or array.', 'amk-schema-core'));
    }

    /**
     * @param mixed  $value
     * @param string $path
     * @param string $property
     * @return void
     */
    private function validate_number_like($value, $path, $property) {
        if ($this->is_placeholder_value($value)) {
            return;
        }

        if (is_int($value) || is_float($value)) {
            return;
        }

        if (is_string($value) && $value !== '' && is_numeric($value)) {
            return;
        }

        $this->add_warning($path, '`' . $property . __('` should be a number or numeric string.', 'amk-schema-core'));
    }

    /**
     * @param mixed  $value
     * @param string $path
     * @param string $property
     * @return void
     */
    private function validate_schema_reference_or_node($value, $path, $property) {
        if ($this->is_placeholder_value($value)) {
            return;
        }

        if (is_string($value) || is_numeric($value)) {
            return;
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

        if (!is_array($value)) {
            $this->add_warning($path, '`' . $property . __('` must be an object, array, reference string, or placeholder.', 'amk-schema-core'));
            return;
        }

        if ($this->is_assoc($value)) {
            $this->validate_node($value, $path, false);
            return;
        }

        foreach ($value as $index => $item) {
            if ($this->is_placeholder_value($item)) {
                continue;
            }

            if (is_string($item) || is_numeric($item)) {
                continue;
            }

            if (!is_array($item) && !is_object($item)) {
                $this->add_warning($path . '[' . $index . ']', '`' . $property . __('` must contain objects or references.', 'amk-schema-core'));
                continue;
            }

            $this->validate_node($item, $path . '[' . $index . ']', false);
        }
    }

    /**
     * @param mixed  $value
     * @param string $path
     * @return void
     */
    private function validate_item_list_element($value, $path) {
        if ($this->is_placeholder_value($value)) {
            return;
        }

        if (!is_array($value)) {
            $this->add_error($path, __('`itemListElement` must be an array.', 'amk-schema-core'));
            return;
        }

        if ($this->is_assoc($value)) {
            $this->validate_node($value, $path, false);
            return;
        }

        foreach ($value as $index => $item) {
            $this->validate_node($item, $path . '[' . $index . ']', false);
        }
    }

    /**
     * @param mixed  $value
     * @param string $path
     * @return void
     */
    private function validate_nested_values($value, $path) {
        if ($this->is_placeholder_value($value)) {
            return;
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

        if (!is_array($value)) {
            return;
        }

        if ($this->is_assoc($value)) {
            $this->validate_node($value, $path, false);
            return;
        }

        foreach ($value as $index => $item) {
            $this->validate_nested_values($item, $path . '[' . $index . ']');
        }
    }

    /**
     * @param string $type
     * @return string
     */
    private function normalize_type($type) {
        if (!is_string($type)) {
            return '';
        }

        $type = trim($type);

        if ($type === '') {
            return '';
        }

        if (strpos($type, 'https://schema.org/') === 0) {
            $type = substr($type, strlen('https://schema.org/'));
        }

        if (strpos($type, 'http://schema.org/') === 0) {
            $type = substr($type, strlen('http://schema.org/'));
        }

        if (strpos($type, 'schema:') === 0) {
            $type = substr($type, strlen('schema:'));
        }

        return trim($type);
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function is_placeholder_value($value) {
        if (is_string($value)) {
            return $this->has_placeholder($value);
        }

        return false;
    }

    /**
     * @param string $value
     * @return bool
     */
    private function is_placeholder_string($value) {
        if (!is_string($value)) {
            return false;
        }

        return (bool) preg_match('/^\s*\{\{\s*[^}]+\s*\}\}\s*$/', $value);
    }

    /**
     * @param string $value
     * @return bool
     */
    private function has_placeholder($value) {
        if (!is_string($value)) {
            return false;
        }

        return (bool) preg_match('/\{\{\s*[^}]+\s*\}\}/', $value);
    }

    /**
     * @param array $array
     * @return bool
     */
    private function is_assoc($array) {
        if (!is_array($array)) {
            return false;
        }

        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * @param string $path
     * @param string $message
     * @return void
     */
    private function add_error($path, $message) {
        $this->errors[] = [
            'path'    => $path,
            'message' => $message,
        ];
    }

    /**
     * @param string $path
     * @param string $message
     * @return void
     */
    private function add_warning($path, $message) {
        $this->warnings[] = [
            'path'    => $path,
            'message' => $message,
        ];
    }
}