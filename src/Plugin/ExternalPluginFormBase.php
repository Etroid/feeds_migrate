<?php

namespace Drupal\feeds_migrate\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\feeds_migrate\Plugin\PluginAwareInterface;

/**
 * Base class for Feeds plugins that have external configuration forms.
 */
abstract class ExternalPluginFormBase implements PluginFormInterface, PluginAwareInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The Migrate plugin.
   *
   * @var object
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function setPlugin(PluginInspectionInterface $plugin) {
    $this->plugin = $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Validation is optional.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->plugin->setConfiguration($form_state->getValues());
  }

}
