<?php
/**
 * Standalone test runner for the theme's pure PHP helpers.
 *
 * Runs with plain `php` — no WordPress, no PHPUnit. Loads the libs under
 * test, then every tests/test-*.php file, and exits 1 on any failure.
 *
 *   php tests/run.php    (or: pnpm test)
 */

if ( PHP_SAPI !== 'cli' ) {
    exit;
}

require_once __DIR__ . '/../lib/Schema.lib.php';
require_once __DIR__ . '/../lib/SocialProfiles.lib.php';

$failures = [];
$passes   = 0;

function assert_equal( $expected, $actual, string $label ): void {
    global $failures, $passes;
    if ( $expected === $actual ) {
        $passes++;
        return;
    }
    $failures[] = sprintf(
        "FAIL %s\n  expected: %s\n  actual:   %s",
        $label,
        var_export( $expected, true ),
        var_export( $actual, true )
    );
}

function assert_true( $actual, string $label ): void {
    assert_equal( true, $actual, $label );
}

/**
 * A Yoast 22+ graph as emitted on a real site, host renamed.
 *
 * 'post'         — Article, WebPage, ImageObject, BreadcrumbList, WebSite,
 *                  Organization, and the raw user-derived author Person.
 * 'front'        — WebPage, BreadcrumbList, WebSite, Organization.
 * 'professional' — a profile page: WebPage (with primaryImageOfPage),
 *                  ImageObject, BreadcrumbList, WebSite, Organization.
 */
