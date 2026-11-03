# Loopsi Gutenberg Blocks

**Advanced WordPress query blocks for the Gutenberg editor** — build dynamic post loops with custom post types, taxonomies, meta queries, flexible layouts, and modern pagination patterns.

Loopsi gives content editors a powerful, inspector-driven Query block without writing PHP. Developers get a clean, production-minded codebase built on the modern Block API, server-side rendering, and Composer.

**Published on the WordPress.org plugin directory:** [wordpress.org/plugins/loopsi-blocks](https://wordpress.org/plugins/loopsi-blocks/)

<p align="center">
  <a href="https://wordpress.org/plugins/loopsi-blocks/"><strong>WordPress.org Plugin</strong></a> ·
  <a href="https://akramwp.com"><strong>Portfolio</strong></a> ·
  <a href="https://www.toptal.com/developers/resume/akram-ul-haq"><strong>Toptal</strong></a> ·
  <a href="https://www.linkedin.com/in/akram-ul-haq-bab08a43/"><strong>LinkedIn</strong></a> ·
  <a href="https://profiles.wordpress.org/kamig478/"><strong>WordPress.org Profile</strong></a> ·
  <a href="https://github.com/akramg478"><strong>GitHub</strong></a>
</p>

---

## Why this exists

WordPress’s built-in Query Loop is solid for simple cases. Real projects often need more:

- Loop **custom post types** (products, events, team, listings…)
- Filter by **taxonomies** and **custom field / meta** values
- Switch between **grid** and **list** layouts
- Choose how users browse results: **numbered pagination**, **Load More**, or **infinite scroll**
- Toggle card fields (title, thumbnail, excerpt, author, read more)

Loopsi Posts is built for that multipurpose use case — one block, many presentations.

---

## Features

### Query anything public
- Any **viewable custom post type**
- Order by date, title, modified, menu order, or random
- Sticky post modes: inherit, sticky only, or exclude sticky
- Optional keyword search scoped to the query

### Taxonomy filters
- Auto-detects public taxonomies for the selected post type
- Checkbox term selection in the block sidebar
- Works with categories, tags, and custom taxonomies

### Meta query builder
- Add multiple custom-field conditions from the inspector
- Control meta key, value, compare operator, and value type
- Ideal for ACF / metabox / custom meta–driven listings

### Layouts that adapt
- **Grid** or **list** layout
- **1–6 columns** for grids
- Card display toggles: title, featured image, excerpt, author, read more
- Customizable “Read more” label

### Pagination patterns
| Mode | Behavior |
|------|----------|
| **None** | Single page of results |
| **Pagination** | Classic numbered pages |
| **Load More** | AJAX append via button |
| **Infinite Scroll** | Intersection Observer–driven continuous load |

Frontend pagination uses a dedicated AJAX endpoint with nonce verification and sanitized attributes — not a fragile full-page reload hack.

### Performance & safety
- Optional **render caching** with configurable TTL
- Cache keys tracked and **auto-cleared** when posts are saved
- Attribute sanitization before query / AJAX use
- PHP 8+ typed architecture (`final` classes, PSR-4 autoload)

---

## Tech stack (for reviewers)

| Layer | Choices |
|-------|---------|
| Block API | `block.json` · API version 3 · dynamic `render.php` |
| Editor UI | `@wordpress/scripts`, InspectorControls, ServerSideRender |
| PHP | PHP 8.0+ · Composer PSR-4 (`Loopsi\`) |
| Query | Dedicated `QueryBuilder` → `WP_Query` |
| Frontend JS | Vanilla AJAX Load More / Infinite Scroll |
| Caching | Transients + invalidation on `save_post` |

This is intentional Gutenberg engineering: block registration, editor/sidebar UX, dynamic rendering, and progressive enhancement on the front end.

---

## Requirements

- WordPress **5.3+** (built and tested with modern WP / Gutenberg)
- PHP **8.0+**
- For development from source: **Composer**, **Node.js**, npm

---

## Installation

### From WordPress.org (recommended)

Install **[Loopsi - Blocks](https://wordpress.org/plugins/loopsi-blocks/)** from the official directory:

1. In wp-admin go to **Plugins → Add New**
2. Search for **Loopsi Blocks** (or install from [wordpress.org/plugins/loopsi-blocks](https://wordpress.org/plugins/loopsi-blocks/))
3. Activate **Loopsi - Blocks**
4. Insert the **Loopsi Posts** block in the editor

### From a release ZIP

1. Upload the plugin ZIP via **Plugins → Add New → Upload**
2. Activate **Loopsi - Blocks**
3. Insert the **Loopsi Posts** block in the editor

No Node or Composer required on production when installing from WordPress.org or a built release.

### Developers (from source)

```bash
# Clone into your plugins directory
cd wp-content/plugins
git clone https://github.com/akramg478/loopsi-gutenberg-blocks.git loopsi-blocks
cd loopsi-blocks

composer install --no-dev
npm install
npm run build
```

Then activate the plugin in WordPress.

> The WordPress plugin folder / text domain remain `loopsi-blocks`.  
> The GitHub repo name `loopsi-gutenberg-blocks` is for discoverability.

---

## Usage

1. Open a page or template in the **block editor**
2. Insert **Loopsi Posts** (`loopsi/posts-query`)
3. In the sidebar:
   - Pick the **post type**
   - Set posts per page, order, sticky behavior
   - Filter by **taxonomies** and **meta conditions**
   - Choose **layout**, **columns**, and card fields
   - Select **pagination**, **Load More**, or **infinite scroll**
4. Preview updates via server-side render in the editor
5. Publish — the front end uses the same query + display settings

---

## Project structure

```text
loopsi-blocks/
├── loopsi-blocks.php          # Plugin bootstrap
├── includes/
│   ├── Plugin.php             # Hooks, block registration, AJAX
│   └── Core/
│       ├── QueryBuilder.php       # Attributes → WP_Query
│       ├── BlockRenderer.php      # Dynamic render + AJAX pages
│       ├── PostCardRenderer.php   # Card markup
│       ├── AttributeSanitizer.php # Request/attribute hardening
│       └── CacheManager.php       # Transient render cache
├── src/
│   ├── index.js
│   └── blocks/posts-query/
│       ├── block.json
│       ├── edit.js            # Inspector UI
│       ├── render.php
│       └── *.scss
├── build/                     # Compiled block assets
├── assets/js/load-more.js     # Load More + infinite scroll
├── package.json
└── composer.json
```

---

## Development scripts

```bash
npm start          # Watch mode (wp-scripts)
npm run build      # Production build
npm run plugin-zip # Build + ZIP for distribution
```

Human-readable block source lives in `src/`. Generated assets live in `build/`.

---

## Architecture notes

- **Dynamic block** — markup is rendered in PHP so queries stay current and SEO-friendly
- **Separation of concerns** — query building, sanitization, card output, and caching are isolated classes
- **AJAX continuity** — Load More / infinite scroll reuses the same sanitized attribute set as the initial render
- **Editor fidelity** — `ServerSideRender` keeps the canvas close to the front-end output

---

## Roadmap ideas

- Additional block variations / presets
- Style variations and theme.json-friendly design tokens
- More card templates (overlay, horizontal media, minimal)
- REST-based pagination alternative alongside admin-ajax

---

## Author

**Akram Ul Haq** — WordPress engineer specializing in **Gutenberg blocks**, custom plugins, and production-grade PHP/JS for real client sites.

This repo is a sample of how I ship editor-native features: Block API v3, inspector UX, dynamic server render, taxonomy/meta querying, and AJAX pagination — the same patterns I use on client projects.

| | |
|--|--|
| **Plugin on WordPress.org** | [wordpress.org/plugins/loopsi-blocks](https://wordpress.org/plugins/loopsi-blocks/) — public directory listing |
| **Hire / availability** | [Toptal profile](https://www.toptal.com/developers/resume/akram-ul-haq) — pre-vetted WordPress developer |
| **Work & case studies** | [akramwp.com](https://akramwp.com) |
| **Connect** | [LinkedIn](https://www.linkedin.com/in/akram-ul-haq-bab08a43/) |
| **WordPress.org profile** | [profiles.wordpress.org/kamig478](https://profiles.wordpress.org/kamig478/) |
| **Code** | [github.com/akramg478](https://github.com/akramg478) |

Open to freelance and contract engagements: custom Gutenberg blocks, plugin architecture, WooCommerce / CPT-heavy builds, and performance-minded WordPress development.

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) / plugin headers.

---

<p align="center">
  <sub>Built by <a href="https://akramwp.com">Akram Ul Haq</a> — Gutenberg blocks for real WordPress projects.</sub>
</p>
