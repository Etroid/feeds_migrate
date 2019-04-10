<?php

namespace Drupal\feeds_migrate\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;

/**
 * Interface for migrate plugins that have external configuration forms.
 */
interface MigrateFormPluginInterface extends ContainerInjectionInterface {

  /**
   * Sets the migration entity for this plugin.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $entity
   *   The migration Entity.
   */
  public function setEntity(MigrationInterface $entity);

  /**
   * Sets the plugin for this object.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin.
   */
  public function setPlugin(PluginInspectionInterface $plugin);

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state);

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state);

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
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state);

}
