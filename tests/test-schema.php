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

// --- Professional ------------------------------------------------------------

assert_equal( [ 'professional' => 'professional', 'service' => 'service' ], Schema::post_types(), 'post_types() defaults' );

$pro_url       = 'https://example.com/team/jane-doe/';
$person_id     = $pro_url . '#person';
$full_pro      = [
    'kind'          => 'professional',
    'name'          => 'Jane Doe',
    'url'           => $pro_url,
    'job_title'     => 'Partner',
    'profile_links' => [ 'https://lso.ca/lawyer/jane-doe', 'https://www.linkedin.com/in/jane-doe' ],
    'services'      => [
        [ 'name' => 'Corporate Law', 'url' => 'https://example.com/services/corporate-law/' ],
        [ 'name' => 'Commercial Real Estate', 'url' => 'https://example.com/services/commercial-real-estate/' ],
    ],
];
$minimal_pro   = [ 'kind' => 'professional', 'name' => 'Jane Doe', 'url' => $pro_url ];
$pro_graph     = yoast_graph_fixture( 'professional' );
$webpage_index = 0;

$extended = Schema::extend( $pro_graph, [ 'page' => $full_pro ] );
assert_equal( count( $pro_graph ) + 1, count( $extended ), 'full Professional -> one piece added' );
assert_equal( $person_id, $extended[ count( $pro_graph ) ]['@id'] ?? null, 'Person appended after Yoast pieces' );
assert_equal(
    [
        '@type'      => 'Person',
        '@id'        => $person_id,
        'name'       => 'Jane Doe',
        'url'        => $pro_url,
        'worksFor'   => [ '@id' => $org_id ],
        'jobTitle'   => 'Partner',
        'sameAs'     => [ 'https://lso.ca/lawyer/jane-doe', 'https://www.linkedin.com/in/jane-doe' ],
        'knowsAbout' => [
            [
                '@type' => 'Service',
                '@id'   => 'https://example.com/services/corporate-law/#service',
                'name'  => 'Corporate Law',
                'url'   => 'https://example.com/services/corporate-law/',
            ],
            [
                '@type' => 'Service',
                '@id'   => 'https://example.com/services/commercial-real-estate/#service',
                'name'  => 'Commercial Real Estate',
                'url'   => 'https://example.com/services/commercial-real-estate/',
            ],
        ],
        'image'      => [ '@id' => $pro_url . '#primaryimage' ],
    ],
    schema_piece( $extended, $person_id ),
    'full Professional -> Person with every property'
);
assert_equal( [ '@id' => $person_id ], $extended[ $webpage_index ]['mainEntity'] ?? null, 'WebPage mainEntity -> Person' );
assert_equal(
    array_diff_key( $extended[ $webpage_index ], [ 'mainEntity' => true ] ),
    $pro_graph[ $webpage_index ],
    'WebPage otherwise unchanged'
);
foreach ( $pro_graph as $i => $piece ) {
    if ( $i !== $webpage_index ) {
        assert_equal( $piece, $extended[ $i ], 'Professional leaves untouched: ' . $piece['@id'] );
    }
}

$profile_graph = $pro_graph;

$profile_graph[ $webpage_index ]['@type'] = [ 'WebPage', 'ProfilePage' ];

$extended = Schema::extend( $profile_graph, [ 'page' => $full_pro ] );
assert_equal( [ '@id' => $person_id ], $extended[ $webpage_index ]['mainEntity'] ?? null, 'ProfilePage @type array -> mainEntity set' );

$no_image = array_values(
    array_filter( $pro_graph, fn( $piece ) => ( $piece['@id'] ?? null ) !== $pro_url . '#primaryimage' )
);
$extended = Schema::extend( $no_image, [ 'page' => $minimal_pro ] );
assert_equal(
    [
        '@type'    => 'Person',
        '@id'      => $person_id,
        'name'     => 'Jane Doe',
        'url'      => $pro_url,
        'worksFor' => [ '@id' => $org_id ],
    ],
    schema_piece( $extended, $person_id ),
    'minimal Professional -> Person without jobTitle, sameAs, knowsAbout, image'
);

$extended = Schema::extend(
    $no_image,
    [ 'page' => $minimal_pro + [ 'job_title' => '', 'profile_links' => [], 'services' => [] ] ]
);
assert_equal( 5, count( schema_piece( $extended, $person_id ) ?? [] ), 'empty optional Professional facts add no properties' );

$extended = Schema::extend( $pro_graph, [ 'firm' => $full_firm + [ 'offices' => $offices ], 'page' => $full_pro ] );
$org      = schema_piece( $extended, $org_id );
assert_equal( [ 'Organization', 'LegalService' ], $org['@type'], 'Firm facts still apply on a Professional page' );
assert_equal( '416-555-0100', $org['telephone'] ?? null, 'Firm contact still applies on a Professional page' );
assert_equal( $person_id, end( $extended )['@id'] ?? null, 'Person appended after Office pieces' );
assert_equal( [ '@id' => $org_id ], schema_piece( $extended, $person_id )['worksFor'] ?? null, 'Person worksFor the Organization alongside Firm facts' );

