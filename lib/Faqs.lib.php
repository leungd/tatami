<?php
/**
 * The FAQs block (`tatami/faqs`) and its questions (`tatami/faq`).
 *
 * Writers insert one FAQs block and add a Question block per question; a
 * question's answer is its inner Paragraph and List blocks. Both render
 * server-side through views/blocks/, and Tatami\Schema reads the same
 * question blocks into FAQPage, so the graph matches what the page shows.
 * Two halves:
 *
 * - Pure core — Faqs::item(), Faqs::from_blocks(), Faqs::answer_blocks().
 *   No WordPress calls: this is the test seam (tests/run.php loads this
 *   file under plain `php`).
 * - Adapter — registers the blocks from blocks/faqs/ and blocks/faq/, hides Yoast's
 *   FAQ block from the inserter, and adds `wp tatami migrate-faqs`, which
 *   moves the retired `faqs` ACF repeater into blocks.
 *
 *   Faqs::for_post( $post );  // [ [ 'question' => 'plain text', 'answer' => '<p>html</p>' ], … ]
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

namespace Tatami;

use Timber\Timber;

class Faqs {

    public const BLOCK      = 'tatami/faq';
    public const LIST_BLOCK = 'tatami/faqs';

    public function __construct() {
        if ( ! function_exists( 'add_action' ) ) {
            return;
        }

        add_action( 'init', [ $this, 'register_block' ] );
        add_filter( 'register_block_type_args', [ $this, 'hide_yoast_faq_block' ], 10, 2 );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'tatami migrate-faqs', [ self::class, 'migrate' ], [
                'shortdesc' => 'Move each post\'s `faqs` ACF repeater rows into an FAQs block at the end of its content.',
                'synopsis'  => [ [ 'type' => 'flag', 'name' => 'dry-run', 'optional' => true ] ],
            ] );
        }
    }

    // The question block registers the shared editor script, which the FAQs block names by handle.
    public function register_block(): void {
        register_block_type( dirname( __DIR__ ) . '/blocks/faq', [ 'render_callback' => [ self::class, 'render' ] ] );
        register_block_type( dirname( __DIR__ ) . '/blocks/faqs', [ 'render_callback' => [ self::class, 'render_list' ] ] );
    }

    public static function render_list( array $attributes, string $content ): string {
        return '' === trim( $content ) ? '' : (string) Timber::compile( 'blocks/faqs.twig', [ 'questions' => $content ] );
    }

    public static function render( array $attributes, string $content ): string {
        $faq = self::item( (string) ( $attributes['question'] ?? '' ), $content );

        return $faq ? (string) Timber::compile( 'blocks/faq.twig', $faq ) : '';
    }

    // Two FAQ sources on one page would give Yoast's graph two sets of Questions.
    public function hide_yoast_faq_block( array $args, string $name ): array {
        if ( 'yoast/faq-block' === $name ) {
            $args['supports'] = array_merge( $args['supports'] ?? [], [ 'inserter' => false ] );
        }
        return $args;
    }

    public static function for_post( \WP_Post $post ): array {
        return has_block( self::BLOCK, $post ) ? self::from_blocks( parse_blocks( $post->post_content ) ) : [];
    }

    /**
     * One FAQ, or null when the question or the answer is empty.
     * The question is stored as RichText HTML; it comes back as plain text.
     */
    public static function item( string $question, string $answer ): ?array {
        $question = trim( html_entity_decode( strip_tags( $question ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        $answer   = trim( $answer );

        if ( '' === $question || '' === trim( strip_tags( $answer ) ) ) {
            return null;
        }
        return [ 'question' => $question, 'answer' => $answer ];
    }

    /**
     * Every FAQ in parse_blocks() output, in document order, including FAQ
     * blocks nested in groups or columns.
     */
    public static function from_blocks( array $blocks ): array {
        $faqs = [];
        foreach ( $blocks as $block ) {
            if ( self::BLOCK === ( $block['blockName'] ?? null ) ) {
                $faq = self::item( (string) ( $block['attrs']['question'] ?? '' ), self::saved_html( $block ) );
                if ( $faq ) {
                    $faqs[] = $faq;
                }
            } elseif ( ! empty( $block['innerBlocks'] ) ) {
                array_push( $faqs, ...self::from_blocks( $block['innerBlocks'] ) );
            }
        }
        return $faqs;
    }

    // Paragraph and List are static blocks, so their saved markup is what the page renders.
    private static function saved_html( array $block ): string {
        $html  = '';
        $inner = 0;
        foreach ( $block['innerContent'] ?? [] as $chunk ) {
            $html .= is_string( $chunk ) ? $chunk : self::saved_html( $block['innerBlocks'][ $inner++ ] );
        }
        return $html;
    }

    /**
     * WYSIWYG answer HTML as Paragraph and List block markup, or null when it
     * holds anything else (a heading, a blockquote, an aligned paragraph, a
     * nested list) that needs moving by hand.
     */
    public static function answer_blocks( string $html ): ?string {
        $doc = new \DOMDocument();
        if ( '' === trim( $html ) || ! @$doc->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>' ) ) {
            return null;
        }

        $blocks = [];
        foreach ( $doc->getElementsByTagName( 'body' )->item( 0 )->childNodes as $node ) {
            if ( $node instanceof \DOMText && '' === trim( $node->textContent ) ) {
                continue;
            }
            if ( ! $node instanceof \DOMElement || $node->hasAttributes() ) {
                return null;
            }

            if ( 'p' === $node->tagName ) {
                $blocks[] = "<!-- wp:paragraph -->\n<p>" . self::inner_html( $node ) . "</p>\n<!-- /wp:paragraph -->";
                continue;
            }

            if ( ! in_array( $node->tagName, [ 'ul', 'ol' ], true ) ) {
                return null;
            }

            $items = [];
            foreach ( $node->childNodes as $li ) {
                if ( $li instanceof \DOMText && '' === trim( $li->textContent ) ) {
                    continue;
                }
                if ( ! $li instanceof \DOMElement || 'li' !== $li->tagName || $li->hasAttributes()
                    || $li->getElementsByTagName( 'ul' )->length || $li->getElementsByTagName( 'ol' )->length ) {
                    return null;
                }
                $items[] = "<!-- wp:list-item -->\n<li>" . self::inner_html( $li ) . "</li>\n<!-- /wp:list-item -->";
            }

            $ordered  = 'ol' === $node->tagName;
            $blocks[] = '<!-- wp:list' . ( $ordered ? ' {"ordered":true}' : '' ) . " -->\n"
                . '<' . $node->tagName . ' class="wp-block-list">' . implode( "\n\n", $items ) . '</' . $node->tagName . ">\n"
                . '<!-- /wp:list -->';
        }

        return $blocks ? implode( "\n\n", $blocks ) : null;
    }

    private static function inner_html( \DOMElement $element ): string {
        $html = '';
        foreach ( $element->childNodes as $child ) {
            $html .= $element->ownerDocument->saveHTML( $child );
        }
        return trim( $html );
    }

    /**
     * Appends an FAQs block, one question per `faqs` repeater row, to the end of each post
     * that has rows and no FAQ blocks yet. The repeater's meta is left in
     * place; the post's revision history holds the content before the move.
     */
    public static function migrate( array $args, array $assoc_args ): void {
        if ( ! function_exists( 'get_field' ) ) {
            \WP_CLI::error( 'ACF is not active, so the `faqs` repeater cannot be read.' );
        }

        $dry_run = ! empty( $assoc_args['dry-run'] );
        $ids     = get_posts( [
            'post_type'      => array_values( get_post_types( [ 'show_ui' => true ] ) ),
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [ [ 'key' => 'faqs', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ] ],
        ] );

        $moved = 0;
        foreach ( $ids as $id ) {
            $post  = get_post( $id );
            $label = sprintf( '#%d "%s"', $id, $post->post_title );

            if ( has_block( self::BLOCK, $post ) ) {
                \WP_CLI::log( "Skipped {$label}: it already has FAQ blocks." );
                continue;
            }

            $blocks = [];
            foreach ( (array) get_field( 'faqs', $id ) as $i => $row ) {
                $question = trim( (string) ( $row['question'] ?? '' ) );
                $answer   = self::answer_blocks( (string) ( $row['answer'] ?? '' ) );
                if ( '' === $question || null === $answer ) {
                    \WP_CLI::warning( sprintf( 'Skipped %s: row %d needs moving by hand.', $label, $i + 1 ) );
                    continue 2;
                }
                $blocks[] = serialize_block( [
                    'blockName'    => self::BLOCK,
                    'attrs'        => [ 'question' => esc_html( $question ) ],
                    'innerBlocks'  => [],
                    'innerContent' => [ "\n" . $answer . "\n" ],
                ] );
            }

            if ( ! $dry_run ) {
                $list    = serialize_block( [
                    'blockName'    => self::LIST_BLOCK,
                    'attrs'        => [],
                    'innerBlocks'  => [],
                    'innerContent' => [ "\n" . implode( "\n\n", $blocks ) . "\n" ],
                ] );
                $content = rtrim( $post->post_content ) . "\n\n" . $list;
                $result  = wp_update_post( wp_slash( [ 'ID' => $id, 'post_content' => $content ] ), true );
                if ( is_wp_error( $result ) ) {
                    \WP_CLI::warning( "Skipped {$label}: " . $result->get_error_message() );
                    continue;
                }
            }

            \WP_CLI::log( sprintf( '%s %s: %d FAQs.', $dry_run ? 'Would move' : 'Moved', $label, count( $blocks ) ) );
            $moved++;
        }

        \WP_CLI::success( sprintf( '%d posts %s.', $moved, $dry_run ? 'would be moved' : 'moved' ) );
    }
}
