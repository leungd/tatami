<?php
/**
 * Extends Yoast SEO's schema graph with the theme's facts.
 *
 * Yoast owns SEO output (house ADR); the theme never prints JSON-LD. This
 * class adds to Yoast's single graph through the `wpseo_schema_graph`
 * filter. Two halves:
 *
 * - Pure core — Schema::extend( $graph, $facts ). Yoast's graph (array of
 *   pieces) plus plain facts in, graph out. No WordPress, ACF or Yoast
 *   calls: this is the test seam (tests/run.php loads this file under
 *   plain `php`).
 * - Adapter — registers the filter when Yoast is active and gathers facts
 *   from the house-named ACF fields and Tatami\Attribution. No-ops without
 *   Yoast; without ACF only a post's Attribution (the Firm) applies.
 *
 * Facts shape (every key optional; empty facts leave the graph unchanged):
 *
 *   [
 *     'firm' => [
 *       'type'          => 'legal',  // legal | accounting | financial | professional
 *       'address'       => [ 'street_address', 'city', 'province', 'postal_code', 'country' ],
 *       'phone_number'  => '',
 *       'fax_number'    => '',
 *       'email_address' => '',
 *       'offices'       => [ [ 'name', 'address' => [ …as above… ], 'phone_number', 'fax_number', 'email_address' ], … ],
 *       'area_served'   => [ 'Ottawa', 'Eastern Ontario' ],
 *     ],
 *     'page' => [  // the queried page; unknown kinds are ignored
 *       'kind'          => 'professional',
 *       'name'          => 'Jane Doe',
 *       'url'           => 'https://example.com/team/jane-doe/',  // trailing slash; Person @id is <url>#person
 *       'job_title'     => 'Partner',
 *       'profile_links' => [ 'https://…', … ],
 *       'services'      => [ [ 'name', 'url' ], … ],  // url with trailing slash; Service @id is <url>#service
 *       'faqs'          => [ [ 'question' => 'plain text', 'answer' => '<p>html</p>' ], … ],  // present on any singular
 *     ],
 *     // or, on a Service single:
 *     'page' => [
 *       'kind'        => 'service',
 *       'name'        => 'Business Law',
 *       'url'         => 'https://example.com/services/business-law/',  // trailing slash; Service @id is <url>#service
 *       'description' => 'plain text',  // the manual excerpt; empty omits it
 *       'faqs'        => [ … ],
 *     ],
 *     // or, on a blog post:
 *     'page' => [
 *       'kind'        => 'post',
 *       'attribution' => [ 'state' => 'written_by', 'name' => 'Jane Doe', 'url' => 'https://example.com/team/jane-doe/' ],
 *                        // firm | written_by | reviewed_by; url is the Professional's profile (null for firm); Person @id is <url>#person
 *       'faqs'        => [ … ],
 *     ],
 *   ]
 *
 * Post type names come from Schema::post_types() (filter
 * `tatami/schema/post_types`), so a derivative with legacy post types maps
 * them in Site.lib.php:
 *
 *   add_filter( 'tatami/schema/post_types', fn( $types ) => [ 'professional' => 'lawyer', 'service' => 'practice-area' ] + $types );
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

namespace Tatami;

class Schema {

    private const FIRM_SUBTYPES = [
        'legal'      => 'LegalService',
        'accounting' => 'AccountingService',
        'financial'  => 'FinancialService',
    ];

    public function __construct() {
        if ( defined( 'WPSEO_VERSION' ) ) {
            add_filter( 'wpseo_schema_graph', [ $this, 'filter_graph' ], 20, 2 );
        }
    }

    public static function post_types(): array {
        $defaults = [ 'professional' => 'professional', 'service' => 'service' ];

        return function_exists( 'apply_filters' ) ? apply_filters( 'tatami/schema/post_types', $defaults ) : $defaults;
    }

    public function filter_graph( $graph, $context ) {
        if ( ! is_array( $graph ) ) {
            return $graph;
        }

        $queried  = get_queried_object();
        $singular = $queried instanceof \WP_Post && is_singular();
        $facts    = [];

        if ( function_exists( 'get_field' ) ) {
            $facts['firm'] = $this->firm_facts();
            if ( $singular ) {
                $facts['page'] = [ 'kind' => 'page', 'faqs' => $this->faq_facts( $queried ) ];
                if ( is_singular( self::post_types()['professional'] ) ) {
                    $facts['page'] = $this->professional_facts( $queried ) + $facts['page'];
                }
                if ( is_singular( self::post_types()['service'] ) ) {
                    $facts['page'] = $this->service_facts( $queried ) + $facts['page'];
                }
            }
        }

        // Without ACF a post still credits the Firm, so Yoast's user Person never shows.
        if ( $singular && is_singular( 'post' ) ) {
            $attribution   = Attribution::resolve( $queried->ID );
            $facts['page'] = [
                'kind'        => 'post',
                'attribution' => [ 'state' => $attribution['state'], 'name' => $attribution['name'], 'url' => $attribution['url'] ],
            ] + ( $facts['page'] ?? [] );
        }

        return self::extend( $graph, $facts );
    }

    private function professional_facts( \WP_Post $post ): array {
        $links = get_field( 'profile_links', $post->ID );
        $links = is_array( $links ) ? array_values( array_filter( array_map( fn( $row ) => trim( (string) ( $row['url'] ?? '' ) ), $links ) ) ) : [];

        $services = [];
        foreach ( (array) get_field( 'services', $post->ID ) as $service_id ) {
            if ( $service_id && 'publish' === get_post_status( $service_id ) ) {
                $services[] = [
                    'name' => html_entity_decode( get_the_title( $service_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
                    'url'  => trailingslashit( get_permalink( $service_id ) ),
                ];
            }
        }

        return [
            'kind'          => 'professional',
            'name'          => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
            'url'           => trailingslashit( get_permalink( $post ) ),
            'job_title'     => trim( (string) get_field( 'job_title', $post->ID ) ),
            'profile_links' => $links,
            'services'      => $services,
        ];
    }

    private function service_facts( \WP_Post $post ): array {
        return [
            'kind'        => 'service',
            'name'        => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
            'url'         => trailingslashit( get_permalink( $post ) ),
            // Only a manual excerpt; WordPress's auto-excerpt of the body is not a description.
            'description' => has_excerpt( $post ) ? trim( wp_strip_all_tags( $post->post_excerpt ) ) : '',
        ];
    }

    private function faq_facts( \WP_Post $post ): array {
        $rows = get_field( 'faqs', $post->ID );
        if ( ! is_array( $rows ) ) {
            return [];
        }

        return array_values( array_filter(
            $rows,
            fn( $row ) => '' !== trim( (string) ( $row['question'] ?? '' ) ) && ! empty( $row['answer'] )
        ) );
    }

    private function firm_facts(): array {
        return array_filter( [
            'type'          => get_field( 'firm_type', 'option' ),
            'address'       => array_filter( (array) get_field( 'address', 'option' ) ),
            'phone_number'  => get_field( 'phone_number', 'option' ),
            'fax_number'    => get_field( 'fax_number', 'option' ),
            'email_address' => get_field( 'email_address', 'option' ),
            'offices'       => $this->office_facts(),
            'area_served'   => $this->area_served_facts(),
        ] );
    }

    private function office_facts(): array {
        $rows = get_field( 'offices', 'option' );
        if ( ! is_array( $rows ) ) {
            return [];
        }

        $offices = [];
        foreach ( $rows as $row ) {
            $office = array_filter( [
                'name'          => $row['name'] ?? '',
                'address'       => array_filter( (array) ( $row['address'] ?? [] ) ),
                'phone_number'  => $row['phone_number'] ?? '',
                'fax_number'    => $row['fax_number'] ?? '',
                'email_address' => $row['email_address'] ?? '',
            ] );
            if ( $office ) {
                $offices[] = $office;
            }
        }
        return $offices;
    }

    private function area_served_facts(): array {
        $rows = get_field( 'area_served', 'option' );
        if ( ! is_array( $rows ) ) {
            return [];
        }

        return array_values( array_filter( array_map( fn( $row ) => trim( (string) ( $row['name'] ?? '' ) ), $rows ) ) );
    }

    public static function extend( array $graph, array $facts ): array {
        $org = self::organization_index( $graph );
        if ( null === $org ) {
            return $graph;
        }

        if ( ! empty( $facts['firm'] ) ) {
            $graph[ $org ] = self::with_firm( $graph[ $org ], $facts['firm'] );
            foreach ( self::offices( $graph[ $org ], $facts['firm']['offices'] ?? [] ) as $office ) {
                $graph[ $org ]['department'][] = [ '@id' => $office['@id'] ];
                $graph[]                       = $office;
            }
        }

        if ( 'professional' === ( $facts['page']['kind'] ?? null ) ) {
            $graph = self::with_professional( $graph, $facts['page'], $graph[ $org ]['@id'] );
        }

        if ( 'service' === ( $facts['page']['kind'] ?? null ) ) {
            $graph = self::with_service( $graph, $facts['page'], $graph[ $org ]['@id'], $facts['firm']['area_served'] ?? [] );
        }

        if ( 'post' === ( $facts['page']['kind'] ?? null ) && isset( $facts['page']['attribution'] ) ) {
            $graph = self::with_attribution( $graph, $facts['page']['attribution'], $graph[ $org ]['@id'] );
        }

        if ( ! empty( $facts['page']['faqs'] ) ) {
            $graph = self::with_faqs( $graph, $facts['page']['faqs'] );
        }

        return $graph;
    }

    private static function with_professional( array $graph, array $professional, string $org_id ): array {
        $url    = (string) $professional['url'];
        $person = [
            '@type'    => 'Person',
            '@id'      => $url . '#person',
            'name'     => $professional['name'],
            'url'      => $url,
            'worksFor' => [ '@id' => $org_id ],
        ];

        if ( ! empty( $professional['job_title'] ) ) {
            $person['jobTitle'] = $professional['job_title'];
        }
        if ( ! empty( $professional['profile_links'] ) ) {
            $person['sameAs'] = array_values( $professional['profile_links'] );
        }
        foreach ( $professional['services'] ?? [] as $service ) {
            $person['knowsAbout'][] = [
                '@type' => 'Service',
                '@id'   => $service['url'] . '#service',
                'name'  => $service['name'],
                'url'   => $service['url'],
            ];
        }
        if ( in_array( $url . '#primaryimage', array_column( $graph, '@id' ), true ) ) {
            $person['image'] = [ '@id' => $url . '#primaryimage' ];
        }

        $webpage = self::webpage_index( $graph );
        if ( null !== $webpage ) {
            $graph[ $webpage ]['mainEntity'] = [ '@id' => $person['@id'] ];
        }

        $graph[] = $person;
        return $graph;
    }

    private static function with_service( array $graph, array $service, string $org_id, array $area_served ): array {
        $url   = (string) $service['url'];
        $piece = [
            '@type'    => 'Service',
            '@id'      => $url . '#service',
            'name'     => $service['name'],
            'url'      => $url,
            'provider' => [ '@id' => $org_id ],
        ];

        if ( ! empty( $service['description'] ) ) {
            $piece['description'] = $service['description'];
        }
        if ( $area_served ) {
            $piece['areaServed'] = array_values( $area_served );
        }

        $webpage = self::webpage_index( $graph );
        if ( null !== $webpage ) {
            $graph[ $webpage ]['mainEntity'] = [ '@id' => $piece['@id'] ];
        }

        $graph[] = $piece;
        return $graph;
    }

    private static function with_attribution( array $graph, array $attribution, string $org_id ): array {
        $url   = $attribution['url'] ?? null;
        $state = $attribution['state'] ?? 'firm';
        if ( ! in_array( $state, [ 'written_by', 'reviewed_by' ], true ) || ! is_string( $url ) || '' === $url ) {
            $state = 'firm';
        }

        // Yoast's Person is derived from the WordPress user, never the public credit.
        $graph = array_values( array_filter(
            $graph,
            fn( $piece ) => ! ( in_array( 'Person', (array) ( $piece['@type'] ?? [] ), true )
                && str_contains( (string) ( $piece['@id'] ?? '' ), '#/schema/person/' ) )
        ) );

        $person = 'firm' === $state ? null : [ '@id' => $url . '#person' ];
        $author = 'written_by' === $state ? $person : [ '@id' => $org_id ];

        foreach ( $graph as $i => $piece ) {
            if ( array_intersect( [ 'Article', 'BlogPosting', 'NewsArticle' ], (array) ( $piece['@type'] ?? [] ) ) ) {
                $graph[ $i ]['author'] = $author;
            }
        }

        $webpage = self::webpage_index( $graph );
        if ( null !== $webpage ) {
            if ( array_key_exists( 'author', $graph[ $webpage ] ) ) {
                $graph[ $webpage ]['author'] = $author;
            }
            if ( 'reviewed_by' === $state ) {
                $graph[ $webpage ]['reviewedBy'] = $person;
            }
        }

        if ( $person ) {
            $graph[] = [
                '@type' => 'Person',
                '@id'   => $person['@id'],
                'name'  => $attribution['name'] ?? '',
                'url'   => $url,
            ];
        }

        return $graph;
    }

    private static function with_faqs( array $graph, array $faqs ): array {
        $webpage = self::webpage_index( $graph );
        if ( null === $webpage ) {
            return $graph;
        }

        $piece = $graph[ $webpage ];
        $types = (array) $piece['@type'];
        if ( ! in_array( 'FAQPage', $types, true ) ) {
            $types[] = 'FAQPage';
        }
        $piece['@type'] = $types;

        $questions = [];
        foreach ( $faqs as $faq ) {
            $questions[] = [
                '@type'          => 'Question',
                'name'           => $faq['question'],
                'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $faq['answer'] ],
            ];
        }

        // A Professional's Person or a Service stays the first main entity.
        $existing            = $piece['mainEntity'] ?? [];
        $piece['mainEntity'] = array_merge( isset( $existing['@id'] ) ? [ $existing ] : $existing, $questions );

        $graph[ $webpage ] = $piece;
        return $graph;
    }

    private static function with_firm( array $piece, array $firm ): array {
        $subtype = self::FIRM_SUBTYPES[ $firm['type'] ?? '' ] ?? 'ProfessionalService';
        $types   = (array) $piece['@type'];
        if ( ! in_array( $subtype, $types, true ) ) {
            $types[] = $subtype;
        }
        $piece['@type'] = $types;

        $address = self::postal_address( (array) ( $firm['address'] ?? [] ) );
        if ( $address ) {
            $piece['address'] = $address;
        }

        if ( ! empty( $firm['area_served'] ) ) {
            $piece['areaServed'] = array_values( $firm['area_served'] );
        }

        return array_merge( $piece, self::contact( $firm ) );
    }

    private static function offices( array $organization, array $offices ): array {
        $base   = rtrim( (string) ( $organization['url'] ?? '' ), '/' ) . '/#/schema/office/';
        $pieces = [];
        $used   = [];
        foreach ( array_values( $offices ) as $i => $office ) {
            $name = $office['name'] ?? '';
            $id   = $base . ( self::slug( $name ) ?: $i + 1 );
            if ( isset( $used[ $id ] ) ) {
                $id .= '-' . ++$used[ $id ];
            } else {
                $used[ $id ] = 1;
            }
            $piece = [
                '@type' => $organization['@type'],
                '@id'   => $id,
            ];
            if ( $name ) {
                $piece['name'] = $name;
            }
            $address = self::postal_address( (array) ( $office['address'] ?? [] ) );
            if ( $address ) {
                $piece['address'] = $address;
            }
            $piece    = array_merge( $piece, self::contact( $office ) );
            $pieces[] = $piece + [ 'parentOrganization' => [ '@id' => $organization['@id'] ] ];
        }
        return $pieces;
    }

    private static function contact( array $facts ): array {
        return array_filter( [
            'telephone' => $facts['phone_number'] ?? '',
            'faxNumber' => $facts['fax_number'] ?? '',
            'email'     => $facts['email_address'] ?? '',
        ] );
    }

    private static function slug( string $name ): string {
        return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ), '-' );
    }

    private static function postal_address( array $address ): ?array {
        $parts = array_filter( [
            'streetAddress'   => $address['street_address'] ?? '',
            'addressLocality' => $address['city'] ?? '',
            'addressRegion'   => $address['province'] ?? '',
            'postalCode'      => $address['postal_code'] ?? '',
            'addressCountry'  => $address['country'] ?? '',
        ] );

        return $parts ? [ '@type' => 'PostalAddress' ] + $parts : null;
    }

    private static function webpage_index( array $graph ): ?int {
        foreach ( $graph as $index => $piece ) {
            foreach ( (array) ( $piece['@type'] ?? [] ) as $type ) {
                if ( str_ends_with( $type, 'Page' ) ) {
                    return $index;
                }
            }
        }
        return null;
    }

    // Yoast's Organization piece is <home>/#organization. A site represented as
    // a Person has no such piece (its Person+Organization sits under
    // #/schema/person/), so nothing is added.
    private static function organization_index( array $graph ): ?int {
        foreach ( $graph as $index => $piece ) {
            if ( str_ends_with( (string) ( $piece['@id'] ?? '' ), '#organization' ) ) {
                return $index;
            }
        }
        return null;
    }
}
