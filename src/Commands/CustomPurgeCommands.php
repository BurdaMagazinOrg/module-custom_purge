<?php

namespace Drupal\custom_purge\Commands;

use Drupal\custom_purge\UrlPurger;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class CustomPurgeCommands extends DrushCommands {

  /**
   * The UrlPurger.
   *
   * @var \Drupal\custom_purge\UrlPurger
   */
  protected $urlPurger;

  /**
   * Construct of CustomPurgeCommands.
   *
   * @param \Drupal\custom_purge\UrlPurger $url_purger
   *   The custom purge url purger.
   */
  public function __construct(UrlPurger $url_purger) {
    parent::__construct();
    $this->urlPurger = $url_purger;
  }

  /**
   * Calls the purgeAllProviders method.
   *
   * @param string $url
   *   The url to purge.
   * @command custom-purge-all-providers
   */
  function drushCustomPurgeAllProviders(string $url) {
    $this->logger->info($url);
    $url_array = explode(',', $url);
    $purger = $this->urlPurger;
    $purger->purgeAllProviders($url_array);
  }

}
