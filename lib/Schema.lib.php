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
 *   from the house-named ACF fields. No-ops without Yoast or ACF.
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
 *     ],
 *   ]
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

    public function filter_graph( $graph, $context ) {
        if ( ! is_array( $graph ) || ! function_exists( 'get_field' ) ) {
            return $graph;
        }

        return self::extend( $graph, [ 'firm' => $this->firm_facts() ] );
    }

    private function firm_facts(): array {
        return array_filter( [
            'type'          => get_field( 'firm_type', 'option' ),
            'address'       => array_filter( (array) get_field( 'address', 'option' ) ),
            'phone_number'  => get_field( 'phone_number', 'option' ),
            'fax_number'    => get_field( 'fax_number', 'option' ),
            'email_address' => get_field( 'email_address', 'option' ),
        ] );
    }

    public static function extend( array $graph, array $facts ): array {
        $org = self::organization_index( $graph );
        if ( null === $org ) {
            return $graph;
        }

        if ( ! empty( $facts['firm'] ) ) {
            $graph[ $org ] = self::with_firm( $graph[ $org ], $facts['firm'] );
        }

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

        $contact = [
            'telephone' => $firm['phone_number'] ?? '',
            'faxNumber' => $firm['fax_number'] ?? '',
            'email'     => $firm['email_address'] ?? '',
        ];

        return array_merge( $piece, array_filter( $contact ) );
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

    private static function organization_index( array $graph ): ?int {
        foreach ( $graph as $index => $piece ) {
            if ( in_array( 'Organization', (array) ( $piece['@type'] ?? [] ), true ) ) {
                return $index;
            }
        }
        return null;
    }
}
