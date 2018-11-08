<?php

namespace Drupal\feeds_migrate\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\feeds_migrate\Plugin\PluginAwareInterface;
use ReflectionClass;

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

  /**
   * Copies top-level form values to entity properties.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the current form should operate upon.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {}

  /**
   * Gets the migration from the plugin.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface
   *   The migration.
   */
  protected function getMigration() {
    if (!empty($this->plugin)) {
      // Get migration from plugin. We need to use reflection here as there
      // is no public method to retrieve the plugin's migration.
      $class = new ReflectionClass(get_class($this->plugin));
      $property = $class->getProperty('migration');
      $property->setAccessible(TRUE);
      $migration = $property->getValue($this->plugin);

      return $migration;
    }
  }

}
