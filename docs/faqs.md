# FAQs (house tool)

FAQs are blocks. A writer inserts an **FAQs** block (`tatami/faqs`) where the questions belong in the content, then adds one **Question** block (`tatami/faq`) per question with the **Add Question** button at the end of the list or in the block toolbar, typing the question inline and the answer as ordinary Paragraph and List blocks. The base fixes three things and leaves the look to the site: the **blocks**, because `Tatami\Schema` builds the page's FAQPage schema from it; the **disclosure pattern**, because answers must be in the HTML, keyboard-operable, and readable with JavaScript off; and the **answer's allowed blocks**, so the schema's answer text stays clean HTML. The blocks work on any post type that uses the block editor and do not need ACF.

## Blocks

`blocks/faqs/block.json` is the list; `blocks/faq/` holds the question block's `block.json`, the unbuilt editor script for both blocks (`index.js`, using the editor's `wp` globals — no build step), `index.asset.php` naming its script dependencies, and `editor.css`. `Tatami\Faqs` (`lib/Faqs.lib.php`, base plumbing) registers both and renders them server-side.

- **FAQs** — holds only Question blocks and starts with one. **Add Question** in its toolbar appends one; on a Question's toolbar it adds one below that question (core Accordion's "Add item" pattern), and the list ends with an **Add Question** button. It renders its questions in a `<div>` (`views/blocks/faqs.twig`), and nothing when none of them render.
- **Question** — insertable only inside FAQs. Its `question` attribute is edited as RichText with formatting and line breaks turned off; Enter moves to the answer. It is read back as plain text (tags stripped, entities decoded).
- **Answer** — the question block's inner blocks, limited to `core/paragraph` and `core/list` (list items come with the list). Inline formatting and links are allowed.
- **Empty is nothing.** A question block without a question, or whose answer has no text, renders nothing and adds no Question to the graph.
- **Editor frame.** `editor.css` (editor only) boxes each question with "Question" and "Answer" labels in the editor's UI font, so writers can see where one starts and ends. It never reaches the front end.
- An FAQ section's heading is a Heading block the writer puts above the FAQs block. Several FAQs blocks on one page all count, in document order. Questions inside a synced pattern are not read into the schema.

Yoast's own FAQ block is hidden from the inserter (`register_block_type_args`), so there is one way to write an FAQ. Its blocks already in a post's content keep working and keep Yoast's own FAQ schema; replace them with an FAQs block.

## Markup

`views/blocks/faq.twig` renders each question as one native `<details>`/`<summary>`: the question autoescaped in the `<summary>`, the rendered answer blocks `|raw` in a `<div>`. No script. The base adds no classes; a site styles its FAQs by adding utilities in that file (`summary::marker`, `details[open]`, `[&_p]:…`). The blocks only show on templates that output the post content (`{{ post.content|raw }}`); a template that builds its body from ACF fields shows no FAQs.

A custom-scripted accordion is not a substitute: `<details>` gives the open/closed state, keyboard handling, and JavaScript-off fallback natively, and styles freely.

## Schema

On any singular whose content has Question blocks, `Tatami\Schema` adds `FAQPage` to Yoast's WebPage `@type` and sets `mainEntity` to one `Question` (`name`) with an `acceptedAnswer` `Answer` (`text`, the answer's Paragraph and List HTML) per question, in document order. `Tatami\Faqs::for_post()` reads them from the same blocks the page renders, so the graph matches the visible text. On a Professional or Service page the Person or Service reference stays first in `mainEntity`, followed by the Questions. No Question blocks adds nothing.

FAQ rich results have been restricted to authoritative government and health sites since 2023, so law and financial firms won't get one; the value here is AI-readable Q&A, on the page and in the graph.

Hand-rolled `<script type="application/ld+json">` or `itemscope` FAQ markup in a template is a defect to replace with an FAQs block, never a pattern to copy.

## Moving from the `faqs` repeater

Before this block, FAQs were a `faqs` ACF repeater rendered by the site's template. The schema no longer reads it. To move a site over:

1. `wp tatami migrate-faqs --dry-run` lists the posts with repeater rows and what would move; `wp tatami migrate-faqs` appends an FAQs block, one question per row, to the end of each post's content. It needs ACF active. Posts that already have FAQ blocks are skipped. A row whose answer holds anything besides plain paragraphs and lists (a heading, a blockquote, an aligned paragraph, a nested list) skips its whole post with a warning — move that one by hand. The repeater's meta is left in place, and each changed post gets a revision.
2. Remove the repeater markup from the site's templates (it would now render the FAQs twice) and make sure those templates output `post.content` where the FAQs should appear.
3. Open each moved post, move the FAQs block where it belongs in the content, and add a heading above them if the old template supplied one.
4. Delete the site's `group_<site>_faqs.json` from `acf-json/`.
