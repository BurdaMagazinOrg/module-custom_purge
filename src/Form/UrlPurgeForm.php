<?php

namespace Drupal\custom_purge\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_purge\UrlPurger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UrlPurgeForm.
 *
 * @package Drupal\custom_purge\Form
 */
class UrlPurgeForm extends FormBase {

  /**
   * The flood control mechanism.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The Url purger.
   *
   * @var \Drupal\custom_purge\UrlPurger
   */
  protected $purger;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood control mechanism.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date service.
   * @param \Drupal\custom_purge\UrlPurger $purger
   *   The Url purger.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(FloodInterface $flood, DateFormatterInterface $date_formatter, UrlPurger $purger, ModuleHandlerInterface $module_handler) {
    $this->flood = $flood;
    $this->dateFormatter = $date_formatter;
    $this->purger = $purger;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flood'),
      $container->get('date.formatter'),
      $container->get('custom_purge.url_purger'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_purge_url_purger';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get config settings.
    $custom_purge_config = $this->config('custom_purge.settings');
    $max_url_per_request = $custom_purge_config->get('max_url_per_request');

    // Flood protection.
    $limit = $custom_purge_config->get('flood_limit');
    // Get interval - convert to seconds.
    $interval = $custom_purge_config->get('flood_interval') * 60 * 60;
    // Number of flood entries.
    $flood_count= $this->getFloodCount('custom_purge_url_purger');

    $flood_info = $this->t('You already cleared %flood_count of %limit cache entries in @interval.', [
      '%limit' => $limit,
      '@interval' => $this->dateFormatter->formatInterval($interval),
      '%flood_count' => $flood_count,
    ]);

    $form['cache_clear_info'] = [
      '#markup' => $flood_info
    ];

    // In case of reached flood count we disable the form and don't allow further submissions.
    $disabled = FALSE;
    if ($flood_count >= $limit) {
      $disabled = TRUE;
      // Show message to users.
      drupal_set_message($this->t('You cannot clear more than %limit cache entries in @interval. Try again later.', [
        '%limit' => $limit,
        '@interval' => $this->dateFormatter->formatInterval($interval),
      ]), 'warning');
    }

    // Textarea to enter urls to be purged after submitting the form
    $form['purgable_urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('URLs that will be purged from caches (drupal, varnish, cloudflare)'),
      '#description' => $this->t('Maximum number of purgable URLs @max_numer Enter one IP per line.', ['@max_numer' => $max_url_per_request]),
      '#required' => TRUE,
      '#rows' => 20
    ];
    // Submit the form.
    $form['purge_submit'] = array(
      '#type' => 'submit',
      '#value' => t('Purge urls from caches'),
      '#button_type' => 'primary',
      '#disabled' => $disabled,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $config = $this->config('custom_purge.settings');

    // Flood protection.
    $limit = $config->get('flood_limit');
    // Get interval - convert to seconds.
    $interval = $config->get('flood_interval') * 60 * 60;

    if (!$this->flood->isAllowed('custom_purge_url_purger', $limit, $interval, 'custom_purge_url_purger')) {
      $form_state->setErrorByName('', $this->t('You cannot clear more than %limit cache entries in @interval. Try again later.', array(
        '%limit' => $limit,
        '@interval' => $this->dateFormatter->formatInterval($interval),
      )));
    }

    // Validation for max_url_per_request.
    $max_url_per_request = $config->get('max_url_per_request');
    // Get urls to be pruged.
    $urls = preg_split('/\r\n|\r|\n/', $form_state->getValue('purgable_urls'));

    // Check for url count.
    if (count($urls) <= $max_url_per_request) {
      foreach ($urls as $url) {
        // Check for valid urls - if less/ equal then max_url_per_request.
        if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED)) {
          $form_state->setErrorByName('purgable_urls', $this->t('Please provide valid urls in form below.'));
        }
      }
    }
    else {
      $form_state->setErrorByName('purgable_urls', $this->t('Maximum number of purgable URLs @max_numer. Please enter less or equal.', ['@max_numer' => $max_url_per_request]));
    }

    // Set urls to be purged in form-state.
    $form_state->set('purgable_urls_array', $urls);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get urls from form_state.
    $urls = $form_state->get('purgable_urls_array');
    // No further processing if urls are empty - just show warning.
    if (empty($urls)) {
      drupal_set_message(t('No url(s) were provided'), 'warning');
      return;
    }

    // Purge drupal caches.
    $this->purgeDrupalCacheRender($urls);
    // Purge varnish caches.
    $this->purgeVarnishCache($urls);
    // Purge cloudflare caches.
    $this->purgeCloudflareCache($urls);

    // Allow other modules to act upon the manual purge of Urls.
    $this->moduleHandler->invokeAll('manual_custom_purge', [$urls]);

    // Register flood event for each url.
    $flood_interval = $this->config('custom_purge.settings')->get('flood.interval') * 60 * 60;
    foreach ($urls as $url){
      $this->flood->register('custom_purge_url_purger', $flood_interval, 'custom_purge_url_purger');
    }
  }

