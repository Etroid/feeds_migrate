<?php

namespace Drupal\feeds_migrate;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface FeedsMigrateUiFieldInterface.
 *
 * @package Drupal\feeds_migrate
 */
interface MappingFieldFormInterface extends PluginInspectionInterface, PluginFormInterface, ConfigurableInterface, ContainerFactoryPluginInterface {

  /**
   * Gets this plugin's configuration.
   *
   * @param string $property
   *   The field property to get the configuraiton for.
   *
   * @return array
   *   An array of this plugin's configuration.
   */
  public function getConfiguration($property = NULL);

  /**
   * Get the destination key for a mapping field.
   *
   * @return string
   *   Destination key of the mapping field.
   */
  public function getDestinationKey();

  /**
   * Get the destination field instance for a mapping field.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   Destination field of the mapping.
   */
  public function getDestinationField();

  /**
   * Get the label about a mapping field.
   *
   * @param string $property
   *   The field property to get the process plugin label for.
   *
   * @return string
   *   Text representation of the destination.
   */
  public function getLabel($property = NULL);

  /**
   * Get the summary about a mapping field.
   *
   * @param string $property
   *   A field property to get the process plugin summary for.
   *
   * @return string
   *   Text representation of the process.
   */
  public function getSummary($property = NULL);

}
