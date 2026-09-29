# Deploying (Local → WP Engine)

House policy: sites are developed in Local and deployed to WP Engine with Local's Connect push/pull. WP Engine serves every file it receives at a public URL, so the theme's development files — this doc included — must never be pushed.

## The ignore files

Local reads exactly two files, both in the site root: `app/public/.wpe-push-ignore` and `app/public/.wpe-pull-ignore`. A copy inside the theme is not read. Rules are rsync exclude patterns, so theme rules name the theme's path from the site root (`wp-content/themes/tatami/docs/`). The theme folder stays `tatami`; a site that renames it edits those paths.

The base ships the whole file as `recipes/wpe/wpe-ignore`. From the site's `app/public/`:

```bash
cp wp-content/themes/tatami/recipes/wpe/wpe-ignore .wpe-push-ignore
cp wp-content/themes/tatami/recipes/wpe/wpe-ignore .wpe-pull-ignore
```

Copy, never move: the recipe is a base file, and removing it from the theme conflicts with the next upstream pull. A site's own additions go into **both** files.

**The two files must stay identical.** Local syncs with rsync `--delete`: a path ignored on push but not on pull is missing on WP Engine, so the next pull deletes it locally — uncommitted work included.

What ships: the PHP routers, `lib/`, `views/`, `build/`, `vendor/`, `acf-json/`, `style.css`, `screenshot.png`, `LICENSE`. `pnpm test` fails if a top-level theme entry is neither shipped nor excluded by the recipe, so a new file in the base forces the choice.

## Before every push

Run `pnpm build` (and `composer install` if PHP dependencies changed). WP Engine never builds: `build/` and `vendor/` are pushed from the local copy, even though neither is committed.

## Files already on the server

An ignore rule only stops future uploads; copies pushed before the rule existed stay public. Check once per install with a cache-busting request — WP Engine's page cache can serve a deleted file:

```bash
curl -s -o /dev/null -w "%{http_code}\n" "https://<install>.wpengine.com/wp-content/themes/tatami/AGENTS.md?nocache=$RANDOM"
```

Anything but 404 means the dev files are still there: delete them over SSH (`~/sites/<install>/wp-content/themes/tatami/`), then `wp page-cache flush`.
