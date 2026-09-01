=== BareTOC – Lightweight Table of Contents ===
Contributors: sitefueler
Tags: table of contents, toc, headings, anchors, seo
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, unstyled, and SEO-friendly table of contents. BareTOC handles structure while your theme handles design.

== Description ==

BareTOC generates semantic, accessible anchor navigation from headings in WordPress content. It is shortcode-first, preserves existing heading markup, and adds no frontend CSS or JavaScript during regular post-content use by default.

Core features:

* Use `[baretoc]` wherever the table of contents should appear.
* Place the same shortcode once in a reusable page-builder or single-post template.
* Include any combination of H1 through H6; H2 through H4 are the defaults.
* Preserve existing heading IDs, classes, and other attributes.
* Generate unique IDs for headings that need them.
* Render the correct nested hierarchy even when heading levels are skipped.
* Choose numbered, bullet, or theme-controlled no-marker lists.
* Prevent duplicated numbering such as "2. 2. Installation" without altering headings.
* Set a title, title element, and minimum heading count.
* Opt into a shortcode-only full-header open and close trigger with one accessible plus/minus icon.
* Optionally animate opening and closing with a global reduced-motion-aware setting.
* Use shortcode-only placement or automatic insertion.
* Disable BareTOC or override heading levels on an individual post.
* Exclude a heading with the `baretoc-ignore` or `no-toc` class.
* Optionally enable smooth scrolling that respects reduced-motion preferences.
* Let Rank Math recognize BareTOC automatically.
* Describe every generated TOC with Schema.org ItemList JSON-LD.
* Get consistent title/control alignment with three tiny structural CSS rules, and optionally enable minimal appearance CSS.
* Receive future BareTOC releases through the native WordPress update dashboard.

BareTOC performs no frontend external requests or AJAX and uses no icon or font libraries. WordPress background and administrator update checks contact the SiteFueler Update API over HTTPS. These checks send the installed BareTOC, WordPress, and PHP versions and the selected release channel; they do not send the site's URL. Valid metadata is cached for six hours.

== Installation ==

1. Upload the `baretoc` folder to `/wp-content/plugins/` or install its ZIP through Plugins > Add New.
2. Activate BareTOC.
3. Add `[baretoc]` to a post or page.
4. Optionally adjust defaults under Settings > BareTOC.

== Shortcode ==

Use the global settings:

`[baretoc]`

Override settings for one TOC:

`[baretoc headings="h2,h3" title="On this page" list="bullets"]`

Supported attributes:

* `headings`: comma- or space-separated levels such as `h2,h3,h4`.
* `title`: visible TOC title; use an empty value to hide it.
* `list`: `numbered`, `bullets`, or `none`.
* `clean_numbers`: `yes` or `no`; overrides smart numbering cleanup for this TOC.
* `minimum` or `min`: minimum number of matching headings.
* `title_element`: `div`, `p`, `h2`, or `h3`.
* `container`: optional CSS selector limiting heading discovery when used in a page-builder template.
* `toggle`: `yes` or `no`; the control is absent unless explicitly enabled.
* `initial`: `open` or `closed`; applies only when the toggle is enabled.

== Frequently Asked Questions ==

= Why is BareTOC unstyled? =

That is intentional. BareTOC emits stable classes and clean HTML so the active theme can control every visual choice. Only the three flex declarations needed to align the title and optional control are included by default. An optional minimal appearance stylesheet is available in Settings > BareTOC.

= How do I exclude one heading? =

Add `baretoc-ignore` or `no-toc` to that heading's class attribute.

= Will BareTOC replace my existing heading IDs? =

No. Existing IDs are always preserved. Generated IDs are only added when an included heading has no ID, and collisions receive `-2`, `-3`, and later suffixes.

= Does it add schema markup? =

Yes. BareTOC combines semantic `<nav>` markup, a descriptive `aria-label`, crawlable fragment links, and a Schema.org `ItemList` JSON-LD block. This gives search engines a clear machine-readable representation of the page navigation, but does not guarantee a special search-result presentation.

= Does the "None" list mode require CSS? =