  /**
   * Clean up drupal cache_render table by given urls.
   *
   * @param $urls
   *  - array of urls to be purged.
   */
  public function purgeDrupalCacheRender($urls) {
    $this->purger->purgeDrupalCacheRender($urls);

    // Show status message.
    drupal_set_message(t('Drupal cache_render was purged successfully - processed @processed url(s)', ['@processed' => count($urls)]));

    // Add log entry for purged urls.
    $message = 'purgeDrupalCache <pre>' . print_r($urls, TRUE) . '</pre>';
    \Drupal::logger('custom_purge')->notice($message);
  }

  /**
   * Clean up varnish cache with given urls.
   *
   * @param $urls
   *  - array of urls to be purged.
   */
  public function purgeVarnishCache($urls) {
    $info = $this->purger->purgeVarnishCache($urls);

    // Check for possible errors / basic logging.
    if (count($info['errors'])) {
      // Show status message.
      drupal_set_message(t('Varnish was purged successfully - processed @processed/@urls url(s). Please check logs fore more information.', [
        '@processed' => count($info['processed']),
        '@urls' => count($urls)
      ]), 'warning');

      // Add log entry for erroneous purged urls.
      $message_errors = 'purgeVarnishCache error: <pre>' . print_r($info['errors'], TRUE) . '</pre>';
      \Drupal::logger('custom_purge')->alert($message_errors);

      // Add log entry for successfully purged urls.
      if (count($info['processed'])) {
        $message = 'purgeVarnishCache success <pre>' . print_r($info['processed'], TRUE) . '</pre>';
        \Drupal::logger('custom_purge')->info($message);
      }
    }
    else {
      // Show status message.
      drupal_set_message(t('Varnish was purged successfully - processed @processed url(s)', ['@processed' => count($info['processed'])]));

      // Add log entry for purged urls.
      $message = 'purgeVarnishCache success <pre>' . print_r($urls, TRUE) . '</pre>';
      \Drupal::logger('custom_purge')->info($message);
    }
  }

  /**
   * Purge cloudflare cache for fiven urls.
   *
   * @param $url
   */
  function purgeCloudflareCache($urls) {
    $info = $this->purger->purgeCloudflareCache($urls);

    // Logging / Messages.
    if (count($info['processed'])) {
      // Show status message.
      drupal_set_message(t('Cloudflare cache was purged successfully - processed @processed url(s)', ['@processed' => count($urls)]));

      // Add log entry for purged urls.
      $message = 'purgeCloudflareCache success <pre>' . print_r($urls, TRUE) . '</pre>';
      \Drupal::logger('custom_purge')->info($message);
    }
    else {
      // Show status message.
      drupal_set_message(t('Error while clearing cloudflare cache for given urls. Please check log.'));

      // Add log entry for erroneous urls.
      $message = 'purgeCloudflareCache error <pre>' . print_r($urls, TRUE) . '</pre>';
      \Drupal::logger('custom_purge')->alert($message);
    }
  }

  /**
   * Get flood counts for given identifier.
   *
   * @param $identifier
   * @return int
   */
  protected function getFloodCount($identifier) {
    // Custom db query to get row count for checking flood protection.
    $query = \Drupal::database()->select('flood', 'f');
    $query->addField('f', 'fid');
    $query->condition('event', 'custom_purge_url_purger');
    $query->condition('identifier', $identifier);
    return $query->countQuery()->execute()->fetchField();
  }
}
