=== BareTOC – Lightweight Table of Contents ===
Contributors: sitefueler
Tags: table of contents, toc, headings, anchors, seo
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, unstyled, and SEO-friendly table of contents. BareTOC handles structure while your theme handles design.

== Description ==

BareTOC generates semantic, accessible anchor navigation from headings in WordPress content. It is shortcode-first, preserves existing heading markup, and adds no frontend CSS or JavaScript by default.

Core features:

* Use `[baretoc]` wherever the table of contents should appear.
* Include any combination of H1 through H6; H2 through H4 are the defaults.
* Preserve existing heading IDs, classes, and other attributes.
* Generate unique IDs for headings that need them.
* Render the correct nested hierarchy even when heading levels are skipped.
* Choose numbered, bullet, or theme-controlled no-marker lists.
* Prevent duplicated numbering such as "2. 2. Installation" without altering headings.
* Set a title, title element, and minimum heading count.
* Use shortcode-only placement or automatic insertion.
* Disable BareTOC or override heading levels on an individual post.
* Exclude a heading with the `baretoc-ignore` or `no-toc` class.
* Optionally enable smooth scrolling that respects reduced-motion preferences.
* Let Rank Math recognize BareTOC automatically.
* Opt into a tiny structural stylesheet, or load no plugin CSS at all.

BareTOC makes no external requests, performs no frontend AJAX, and uses no icon or font libraries. Its small dependency-free script loads only when smooth scrolling is enabled and a TOC is rendered.

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

== Frequently Asked Questions ==

= Why is BareTOC unstyled? =

That is intentional. BareTOC emits stable classes and clean HTML so the active theme can control every visual choice. An optional minimal stylesheet is available in Settings > BareTOC.

= How do I exclude one heading? =

Add `baretoc-ignore` or `no-toc` to that heading's class attribute.

= Will BareTOC replace my existing heading IDs? =

No. Existing IDs are always preserved. Generated IDs are only added when an included heading has no ID, and collisions receive `-2`, `-3`, and later suffixes.

= Does it add schema markup? =

No special TOC schema is needed. BareTOC outputs semantic `<nav>` markup, a descriptive `aria-label`, and crawlable fragment links.

= Does the "None" list mode require CSS? =

BareTOC still emits a semantic unordered list with a `baretoc-list--none` class. Your theme can remove markers or create CSS-counter numbering. The optional minimal stylesheet removes markers automatically.

= Can smooth scrolling be disabled? =

Yes. It is disabled by default and can be enabled or disabled under Settings > BareTOC. When enabled, it applies only to BareTOC links and respects the visitor's reduced-motion preference.

= Why does BareTOC remove the number at the start of a TOC label? =

Smart numbering cleanup prevents the list marker and a number already written into the heading from appearing together. For example, the heading "2. Installation" is displayed as "Installation" inside an ordered list, where the list supplies the number. The original heading and its anchor are never changed. Disable the option under Settings > BareTOC or use `[baretoc clean_numbers="no"]` to retain the complete label.

== Changelog ==

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
