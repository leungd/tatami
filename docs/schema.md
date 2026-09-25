# Schema (house tool)

Yoast SEO owns SEO output (house ADR): titles, meta descriptions, canonical, Open Graph, and the single JSON-LD graph. **The theme never prints JSON-LD, microdata, or head meta.** `Tatami\Schema` (`lib/Schema.lib.php`) adds the site's facts to Yoast's graph through the `wpseo_schema_graph` filter — never a second graph.

- **Facts come from house-named ACF fields.** The field names below are fixed because `Tatami\Schema` reads them; keys stay site-prefixed.
- **No-ops cleanly.** Without Yoast the filter is never registered; without ACF the only change is a post's Attribution, which falls back to the Firm. Empty fields leave Yoast's graph unchanged, and a graph with no `#organization` piece (a site represented as a Person in Yoast) is returned as-is.
- **Firm → Organization.** With the Firm fields filled, Yoast's Organization piece gains the Firm-type subtype on its `@type` (`legal` → `LegalService`, `accounting` → `AccountingService`, `financial` → `FinancialService`, anything else → `ProfessionalService`), a structured `PostalAddress`, `telephone`, `faxNumber`, and `email`.
- **Offices.** The Address above is the main office. Each additional Office (`offices` repeater — empty on a single-office site) becomes its own piece with the Firm's `@type`, a stable `@id` (`<home>/#/schema/office/<slug of name>`, or the 1-based row number when unnamed — renaming an Office changes its `@id`), `name`, a structured `address`, `telephone`, `faxNumber`, `email`, and `parentOrganization` → the Firm. The Organization lists them in row order as `department`. Office pieces are appended after Yoast's, leaving Yoast's order intact.
- **Area served.** `area_served` rows become the Organization's `areaServed`, a plain list of place names in row order. One list for the whole Firm — every Service inherits it; empty adds nothing.
- **Post types.** The base assumes the post type names `professional` and `service` (the URL rewrite slug is independent — e.g. `'rewrite' => [ 'slug' => 'lawyers' ]`). New sites register the CPTs under these house names. A site with legacy names maps them with one line in `Site.lib.php`, and everything in the base that needs either name reads it from `Tatami\Schema::post_types()`:

  ```php
  add_filter( 'tatami/schema/post_types', fn( $types ) => [ 'professional' => 'lawyer' ] + $types );
  ```

  A legacy services post type (`services`, `practice-area`) is mapped the same way: `[ 'service' => 'practice-area' ]`, or both keys in one array.
- **Professional → Person.** On a Professional single, a Person piece is appended after Yoast's (and any Office pieces) with a stable `@id` (`<profile URL>#person`), `name`, `url`, `worksFor` → the Firm, `jobTitle` (`job_title`), `sameAs` (`profile_links`), `knowsAbout` (the published `services`, each as a Service with `@id` `<service URL>#service`, `name`, `url`), and `image` → Yoast's `#primaryimage` (the featured image) when the graph has one. Empty fields omit their properties. The Person is the page's main entity: Yoast's WebPage piece gains `mainEntity` → the Person. Yoast's schema page type for Professionals is set to **Profile page** in Yoast's settings (Content types → the Professional post type → Schema) — a Launch checklist item, not code.
- **Service → Service.** On a Service single, a Service piece is appended after Yoast's (and any Office pieces) with a stable `@id` (`<service URL>#service`), `name`, `url`, `description` from the post's manual excerpt (omitted when it has none — WordPress's auto-excerpt of the body is not used), `provider` → the Firm, and `areaServed` = the Firm's Area served list (omitted when empty). The Service is the page's main entity: Yoast's WebPage piece gains `mainEntity` → the Service. Professionals link to Services through `knowsAbout` with the same `@id`, never the reverse — no new fields.
- **FAQs.** On any singular with `faqs` rows, Yoast's WebPage piece gains `FAQPage` and one Question per row in `mainEntity` — see `docs/faqs.md`.
- **Attribution.** On a blog post the Article's `author` follows the post's Attribution (the Firm's Organization or the Professional's Person), `reviewedBy` marks a reviewing Professional, and Yoast's user-derived Person is removed — see `docs/attribution.md`.
- **The pure core is the test seam.** `Tatami\Schema::extend( array $graph, array $facts ): array` takes Yoast's graph plus plain facts and returns the graph, with no WordPress/ACF/Yoast calls; the adapter only gathers facts. Test it through `tests/` (see "Build & dev workflow" in AGENTS.md), asserting on the returned graph.

## Firm fields (per site)

Recipe: `recipes/acf/group_SITE_site_settings.json` — the site's Site Settings group (see "Options page" in `docs/acf-fields.md`). Copy it into `acf-json/` and replace `SITE` with the site prefix. It carries `firm_type`, the `address` group (street, city, province, postal code, country), `phone_number`, `fax_number`, `email_address`, the `offices` repeater (additional offices only), and the `area_served` repeater. The instructions on each field tell editors to enter the address exactly as the Google Business Profile shows it.

## Professional fields (per site)

Recipe: `recipes/acf/group_SITE_professional.json` — `job_title`, the `profile_links` repeater (the regulator's directory listing first, then LinkedIn), and the `services` relationship. A site with legacy post type names changes the location value and the relationship's `post_type` to its own.

## Address (house tool)

`macros/address.twig` renders an `address` group in the Canadian format Google Business Profile uses, so the visible address matches the listing and the schema: single-line `100 King St W Suite 5600, Toronto, ON M5X 1C9`, or with `multiline` the street, `<br>`, then `Toronto, ON M5X 1C9`. Empty parts are skipped, country is not displayed (it exists for schema), and it emits no wrapper element — the caller places it.

```twig
{% from 'macros/address.twig' import address %}
<address class="not-italic">{{ address(options.address, true) }}</address>
```

## Social profiles (house tool)

The Firm's social profiles are entered in one place: **Yoast SEO → Settings → Site representation** (the Facebook and X fields plus "Other profiles"). Yoast already emits them as the Organization's `sameAs`; templates read the same list from `{{ social_profiles }}` (global context). Never add an ACF repeater for social links.

Each item is `{ network, url }`. `network` is inferred from the URL host by `Tatami\SocialProfiles::network()` — `facebook`, `x`, `instagram`, `linkedin`, `youtube`, `tiktok`, `threads`, `bluesky`, `pinterest`, `wikipedia`, `avvo`, or `link` for any other host — so the site's `macros/icon.twig` needs one glyph per network it uses plus a generic `link` glyph. The X field stores a handle; the list carries the built `https://x.com/<handle>` URL. Order follows Yoast (Facebook, X, then Other profiles); duplicate URLs are listed once. Without Yoast the list is empty.

```twig
{% from 'macros/icon.twig' import icon %}
{% if social_profiles is not empty %}
  <ul class="flex gap-4">
    {% for profile in social_profiles %}
      <li>
        <a href="{{ profile.url }}" target="_blank" rel="noopener noreferrer">
          {{ icon(profile.network) }}
          <span class="sr-only">{{ profile.network|capitalize }}</span>
        </a>
      </li>
    {% endfor %}
  </ul>
{% endif %}
```
