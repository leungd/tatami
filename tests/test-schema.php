<?php
/**
 * Tatami\Schema::extend() — Firm facts on Yoast's Organization piece.
 * Loaded by tests/run.php.
 */

if ( PHP_SAPI !== 'cli' ) {
    exit;
}

use Tatami\Schema;

function schema_piece( array $graph, string $id ): ?array {
    foreach ( $graph as $piece ) {
        if ( ( $piece['@id'] ?? null ) === $id ) {
            return $piece;
        }
    }
    return null;
}

$org_id       = 'https://example.com/#organization';
$full_address = [
    'street_address' => '100 King St W Suite 5600',
    'city'           => 'Toronto',
    'province'       => 'ON',
    'postal_code'    => 'M5X 1C9',
    'country'        => 'Canada',
];
$full_firm    = [
    'type'          => 'legal',
    'address'       => $full_address,
    'phone_number'  => '416-555-0100',
    'fax_number'    => '416-555-0101',
    'email_address' => 'info@example.com',
];

// --- Firm type -> subtype ----------------------------------------------------

$subtypes = [
    'legal'        => 'LegalService',
    'accounting'   => 'AccountingService',
    'financial'    => 'FinancialService',
    'professional' => 'ProfessionalService',
    ''             => 'ProfessionalService',
    'bakery'       => 'ProfessionalService',
];

foreach ( $subtypes as $type => $subtype ) {
    $org = schema_piece( Schema::extend( yoast_graph_fixture(), [ 'firm' => [ 'type' => $type ] ] ), $org_id );
    assert_equal( [ 'Organization', $subtype ], $org['@type'], "firm type '$type' -> $subtype" );
}

$org = schema_piece( Schema::extend( yoast_graph_fixture(), [ 'firm' => [ 'phone_number' => '416-555-0100' ] ] ), $org_id );
assert_equal( [ 'Organization', 'ProfessionalService' ], $org['@type'], 'missing firm type -> ProfessionalService' );

$graph = yoast_graph_fixture();
foreach ( $graph as $i => $piece ) {
    if ( ( $piece['@id'] ?? null ) === $org_id ) {
        $graph[ $i ]['@type'] = [ 'Organization', 'LegalService' ];
    }
}
$org = schema_piece( Schema::extend( $graph, [ 'firm' => [ 'type' => 'legal' ] ] ), $org_id );
assert_equal( [ 'Organization', 'LegalService' ], $org['@type'], 'subtype already present is not duplicated' );

// --- Address -----------------------------------------------------------------

$org = schema_piece( Schema::extend( yoast_graph_fixture( 'front' ), [ 'firm' => [ 'address' => $full_address ] ] ), $org_id );
assert_equal(
    [
        '@type'           => 'PostalAddress',
        'streetAddress'   => '100 King St W Suite 5600',
        'addressLocality' => 'Toronto',
        'addressRegion'   => 'ON',
        'postalCode'      => 'M5X 1C9',
        'addressCountry'  => 'Canada',
    ],
    $org['address'] ?? null,
    'full address -> PostalAddress with all five properties'
);

$partial = [ 'street_address' => '', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => '', 'country' => '' ];
$org     = schema_piece( Schema::extend( yoast_graph_fixture(), [ 'firm' => [ 'address' => $partial ] ] ), $org_id );
assert_equal(
    [ '@type' => 'PostalAddress', 'addressLocality' => 'Toronto', 'addressRegion' => 'ON' ],
    $org['address'] ?? null,
    'partial address omits empty properties'
);

$empty = [ 'street_address' => '', 'city' => '', 'province' => '', 'postal_code' => '', 'country' => '' ];
$org   = schema_piece( Schema::extend( yoast_graph_fixture(), [ 'firm' => [ 'type' => 'legal', 'address' => $empty ] ] ), $org_id );
assert_true( ! array_key_exists( 'address', $org ), 'all-empty address adds no address' );

// --- Contact points ----------------------------------------------------------

$org = schema_piece( Schema::extend( yoast_graph_fixture(), [ 'firm' => $full_firm ] ), $org_id );
assert_equal( '416-555-0100', $org['telephone'] ?? null, 'phone_number -> telephone' );
assert_equal( '416-555-0101', $org['faxNumber'] ?? null, 'fax_number -> faxNumber' );
assert_equal( 'info@example.com', $org['email'] ?? null, 'email_address -> email' );

$org = schema_piece(
    Schema::extend( yoast_graph_fixture(), [ 'firm' => [ 'type' => 'legal', 'phone_number' => '', 'fax_number' => '', 'email_address' => '' ] ] ),
    $org_id
);
assert_true( ! array_key_exists( 'telephone', $org ), 'empty phone_number adds no telephone' );
assert_true( ! array_key_exists( 'faxNumber', $org ), 'empty fax_number adds no faxNumber' );
assert_true( ! array_key_exists( 'email', $org ), 'empty email_address adds no email' );

// --- Unchanged graphs --------------------------------------------------------

$graph = yoast_graph_fixture();
assert_equal( $graph, Schema::extend( $graph, [] ), 'empty facts -> graph unchanged' );
assert_equal( $graph, Schema::extend( $graph, [ 'firm' => [] ] ), 'empty firm facts -> graph unchanged' );

$no_org = array_values(
    array_filter( yoast_graph_fixture(), fn( $piece ) => ( $piece['@id'] ?? null ) !== $org_id )
);
assert_equal( $no_org, Schema::extend( $no_org, [ 'firm' => $full_firm ] ), 'no Organization piece -> graph unchanged' );

