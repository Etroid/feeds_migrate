<?php

namespace Drupal\feeds_migrate\Plugin\migrate_plus\data_fetcher\Form;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginBase;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base class for external migrate data fetcher plugin configuration forms.
 */
abstract class DataFetcherFormPluginBase extends MigrateFormPluginBase {

  /**
   * Manager for entity types.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * MigratePluginFormBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $migrate_plugin
   *   The migrate plugin instance this form plugin is for.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PluginInspectionInterface $migrate_plugin, MigrationInterface $migration, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migrate_plugin, $migration);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, PluginInspectionInterface $migrate_plugin = NULL, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migrate_plugin,
      $migration,
      $container->get('entity_type.manager')
    );
  }

}
