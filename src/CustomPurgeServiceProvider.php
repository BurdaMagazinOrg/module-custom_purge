<?php

namespace Drupal\custom_purge;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The service provider of the MSG Caching module.
 */
class CustomPurgeServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $service = $container->register('custom_purge.url_purger')
      ->setClass(UrlPurger::class)->addArgument(new Reference('config.factory'));
    $version = explode('.', \Drupal::VERSION);
    $major = (int) $version[1];
    if ($major < 4) {
      $service->addArgument(new Reference('cache.render'));
    }
    else {
      // The cache bin for the page cache has been split
      // from the render cache bin since 8.4.
      // @see https://www.drupal.org/node/2889603
      $service->addArgument(new Reference('cache.page'));
    }
  }

}
