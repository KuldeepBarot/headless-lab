<?php

namespace Drupal\config_expose\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigExposeForm extends ConfigFormBase {

  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * Tracks the valid config entity type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $definitions = [];

  /**
   * Constructs a new ConfigExposeSettingsForm.
   *
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config storage.
   */
  public function __construct(StorageInterface $config_storage) {
    $this->configStorage = $config_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_expose_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'config_expose.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $configs = $this->getConfigsList();
    $selected = $this->config('config_expose.settings')->get('selected_configs') ?: [];
    $form['configurations'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select configurations to be exposed'),
      '#options' => $configs,
      '#default_value' => $selected,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * Returns a list of configurations.
   *
   * @return array
   *   A list of available configurations.
   */
  private function getConfigsList() {
    $config_name = $this->configStorage->listAll();
    $config_list = array_combine($config_name, $config_name);
    return $config_list;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_configs = array_values(array_filter($form_state->getValue('configurations')));
    $this->config('config_expose.settings')
      ->set('selected_configs', $selected_configs)
      ->save();

    parent::submitForm($form, $form_state);
  }
}
