# Launch checklist

Set these on production before launch; each says where it lives.

- [ ] **Yoast SEO is active** (Plugins). House policy — without it the base outputs no schema.
- [ ] **Site representation** (Yoast SEO → Settings → Site representation): Organization, not Person; organisation name; logo; social profiles (Facebook, X handle, Other profiles). The same list feeds `{{ social_profiles }}` and the Organization's `sameAs` (see "Social profiles" in `docs/schema.md`).
- [ ] **Author archives off** (Yoast SEO → Settings → Advanced → Author archives). The theme already 404s them; this also drops them from the sitemaps and Yoast's graph.
- [ ] **Professional page type = Profile page** (Yoast SEO → Settings → Content types → the Professional post type → Schema → Page type).
- [ ] **llms.txt enabled** (Yoast SEO → Settings → Site features → llms.txt) and regenerated on production once content is final. Never ship a locally generated file.
- [ ] **Search/retrieval AI bots allowed**: `robots.txt` has no `Disallow` for them, and the host/CDN does not block them (Cloudflare → Security → Bots → "Block AI bots" off, or an allow rule for the retrieval agents). Training bots (GPTBot, ClaudeBot, Google-Extended, CCBot, …) are allowed by default unless the client decides otherwise — record the client's decision.
- [ ] **Firm type set** in `Site.lib.php` with the `tatami/schema/firm_type` filter (see `docs/schema.md`).
- [ ] **Firm fields filled** (Site Settings): the main Office address exactly as the Google Business Profile shows it; phone, fax, email; Offices only if there is more than one; Area served (see `docs/schema.md`).
- [ ] **Schema checks run** — the Definition-of-done schema checks in AGENTS.md, on the pages listed there.
- [ ] **Theme dev files kept off WP Engine**: `recipes/wpe/wpe-ignore` is in `app/public/` as both `.wpe-push-ignore` and `.wpe-pull-ignore`, identical, and `/wp-content/themes/tatami/AGENTS.md` returns 404 on production with a cache-busting query (see `docs/deploy.md`).
- [ ] **Assets built before the push**: `pnpm build` ran; WP Engine never builds.