BareTOC still emits a semantic unordered list with a `baretoc-list--none` class. Your theme can remove markers or create CSS-counter numbering. The optional minimal stylesheet removes markers automatically.

= Can smooth scrolling be disabled? =

Yes. It is disabled by default and can be enabled or disabled under Settings > BareTOC. When enabled, it applies only to BareTOC links and respects the visitor's reduced-motion preference.

= Can I place the shortcode in a page-builder template? =

Yes. Add `[baretoc]` once to a reusable single-post or single-page template. BareTOC automatically scans the rendered page in this context and keeps the TOC hidden when the number of matching headings is below the configured minimum. If needed, limit scanning to the content wrapper with an attribute such as `[baretoc minimum="3" container=".entry-content"]`.

= How do I add an open and close control? =

Use `[baretoc toggle="yes"]` to add the control and start open. Use `[baretoc toggle="yes" initial="closed"]` to start closed. This is intentionally a per-shortcode feature with no global setting. Without `toggle="yes"`, the TOC remains open and no control or toggle script is added.

The separate Smooth open/close checkbox under Settings > BareTOC globally controls whether enabled drawers animate. It is disabled by default and always respects the visitor's reduced-motion preference.

= Why does BareTOC remove the number at the start of a TOC label? =

Smart numbering cleanup prevents the list marker and a number already written into the heading from appearing together. For example, the heading "2. Installation" is displayed as "Installation" inside an ordered list, where the list supplies the number. The original heading and its anchor are never changed. Disable the option under Settings > BareTOC or use `[baretoc clean_numbers="no"]` to retain the complete label.

= How are plugin updates delivered? =

BareTOC uses WordPress's native update system. During normal dashboard or background update checks, it requests signed release metadata from the SiteFueler Update API. Available releases appear on the Plugins and Updates screens and install through WordPress's standard updater. Sites upgrading from a version earlier than 1.3.0 must install version 1.3.0 manually once; later releases can then be installed from the dashboard.

== Changelog ==

= 1.3.4 =

* Kept the title inside the header container whether or not the shortcode toggle is enabled.
* Made the complete header the accessible toggle trigger and simplified the two icon wrappers to one SVG.
* Added the lightweight header flex alignment by default while keeping appearance CSS optional.
* Matched the server and reusable-template markup structure.
* Added Schema.org ItemList JSON-LD for generated TOCs.

= 1.3.3 =

* Added an optional global smooth open/close animation setting.
* Added reduced-motion handling and an immediate fallback.
* Simplified the settings-page shortcode reference design.

= 1.3.2 =

* Added shortcode-only open and close controls using accessible plus and minus icons.
* Added optional open and closed initial states.
* Added a complete shortcode reference to the BareTOC settings screen.
* Added matching toggle behavior in reusable page-builder templates.

= 1.3.1 =

* Added reusable page-builder and single-template shortcode support.
* Added an on-demand rendered-page heading fallback for template shortcodes.
* Kept template TOCs hidden below the configured minimum heading count.
* Added the optional `container` shortcode attribute.

= 1.3.0 =

* Added native WordPress dashboard updates through the SiteFueler Update API.
* Validated release identity, HTTPS hosts, signed download parameters, and ZIP layout before installation.
* Added six-hour metadata caching and cache invalidation after upgrades.

= 1.2.1 =

* Refined the optional minimal stylesheet with a neutral card surface, balanced spacing and polished links.
* Added hierarchical numbering such as 1.1 and 1.1.1 to the optional numbered-list design.
* Improved indentation for nested bullet and no-marker lists.

= 1.2.0 =

* Added smart numbering cleanup for consistently styled TOC labels.
* Added a global enable/disable control and `clean_numbers` shortcode override.
* Preserved original headings and anchors while cleaning display labels only.

= 1.1.0 =

* Added optional smooth scrolling with an enable/disable setting.
* Limited the script to pages where a TOC is actually rendered.
* Added reduced-motion support and native navigation fallbacks.

= 1.0.0 =

* Initial release.
* Added shortcode and automatic placement modes.
* Added nested heading parsing and collision-safe anchors.
* Added global settings and per-post controls.
* Added Rank Math detection and opt-in minimal CSS.
