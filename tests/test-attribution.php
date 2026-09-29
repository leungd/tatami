<?php
/**
 * Tatami\Attribution — who fills a single author slot.
 * Loaded by tests/run.php.
 */

if ( PHP_SAPI !== 'cli' ) {
    exit;
}

use Tatami\Attribution;

// --- author() ----------------------------------------------------------------

$firm_name = 'Example Law LLP';
$firm_url  = 'https://example.com/';

assert_equal(
    [ 'name' => 'Jane Doe', 'url' => 'https://example.com/team/jane-doe/' ],
    Attribution::author(
        [ 'state' => 'written_by', 'label' => 'Written by', 'name' => 'Jane Doe', 'url' => 'https://example.com/team/jane-doe/' ],
        $firm_name,
        $firm_url
    ),
    'author: written_by names the credited person and links the profile'
);

assert_equal(
    [ 'name' => 'Guest Writer', 'url' => null ],
    Attribution::author(
        [ 'state' => 'written_by', 'label' => 'Written by', 'name' => 'Guest Writer', 'url' => null ],
        $firm_name,
        $firm_url
    ),
    'author: a name-only credit has no url'
);

assert_equal(
    [ 'name' => $firm_name, 'url' => $firm_url ],
    Attribution::author(
        [ 'state' => 'reviewed_by', 'label' => 'Reviewed by', 'name' => 'Jane Doe', 'url' => 'https://example.com/team/jane-doe/' ],
        $firm_name,
        $firm_url
    ),
    'author: reviewed_by names the Firm, never the reviewer'
);

assert_equal(
    [ 'name' => $firm_name, 'url' => $firm_url ],
    Attribution::author(
        [ 'state' => 'firm', 'label' => 'Written on behalf of', 'name' => $firm_name, 'url' => null ],
        $firm_name,
        $firm_url
    ),
    'author: firm names the Firm and links home'
);
