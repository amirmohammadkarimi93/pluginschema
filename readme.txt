=== AMK Schema Core ===
Contributors: amirmohammadkarimi
Tags: schema, json-ld, woocommerce, seo, structured data
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
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

== Changelog ==

= 1.0.0 =

* Initial release.
