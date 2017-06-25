<?php

namespace Drupal\custom_purge\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class CustomPurgeAdmin.
 *
 * @package Drupal\custom_purge\Form
 */
class CustomPurgeAdmin extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'custom_purge.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('custom_purge.settings');
    // General settings.
    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General'),
      '#open' => TRUE
    ];
    // Maximum number of urls that can be purged by single submission
    $max_url_per_request = $config->get('max_url_per_request');
    $form['general']['max_url_per_request'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Max url to purge per request'),
      '#default_value' => !empty($max_url_per_request) ? $max_url_per_request : 25 ,
    ];

    $flood_interval = $config->get('flood_interval');
    $form['general']['flood_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Flood interval'),
      '#field_suffix' => $this->t('hour(s)'),
      '#default_value' => !empty($flood_interval) ? $flood_interval : 24,
    ];

    $flood_limit = $config->get('flood_limit');
    $form['general']['flood_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Flood limit'),
      '#default_value' => !empty($flood_limit) ? $flood_limit : 100,
    ];

    $form['general']['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain'),
      '#default_value' => $config->get('domain')
    ];

    // Varnish related settings.
    $form['varnish'] = [
      '#type' => 'details',
      '#title' => $this->t('Varnish'),
      '#open' => TRUE
    ];
    $form['varnish']['varnish_port'] = [
      '#type' => 'number',
      '#title' => $this->t('Port'),
      '#default_value' => $config->get('varnish_port')
    ];
    $form['varnish']['varnish_ip'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IP'),
      '#default_value' => $config->get('varnish_ip')
    ];
    $form['varnish']['varnish_acquia_environment'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Acquia environment'),
      '#default_value' => $config->get('varnish_acquia_environment')
    ];

    // Cloudflare related settings.
    $form['cloudflare'] = [
      '#type' => 'details',
      '#title' => $this->t('Cloudflare'),
      '#open' => TRUE
    ];

    $cloudflare_settings_url = Url::fromRoute('cloudflare.admin_settings_form')->toString();
    $form['cloudflare']['info'] = [
      '#markup' => $this->t('Cloudflare can be configured via <a href="@cloudflare_settings">CloudFlare Settings</a>', ['@cloudflare_settings' => $cloudflare_settings_url]),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('custom_purge.settings')
      ->set('max_url_per_request', $form_state->getValue('max_url_per_request'))
      ->set('domain', $form_state->getValue('domain'))
      ->set('flood_interval', $form_state->getValue('flood_interval'))
      ->set('flood_limit', $form_state->getValue('flood_limit'))
      ->set('varnish_port', $form_state->getValue('varnish_port'))
      ->set('varnish_ip', $form_state->getValue('varnish_ip'))
      ->set('varnish_acquia_environment', $form_state->getValue('varnish_acquia_environment'))
      ->save();
  }
}