$pro_no_org = array_values(
    array_filter( $pro_graph, fn( $piece ) => ( $piece['@id'] ?? null ) !== $org_id )
);
assert_equal( $pro_no_org, Schema::extend( $pro_no_org, [ 'page' => $full_pro ] ), 'Professional without Organization -> graph unchanged' );

assert_equal( $pro_graph, Schema::extend( $pro_graph, [ 'page' => [ 'kind' => 'recipe', 'name' => 'x', 'url' => $pro_url ] ] ), 'unknown page kind -> graph unchanged' );

// --- FAQs --------------------------------------------------------------------

$faq_rows      = [
    [ 'question' => 'How long does a trademark application take?', 'answer' => '<p>Usually <strong>12–18 months</strong> in Canada.</p>' ],
    [ 'question' => 'Do I need a lawyer to incorporate?', 'answer' => '<p>No, but we recommend one &amp; a shareholders\' agreement.</p>' ],
];
$faq_questions = [
    [
        '@type'          => 'Question',
        'name'           => 'How long does a trademark application take?',
        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => '<p>Usually <strong>12–18 months</strong> in Canada.</p>' ],
    ],
    [
        '@type'          => 'Question',
        'name'           => 'Do I need a lawyer to incorporate?',
        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => '<p>No, but we recommend one &amp; a shareholders\' agreement.</p>' ],
    ],
];
$faq_page      = [ 'kind' => 'page', 'faqs' => $faq_rows ];

$front    = yoast_graph_fixture( 'front' );
$extended = Schema::extend( $front, [ 'page' => $faq_page ] );
assert_equal( [ 'WebPage', 'FAQPage' ], $extended[0]['@type'], 'FAQs on front page -> WebPage @type gains FAQPage' );
assert_equal( $faq_questions, $extended[0]['mainEntity'] ?? null, 'FAQs on front page -> mainEntity Questions in row order' );
assert_equal( count( $front ), count( $extended ), 'FAQs add no pieces' );
assert_equal(
    array_diff_key( $extended[0], [ '@type' => true, 'mainEntity' => true ] ),
    array_diff_key( $front[0], [ '@type' => true ] ),
    'FAQs leave the rest of the WebPage unchanged'
);

$post_graph = yoast_graph_fixture( 'post' );
$extended   = Schema::extend( $post_graph, [ 'page' => $faq_page ] );
assert_equal( [ 'WebPage', 'FAQPage' ], $extended[1]['@type'], 'FAQs on a post -> string WebPage @type becomes [WebPage, FAQPage]' );
assert_equal( $faq_questions, $extended[1]['mainEntity'] ?? null, 'FAQs on a post -> mainEntity Questions' );
assert_equal( $post_graph[0], $extended[0], 'FAQs leave the Article untouched' );

assert_equal( $front, Schema::extend( $front, [ 'page' => [ 'kind' => 'page', 'faqs' => [] ] ] ), 'empty FAQs -> graph unchanged' );
assert_equal( $front, Schema::extend( $front, [ 'page' => [ 'kind' => 'page' ] ] ), 'missing FAQs -> graph unchanged' );
assert_equal(
    $front,
    Schema::extend( $front, [ 'firm' => [], 'page' => [ 'kind' => 'page', 'faqs' => [] ] ] ),
    'empty firm and empty FAQs -> graph unchanged'
);
$extended = Schema::extend( $front, [ 'firm' => $full_firm, 'page' => [ 'kind' => 'page', 'faqs' => [] ] ] );
assert_equal( $front[0], $extended[0], 'Firm facts with empty FAQs -> WebPage byte-identical' );

$faq_typed             = $front;
$faq_typed[0]['@type'] = [ 'WebPage', 'FAQPage' ];
$extended              = Schema::extend( $faq_typed, [ 'page' => $faq_page ] );
assert_equal( [ 'WebPage', 'FAQPage' ], $extended[0]['@type'], 'FAQPage already present is not duplicated' );

$extended = Schema::extend( $pro_graph, [ 'page' => $full_pro + [ 'faqs' => $faq_rows ] ] );
assert_equal( [ 'WebPage', 'FAQPage' ], $extended[ $webpage_index ]['@type'], 'Professional with FAQs -> WebPage gains FAQPage' );
assert_equal(
    array_merge( [ [ '@id' => $person_id ] ], $faq_questions ),
    $extended[ $webpage_index ]['mainEntity'] ?? null,
    'Professional with FAQs -> mainEntity is Person first, then Questions'
);
assert_equal( $person_id, end( $extended )['@id'] ?? null, 'Professional with FAQs -> Person still appended' );

$extended = Schema::extend( $pro_graph, [ 'page' => $full_pro + [ 'faqs' => [] ] ] );
assert_equal( [ '@id' => $person_id ], $extended[ $webpage_index ]['mainEntity'] ?? null, 'Professional with empty FAQs -> mainEntity stays the Person reference' );
assert_equal( 'WebPage', $extended[ $webpage_index ]['@type'], 'Professional with empty FAQs -> no FAQPage' );

$no_webpage = array_values( array_slice( $front, 1 ) );
assert_equal( $no_webpage, Schema::extend( $no_webpage, [ 'page' => $faq_page ] ), 'FAQs without a WebPage piece -> graph unchanged' );
