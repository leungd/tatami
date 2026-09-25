# Tatami

Glossary for the Tatami base theme and the house conventions its derivative sites share. Terms here describe the content model every Tatami site is built on, not any one client's site.

## Language

**Service**:
A practice-area page a firm offers work under. Each derivative registers it as its own post type; it is the most common host of Related Posts.
_Avoid_: Practice area page, service page (the page *is* the Service)

**Related Posts**:
The blog posts a page is allowed to display alongside its own content, chosen by the categories that page subscribes to. The host page (a Service, a Professional, any post type) decides which posts qualify; a post never declares which pages it belongs to, and the same post may qualify for several pages.
_Avoid_: Latest posts, insights, news (those name a *recent* list with no subscription filter)

**Firm**:
The professional-services business a derivative site represents: a law firm, a financial firm, or similar. The base theme knows only that a Firm delivers Services through its people; each derivative says what kind of Firm it is.
_Avoid_: Client (ambiguous with the Firm's own clients), company, business

**AI citation**:
The site being quoted or linked as a source in an AI-generated answer (ChatGPT, Perplexity, Google AI Overviews / AI Mode, Claude). Earned mostly by the writing; the theme can only make pages easy to parse and attribute.
_Avoid_: AI SEO, GEO, LLM ranking (none name a single outcome)

**Entity**:
The Firm, or one of its people, as a distinct real-world thing that search engines and language models recognise and name in answers — as opposed to a page that merely mentions it. A theme helps by describing each Entity consistently in structured data and linking it to its profiles elsewhere.
_Avoid_: Brand, profile

**Professional**:
A person at the Firm who has their own profile page — a lawyer, advisor, accountant. Each site labels them however it likes on screen; the house word is Professional. Support staff without profile pages are not Professionals.
_Avoid_: Lawyer, team member, staff, person (each is either firm-specific or too broad)

**Attribution**:
The credit a blog post publicly carries: the Firm itself, "Written by" a Professional, or "Reviewed by" a Professional. It deliberately ignores the WordPress user who entered the post.
_Avoid_: Author, byline (both suggest the WordPress user account)

**Office**:
A physical location of the Firm, with its own address and phone. A Firm has one main Office and may have more.
_Avoid_: Location (also used for areas the Firm serves without an Office there)

**Area served**:
The regions the Firm takes work from, as a list — which may reach well beyond the cities where it has an Office. One list for the whole Firm; every Service inherits it.
_Avoid_: Location, service area (the first collides with Office, the second reads as a property of one Service)

**Base**:
The Tatami theme as published, with no site in it. It ships the plumbing every site shares and knows nothing about any one Firm.
_Avoid_: Starter, parent theme (a Derivative is a copy, not a WordPress child theme)

**Derivative**:
A site's theme that began as a copy of the Base and pulls the Base's updates. It owns everything site-specific and never sends changes back.
_Avoid_: Fork (implies contributing back), child theme, client site (names the site, not its theme)

**House tool**:
A capability the Base ships code for and every Derivative adopts the same way, usually keyed on fixed field names the Base reads.
_Avoid_: Feature, component

**House convention**:
A rule every Derivative follows that no Base code enforces, such as how fields are named or where a query lives.
_Avoid_: Best practice, guideline

**Base plumbing**:
The parts of a Derivative that must stay identical to the Base so updates apply cleanly.
_Avoid_: Core, framework

**Site surface**:
The parts of a Derivative the site is expected to change freely.
_Avoid_: Custom code, overrides

**Host**:
A page that subscribes to categories and so is allowed to show Related Posts. Any post type can be a Host.
_Avoid_: Parent, container
