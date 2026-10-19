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
 * - Pure core — Faqs::item(), Faqs::from_blocks().
 *   No WordPress calls: this is the test seam (tests/run.php loads this
 *   file under plain `php`).
 * - Adapter — registers the blocks from blocks/faqs/ and blocks/faq/, and hides
 *   Yoast's FAQ block from the inserter.
 *
 *   Faqs::for_post( $post );  // [ [ 'question' => 'plain text', 'answer' => '<p>html</p>' ], … ]
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

namespace Tatami;

use Timber\Timber;

class Faqs {

    public const BLOCK = 'tatami/faq';

    public function __construct() {
        if ( ! function_exists( 'add_action' ) ) {
            return;
        }

        add_action( 'init', [ $this, 'register_block' ] );
        add_filter( 'register_block_type_args', [ $this, 'hide_yoast_faq_block' ], 10, 2 );
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
}
