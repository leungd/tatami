<?php
/**
 * Tatami\SocialProfiles — Yoast Site representation profiles as { network, url }.
 * Loaded by tests/run.php.
 */

if ( PHP_SAPI !== 'cli' ) {
    exit;
}

use Tatami\SocialProfiles;

// --- from_yoast() ------------------------------------------------------------

$wpseo_social = [
    'facebook_site'     => 'https://www.facebook.com/examplelaw',
    'instagram_url'     => 'https://www.instagram.com/stale-legacy/',
    'linkedin_url'      => 'https://www.linkedin.com/company/stale-legacy/',
    'myspace_url'       => '',
    'og_default_image'  => 'https://example.com/wp-content/uploads/og.jpg',
    'opengraph'         => true,
    'pinterest_url'     => '',
    'twitter'           => true,
    'twitter_site'      => 'examplelaw',
    'twitter_card_type' => 'summary_large_image',
    'youtube_url'       => '',
    'wikipedia_url'     => '',
    'other_social_urls' => [
        'https://ca.linkedin.com/company/example-law/',
        'https://www.instagram.com/examplelaw/',
        'https://www.lawsociety.bc.ca/lsbc/apps/lkup/directory/mbr-search.cfm',
    ],
    'mastodon_url'      => '',
];

assert_equal(
    [
        [ 'network' => 'facebook', 'url' => 'https://www.facebook.com/examplelaw' ],
        [ 'network' => 'x', 'url' => 'https://x.com/examplelaw' ],
        [ 'network' => 'linkedin', 'url' => 'https://ca.linkedin.com/company/example-law/' ],
        [ 'network' => 'instagram', 'url' => 'https://www.instagram.com/examplelaw/' ],
        [ 'network' => 'link', 'url' => 'https://www.lawsociety.bc.ca/lsbc/apps/lkup/directory/mbr-search.cfm' ],
    ],
    SocialProfiles::from_yoast( $wpseo_social ),
    'full wpseo_social -> Facebook, X, then other profiles in order; legacy per-network keys ignored'
);

assert_equal( [], SocialProfiles::from_yoast( [] ), 'empty option -> []' );

assert_equal(
    [],
    SocialProfiles::from_yoast( [ 'facebook_site' => '', 'twitter_site' => '', 'other_social_urls' => [ '', '' ] ] ),
    'all-empty values -> []'
);

$twitter_inputs = [
    'examplelaw'                     => 'bare handle',
    '@examplelaw'                    => 'handle with @',
    'https://twitter.com/examplelaw' => 'pasted twitter.com URL',
    'https://x.com/examplelaw/'      => 'pasted x.com URL',
];

foreach ( $twitter_inputs as $input => $label ) {
    assert_equal(
        [ [ 'network' => 'x', 'url' => 'https://x.com/examplelaw' ] ],
        SocialProfiles::from_yoast( [ 'twitter_site' => $input ] ),
        "twitter_site $label -> https://x.com/examplelaw"
    );
}

assert_equal(
    [
        [ 'network' => 'facebook', 'url' => 'https://www.facebook.com/examplelaw' ],
        [ 'network' => 'youtube', 'url' => 'https://www.youtube.com/@examplelaw' ],
    ],
    SocialProfiles::from_yoast(
        [
            'facebook_site'     => 'https://www.facebook.com/examplelaw',
            'other_social_urls' => [
                'https://www.facebook.com/examplelaw',
                'https://www.youtube.com/@examplelaw',
                'https://www.youtube.com/@examplelaw',
            ],
        ]
    ),
    'identical URLs are listed once'
);

// --- network() ---------------------------------------------------------------

$networks = [
    'https://www.linkedin.com/in/jane'                => 'linkedin',
    'https://ca.linkedin.com/in/jane'                 => 'linkedin',
    'https://x.com/examplelaw'                        => 'x',
    'https://twitter.com/examplelaw'                  => 'x',
    'https://mobile.twitter.com/examplelaw'           => 'x',
    'https://www.youtube.com/@examplelaw'             => 'youtube',
    'https://youtu.be/dQw4w9WgXcQ'                    => 'youtube',
    'https://www.facebook.com/examplelaw'             => 'facebook',
    'https://m.facebook.com/examplelaw'               => 'facebook',
    'https://fb.com/examplelaw'                       => 'facebook',
    'https://www.instagram.com/examplelaw/'           => 'instagram',
    'https://www.tiktok.com/@examplelaw'              => 'tiktok',
    'https://www.threads.net/@examplelaw'             => 'threads',
    'https://bsky.app/profile/examplelaw.bsky.social' => 'bluesky',
    'https://www.pinterest.ca/examplelaw/'            => 'pinterest',
    'https://www.pinterest.com/examplelaw/'           => 'pinterest',
    'https://en.wikipedia.org/wiki/Example_Law'       => 'wikipedia',
    'https://www.avvo.com/attorneys/jane.html'        => 'avvo',
    'https://www.lawsociety.bc.ca/directory'          => 'link',
    'https://mastodon.social/@examplelaw'             => 'link',
    'https://example.com/'                            => 'link',
    'linkedin.com/in/jane'                            => 'link',
    'not a url'                                       => 'link',
    ''                                                => 'link',
];

foreach ( $networks as $url => $network ) {
    assert_equal( $network, SocialProfiles::network( $url ), "network('$url') -> $network" );
}
