=== AMK Schema Core ===
Contributors: amirmohammadkarimi
Tags: schema, json-ld, woocommerce, seo, structured data
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced Schema.org JSON-LD engine for WordPress and WooCommerce.

== Description ==

AMK Schema Core generates dynamic Schema.org JSON-LD for WordPress and WooCommerce pages.

The plugin includes template-based schema generation, resolver variables, conditional cleanup rules, global organization settings, WooCommerce product data support, breadcrumbs, and JSON-LD preview tools.

Default schema templates include:

* Organization / OnlineStore
* WebSite
* WebPage
* Article
* Product
* CollectionPage
* BreadcrumbList

== Features ==

* Template-based JSON-LD schema builder.
* Dynamic placeholders such as title, description, URL, product price, SKU, GTIN, brand, and rating.
* WooCommerce simple and variable product support.
* ProductGroup output for variable products.
* Global organization, contact, address, social, return policy, and shipping settings.
* Multiple-country support for shipping destinations and merchant return policies.
* Worldwide shipping selection without generating an invalid Country value.
* Conditional rules for removing empty schema fields.
* REST-powered preview and template management.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/amk-schema-core`.
2. Activate the plugin through the WordPress Plugins screen.
3. Open AMK Schema Core from the WordPress admin menu.
4. Review global settings and default schema templates.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

No. General WordPress schema works without WooCommerce. Product schema features require WooCommerce.

= Does the plugin output multiple script tags? =

No. Frontend output is normalized into one JSON-LD `@graph` block.

= Can I create custom templates? =

Yes. Templates can define custom JSON-LD, bindings, conditions, priority, scope, and override behavior.

= Can shipping and return policies contain multiple countries? =

Yes. Shipping destinations and merchant return policies can use one or more valid two-letter country codes.

The worldwide option is handled as a policy mode and is not printed as a fake country named `Worldwide`.

== Changelog ==

= 1.0.3 =

* Fixed a fatal error caused by missing `normalize_schema_country_list()` in the data resolver.
* Added the missing shipping destination builder used for country-specific shipping policies.
* Added normalized support for multiple shipping and return-policy countries.
* Added backward-compatible migration for legacy single-country commerce settings.
* Added support for legacy comma-separated and pipe-separated country values.
* Prevented invalid `Worldwide` values from being printed as Schema.org Country names.
* Improved handling of worldwide and country-specific shipping modes.
* Improved handling of worldwide and country-specific merchant return-policy modes.
* Added explicit empty country-selector submission to allow saved countries to be cleared.
* Removed duplicate country-setting definitions and completed commerce defaults.
* Updated plugin version to 1.0.3.

= 1.0.0 =

* Initial release.
