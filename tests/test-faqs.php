<?php
/**
 * Tatami\Faqs — FAQ blocks to schema facts, and WYSIWYG answers to blocks.
 * Loaded by tests/run.php.
 */

if ( PHP_SAPI !== 'cli' ) {
    exit;
}

use Tatami\Faqs;

function faq_block( string $question, array $answer_blocks ): array {
    $inner_content = [ "\n" ];
    foreach ( $answer_blocks as $_ ) {
        $inner_content[] = null;
        $inner_content[] = "\n\n";
    }
    return [
        'blockName'    => 'tatami/faq',
        'attrs'        => [ 'question' => $question ],
        'innerBlocks'  => $answer_blocks,
        'innerHTML'    => "\n\n",
        'innerContent' => $inner_content,
    ];
}

function paragraph_block( string $html ): array {
    return [ 'blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => $html, 'innerContent' => [ $html ] ];
}

function list_block( array $items ): array {
    $item_blocks   = [];
    $inner_content = [ '<ul class="wp-block-list">' ];
    foreach ( $items as $item ) {
        $item_blocks[]   = [ 'blockName' => 'core/list-item', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => "<li>$item</li>", 'innerContent' => [ "<li>$item</li>" ] ];
        $inner_content[] = null;
    }
    $inner_content[] = '</ul>';
    return [ 'blockName' => 'core/list', 'attrs' => [], 'innerBlocks' => $item_blocks, 'innerHTML' => '<ul class="wp-block-list"></ul>', 'innerContent' => $inner_content ];
}

// --- item() ------------------------------------------------------------------

assert_equal(
    [ 'question' => 'Q & A?', 'answer' => '<p>Yes.</p>' ],
    Faqs::item( ' Q &amp; A? ', "\n<p>Yes.</p>\n" ),
    'item -> question decoded to plain text, answer trimmed'
);
assert_equal( 'Bold question', Faqs::item( '<strong>Bold</strong> question', '<p>a</p>' )['question'], 'item -> tags stripped from the question' );
assert_equal( null, Faqs::item( '', '<p>Yes.</p>' ), 'item -> empty question is no FAQ' );
assert_equal( null, Faqs::item( 'Why?', '' ), 'item -> empty answer is no FAQ' );
assert_equal( null, Faqs::item( 'Why?', "<p></p>\n\n<p> </p>" ), 'item -> answer of empty paragraphs is no FAQ' );

// --- from_blocks() -----------------------------------------------------------

$blocks = [
    paragraph_block( '<p>Intro.</p>' ),
    faq_block( 'How long does a trademark application take?', [ paragraph_block( '<p>Usually <strong>12–18 months</strong>.</p>' ) ] ),
    [
        'blockName'    => 'core/group',
        'attrs'        => [],
        'innerBlocks'  => [
            faq_block( 'What do I need?', [ paragraph_block( '<p>Two things:</p>' ), list_block( [ 'A name', 'A logo' ] ) ] ),
        ],
        'innerHTML'    => '<div class="wp-block-group"></div>',
        'innerContent' => [ '<div class="wp-block-group">', null, '</div>' ],
    ],
    faq_block( '', [ paragraph_block( '<p>Orphan answer.</p>' ) ] ),
    faq_block( 'Unanswered?', [ paragraph_block( '<p></p>' ) ] ),
];

assert_equal(
    [
        [ 'question' => 'How long does a trademark application take?', 'answer' => '<p>Usually <strong>12–18 months</strong>.</p>' ],
        [ 'question' => 'What do I need?', 'answer' => "<p>Two things:</p>\n\n<ul class=\"wp-block-list\"><li>A name</li><li>A logo</li></ul>" ],
    ],
    Faqs::from_blocks( $blocks ),
    'from_blocks -> FAQs in document order, nested ones included, empty ones dropped'
);
assert_equal( [], Faqs::from_blocks( [ paragraph_block( '<p>No FAQs.</p>' ) ] ), 'from_blocks -> no FAQ blocks, no FAQs' );
assert_equal( [], Faqs::from_blocks( [] ), 'from_blocks -> no blocks, no FAQs' );

// --- answer_blocks() ---------------------------------------------------------

assert_equal(
    "<!-- wp:paragraph -->\n<p>Usually <strong>12–18 months</strong> &amp; <a href=\"https://example.com/\">more</a>.</p>\n<!-- /wp:paragraph -->",
    Faqs::answer_blocks( '<p>Usually <strong>12–18 months</strong> &amp; <a href="https://example.com/">more</a>.</p>' ),
    'answer_blocks -> a paragraph keeps its inline markup and entities'
);
assert_equal(
    "<!-- wp:paragraph -->\n<p>Two things:</p>\n<!-- /wp:paragraph -->\n\n"
    . "<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\"><!-- wp:list-item -->\n<li>A name</li>\n<!-- /wp:list-item -->\n\n"
    . "<!-- wp:list-item -->\n<li>A <em>logo</em></li>\n<!-- /wp:list-item --></ol>\n<!-- /wp:list -->",
    Faqs::answer_blocks( "<p>Two things:</p>\n<ol>\n<li>A name</li>\n<li>A <em>logo</em></li>\n</ol>\n" ),
    'answer_blocks -> paragraph then ordered list'
);
assert_true(
    str_starts_with( (string) Faqs::answer_blocks( '<ul><li>One</li></ul>' ), "<!-- wp:list -->\n<ul class=\"wp-block-list\">" ),
    'answer_blocks -> unordered list has no ordered attribute'
);
assert_equal( null, Faqs::answer_blocks( '' ), 'answer_blocks -> empty answer needs moving by hand' );
assert_equal( null, Faqs::answer_blocks( '<h3>Heading</h3><p>Body</p>' ), 'answer_blocks -> heading needs moving by hand' );
assert_equal( null, Faqs::answer_blocks( '<blockquote><p>Quote</p></blockquote>' ), 'answer_blocks -> blockquote needs moving by hand' );
assert_equal( null, Faqs::answer_blocks( '<p style="text-align: center;">Centred</p>' ), 'answer_blocks -> aligned paragraph needs moving by hand' );
assert_equal( null, Faqs::answer_blocks( '<ul><li>One<ul><li>Nested</li></ul></li></ul>' ), 'answer_blocks -> nested list needs moving by hand' );
assert_equal( null, Faqs::answer_blocks( 'Bare text' ), 'answer_blocks -> text outside a paragraph needs moving by hand' );
