<?php
/**
 * Attribution — the one public credit a blog post carries.
 *
 * Exactly one of three states: firm (default), written_by, reviewed_by a
 * Professional. Every public credit surface — the template byline, Yoast's
 * author meta, the share-card row, and the schema graph (through
 * Tatami\Schema) — derives from resolve(), never from the WordPress user
 * who entered the post. Fields (per site): see "Attribution (house tool)"
 * in AGENTS.md.
 *
 *   $context['attribution'] = Tatami\Attribution::resolve( $post->ID );
 *   // [ 'state' => 'written_by', 'label' => 'Written by', 'name' => 'Jane Doe', 'url' => 'https://…/jane-doe/' ]
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

namespace Tatami;

class Attribution {

    public function __construct() {
        add_action( 'acf/save_post', [ $this, 'fill_name_from_person' ], 20 );
        add_action( 'init', [ $this, 'hide_author_box' ], 20 );
        add_action( 'rest_api_init', [ $this, 'remove_author_support' ] );

        add_filter( 'wpseo_meta_author', [ $this, 'filter_meta_author' ], 10, 2 );
        add_filter( 'wpseo_enhanced_slack_data', [ $this, 'filter_slack_data' ], 10, 2 );
    }

    /**
     * A personal credit needs a published Professional; anything missing,
     * unpublished, or of the wrong post type degrades to the Firm. Without
     * ACF every post credits the Firm.
     *
     * @return array{state:string,label:string,name:string,url:?string}
     */
    public static function resolve( int $post_id ): array {
        $firm = [
            'state' => 'firm',
            'label' => __( 'Written on behalf of', 'tatami' ),
            'name'  => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
            'url'   => null,
        ];

        if ( ! function_exists( 'get_field' ) ) {
            return $firm;
        }

        $state = get_field( 'attribution_state', $post_id );
        if ( 'written_by' !== $state && 'reviewed_by' !== $state ) {
            return $firm;
        }

        $person_id = (int) get_field( 'attribution_person', $post_id );
        if ( ! self::is_published_professional( $person_id ) ) {
            return $firm;
        }

        $name = trim( (string) get_field( 'attribution_name', $post_id ) );
        if ( '' === $name ) {
            $name = html_entity_decode( get_the_title( $person_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        }

        return [
            'state' => $state,
            'label' => 'reviewed_by' === $state ? __( 'Reviewed by', 'tatami' ) : __( 'Written by', 'tatami' ),
            'name'  => $name,
            'url'   => trailingslashit( get_permalink( $person_id ) ),
        ];
    }

    private static function is_published_professional( int $person_id ): bool {
        return $person_id
            && Schema::post_types()['professional'] === get_post_type( $person_id )
            && 'publish' === get_post_status( $person_id );
    }

    public function fill_name_from_person( $post_id ): void {
        if ( 'post' !== get_post_type( $post_id ) ) {
            return;
        }
        $state = get_field( 'attribution_state', $post_id );
        if ( 'written_by' !== $state && 'reviewed_by' !== $state ) {
            return;
        }
        $person_id = (int) get_field( 'attribution_person', $post_id );
        if ( self::is_published_professional( $person_id ) ) {
            update_field( 'attribution_name', html_entity_decode( get_the_title( $person_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $post_id );
        }
    }

    /**
     * The WordPress Author box expresses nothing public, so it is hidden as a
     * second, wrong place to assign credit. Support is removed only in admin
     * and REST (where the editor reads it): Yoast skips Article schema for a
     * post type without author support, so the front end keeps it.
     */
    public function hide_author_box(): void {
        if ( is_admin() ) {
            $this->remove_author_support();
        }
    }

    public function remove_author_support(): void {
        remove_post_type_support( 'post', 'author' );
    }

    private function presented_post_id( $presentation ): int {
        if ( ! is_object( $presentation ) || empty( $presentation->model ) ) {
            return 0;
        }
        if ( 'post' !== $presentation->model->object_type || 'post' !== $presentation->model->object_sub_type ) {
            return 0;
        }
        return (int) $presentation->model->object_id;
    }

    // <meta name="author">
    public function filter_meta_author( $author_name, $presentation ) {
        $post_id = $this->presented_post_id( $presentation );
        return $post_id ? self::resolve( $post_id )['name'] : $author_name;
    }

    // twitter:label1 / twitter:data1 — Yoast's "Written by" share-card row.
    public function filter_slack_data( $data, $presentation ) {
        $post_id = $this->presented_post_id( $presentation );
        if ( ! $post_id ) {
            return $data;
        }
        $attribution = self::resolve( $post_id );
        unset( $data[ __( 'Written by', 'wordpress-seo' ) ], $data['Written by'] );
        return array_merge( [ $attribution['label'] => $attribution['name'] ], $data );
    }
}
