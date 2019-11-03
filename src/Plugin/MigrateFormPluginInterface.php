<?php

namespace Drupal\feeds_migrate\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for migrate plugins that have external configuration forms.
 */
interface MigrateFormPluginInterface extends PluginInspectionInterface, PluginFormInterface, ConfigurableInterface, ContainerFactoryPluginInterface {

  /**
   * Indicates that the form is displayed in the context of a migration.
   */
  const FORM_TYPE_CONFIGURATION = 'configuration';

  /**
   * Indicates that the form is displayed directly below the plugin selector.
   */
  const FORM_TYPE_OPTION = 'option';

  /**
   * Indicates that a form is displayed in the context of an importer.
   */
  const FORM_TYPE_IMPORTER = 'importer';

  /**
   * Get the summary about this plugin.
   *
   * @return string
   *   Text representation of the plugin.
   */
  public function getSummary();

  /**
   * Copies top-level form values to entity properties.
   *
   * This should not change existing entity properties that are not being edited
   * by this form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the current form should operate upon.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @deprecated This will be removed in favor of ::getConfiguration.
   * Plugins should not save its configuration directly on the parent entity.
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state);

}
