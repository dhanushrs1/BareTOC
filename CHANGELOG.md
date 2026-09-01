# Changelog

## 1.3.4

- Kept the TOC title inside `.baretoc-header` whether or not the shortcode toggle is enabled.
- Made the complete header the accessible open/close trigger, with one SVG switching between plus and minus.
- Added the three lightweight header flex declarations as default structural CSS.
- Matched server-rendered and reusable-template header markup.
- Added Schema.org `ItemList` JSON-LD for every generated TOC.

## 1.3.3

- Added a global, opt-in smooth open/close animation setting for shortcode-enabled TOC drawers.
- Added reduced-motion handling and a no-animation fallback for the drawer transition.
- Simplified the shortcode-reference presentation on the settings page.

## 1.3.2

- Added shortcode-only open and close controls with accessible plus and minus icons.
- Added `toggle="yes"` and optional `initial="open|closed"` shortcode attributes without adding a global toggle setting.
- Added a complete, copy-ready shortcode reference to Settings > BareTOC.
- Added matching toggle behavior for regular content and reusable page-builder templates.

## 1.3.1

- Added automatic support for `[baretoc]` inside reusable page-builder and single-post templates.
- Added a lightweight rendered-page fallback that loads only when the shortcode runs outside normal post content.
- Kept template TOCs hidden when fewer than the configured minimum headings are present.
- Added the optional `container` shortcode attribute for limiting template heading discovery.

## 1.3.0

- Added native WordPress dashboard update checks through the SiteFueler Update API.
- Added validation for release identity, HTTPS download hosts, signed download parameters, and canonical ZIP layout.
- Added six-hour network-wide metadata caching with automatic invalidation after upgrades.
- Added optional access-key, API endpoint, and stable/beta channel developer controls.

## 1.2.1

- Refined the optional minimal stylesheet with a neutral card surface, balanced spacing, and polished links.
- Added hierarchical numbering such as 1.1 and 1.1.1 to the optional numbered-list design.
- Improved indentation for nested bullet and no-marker lists.

## 1.2.0

- Added smart numbering cleanup for consistently styled TOC labels.
- Added a global enable/disable control and `clean_numbers` shortcode override.
- Preserved original headings and anchors while cleaning display labels only.
- Added the `baretoc_item_title` display-label filter.

## 1.1.0

- Added optional dependency-free smooth scrolling with an enable/disable setting.
- Limited the script to pages where a TOC is actually rendered.
- Added reduced-motion support and native navigation fallbacks.

## 1.0.0

- Added semantic `[baretoc]` shortcode output.
- Added automatic H1–H6 detection and nested hierarchy.
- Added safe generated IDs with duplicate collision handling.
- Added global settings and compact per-post controls.
- Added shortcode-only and automatic placement modes.
- Added numbered, bullet, and no-marker list modes.
- Added ignore classes and Rank Math detection.
- Added opt-in minimal CSS.
