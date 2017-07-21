<?php
/**
 * @file
 * Hooks provided by the custom_purge module.
 */

/**
 * Allow other modules to act upon the manual purge of Urls.
 *
 * @param string[] $urls
 *   Urls which were (not) successfully purged.
 */
function hook_manual_custom_purge(array $urls) {}
