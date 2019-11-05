<?php

use Drupal\custom_purge;

function custom_purge_drush_command() {
  $items = array();
  $items['custom-purge-all-providers'] = array(
    'description' => "Calls the purgeAllProviders method.",
    'arguments' => array(
      'url' => 'The url to purge.',
    ),
  );
  return $items;
}

function drush_custom_purge_all_providers(string $url) {
  drush_print($url);
  $url_array = explode(',', $url);
  $purger = \Drupal::service('custom_purge.url_purger');
  $purger->purgeAllProviders($url_array);
}
