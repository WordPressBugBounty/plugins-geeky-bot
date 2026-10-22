<?php
/**
 * The public half of geekybot.com's licence signing key (Ed25519).
 *
 * Commerce Pro only runs on a statement this key verifies: geekybot.com signs
 * every licence check it answers, and the private half never leaves that
 * server. Public by design; changing it here does not license anything.
 */
if (!defined('ABSPATH')) {
    exit;
}

return array(
    'alg' => 'Ed25519',
    'kid' => '555d63922dac',
    'public_key' => 'oaHlPEWZ29TtQErTEse9ptzpQHoWBZZoPcfgXbrhyK8=',
);
