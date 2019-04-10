<?php

namespace Drupal\feeds_migrate;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface FeedsMigrateUiFieldInterface.
 *
 * @package Drupal\feeds_migrate
 */
interface MappingFieldFormInterface extends PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * Get a mapping field's key.
   *
   * @param array $mapping
   *   A migration mapping configuration.
   *
   * @return string
   *   A field's key/name.
   */
  public function getKey(array $mapping);

  /**
   * Get a mapping field's label.
   *
   * @param array $mapping
   *   A migration mapping configuration.
   *
   * @return string
   *   A field's label.
   */
  public function getLabel(array $mapping);

  /**
   * Get the summary about a mapping field.
   *
   * @param array $mapping
   *   A migration mapping configuration.
   * @param string $property
   *   A field property to get the process plugin summary for.
   *
   * @return string
   */
  public function getSummary(array $mapping, $property = NULL);

  /**
   * Every field (property) can add one or many migration process plugins to
   * prepare the data before it is stored.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildProcessPluginsConfigurationForm(array &$form, FormStateInterface $form_state);

  /**
   * Returns the mapping for this field based on the configuration form.
   * 
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   A migration mapping configuration.
   */
  public function getConfigurationFormMapping(array &$form, FormStateInterface $form_state);

  /**
   * Returns whether the field is a unique field in the migration source.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   TRUE if unique, FALSE if not.
   */
  public function isUnique(array &$form, FormStateInterface $form_state);

}
