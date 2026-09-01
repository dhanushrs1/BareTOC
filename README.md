# BareTOC

[![CI](https://github.com/dhanushrs1/BareTOC/actions/workflows/ci.yml/badge.svg)](https://github.com/dhanushrs1/BareTOC/actions/workflows/ci.yml)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL_v2%2B-blue.svg)](LICENSE)

BareTOC is a standalone SiteFueler WordPress plugin that creates a clean, semantic table of contents from post headings.

Its rule is simple: the plugin owns structure; the active theme owns design. The default frontend payload is no CSS, no JavaScript, no external requests, and no additional database query.

## Features

- `[baretoc]` shortcode with per-instance overrides
- selectable H1–H6 levels (H2–H4 by default)
- valid nested list hierarchy, including skipped heading levels
- generated collision-safe heading IDs
- preservation of every existing ID, class, and heading attribute
- numbered, bulleted, and marker-free list modes
- smart removal of duplicate heading numbers from TOC labels
- configurable title and minimum heading count
- shortcode-only, before-content, after-first-paragraph, and before-first-heading placement
- per-post disable switch and heading-level override
- `.baretoc-ignore` and `.no-toc` opt-out classes
- semantic `<nav>` output and native anchor navigation
- optional smooth scrolling that respects reduced-motion preferences
- automatic Rank Math detection
- optional lightweight neutral design with hierarchical numbering; disabled by default
- native WordPress dashboard updates through signed SiteFueler release packages
- no frontend JavaScript unless smooth scrolling is explicitly enabled

## Shortcode

```text
[baretoc]
[baretoc headings="h2,h3"]
[baretoc title="On this page"]
[baretoc headings="h2,h3,h4" list="numbered"]
[baretoc title="Contents" headings="h2,h3" list="none"]
[baretoc clean_numbers="no"]
```

Supported attributes are `headings`, `title`, `list`, `clean_numbers`, `minimum` (or `min`), and `title_element`.

## Installation

1. Download a release ZIP or clone this repository as `wp-content/plugins/baretoc`.
2. Activate **BareTOC – Lightweight Table of Contents** in WordPress.
3. Add `[baretoc]` to a post or page.
4. Configure global defaults under **Settings → BareTOC** when needed.

## Updates

BareTOC 1.3.0 and newer use WordPress's native `Update URI` integration. During normal background or administrator update checks, the plugin requests release metadata from `https://api.sitefueler.com/updater/index.php`. A newer release appears on WordPress's standard **Plugins** and **Updates** screens and installs through the normal plugin upgrader.

The update client:

- accepts metadata and packages over HTTPS only
- requires the signed package URL to use the configured API host
- validates the release slug, version, expiry, checksum, and signature fields
- rejects ZIPs whose canonical root is not `baretoc/baretoc.php`
- caches valid metadata network-wide for six hours
- sends only the installed BareTOC, WordPress, and PHP versions and the selected channel; it does not send the site URL

No browser-side or frontend asset makes an update request. Sites running a version earlier than 1.3.0 need to install 1.3.0 manually once; subsequent versions can be installed from the WordPress dashboard.

## Styling API

BareTOC adds no appearance CSS by default. Themes can target:

```css
.baretoc {}
.baretoc-title {}
.baretoc-list {}
.baretoc-item {}
.baretoc-link {}
.baretoc-sublist {}
.baretoc-level-2 {}
.baretoc-list--numbered {}
.baretoc-list--bullets {}
.baretoc-list--none {}
```

## Developer filters

- `baretoc_heading_levels`
- `baretoc_heading_id`
- `baretoc_heading_title`
- `baretoc_item_title`
- `baretoc_items`
- `baretoc_title`
- `baretoc_classes`
- `baretoc_shortcode_args`
- `baretoc_output`
- `baretoc_update_api_url`
- `baretoc_update_access_key`
- `baretoc_update_channel`

## Requirements

- WordPress 6.2 or newer
- PHP 7.4 or newer

## Development

```bash
composer install
composer check
```

The automated suite validates behavior, updater security, WordPress coding standards, PHP compatibility, and JavaScript syntax. Tagged versions matching `v*.*.*` produce a clean installable ZIP through GitHub Actions. Maintainers then publish that verified ZIP through the SiteFueler release manager by following [docs/RELEASING.md](docs/RELEASING.md).

Contributions are welcome through focused pull requests. Read [CONTRIBUTING.md](CONTRIBUTING.md) before starting, and report suspected vulnerabilities privately as described in [SECURITY.md](SECURITY.md).

## Ownership

BareTOC is an independent WordPress plugin by [SiteFueler](https://sitefueler.com/) with no third-party framework dependencies.

## License

BareTOC is free software licensed under [GPL-2.0-or-later](LICENSE).