function yoast_graph_fixture( string $kind = 'post' ): array {
    $home   = 'https://example.com/';
    $org    = [ '@id' => $home . '#organization' ];
    $person = $home . '#/schema/person/4f0a3a4bd2c55ec6a7e5a8d0c1f3b9e2';

    $website = [
        '@type'           => 'WebSite',
        '@id'             => $home . '#website',
        'url'             => $home,
        'name'            => 'Example Law',
        'description'     => '',
        'publisher'       => $org,
        'potentialAction' => [
            [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $home . '?s={search_term_string}',
                ],
                'query-input' => [
                    '@type'         => 'PropertyValueSpecification',
                    'valueRequired' => true,
                    'valueName'     => 'search_term_string',
                ],
            ],
        ],
        'inLanguage'      => 'en-US',
    ];

    $organization = [
        '@type' => 'Organization',
        '@id'   => $home . '#organization',
        'name'  => 'Example Law',
        'url'   => $home,
        'logo'  => [
            '@type'      => 'ImageObject',
            'inLanguage' => 'en-US',
            '@id'        => $home . '#/schema/logo/image/',
            'url'        => $home . 'wp-content/uploads/2025/11/logo.jpg',
            'contentUrl' => $home . 'wp-content/uploads/2025/11/logo.jpg',
            'width'      => 1000,
            'height'     => 1000,
            'caption'    => 'Example Law',
        ],
        'image' => [ '@id' => $home . '#/schema/logo/image/' ],
    ];

    if ( 'front' === $kind ) {
        return [
            [
                '@type'           => 'WebPage',
                '@id'             => $home,
                'url'             => $home,
                'name'            => 'Toronto Business Lawyers | Example Law',
                'isPartOf'        => [ '@id' => $home . '#website' ],
                'about'           => $org,
                'datePublished'   => '2022-06-16T00:19:21+00:00',
                'dateModified'    => '2022-10-24T16:09:35+00:00',
                'description'     => 'Example Law advises businesses across Ontario.',
                'breadcrumb'      => [ '@id' => $home . '#breadcrumb' ],
                'inLanguage'      => 'en-US',
                'potentialAction' => [ [ '@type' => 'ReadAction', 'target' => [ $home ] ] ],
            ],
            [
                '@type'           => 'BreadcrumbList',
                '@id'             => $home . '#breadcrumb',
                'itemListElement' => [ [ '@type' => 'ListItem', 'position' => 1, 'name' => 'Home' ] ],
            ],
            $website,
            $organization,
        ];
    }

    if ( 'professional' === $kind ) {
        $url   = $home . 'team/jane-doe/';
        $image = $home . 'wp-content/uploads/2022/07/jane-doe.jpg';

        return [
            [
                '@type'              => 'WebPage',
                '@id'                => $url,
                'url'                => $url,
                'name'               => 'Jane Doe | Toronto Corporate & Commercial Lawyer',
                'isPartOf'           => [ '@id' => $home . '#website' ],
                'primaryImageOfPage' => [ '@id' => $url . '#primaryimage' ],
                'image'              => [ '@id' => $url . '#primaryimage' ],
                'thumbnailUrl'       => $image,
                'datePublished'      => '2022-07-23T18:05:06+00:00',
                'dateModified'       => '2025-09-02T20:46:21+00:00',
                'description'        => 'Jane is a corporate and commercial lawyer.',
                'breadcrumb'         => [ '@id' => $url . '#breadcrumb' ],
                'inLanguage'         => 'en-US',
                'potentialAction'    => [ [ '@type' => 'ReadAction', 'target' => [ $url ] ] ],
            ],
            [
                '@type'      => 'ImageObject',
                'inLanguage' => 'en-US',
                '@id'        => $url . '#primaryimage',
                'url'        => $image,
                'contentUrl' => $image,
                'width'      => 2048,
                'height'     => 1365,
                'caption'    => 'Photo of business lawyer, Jane Doe',
            ],
            [
                '@type'           => 'BreadcrumbList',
                '@id'             => $url . '#breadcrumb',
                'itemListElement' => [
                    [ '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $home ],
                    [ '@type' => 'ListItem', 'position' => 2, 'name' => 'Team', 'item' => $home . 'team/' ],
                    [ '@type' => 'ListItem', 'position' => 3, 'name' => 'Jane Doe' ],
                ],
            ],
            $website,
            $organization,
        ];
    }

    $url   = $home . 'blog/protecting-your-business/';
    $image = $home . 'wp-content/uploads/2026/07/departing-employee.jpg';

    return [
        [
            '@type'            => [ 'Article', 'BlogPosting' ],
            '@id'              => $url . '#article',
            'isPartOf'         => [ '@id' => $url ],
            'author'           => [ 'name' => 'Jane Editor', '@id' => $person ],
            'headline'         => 'Protecting Your Business When a Former Employee Joins a Competitor',
            'datePublished'    => '2026-07-31T19:00:00+00:00',
            'dateModified'     => '2026-08-01T01:18:16+00:00',
            'mainEntityOfPage' => [ '@id' => $url ],
            'wordCount'        => 1408,
            'commentCount'     => 0,
            'publisher'        => $org,
            'image'            => [ '@id' => $url . '#primaryimage' ],
            'thumbnailUrl'     => $image,
            'articleSection'   => [ 'Employment Law' ],
            'inLanguage'       => 'en-US',
        ],
        [
            '@type'              => 'WebPage',
            '@id'                => $url,
            'url'                => $url,
            'name'               => 'Employee Joins a Competitor? Litigation Options',
            'isPartOf'           => [ '@id' => $home . '#website' ],
            'primaryImageOfPage' => [ '@id' => $url . '#primaryimage' ],
            'image'              => [ '@id' => $url . '#primaryimage' ],
            'thumbnailUrl'       => $image,
            'datePublished'      => '2026-07-31T19:00:00+00:00',
            'dateModified'       => '2026-08-01T01:18:16+00:00',
            'description'        => 'Employer options when a former employee joins a competitor.',
            'breadcrumb'         => [ '@id' => $url . '#breadcrumb' ],
            'inLanguage'         => 'en-US',
            'potentialAction'    => [ [ '@type' => 'ReadAction', 'target' => [ $url ] ] ],
        ],
        [
            '@type'      => 'ImageObject',
            'inLanguage' => 'en-US',
            '@id'        => $url . '#primaryimage',
            'url'        => $image,
            'contentUrl' => $image,
            'width'      => 1920,
            'height'     => 1280,
            'caption'    => 'An employee leaving with a box of personal effects.',
        ],
        [
            '@type'           => 'BreadcrumbList',
            '@id'             => $url . '#breadcrumb',
            'itemListElement' => [
                [ '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $home ],
                [ '@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => $home . 'blog/' ],
                [ '@type' => 'ListItem', 'position' => 3, 'name' => 'Protecting Your Business When a Former Employee Joins a Competitor' ],
            ],
        ],
        $website,
        $organization,
        [
            '@type' => 'Person',
            '@id'   => $person,
            'name'  => 'Jane Editor',
            'image' => [
                '@type'      => 'ImageObject',
                'inLanguage' => 'en-US',
                '@id'        => $home . '#/schema/person/image/',
                'url'        => 'https://secure.gravatar.com/avatar/4f0a3a4bd2c55ec6a7e5a8d0c1f3b9e2?s=96&d=mm&r=g',
                'contentUrl' => 'https://secure.gravatar.com/avatar/4f0a3a4bd2c55ec6a7e5a8d0c1f3b9e2?s=96&d=mm&r=g',
                'caption'    => 'Jane Editor',
            ],
            'url'   => $home . 'author/jane/',
        ],
    ];
}

foreach ( glob( __DIR__ . '/test-*.php' ) as $test_file ) {
    require $test_file;
}

foreach ( $failures as $failure ) {
    echo $failure, "\n";
}

printf( "%d passed, %d failed\n", $passes, count( $failures ) );

exit( $failures ? 1 : 0 );