$graph    = yoast_graph_fixture();
$extended = Schema::extend( $graph, [ 'firm' => $full_firm ] );
foreach ( $graph as $i => $piece ) {
    if ( ( $piece['@id'] ?? null ) !== $org_id ) {
        assert_equal( $piece, $extended[ $i ], 'untouched piece: ' . $piece['@id'] );
    }
}
assert_equal( count( $graph ), count( $extended ), 'no pieces added or removed' );

// --- Offices -----------------------------------------------------------------

$ottawa_address = [
    'street_address' => '50 O\'Connor St Suite 300',
    'city'           => 'Ottawa',
    'province'       => 'ON',
    'postal_code'    => 'K1P 6L2',
    'country'        => 'Canada',
];
$offices        = [
    [
        'name'          => 'Ottawa Office',
        'address'       => $ottawa_address,
        'phone_number'  => '613-555-0100',
        'fax_number'    => '613-555-0101',
        'email_address' => 'ottawa@example.com',
    ],
    [
        'name'          => 'Kingston',
        'address'       => [ 'street_address' => '', 'city' => 'Kingston', 'province' => 'ON', 'postal_code' => '', 'country' => '' ],
        'phone_number'  => '613-555-0200',
        'fax_number'    => '',
        'email_address' => '',
    ],
];
$ottawa_id      = 'https://example.com/#/schema/office/ottawa-office';
$kingston_id    = 'https://example.com/#/schema/office/kingston';

$graph    = yoast_graph_fixture();
$extended = Schema::extend( $graph, [ 'firm' => $full_firm + [ 'offices' => $offices ] ] );
$org      = schema_piece( $extended, $org_id );

assert_equal( count( $graph ) + 2, count( $extended ), 'two offices -> two pieces added' );
assert_equal( array_column( $graph, '@id' ), array_column( array_slice( $extended, 0, count( $graph ) ), '@id' ), 'Yoast pieces keep their order' );
assert_equal( $ottawa_id, $extended[ count( $graph ) ]['@id'] ?? null, 'first office appended after Yoast pieces' );
assert_equal( $kingston_id, $extended[ count( $graph ) + 1 ]['@id'] ?? null, 'second office appended last' );
assert_equal( [ [ '@id' => $ottawa_id ], [ '@id' => $kingston_id ] ], $org['department'] ?? null, 'Organization lists offices as department, in row order' );

assert_equal(
    [
        '@type'              => [ 'Organization', 'LegalService' ],
        '@id'                => $ottawa_id,
        'name'               => 'Ottawa Office',
        'address'            => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => '50 O\'Connor St Suite 300',
            'addressLocality' => 'Ottawa',
            'addressRegion'   => 'ON',
            'postalCode'      => 'K1P 6L2',
            'addressCountry'  => 'Canada',
        ],
        'telephone'          => '613-555-0100',
        'faxNumber'          => '613-555-0101',
        'email'              => 'ottawa@example.com',
        'parentOrganization' => [ '@id' => $org_id ],
    ],
    schema_piece( $extended, $ottawa_id ),
    'office piece: Firm type, @id, name, address, contact, parentOrganization'
);

$kingston = schema_piece( $extended, $kingston_id ) ?? [];
assert_equal( $org['@type'], $kingston['@type'], 'office @type matches the Organization' );
assert_equal( [ '@type' => 'PostalAddress', 'addressLocality' => 'Kingston', 'addressRegion' => 'ON' ], $kingston['address'] ?? null, 'office address omits empty parts' );
assert_equal( '613-555-0200', $kingston['telephone'] ?? null, 'office phone_number -> telephone' );
assert_true( ! array_key_exists( 'faxNumber', $kingston ) && ! array_key_exists( 'email', $kingston ), 'office omits empty fax and email' );
assert_equal( [ '@id' => $org_id ], $kingston['parentOrganization'] ?? null, 'office links back to Organization' );

$unnamed  = [ $offices[0], [ 'address' => $ottawa_address, 'phone_number' => '613-555-0300' ] ];
$extended = Schema::extend( yoast_graph_fixture(), [ 'firm' => [ 'type' => 'accounting', 'offices' => $unnamed ] ] );
$office   = schema_piece( $extended, 'https://example.com/#/schema/office/2' );
assert_true( null !== $office, 'unnamed office @id uses its 1-based row index' );
assert_true( ! array_key_exists( 'name', $office ?? [] ), 'unnamed office has no name' );
assert_equal( [ 'Organization', 'AccountingService' ], $office['@type'] ?? null, 'office @type follows Firm type' );

$extended = Schema::extend( yoast_graph_fixture(), [ 'firm' => $full_firm + [ 'offices' => [] ] ] );
assert_equal( count( yoast_graph_fixture() ), count( $extended ), 'empty offices -> no pieces added' );
assert_true( ! array_key_exists( 'department', schema_piece( $extended, $org_id ) ), 'empty offices -> no department' );

// --- Area served -------------------------------------------------------------

$org = schema_piece( Schema::extend( yoast_graph_fixture(), [ 'firm' => [ 'area_served' => [ 'Ottawa', 'Eastern Ontario' ] ] ] ), $org_id );
assert_equal( [ 'Ottawa', 'Eastern Ontario' ], $org['areaServed'] ?? null, 'area_served -> areaServed, in row order' );

$org = schema_piece( Schema::extend( yoast_graph_fixture(), [ 'firm' => [ 'type' => 'legal', 'area_served' => [] ] ] ), $org_id );
assert_true( ! array_key_exists( 'areaServed', $org ), 'empty area_served adds no areaServed' );

$org = schema_piece( Schema::extend( yoast_graph_fixture(), [ 'firm' => $full_firm ] ), $org_id );
assert_true( ! array_key_exists( 'areaServed', $org ), 'missing area_served adds no areaServed' );
