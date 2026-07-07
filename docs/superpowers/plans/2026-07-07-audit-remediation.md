# Tatami Audit Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close all 54 findings from AUDIT.md (verified in AUDIT-REVIEW.md) in the Tatami base theme, add the §9 AGENTS.md guardrails, and produce a backport list for the johnson-miller derivative.

**Architecture:** Eight dependency-ordered work packages on one branch (`audit-remediation`), one commit each, every commit leaving the theme working. `lib/` moves into a `Tatami\` namespace; Twig autoescape is enabled theme-wide; the duplicated listing loop is extracted into a partial wired to the existing pagination partial; `tailwind.css` adopts the johnson-miller-proven structure; AGENTS.md/README are made true.

**Tech Stack:** WordPress 6.x classic theme, Timber v2.5.1/Twig 3.27 (vendored), Tailwind CSS v4 (CSS-first), Vite 8, pnpm, PHP 8.0+.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-07-audit-remediation-design.md`. Finding details: `AUDIT.md` (untracked, repo root).
- PHP indent: 4 spaces. Twig/CSS/JS indent: 2 spaces. No tabs.
- Text domain: `tatami`. Theme version: `6.0.0`. `build/` stays gitignored.
- pnpm only (never npm/yarn). `pnpm format` must not be run on `views/base.twig` (it's in `.prettierignore` — format it by hand).
- No changelog comments (no "changed to…", "fix for…"). Comments explain why code is the way it is.
- Nothing is pushed to any remote. `AUDIT.md`/`AUDIT-REVIEW.md` stay untracked.
- **Autoescape fact (verified empirically against the vendored Twig):** with `autoescape: 'html'`, strings returned by methods and Twig functions are escaped, but WP functions that *echo* (`wp_head`, `wp_footer` via `{{ function(...) }}`, `{% do action(...) %}`) bypass escaping and keep working. Exactly three outputs need `| raw`: `post.content`, `post.excerpt`, `site.language_attributes`.
- Scratchpad for harness scripts: `/private/tmp/claude-501/-Volumes-Work-themes-tatami/53b83024-ed94-458f-9646-2b75d30e4fa0/scratchpad`
- Verification commands available: `php -l`, `pnpm build`, `pnpm lint`, `pnpm format`, `php <harness>.php`.

---

### Task 0: Branch setup

**Files:** none modified.

- [ ] **Step 1: Create the branch**

```bash
cd /Volumes/Work/themes/tatami && git checkout -b audit-remediation
```

Expected: `Switched to a new branch 'audit-remediation'`.

- [ ] **Step 2: Commit this plan**

```bash
git add docs/superpowers/plans/2026-07-07-audit-remediation.md
git commit -m "Docs: add audit remediation implementation plan

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 1: WP1 — Mechanical hygiene (PAT-6, PAT-8, DOC-4)

**Files:**
- Modify: `lib/Tatami.lib.php`, `lib/Theme.lib.php`, `lib/Vite.lib.php`, `archive.php`, `author.php`, `search.php`, `single.php` (reindent only)
- Modify: `404.php:2-10`, `index.php:2-14` (headers)
- Modify: `style.css`, `README.md`, `AGENTS.md` (versions)

**Interfaces:** none — purely mechanical; no behavior change.

- [ ] **Step 1: Reindent the 7 tab-indented PHP files to 4 spaces**

```bash
cd /Volumes/Work/themes/tatami
for f in lib/Tatami.lib.php lib/Theme.lib.php lib/Vite.lib.php archive.php author.php search.php single.php; do
  expand -t 4 "$f" > "$f.tmp" && mv "$f.tmp" "$f"
done
grep -rl $'\t' --include='*.php' . --exclude-dir=vendor --exclude-dir=node_modules || echo "NO TABS"
```

Expected: `NO TABS`.

- [ ] **Step 2: Replace the stale boilerplate headers**

In `404.php`, replace the whole docblock (lines 2–10) with:

```php
/**
 * The template for displaying 404 pages (Not Found)
 *
 * @package  WordPress
 * @subpackage  Tatami
 */
```

In `index.php`, replace the whole docblock (lines 2–14) with:

```php
/**
 * The main template file — the generic fallback when nothing more
 * specific in the template hierarchy matches a query.
 *
 * @package  WordPress
 * @subpackage  Tatami
 */
```

- [ ] **Step 3: Align versions**

`style.css` — replace the whole header comment with:

```css
/*
 * Theme Name: Tatami
 * Description: Base theme for ULM
 * Author: Umbrella Legal Marketing
 * Version: 6.0.0
*/
```

`README.md:15` — change `- WordPress 5.0+` to `- WordPress 6.x`.

`AGENTS.md` Stack table — change the Build row from `| Build | Vite 5 | Dev server with HMR, manifest-based production builds |` to `| Build | Vite 8 | Dev server with HMR, manifest-based production builds |` (grep for `Vite 5` to find it; fix any other `Vite 5` references found).

- [ ] **Step 4: Verify and commit**

```bash
for f in *.php lib/*.php; do php -l "$f"; done
git add -A ':!AUDIT.md' ':!AUDIT-REVIEW.md' && git commit -m "Fix mechanical hygiene: reindent PHP, refresh headers, align versions (PAT-6, PAT-8, DOC-4)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

Expected: every file `No syntax errors detected`; clean commit.

---

### Task 2: WP2 — `Tatami\` namespace (PAT-7, PAT-9, PAT-11)

**Files:**
- Rename: `lib/Tatami.lib.php` → `lib/Site.lib.php`; `lib/Theme.lib.php` → `lib/Assets.lib.php`
- Modify: `lib/Site.lib.php`, `lib/Assets.lib.php`, `lib/Vite.lib.php`, `functions.php`

**Interfaces:**
- Produces: classes `Tatami\Site` (extends `Timber\Site`), `Tatami\Assets`, `Tatami\Vite` (all-static). Later tasks modify these files under these names. Routers keep calling global `Timber::…` (Timber v2 registers a global `Timber` alias) and, until Task 4, `$context['site']->setup_featured_image($post, $context)` still exists unchanged.

- [ ] **Step 1: Rename the files with git**

```bash
git mv lib/Tatami.lib.php lib/Site.lib.php
git mv lib/Theme.lib.php lib/Assets.lib.php
```

- [ ] **Step 2: Namespace `lib/Site.lib.php`**

Change the top of the file from `<?php` + `use Timber\Site;` + `class TatamiTheme extends Site {` to:

```php
<?php

namespace Tatami;

use Timber\Site as TimberSite;
use Timber\Timber;

class Site extends TimberSite {
```

Replace the constructor's inline ACF closure (the `add_action('admin_enqueue_scripts', function () {...});` block) with:

```php
        add_action( 'admin_enqueue_scripts', array( $this, 'hide_acf_grouped_labels' ) );
```

and add this method after `register_taxonomies()`:

```php
    /**
     * Hide ACF group labels when the group is contained within a tab —
     * the tab already provides the heading.
     */
    public function hide_acf_grouped_labels(): void {
        if ( ! function_exists( 'acf_get_field_groups' ) ) {
            return;
        }

        wp_add_inline_style( 'acf-input', '
            .acf-fields > .acf-field-tab ~ .acf-field-group > .acf-label {
                display: none !important;
            }
        ' );
    }
```

In `theme_supports()`, delete the line `add_theme_support( 'menus' );` (`register_nav_menus()` implies it).

Unqualified `Timber::` calls inside the class (`Timber::get_menu`, `Timber::get_post`) now resolve via the `use Timber\Timber;` import — no other change needed.

- [ ] **Step 3: Namespace `lib/Assets.lib.php`**

Change `class Theme {` to:

```php
<?php

namespace Tatami;

class Assets {
```

(The `Vite::` calls inside resolve to `Tatami\Vite` — same namespace. The docblock `@throws Exception` stays for now; Task 3 removes the throw.)

- [ ] **Step 4: Namespace `lib/Vite.lib.php`**

After `<?php` add:

```php

namespace Tatami;
```

Change both `throw new Exception(` occurrences to `throw new \Exception(` and the `catch (Exception $e)` in `img()` to `catch (\Exception $e)`.

- [ ] **Step 5: Update `functions.php`**

Full new content:

```php
<?php
/**
 * Tatami Theme
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/Vite.lib.php';
require_once __DIR__ . '/lib/Assets.lib.php';
require_once __DIR__ . '/lib/Site.lib.php';

Timber\Timber::init();

// Sets the directories (inside your theme) to find .twig files.
Timber::$dirname = ['views'];

new Tatami\Site();
new Tatami\Assets();
```

- [ ] **Step 6: Verify and commit**

```bash
for f in *.php lib/*.php; do php -l "$f"; done
grep -rn "TatamiTheme\|new Theme()" --include='*.php' . --exclude-dir=vendor || echo "CLEAN"
git add -A ':!AUDIT.md' ':!AUDIT-REVIEW.md' && git commit -m "Move lib/ classes into Tatami namespace (PAT-7, PAT-9, PAT-11)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

Expected: no syntax errors; `CLEAN`.

---

### Task 3: WP3 — Security & runtime plumbing (SEC-1..5, SEC-7, SEC-8, COR-3, COR-4, COR-5, PAT-10, MISC-1, MISC-3, YAG-5, YAG-6)

**Files:**
- Modify: `lib/Site.lib.php`, `lib/Vite.lib.php`, `lib/Assets.lib.php`, `single.php`, `vite.config.js`
- Delete: `author.php`

**Interfaces:**
- Produces: `Tatami\Vite::ready(): bool` (true when hot or manifest loaded); `Vite::init(): ?string` no longer throws on missing manifest. Autoescape is ON from this task forward — Task 5 adds the `| raw` opt-ins (until then, listing pages were already fatal via COR-1, and singular content renders escaped; acceptable mid-branch).

- [ ] **Step 1: Rework the `Tatami\Site` constructor**

Replace the entire `__construct()` body with:

```php
    public function __construct() {
        add_action( 'after_setup_theme', array( $this, 'theme_supports' ) );
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'init', array( $this, 'register_taxonomies' ) );
        add_action( 'template_redirect', array( $this, 'disable_author_archives' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'hide_acf_grouped_labels' ) );
        add_action( 'admin_menu', array( $this, 'remove_comments_admin_menu' ) );

        add_filter( 'upload_mimes', array( $this, 'add_svg_mime_type' ) );
        add_filter( 'timber/context', array( $this, 'add_to_context' ) );
        add_filter( 'timber/twig', array( $this, 'add_to_twig' ) );
        add_filter( 'timber/twig/environment/options', array( $this, 'set_twig_environment_options' ) );

        // Timber substitutes the password form for protected posts' content.
        add_filter( 'timber/post/content/show_password_form_for_protected', '__return_true' );

        // Comments are disabled site-wide per house policy. A site that
        // genuinely needs them deletes these three filters and the
        // remove_comments_admin_menu hook above.
        add_filter( 'comments_open', '__return_false', 20 );
        add_filter( 'pings_open', '__return_false', 20 );
        add_filter( 'comments_array', '__return_empty_array', 20 );

        // XML-RPC pingbacks are a spam/DDoS vector no client site uses.
        add_filter( 'xmlrpc_methods', array( $this, 'disable_xmlrpc_pingbacks' ) );

        parent::__construct();
    }
```

- [ ] **Step 2: Add the new `Tatami\Site` methods**

Add after `hide_acf_grouped_labels()`:

```php
    /**
     * Twig autoescaping is the theme's escaping contract: {{ var }} escapes
     * for HTML; trusted HTML (post.content, post.excerpt) opts in with | raw.
     */
    public function set_twig_environment_options( $options ) {
        $options['autoescape'] = 'html';
        return $options;
    }

    /**
     * Author archives are unused on client sites but publicly reachable
     * (/?author=N resolves on any WP site) and enable username enumeration —
     * 404 them. A site that needs them removes this and uses Timber::get_user().
     */
    public function disable_author_archives(): void {
        if ( ! is_author() ) {
            return;
        }
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
    }

    public function disable_xmlrpc_pingbacks( $methods ) {
        unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
        return $methods;
    }

    public function remove_comments_admin_menu(): void {
        remove_menu_page( 'edit-comments.php' );
    }
```

- [ ] **Step 3: Gate SVG uploads and comment the options-page cost**

Replace `add_svg_mime_type()` with:

```php
    /**
     * Add SVG to allowed mime types — administrators only. SVG can carry
     * scripts (XSS vector); client sites should pair this with a sanitizer
     * plugin such as Safe SVG.
     *
     * @param array $mimes Mime types keyed by the file extension regex corresponding to those types.
     * @return array Modified mime types
     */
    public function add_svg_mime_type($mimes) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $mimes;
        }
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }
```

In `add_to_context()`, put this comment above the `get_fields` call:

```php
        if (function_exists('get_fields')) {
            // Loads every options-page field on every request (including 404s).
            // Cheap while options pages stay lean — bloat here has global cost.
            $context['options'] = get_fields('option');
        }
```

- [ ] **Step 4: Trim `theme_supports()`**

Replace the whole method with (drops `automatic-feed-links`, `comment-form`/`comment-list`, and the post-formats block; adds `script`/`style` so WP emits modern script tags):

```php
    public function theme_supports() {
        add_theme_support( 'title-tag' );
        add_theme_support( 'post-thumbnails' );

        add_theme_support(
            'html5',
            array(
                'gallery',
                'caption',
                'script',
                'style',
            )
        );

        register_nav_menus([
            'primary' => __('Primary Menu', 'tatami'),
        ]);
    }
```

- [ ] **Step 5: Delete `author.php` and the password branch in `single.php`**

```bash
git rm author.php
```

In `single.php`, replace the `if (post_password_required($post->ID)) { ... } else { ... }` block with just the render call (Timber now serves the password form via the filter):

```php
Timber::render(array(
    'single-' . $post->post_type . '.twig',
    'single-' . $post->slug . '.twig',
    'single.twig'
), $context);
```

- [ ] **Step 6: Rewrite `lib/Vite.lib.php`**

Full new content:

```php
<?php

namespace Tatami;

class Vite {

    /**
     * Flag to determine whether hot server is active.
     *
     * @var bool
     */
    private static bool $isHot = false;

    /**
     * The URI to the hot server.
     *
     * @var string
     */
    private static string $server;

    /**
     * The path where compiled assets will go.
     *
     * @var string
     */
    private static string $buildPath = 'build';

    /**
     * Manifest file contents.
     *
     * @var array
     */
    private static array $manifest = [];

    /**
     * Check for the presence of a hot file and load the manifest.
     *
     * @return string|null The Vite client URL when running hot, null otherwise.
     */
    public static function init(): string|null
    {
        // The hot file only means anything on a dev machine. A stale one —
        // crashed dev server, or one that reached a server — must never put
        // the site in hot mode, so hot detection is gated on environment.
        $isDevEnvironment = in_array( wp_get_environment_type(), [ 'local', 'development' ], true );
        static::$isHot    = $isDevEnvironment && file_exists( static::hotFilePath() );

        if (static::$isHot) {
            static::$server = file_get_contents(static::hotFilePath());
            return static::$server . '/@vite/client';
        }

        // Fail soft without a manifest: an unstyled page beats a fatal on
        // every front-end request.
        if (!file_exists($manifestPath = static::buildPath() . '/.vite/manifest.json')) {
            error_log('Tatami: Vite manifest not found — run `pnpm build`, or start `pnpm dev` in a local environment.');
            add_action('admin_notices', array(static::class, 'render_missing_manifest_notice'));
            return null;
        }

        // store our manifest contents.
        static::$manifest = json_decode(file_get_contents($manifestPath), true) ?: [];

        return null;
    }

    /**
     * Whether assets can be resolved (dev server running or manifest loaded).
     *
     * @return bool
     */
    public static function ready(): bool
    {
        return static::$isHot || static::$manifest !== [];
    }

    /**
     * Admin notice shown when no manifest was found.
     *
     * @return void
     */
    public static function render_missing_manifest_notice(): void
    {
        echo '<div class="notice notice-error"><p>Tatami: no Vite manifest found, so no theme assets are enqueued. Run <code>pnpm build</code>.</p></div>';
    }

    /**
     * Enqueue the Vite client module (dev server only).
     *
     * @return void
     */
    public static function enqueue_module(): void
    {
        // we only want to continue if we have a client.
        if (!$client = static::init()) {
            return;
        }

        // enqueue our client script
        wp_enqueue_script('vite-client', $client, [], null);

        // update html script type to module
        static::script_type_module('vite-client');
    }

    /**
     * Return URI path to an asset.
     *
     * @param $asset
     *
     * @return string
     * @throws \Exception
     */
    public static function asset($asset): string
    {
        if (static::$isHot) {
            return static::$server . '/' . ltrim($asset, '/');
        }

        if (!array_key_exists($asset, static::$manifest)) {
            throw new \Exception('Unknown Vite build asset: ' . $asset);
        }

        return implode('/', [ get_stylesheet_directory_uri(), static::$buildPath, static::$manifest[$asset]['file'] ]);
    }

    /**
     * Return URI paths to CSS files associated with a JS entry.
     *
     * @param $asset
     *
     * @return array
     */
    public static function css($asset): array
    {
        if (static::$isHot) {
            return [];
        }

        if (!array_key_exists($asset, static::$manifest) || !isset(static::$manifest[$asset]['css'])) {
            return [];
        }

        return array_map(function($cssFile) {
            return implode('/', [ get_stylesheet_directory_uri(), static::$buildPath, $cssFile ]);
        }, static::$manifest[$asset]['css']);
    }

    /**
     * Internal method to determine hotFilePath.
     *
     * @return string
     */
    private static function hotFilePath(): string
    {
        return implode('/', [static::buildPath(), 'hot']);
    }

    /**
     * Internal method to determine buildPath.
     *
     * @return string
     */
    private static function buildPath(): string
    {
        return implode('/', [get_stylesheet_directory(), static::$buildPath]);
    }

    /**
     * Update html script type to module.
     *
     * Mutates the tag WordPress built rather than replacing it, so attributes
     * added by core or plugins (CSP nonces, integrity) survive.
     *
     * @param string $scriptHandle
     * @return void
     */
    public static function script_type_module(string $scriptHandle): void
    {
        add_filter('script_loader_tag', function ($tag, $handle) use ($scriptHandle) {
            if ($scriptHandle !== $handle) {
                return $tag;
            }

            if (preg_match('/type=(["\'])[^"\']*\1/', $tag)) {
                return preg_replace('/type=(["\'])[^"\']*\1/', 'type="module"', $tag, 1);
            }

            return preg_replace('/<script /', '<script type="module" ', $tag, 1);
        }, 10, 2);
    }

}
```

(Note: `img()` is gone — YAG-5.)

- [ ] **Step 7: Guard enqueueing in `lib/Assets.lib.php`**

Replace `enqueue_styles_scripts()` with (drops the `@throws`, adds the ready guard, `media: 'all'`):

```php
    /**
     * Enqueue theme styles and scripts.
     *
     * Scripts are enqueued in <head> because ES modules are deferred by default,
     * so there is no render-blocking penalty.
     *
     * @return void
     */
    public function enqueue_styles_scripts(): void
    {
        // enqueue the Vite client when the dev server is running
        // (this also loads the manifest for production builds)
        Vite::enqueue_module();

        // no dev server and no build output — Vite::init() already logged it
        if (!Vite::ready()) {
            return;
        }

        // enqueue CSS bundled with the JS entry
        $cssFiles = Vite::css('src/js/main.js');
        foreach ($cssFiles as $index => $cssFile) {
            wp_enqueue_style('theme-style-' . $index, $cssFile, [], null, 'all');
        }

        // register and enqueue the main JS entry
        $filename = Vite::asset('src/js/main.js');
        wp_enqueue_script('theme-script', $filename, [], null, false);

        // update html script type to module
        Vite::script_type_module('theme-script');
    }
```

- [ ] **Step 8: Fix the hot-file port in `vite.config.js`**

Replace the `configureServer(server)` method body with:

```js
    configureServer(server) {
      // Write the hot file only once the server is actually listening, using
      // the resolved port — Vite silently auto-increments when the configured
      // port is taken, and a hot file recording the wrong port 404s every asset.
      server.httpServer?.once('listening', () => {
        const { https, host } = server.config.server;
        const protocol = https ? 'https' : 'http';
        const address = server.httpServer.address();
        const port =
          typeof address === 'object' && address !== null
            ? address.port
            : (server.config.server.port ?? 5173);
        const origin = `${protocol}://${host || 'localhost'}:${port}`;

        fs.mkdirSync(path.dirname(hotFilePath), { recursive: true });
        fs.writeFileSync(hotFilePath, origin);
      });

      const clean = () => {
        if (fs.existsSync(hotFilePath)) fs.unlinkSync(hotFilePath);
      };
      process.on('exit', clean);
      process.on('SIGINT', () => {
        clean();
        process.exit();
      });
      process.on('SIGTERM', () => {
        clean();
        process.exit();
      });
    },
```

- [ ] **Step 9: Verify and commit**

```bash
for f in *.php lib/*.php; do php -l "$f"; done
pnpm lint && pnpm build
grep -rn "post_password_required\|new Timber\\\\User\|function img(" --include='*.php' . --exclude-dir=vendor || echo "CLEAN"
git add -A ':!AUDIT.md' ':!AUDIT-REVIEW.md' && git commit -m "Harden security and asset plumbing (SEC-1..5,7,8, COR-3..5, PAT-10, MISC-1,3, YAG-5,6)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

Expected: no syntax errors, lint/build pass, `CLEAN`.

---

### Task 4: WP4 — Routers + `Tatami\Queries` scaffold (COR-2, A11Y-4, PAT-1, PAT-3, PAT-4, PAT-5, YAG-3, YAG-4, DOC-1)

**Files:**
- Create: `lib/Queries.lib.php`, `views/macros/image.twig`, `views/modules/example.twig`
- Modify: `functions.php`, `home.php`, `archive.php`, `search.php`, `front-page.php`, `page.php`, `single.php`, `lib/Site.lib.php`, `views/partials/page-header.twig`

**Interfaces:**
- Consumes: `Tatami\Site` (Task 2/3 shape).
- Produces: `Tatami\Queries::featured_image_with_fallback( \Timber\Post $post ): ?\Timber\Image` — routers assign it to `$context['featured_image']`; templates read `featured_image.src`, `featured_image.alt`, `featured_image.width`, `featured_image.height`. `setup_featured_image()` and the old `featured_image_src`/`featured_image_alt` keys are gone. Twig macro `acf_image(image_id, sizes, class, loading)` in `views/macros/image.twig`.

- [ ] **Step 1: Create `lib/Queries.lib.php`**

```php
<?php
/**
 * Reusable Timber queries for the Tatami theme.
 *
 * Keeps post-fetching logic in one place so routers (front-page.php,
 * page.php, single.php, …) stay thin and the Site class stays about setup.
 * One static method per logical query, named for intent, with parameters
 * for the variations callers need.
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

namespace Tatami;

use Timber\Image;
use Timber\Post;
use Timber\Timber;

class Queries {

    /**
     * Featured image with parent fallback.
     *
     * Pages deep in a section often have no thumbnail of their own — the
     * section parent's image is the intended hero. Returns a Timber image
     * (templates read featured_image.src / featured_image.alt) or null when
     * neither the post nor its parent has one.
     */
    public static function featured_image_with_fallback( Post $post ): ?Image {
        $image = $post->thumbnail();

        if ( ! $image && $post->post_parent ) {
            $parent = Timber::get_post( $post->post_parent );
            $image  = $parent ? $parent->thumbnail() : null;
        }

        return $image ?: null;
    }
}
```

- [ ] **Step 2: Register it in `functions.php`**

Add after the `Assets.lib.php` require:

```php
require_once __DIR__ . '/lib/Queries.lib.php';
```

- [ ] **Step 3: Remove `setup_featured_image()` from `lib/Site.lib.php`**

Delete the whole `setup_featured_image` method (docblock included).

- [ ] **Step 4: Rewrite `home.php`**

Full new content:

```php
<?php
/**
 * The posts-page (blog index) template.
 *
 * The posts page assigned under Settings → Reading routes here — never
 * through page.php — so page-{slug}.twig resolution has to happen here
 * for the theme's page-template convention to hold.
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$context = Timber::context();

$blog_page = get_option( 'page_for_posts' ) ? Timber::get_post( (int) get_option( 'page_for_posts' ) ) : null;

$templates = array( 'home.twig', 'index.twig' );
if ( $blog_page ) {
    array_unshift( $templates, 'page-' . $blog_page->post_name . '.twig' );
    // The Blog page itself — its title/ACF fields drive the header.
    $context['post'] = $blog_page;
}
$context['title'] = $blog_page ? $blog_page->title() : 'Blog';

Timber::render( $templates, $context );
```

- [ ] **Step 5: Slim the other routers**

`archive.php` — delete the line `$context['posts'] = Timber::get_posts();` and change the `is_category()` branch to:

```php
} elseif ( is_category() ) {
    $context['title'] = 'Category: ' . single_cat_title( '', false );
    array_unshift( $templates, 'archive-category-' . get_queried_object()->slug . '.twig' );
}
```

`search.php` — delete the `$post_type = isset($_GET['post_type']) …` block (3 lines + blank) and the `$context['posts'] = Timber::get_posts();` line.

`front-page.php` — full new content (Timber::context() populates `post` for singular views):

```php
<?php
/**
 * The front page template
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$context = Timber::context();

Timber::render( 'front-page.twig', $context );
```

`page.php` — full new content:

```php
<?php
/**
 * The template for displaying all pages.
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$post             = Timber::get_post();
$context          = Timber::context();
$context['post']  = $post;
$context['title'] = $post->post_title;

$context['featured_image'] = Tatami\Queries::featured_image_with_fallback( $post );

Timber::render( array( 'page-' . $post->post_name . '.twig', 'page.twig' ), $context );
```

`single.php` — full new content (slug template now outranks post-type template — PAT-3):

```php
<?php
/**
 * The Template for displaying all single posts
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$post             = Timber::get_post();
$context          = Timber::context();
$context['post']  = $post;
$context['title'] = get_the_title();

$context['featured_image'] = Tatami\Queries::featured_image_with_fallback( $post );

if ($post->post_type === 'post') {
    $context['tag'] = 'article';
}

Timber::render(array(
    'single-' . $post->slug . '.twig',
    'single-' . $post->post_type . '.twig',
    'single.twig'
), $context);
```

- [ ] **Step 6: Scaffold `views/macros/image.twig`**

```twig
{#
  House image macro — renders an <img> from an attachment ID (ACF image
  fields stored as ID, or any media-library ID).

  {% from 'macros/image.twig' import acf_image %}
  {{ acf_image(post.meta('hero_image'), '(min-width: 64rem) 50vw, 100vw', 'rounded-lg', 'eager') }}

  loading defaults to lazy — pass 'eager' for above-the-fold heroes.
  Empty media-library alt → alt="", the correct decorative-image behavior.
#}
{% macro acf_image(image_id, sizes, class, loading = 'lazy') %}
  {% set img = get_image(image_id) %}
  {% if img %}
    <img
      src="{{ img.src }}"
      srcset="{{ img.srcset }}"
      sizes="{{ sizes|default('100vw') }}"
      width="{{ img.width }}"
      height="{{ img.height }}"
      alt="{{ img.alt }}"
      class="{{ class }}"
      loading="{{ loading }}"
      decoding="async"
    />
  {% endif %}
{% endmacro %}
```

- [ ] **Step 7: Scaffold `views/modules/example.twig`**

```twig
{#
  Reference module — delete or replace on a real site.

  Modules are self-contained sections: they receive data from the caller
  and guard on it, so dropping one into any template is safe — it only
  renders where the router supplied data.

  {% include 'modules/example.twig' with { items: someData } %}
#}
{% if items %}
  <section class="fluid-grid">
    <div class="col-[content-start/content-end]">
      <ul>
        {% for item in items %}
          <li>{{ item.title }}</li>
        {% endfor %}
      </ul>
    </div>
  </section>
{% endif %}
```

- [ ] **Step 8: Demonstrate `featured_image` in `views/partials/page-header.twig`**

Full new content:

```twig
{% if title %}
  <header class="fluid-grid">
    {% if featured_image %}
      <img
        class="col-[full-start/full-end] max-h-96 w-full object-cover"
        src="{{ featured_image.src }}"
        alt="{{ featured_image.alt }}"
        width="{{ featured_image.width }}"
        height="{{ featured_image.height }}"
        loading="eager"
      />
    {% endif %}
    <div class="col-[content-start/content-end]">
      <h1>{{ title }}</h1>
    </div>
  </header>
{% endif %}
```

- [ ] **Step 9: Verify and commit**

```bash
for f in *.php lib/*.php; do php -l "$f"; done
grep -rn "setup_featured_image\|featured_image_src\|'slug'\]\|post_type'\] = \$post_type" --include='*.php' . --exclude-dir=vendor || echo "CLEAN"
pnpm build
git add -A ':!AUDIT.md' ':!AUDIT-REVIEW.md' && git commit -m "Slim routers, scaffold Tatami\\Queries + macros/modules (COR-2, DOC-1, PAT-1,3,4,5, YAG-3,4, A11Y-4)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: WP5 — Twig templates (COR-1, COR-8, YAG-1, YAG-2, PAT-2, A11Y-1,3,5,6,7,8, COR-6, COR-7, SEC-6, SEC-2 template half)

**Files:**
- Create: `views/partials/post-list.twig`
- Delete: `views/partials/menu.twig`
- Modify: `views/base.twig` (by hand — prettierignored), `views/partials/head.twig`, `views/index.twig`, `views/archive.twig`, `views/search.twig`, `views/partials/pagination.twig`, `views/header.twig`, `views/front-page.twig`, `views/page.twig`, `views/single.twig`

**Interfaces:**
- Consumes: `featured_image` context (Task 4), pagination partial (existing).
- Produces: `partials/post-list.twig` taking `posts` (from context) and optional `empty_message`. Trusted-HTML outputs are exactly: `post.content|raw`, `post.excerpt|raw`, `site.language_attributes|raw`.

- [ ] **Step 1: Create `views/partials/post-list.twig`**

```twig
{% if posts is not empty %}
  {% for post in posts %}
    <article class="mb-8">
      <h2>
        <a href="{{ post.link }}">{{ post.title }}</a>
      </h2>
      <p class="text-sm text-neutral-500">
        <time datetime="{{ post.date('c') }}">{{ post.date }}</time>
      </p>
      <div class="prose">
        {{ post.excerpt|raw }}
      </div>
    </article>
  {% endfor %}
  {% include 'partials/pagination.twig' %}
{% else %}
  <p>{{ empty_message|default('No posts found.') }}</p>
{% endif %}
```

- [ ] **Step 2: Reduce the three listing templates to wrappers**

`views/index.twig` and `views/archive.twig` — identical full content:

```twig
{% extends 'base.twig' %}

{% block content %}
  <div class="fluid-grid">
    <div class="col-[content-start/content-end]">
      {% include 'partials/post-list.twig' %}
    </div>
  </div>
{% endblock %}
```

`views/search.twig` — full content:

```twig
{% extends 'base.twig' %}

{% block content %}
  <div class="fluid-grid">
    <div class="col-[content-start/content-end]">
      {% include 'partials/post-list.twig' with {
        empty_message: 'No results found for "' ~ search_query ~ '".',
      } %}
    </div>
  </div>
{% endblock %}
```

- [ ] **Step 3: Fix `views/partials/pagination.twig`**

Apply these changes throughout the file:
1. All 4 `viewbox=` → `viewBox=`.
2. Both current-page `<span>`s (the `'current' in page.class` branches): add `aria-current="page"` and change classes to `relative px-3 py-2 text-sm font-semibold text-white rounded-md shadow-sm bg-neutral-900`.
3. Both disabled prev/next `<span>`s: add `aria-hidden="true"` and change `text-gray-300` to `text-neutral-300`.
4. Neutralize the remaining palette: `text-gray-600` → `text-neutral-600`, `text-gray-400` → `text-neutral-400`, `hover:text-blue-600 hover:bg-blue-50` → `hover:bg-neutral-100`, `border-gray-200` → `border-neutral-200`.
5. Both decorative `<svg>` elements in prev/next: add `aria-hidden="true"` `focusable="false"`.

- [ ] **Step 4: Rewrite `views/header.twig` as the semantic nav skeleton, delete `menu.twig`**

`views/header.twig` full content:

```twig
{# Site header — semantic skeleton; each site styles/extends it per design.
   Dropdown submenus: see the Navigation section in AGENTS.md (disclosure
   pattern — toggle aria-expanded, Escape to close, sr-only labels). #}
{% if menu and menu.items is not empty %}
  <header>
    <div class="fluid-grid py-4">
      <nav class="col-[content-start/content-end]" aria-label="Primary">
        <ul class="flex flex-col gap-4 lg:flex-row lg:gap-6">
          {% for item in menu.items %}
            <li>
              <a
                href="{{ item.link }}"
                {% if item.current %}aria-current="page"{% endif %}
                {% if item.target == '_blank' %}target="_blank" rel="noopener noreferrer"{% endif %}
              >
                {{ item.title }}
              </a>
            </li>
          {% endfor %}
        </ul>
      </nav>
    </div>
  </header>
{% endif %}
```

```bash
git rm views/partials/menu.twig
```

(If `pnpm format`'s Twig parser rejects the `{% if %}` tags inside the `<a>` attribute list, add `views/header.twig` to `.prettierignore` with a comment mirroring the base.twig entry, and format it by hand.)

- [ ] **Step 5: Rewrite `views/base.twig` (by hand, 2-space indent — do NOT run prettier on it)**

Full new content:

```twig
<!DOCTYPE html>
<html {{ site.language_attributes|raw }}>

  {% include 'partials/head.twig' %}

  {% set tag = tag|default('div') %}

  <body class="{{ body_class }}">
    {% do action('wp_body_open') %}

    <a class="skip-link screen-reader-text" href="#main">
      {{ __('Skip to content', 'tatami') }}
    </a>

    {% block header %}
      {% include 'header.twig' %}
    {% endblock %}

    <main id="main">

      <{{tag}} class="flex flex-col gap-y-32 max-w-full">
        {% block pageHeader %}
          {% include 'partials/page-header.twig' with {
            title: title,
          } %}
        {% endblock %}
        {% block content %}
          <div class="fluid-grid py-16">
            <div class="col-[content-start/content-end]">
              Sorry, no content
            </div>
          </div>
        {% endblock %}
        {% block modules %}{% endblock %}
      </{{tag}}>

    </main>

    {% block footer %}
      {% include 'footer.twig' %}
    {% endblock %}

    {{ function('wp_footer') }}

  </body>
</html>
```

(Changes: skip link `#main` + `__()`; `role="main"` dropped; `wp_body_open` added; `wp_footer` outside the footer block; `{% block head %}` wrapper removed so overriding templates can't drop `wp_head` — head customization happens in `partials/head.twig`; `language_attributes|raw` because autoescape would break its internal quotes.)

- [ ] **Step 6: Add `| raw` to trusted HTML and guard `post`**

In `views/front-page.twig`, `views/page.twig`, `views/single.twig`: change `{{ post.content }}` to `{{ post.content|raw }}`. In `views/front-page.twig` additionally wrap the content div in `{% if post %}…{% endif %}` (a fresh install with "latest posts" front page routes here with no `post`):

```twig
{% block content %}
  {% if post %}
    <div class="fluid-grid">
      <div class="col-[content-start/content-end]">
        <div class="prose">
          {{ post.content|raw }}
        </div>
      </div>
    </div>
  {% endif %}
{% endblock %}
```

- [ ] **Step 7: Write the Twig harness and run it**

Create `/private/tmp/claude-501/-Volumes-Work-themes-tatami/53b83024-ed94-458f-9646-2b75d30e4fa0/scratchpad/check_templates.php`:

```php
<?php
// Compile-check every theme template and render the high-risk partials with
// stub data, against the theme's vendored Twig with autoescape ON.
require '/Volumes/Work/themes/tatami/vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

$views = '/Volumes/Work/themes/tatami/views';
$twig  = new Environment(new FilesystemLoader($views), ['autoescape' => 'html']);

// Stubs for the Timber/WP-registered Twig functions the templates use.
$twig->addFunction(new TwigFunction('__', fn ($s, $d = null) => $s));
$twig->addFunction(new TwigFunction('function', function () { return null; }));
$twig->addFunction(new TwigFunction('action', function () { return null; }));
$twig->addFunction(new TwigFunction('get_image', fn ($id) => null));

$fail = [];

// 1. Every template must compile.
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), '.twig')) {
        $name = str_replace('\\', '/', substr($file->getPathname(), strlen($views) + 1));
        try {
            $twig->load($name);
        } catch (Throwable $e) {
            $fail[] = "COMPILE $name: " . $e->getMessage();
        }
    }
}

// 2. post-list + pagination render with stub data (COR-1 regression check).
$page = fn (array $p) => (object) ($p + ['link' => null, 'title' => '', 'class' => '']);

$pagination           = new stdClass();
$pagination->pages    = [
    $page(['link' => '/page/1/', 'title' => '1', 'class' => 'current']),
    $page(['link' => '/page/2/', 'title' => '2', 'class' => '']),
];
$pagination->prev     = null;
$pagination->next     = $page(['link' => '/page/2/', 'title' => 'Next']);
$pagination->current  = 1;
$pagination->total    = 2;

$post = new class {
    public $link  = '/hello/';
    public $title = 'Hello <script>alert(1)</script>';
    public function date($format = null)
    {
        return $format === 'c' ? '2026-07-07T00:00:00+00:00' : 'July 7, 2026';
    }
    public function excerpt()
    {
        return '<p>Excerpt <em>html</em></p>';
    }
};

$posts = new class ([$post]) extends ArrayObject {
    public $pag;
    public function pagination()
    {
        return $this->pag;
    }
};
$posts->pag = $pagination;

$html = $twig->render('partials/post-list.twig', ['posts' => $posts]);

$expect = function (bool $ok, string $msg) use (&$fail) {
    if (!$ok) {
        $fail[] = $msg;
    }
};
$expect(str_contains($html, '<time datetime="2026-07-07T00:00:00+00:00">'), 'post-list: missing <time>');
$expect(str_contains($html, 'aria-current="page"'), 'pagination: missing aria-current');
$expect(str_contains($html, 'Excerpt <em>html</em>'), 'post-list: excerpt was escaped — | raw missing');
$expect(str_contains($html, 'Hello &lt;script&gt;'), 'post-list: title NOT escaped — autoescape broken');
$expect(!str_contains($html, 'viewbox'), 'pagination: lowercase viewbox remains');

// 3. Empty state renders the message instead of fataling.
$emptyPosts = new class ([]) extends ArrayObject {
    public $pag;
    public function pagination()
    {
        return $this->pag;
    }
};
$empty = $twig->render('partials/post-list.twig', ['posts' => $emptyPosts, 'empty_message' => 'Nothing here.']);
$expect(str_contains($empty, 'Nothing here.'), 'post-list: empty_message not rendered');

if ($fail) {
    echo "FAIL\n" . implode("\n", $fail) . "\n";
    exit(1);
}
echo "ALL TEMPLATE CHECKS PASS\n";
```

Run: `php <scratchpad>/check_templates.php`
Expected: `ALL TEMPLATE CHECKS PASS`.

- [ ] **Step 8: Format, verify, commit**

```bash
pnpm format && pnpm build
grep -rn "post.preview\|posts.pagination }}\|viewbox\|#content\|role=\"main\"\|_e(" views/ || echo "CLEAN"
git status --short   # confirm base.twig only changed by hand, menu.twig deleted
git add -A ':!AUDIT.md' ':!AUDIT-REVIEW.md' && git commit -m "Extract post-list, fix base shell + nav + pagination a11y (COR-1,6,7,8, PAT-2, YAG-1,2, A11Y-1,3,5,6,7,8, SEC-2,6)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

Expected: `CLEAN` (the `_e(` grep must find nothing in views/), build passes.

---

### Task 6: WP6 — CSS restructure (PAT-12, PAT-13, A11Y-2, YAG-7, MISC-2)

**Files:**
- Modify: `src/css/tailwind.css` (full rewrite)

**Interfaces:**
- Consumes: `.skip-link screen-reader-text` classes on the skip link (Task 5).
- Produces: `--text-*` tokens in `@theme`; `.screen-reader-text`/`.skip-link` a11y helpers; base heading styles.

- [ ] **Step 1: Rewrite `src/css/tailwind.css`**

Full new content:

```css
@import 'tailwindcss';

/* Configure content sources for Twig templates */
@source "../../views/**/*.twig";

/* Load typography plugin */
@plugin "@tailwindcss/typography";

/* ==========================================================================
   Theme
   Design tokens live in @theme so utilities regenerate from them and the
   values stay visible to theme()/token introspection. Derivative sites add
   brand colors and fonts here (record intended AA color pairings as
   comments next to the tokens).
   ========================================================================== */

@theme {
  /* ── Type scale (fluid) ──
     clamp() spans small→large viewports; text-* utilities pick these up
     automatically. Line-heights tighten as sizes grow. */
  --text-xs: clamp(0.75rem, 0.6rem + 0.8vw, 1rem);
  --text-xs--line-height: 1.4;
  --text-sm: clamp(0.875rem, 0.7rem + 0.9vw, 1.125rem);
  --text-sm--line-height: 1.45;
  --text-base: clamp(1rem, 0.8rem + 1vw, 1.25rem);
  --text-base--line-height: 1.6;
  --text-lg: clamp(1.125rem, 0.9rem + 1.2vw, 1.5rem);
  --text-lg--line-height: 1.55;
  --text-xl: clamp(1.25rem, 1rem + 1.4vw, 1.75rem);
  --text-xl--line-height: 1.4;
  --text-2xl: clamp(1.5rem, 1.2rem + 1.6vw, 2rem);
  --text-2xl--line-height: 1.3;
  --text-3xl: clamp(1.875rem, 1.5rem + 1.8vw, 2.5rem);
  --text-3xl--line-height: 1.2;
  --text-4xl: clamp(2.25rem, 1.8rem + 2vw, 3rem);
  --text-4xl--line-height: 1.15;
  --text-5xl: clamp(3rem, 2.4rem + 2.5vw, 4rem);
  --text-5xl--line-height: 1.1;
  --text-6xl: clamp(3.75rem, 3rem + 3vw, 5rem);
  --text-6xl--line-height: 1;
}

/* ==========================================================================
   Base
   ========================================================================== */

@layer base {
  /* Preflight resets headings to inherit; apply the fluid scale so an
     unclassed <h1> reads as a heading on every page. */
  h1 {
    font-size: var(--text-4xl);
    line-height: var(--text-4xl--line-height);
    font-weight: 700;
  }

  h2 {
    font-size: var(--text-3xl);
    line-height: var(--text-3xl--line-height);
    font-weight: 700;
  }

  h3 {
    font-size: var(--text-2xl);
    line-height: var(--text-2xl--line-height);
    font-weight: 600;
  }

  h4 {
    font-size: var(--text-xl);
    line-height: var(--text-xl--line-height);
    font-weight: 600;
  }

  h5 {
    font-size: var(--text-lg);
    line-height: var(--text-lg--line-height);
    font-weight: 600;
  }

  h6 {
    font-size: var(--text-base);
    line-height: var(--text-base--line-height);
    font-weight: 600;
  }

  /* Non-essential motion stops for users who ask for reduced motion. */
  @media (prefers-reduced-motion: reduce) {
    *,
    ::before,
    ::after {
      animation-duration: 0.01ms !important;
      animation-iteration-count: 1 !important;
      transition-duration: 0.01ms !important;
      scroll-behavior: auto !important;
    }
  }
}

/* ==========================================================================
   Layout
   ========================================================================== */

.fluid-grid {
  --col-gap: clamp(1rem, 3vw, 4rem);
  --content-max-width: var(--breakpoint-lg);
  --padding-left: clamp(
    calc(env(safe-area-inset-left, 0rem) + 1rem),
    2vw,
    calc(env(safe-area-inset-left, 0rem) + 2rem)
  );
  --padding-right: clamp(
    calc(env(safe-area-inset-right, 0rem) + 1rem),
    2vw,
    calc(env(safe-area-inset-right, 0rem) + 2rem)
  );
  --col-width: calc(
    (
        min(
            calc(
              100% - var(--padding-left) - var(--padding-right) - 2 *
                var(--col-gap)
            ),
            var(--content-max-width)
          ) -
          11 * var(--col-gap)
      ) /
      12
  );
  --side-width: minmax(0, 1fr);
  column-gap: var(--col-gap);
  display: grid;
  grid-template-columns:
    [full-start] var(--side-width)
    [content-start col-1] var(--col-width)
    [col-2] var(--col-width)
    [col-3] var(--col-width)
    [col-4] var(--col-width)
    [col-5] var(--col-width)
    [col-6] var(--col-width)
    [col-7] var(--col-width)
    [col-8] var(--col-width)
    [col-9] var(--col-width)
    [col-10] var(--col-width)
    [col-11] var(--col-width)
    [col-12] var(--col-width) [content-end]
    var(--side-width) [full-end];
  width: 100%;
}

/* ==========================================================================
   Accessibility helpers
   .screen-reader-text hides content visually while leaving it for assistive
   tech (it's also the class WP core and plugins emit for SR-only text); on
   :focus it becomes visible, which is what turns the skip link in base.twig
   into a working keyboard bypass.
   ========================================================================== */

.screen-reader-text {
  border: 0;
  clip-path: inset(50%);
  height: 1px;
  width: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  position: absolute;
  word-wrap: normal !important;
}

.screen-reader-text:focus {
  clip-path: none;
  height: auto;
  width: auto;
  top: 0.5rem;
  left: 0.5rem;
  z-index: 100;
  display: block;
  padding: 0.75rem 1.25rem;
  border-radius: 0.375rem;
  background: #171717;
  color: #fff;
  font-size: 0.875rem;
  font-weight: 700;
  text-decoration: none;
  box-shadow: 0 0 0 2px #fff;
}

/* ==========================================================================
   Components
   Prefer utilities in Twig templates; this section is only for markup the
   theme doesn't author (WYSIWYG/the_content() output, plugin overrides)
   and design-system primitives.
   ========================================================================== */
```

(Removed: `--typography-body-max-width` no-op, `body { overflow-x: hidden }`, the `html { @apply … }` double scaling, and the `:root` type-scale block that shadowed `@theme`.)

- [ ] **Step 2: Verify and commit**

```bash
pnpm build && pnpm format
grep -n "typography-body-max-width\|overflow-x\|@apply text-base" src/css/tailwind.css || echo "CLEAN"
git add -A ':!AUDIT.md' ':!AUDIT-REVIEW.md' && git commit -m "Restructure tailwind.css: tokens in @theme, heading base styles, a11y helpers (PAT-12,13, A11Y-2, YAG-7, MISC-2)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

Expected: build passes; `CLEAN`.

---

### Task 7: WP7 — i18n (I18N-1, MISC-4)

**Files:**
- Modify: `style.css`, `lib/Site.lib.php`, `404.php`, `archive.php`, `home.php`, `index.php`, `search.php`, `views/partials/post-list.twig`, `views/search.twig`, `views/404.twig`, `views/partials/pagination.twig`, `views/header.twig`

**Interfaces:**
- Consumes: templates and routers as of Task 6.
- Produces: every user-facing string wrapped with the `tatami` domain. (`base.twig`'s skip link already uses `__('Skip to content', 'tatami')` from Task 5.)

- [ ] **Step 1: Declare and load the text domain**

`style.css` — add to the header comment, after the Version line:

```css
 * Text Domain: tatami
```

`lib/Site.lib.php` — first line inside `theme_supports()`:

```php
        load_theme_textdomain( 'tatami', get_template_directory() . '/languages' );
```

- [ ] **Step 2: Wrap the router strings**

- `404.php`: `$context['title'] = __( 'Oops! Page not found.', 'tatami' );`
- `index.php`: `$context['title'] = __( 'Blog', 'tatami' );`
- `home.php`: `$context['title'] = $blog_page ? $blog_page->title() : __( 'Blog', 'tatami' );`
- `search.php`: `$context['title'] = sprintf( /* translators: %s: search query */ __( 'Search results for %s', 'tatami' ), get_search_query() );`
- `archive.php` title block becomes:

```php
$context['title'] = __( 'Archive', 'tatami' );
if ( is_day() ) {
    /* translators: %s: date */
    $context['title'] = sprintf( __( 'Archive: %s', 'tatami' ), get_the_date( 'D M Y' ) );
} elseif ( is_month() ) {
    /* translators: %s: month */
    $context['title'] = sprintf( __( 'Archive: %s', 'tatami' ), get_the_date( 'M Y' ) );
} elseif ( is_year() ) {
    /* translators: %s: year */
    $context['title'] = sprintf( __( 'Archive: %s', 'tatami' ), get_the_date( 'Y' ) );
} elseif ( is_tag() ) {
    $context['title'] = single_tag_title( '', false );
} elseif ( is_category() ) {
    /* translators: %s: category name */
    $context['title'] = sprintf( __( 'Category: %s', 'tatami' ), single_cat_title( '', false ) );
    array_unshift( $templates, 'archive-category-' . get_queried_object()->slug . '.twig' );
} elseif ( is_post_type_archive() ) {
    $context['title'] = post_type_archive_title( '', false );
    array_unshift( $templates, 'archive-' . get_post_type() . '.twig' );
}
```

- [ ] **Step 3: Wrap the template strings**

- `views/partials/post-list.twig`: `{{ empty_message|default(__('No posts found.', 'tatami')) }}`
- `views/search.twig` include becomes:

```twig
      {% include 'partials/post-list.twig' with {
        empty_message: __('No results found for “%s”.', 'tatami')|format(search_query),
      } %}
```

- `views/404.twig`: wrap both strings — `{{ __("Sorry, we couldn't find what you're looking for.", 'tatami') }}` and `{{ __('Return to Home', 'tatami') }}`.
- `views/header.twig`: `aria-label="{{ __('Primary', 'tatami') }}"`.
- `views/partials/pagination.twig`: `aria-label="{{ __('Pagination Navigation', 'tatami') }}"`; all four `Previous`/`Next` span texts → `{{ __('Previous', 'tatami') }}` / `{{ __('Next', 'tatami') }}`; replace the mobile page-info inner markup (the `Page`/`of` spans) with:

```twig
        <span class="font-medium">
          {{ __('Page %1$s of %2$s', 'tatami')|format(posts.pagination.current, posts.pagination.total) }}
        </span>
```

- [ ] **Step 4: Verify and commit**

```bash
for f in *.php lib/*.php; do php -l "$f"; done
php /private/tmp/claude-501/-Volumes-Work-themes-tatami/53b83024-ed94-458f-9646-2b75d30e4fa0/scratchpad/check_templates.php
pnpm format && pnpm build
grep -rn "'Blog'\|'Archive'\|Oops" --include='*.php' . --exclude-dir=vendor | grep -v "__(" || echo "CLEAN"
git add -A ':!AUDIT.md' ':!AUDIT-REVIEW.md' && git commit -m "Add i18n foundation: text domain, gettext-wrap all user-facing strings (I18N-1, MISC-4)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

Expected: harness passes, `CLEAN`.

---

### Task 8: WP8 — Documentation + deliverables + final sweep (DOC-2, DOC-3, DOC-5, §9 guardrails)

**Files:**
- Modify: `AGENTS.md`, `README.md`
- Create: `BACKPORT-johnson-miller.md` (untracked deliverable — do NOT commit; it belongs to the other project)

**Interfaces:** none — docs must describe the repo as it now is.

- [ ] **Step 1: Update AGENTS.md architecture + naming for the namespace**

Replace the Architecture tree's lib lines with:

```
functions.php          → Bootstraps everything (autoload, init Timber, instantiate classes)
lib/Site.lib.php       → Tatami\Site class (extends Timber\Site) — CPTs, taxonomies, context, hooks, hardening
lib/Queries.lib.php    → Tatami\Queries class — reusable Timber queries (featured images, services, etc.)
lib/Assets.lib.php     → Asset enqueueing via Vite integration
lib/Vite.lib.php       → Tatami\Vite — Vite ↔ WordPress bridge (dev server detection, manifest reading)
```

Then sweep the whole file: `TatamiTheme` → `Tatami\Site`, `TatamiQueries` → `Tatami\Queries`, `lib/Tatami.lib.php` → `lib/Site.lib.php`, `lib/Theme.lib.php` → `lib/Assets.lib.php`. Update the "Add a reusable query" code fence's class declaration comment to `// lib/Queries.lib.php` with `namespace Tatami;` context, and note "All `lib/` classes live in the `Tatami\` namespace" in the PHP rules section.

- [ ] **Step 2: Rewrite the AGENTS.md "Before committing" and Security sections**

"Before committing" item 3–4 become:

```markdown
3. Never commit `node_modules/`, `vendor/`, or anything under `build/`
4. `build/` is never committed — Tatami is a base theme; sites build assets at deploy time (`pnpm build`). A missing build fails soft: the site renders unstyled and logs the error. `acf-json/` should be committed.
```

Security section becomes:

```markdown
## Security

- Twig autoescaping is ON (`autoescape: 'html'`, set in `Tatami\Site::set_twig_environment_options()`): `{{ variable }}` escapes for HTML. Use `| raw` only for trusted HTML — `post.content`, `post.excerpt` (WYSIWYG output), and `site.language_attributes`. WordPress functions that echo (`function('wp_footer')`, `{% do action(...) %}`) bypass escaping; their output is WP's responsibility.
- ACF fields that accept HTML should use `| raw` — plain text fields must not.
- SVG uploads are allowed for administrators only (`manage_options` gate in `Tatami\Site::add_svg_mime_type()`). Pair with a sanitizer plugin (e.g. Safe SVG) on client sites.
- XML-RPC pingbacks are disabled in the base (`Tatami\Site::disable_xmlrpc_pingbacks()`).
- Comments are disabled site-wide in the base; a site that genuinely needs them removes the three comment filters and the admin-menu removal in `Tatami\Site::__construct()`.
- Author archives return 404 (`Tatami\Site::disable_author_archives()`) — unused on client sites and an enumeration vector. A site that needs them removes that hook and uses `Timber::get_user()`.
- Password-protected content renders Timber's password form (filter enabled in `Tatami\Site::__construct()`).
```

- [ ] **Step 3: Add the new AGENTS.md sections**

After the "Add a page template" how-to, insert:

```markdown
### Front page & posts page (house routing pattern)

The standard setup is a static "Home" page + a "Blog" posts page assigned under Settings → Reading.

- The front page renders through `front-page.php` → `front-page.twig` (WP hierarchy name).
- **The posts page never routes through `page.php`** — WordPress serves it via `home.php`. `home.php` participates in the page-template convention: it resolves `page-{slug}.twig` from the assigned Blog page's slug (so a site calling it "News" gets `page-news.twig`), sets `post` to the Blog page (its title/ACF fields drive the header), and falls back to `home.twig` → `index.twig`.
- Naming rule: the front page uses `front-page.twig`; **every other admin-created page — including the posts page — uses `page-{slug}.twig`**.

### Featured images (house tool)

Routers for singular views assign `$context['featured_image'] = Tatami\Queries::featured_image_with_fallback($post);` — a Timber image object with parent fallback (a page without a thumbnail inherits its parent's). Templates read `featured_image.src`, `featured_image.alt`, `featured_image.width`, `featured_image.height`; `partials/page-header.twig` demonstrates consumption as an optional hero. Derivative heroes build on this key. (Older derivatives consume `featured_image_src`/`featured_image_alt` — a breaking difference; don't retrofit them.)
```

In the existing Navigation section, append:

```markdown
The base ships a semantic skeleton in `header.twig` (`<nav aria-label="Primary">`, `menu.items` loop, `aria-current="page"` on the current item, `rel="noopener noreferrer"` on `_blank` targets). Sites needing dropdown submenus use the disclosure pattern: a real `<button>` with `aria-expanded` (toggled in JS) + `aria-controls`, an `sr-only`/visible label (not `title=`), Escape to close, `aria-hidden="true" focusable="false"` on decorative SVGs. Do not use `aria-haspopup` for plain disclosure submenus.
```

- [ ] **Step 4: Add the §9 guardrails to AGENTS.md**

Append to the "Accessibility requirements" section:

```markdown
- **Contrast is a token-time decision:** when defining `@theme` brand colors, every foreground/background pairing the design will use must meet WCAG AA (4.5:1 text, 3:1 large text/UI components). Record the intended pairings as comments next to the tokens. Never introduce a text-on-brand combination without checking it.
- **Focus visibility:** never remove focus outlines without a replacement; every interactive element needs a visible `:focus-visible` state with ≥3:1 contrast against its surroundings.
- **Motion:** wrap all non-essential animation in `motion-safe:` (Tailwind) or `@media (prefers-reduced-motion: no-preference)`. No autoplaying movement > 5s without a pause control. (The base ships a global reduced-motion reset in `tailwind.css`.)
- **State is programmatic, not just visual:** any UI state shown by color/style (current nav item, selected tab, open accordion, current page) must also be expressed in ARIA (`aria-current`, `aria-expanded`, `aria-selected`).
- **Images:** every `<img>` gets `alt` — from the media-library alt field for content images, `alt=""` for decorative; inline decorative SVGs get `aria-hidden="true" focusable="false"`.
- **Forms:** every control gets a programmatic `<label>` (not placeholder-as-label); errors are announced (`aria-describedby` + `role="alert"` or a live region).
- **Touch targets:** interactive targets ≥ 24×24 CSS px (44×44 preferred for primary mobile controls).
- **Language:** all user-facing strings go through `__('…', 'tatami')` in PHP and `{{ __('…', 'tatami') }}` in Twig (return-based — never `_e()` in Twig); no concatenated sentence-building in templates (use `|format`).
```

Then append two new top-level sections before "Extending for a new site":

```markdown
## Definition of done (template work)

Before calling any template work complete, load and eyeball — with `WP_DEBUG` on: the front page, the blog home *with more than one page of posts* (pagination must render), a category archive, a search with results and with none, and a 404. Run one keyboard-only pass: skip link, full nav including any submenus, focus visible throughout.

## Doc integrity

AGENTS.md must describe the repo as it is. If a change makes a statement in AGENTS.md false (files, behavior, versions, security posture), updating AGENTS.md is part of the change.
```

And append to "Extending for a new site":

```markdown
9. **Backport rule:** when site work reveals a fix that isn't site-specific (a11y helpers, Timber API corrections, structural CSS, security gating), flag it for backport to the base theme before the project wraps — keep a `BACKPORT.md` list in the site repo.
```

- [ ] **Step 5: Update README.md**

- Line 73: change to `` The `lib/Vite.lib.php` class (`Tatami\Vite`) provides `asset()`, `css()`, and `enqueue_module()` methods for resolving Vite-built assets in WordPress. ``
- Core PHP Files list: `lib/Tatami.lib.php` → `lib/Site.lib.php - Theme functionality, Timber context, hardening (Tatami\Site)`; `lib/Theme.lib.php` → `lib/Assets.lib.php - Asset enqueueing (Tatami\Assets)`; add `lib/Queries.lib.php - Reusable Timber queries (Tatami\Queries)`.
- Delete the line `- views/partials/menu.twig - Menu component with dropdowns`.
- Customization section: update the three `lib/Tatami.lib.php` references to `lib/Site.lib.php`.

- [ ] **Step 6: Create `BACKPORT-johnson-miller.md` (do not commit)**

```markdown
# johnson-miller — fixes needed (from Tatami base audit 2026-07-07)

Apply in /Volumes/Work/local_sites/johnson-miller/app/public/wp-content/themes/tatami:

1. **CRASH — `views/search.twig:16`**: `{{ posts.pagination }}` fatals whenever search results render (Timber\Pagination has no __toString). Replace the echo with the site's pagination markup or remove it.
2. **CRASH — `author.php:14`**: `new Timber\User(...)` is a fatal in Timber v2 (protected constructor); reachable at `/?author=1` on the live site. Either 404 author archives (base's new `disable_author_archives()` pattern) or use `Timber::get_user()`.
3. **`viewbox` → `viewBox`** in `views/partials/menu.twig` and `views/partials/pagination.twig` (carried from base; both files are unused there — consider deleting them instead).
4. **`lib/Queries.lib.php:18-19, 86-87`**: entity decoding is premised on Twig autoescaping, which is OFF in that theme — term names render decoded *and* unescaped. Either enable autoescape (base's new `set_twig_environment_options()` pattern) or escape at output.
5. Adopt the backport rule: keep a `BACKPORT.md` in the site repo going forward.
```

- [ ] **Step 7: Final sweep — greps, build, doc-integrity**

```bash
cd /Volumes/Work/themes/tatami
grep -rn "posts.pagination }}\|post.preview\|viewbox\|new Timber\\\\User\|single-password\|role=\"main\"\|#content\|_e(\|TatamiTheme\|TatamiQueries" --include='*.php' --include='*.twig' . --exclude-dir=vendor --exclude-dir=node_modules && echo "FOUND LEFTOVERS" || echo "SWEEP CLEAN"
grep -rl $'\t' --include='*.php' . --exclude-dir=vendor || echo "NO TABS"
pnpm build && pnpm lint && pnpm format
php /private/tmp/claude-501/-Volumes-Work-themes-tatami/53b83024-ed94-458f-9646-2b75d30e4fa0/scratchpad/check_templates.php
```

Expected: `SWEEP CLEAN`, `NO TABS`, build/lint/format pass, `ALL TEMPLATE CHECKS PASS`.

Then reread AGENTS.md and README.md top to bottom against the final code (files exist? behaviors true? versions right?). Fix any drift found before committing.

- [ ] **Step 8: Commit**

```bash
git add AGENTS.md README.md && git commit -m "Make AGENTS.md/README true to the code, add guardrails (DOC-2,3,5, §9)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
git log --oneline main..HEAD
```

Expected: 8 commits on `audit-remediation`. `BACKPORT-johnson-miller.md` remains untracked alongside AUDIT.md/AUDIT-REVIEW.md.
