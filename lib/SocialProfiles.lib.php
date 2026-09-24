<?php
/**
 * The Firm's social profiles, read from Yoast SEO → Site representation.
 *
 * Yoast stores them in the autoloaded `wpseo_social` option. Only the
 * fields Yoast still edits and emits as Organization `sameAs` are read:
 * `facebook_site`, `twitter_site` (a bare handle), `other_social_urls`.
 * The older per-network keys (`instagram_url`, `linkedin_url`, …) were
 * copied into `other_social_urls` by Yoast 18.9 but never cleared, so
 * reading them would resurrect profiles an editor has since removed.
 *
 * Pure — no WordPress calls; tests/run.php loads this file under plain
 * `php`. The adapter is one line in Site::add_to_context().
 *
 *   SocialProfiles::from_yoast( get_option( 'wpseo_social' ) )
 *   // [ [ 'network' => 'linkedin', 'url' => 'https://…' ], … ]
 *
 * `network` is a name for a derivative's `macros/icon.twig`: a known
 * network (facebook, x, instagram, linkedin, youtube, tiktok, threads,
 * bluesky, pinterest, wikipedia, avvo) or `link` for any other host.
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

namespace Tatami;

class SocialProfiles {

    private const NETWORKS = [
        'facebook',
        'x',
        'instagram',
        'linkedin',
        'youtube',
        'tiktok',
        'threads',
        'bluesky',
        'pinterest',
        'wikipedia',
        'avvo',
    ];

    // Host labels that differ from the network name.
    private const ALIASES = [
        'twitter' => 'x',
        'fb'      => 'facebook',
        'youtu'   => 'youtube',
        'bsky'    => 'bluesky',
    ];

    /**
     * @param array $social The `wpseo_social` option.
     * @return array<int, array{network: string, url: string}>
     */
    public static function from_yoast( array $social ): array {
        $urls = [
            (string) ( $social['facebook_site'] ?? '' ),
            self::twitter_url( (string) ( $social['twitter_site'] ?? '' ) ),
            ...array_map( 'strval', (array) ( $social['other_social_urls'] ?? [] ) ),
        ];

        $profiles = [];
        foreach ( array_unique( array_filter( array_map( 'trim', $urls ) ) ) as $url ) {
            $profiles[] = [
                'network' => self::network( $url ),
                'url'     => $url,
            ];
        }

        return $profiles;
    }

    public static function network( string $url ): string {
        $host = parse_url( $url, PHP_URL_HOST );
        if ( ! is_string( $host ) || '' === $host ) {
            return 'link';
        }

        $labels = explode( '.', strtolower( $host ) );
        if ( count( $labels ) < 2 ) {
            return 'link';
        }

        $label = $labels[ count( $labels ) - 2 ];
        $label = self::ALIASES[ $label ] ?? $label;

        return in_array( $label, self::NETWORKS, true ) ? $label : 'link';
    }

    // Yoast saves a bare handle, but accept an `@handle` or a pasted profile URL.
    private static function twitter_url( string $handle ): string {
        $handle = trim( $handle );
        if ( preg_match( '`^https?://(?:www\.|mobile\.)?(?:twitter|x)\.com/([A-Za-z0-9_]+)`i', $handle, $matches ) ) {
            $handle = $matches[1];
        }
        $handle = ltrim( $handle, '@' );

        return '' === $handle ? '' : 'https://x.com/' . $handle;
    }
}
