<?php

namespace Drupal\migrate_tamper\Plugin\migrate\process\Form;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginBase;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Drupal\tamper\SourceDefinition;
use Drupal\tamper\TamperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The configuration form for entity destinations.
 *
 * @MigrateForm(
 *   id = "tamper_form",
 *   form_type = "configuration",
 *   parent_type = "process"
 * )
 */
class TamperForm extends MigrateFormPluginBase {

  /**
   * The tamper plugin.
   *
   * @var \Drupal\tamper\TamperInterface
   */
  protected $tamper;

  /**
   * Constructs a new TamperForm object.
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
   * @param \Drupal\tamper\TamperManagerInterface $tamper_manager
   *   The tamper plugin manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PluginInspectionInterface $migrate_plugin, MigrationInterface $migration, TamperManagerInterface $tamper_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migrate_plugin, $migration);

    $migrate_plugin_definition = $migrate_plugin->getPluginDefinition();

    // Instantiate Tamper plugin.
    $this->tamper = $tamper_manager->createInstance($migrate_plugin_definition['tamper_plugin_id'], $this->configuration + [
      'source_definition' => $this->getSourceDefinitionFromMigrateEntity($migration),
    ]);

    // Set additional definitions.
    $this->pluginDefinition['title'] = $migrate_plugin_definition['label'] ?? 'tamper:' . $migrate_plugin_definition['tamper_plugin_id'];
    $this->pluginDefinition['parent_id'] = 'tamper:' . $migrate_plugin_definition['tamper_plugin_id'];
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
      $container->get('plugin.manager.tamper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $this->tamper->buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    return $this->tamper->validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    return $this->tamper->submitConfigurationForm($form, $form_state);
  }

  /**
   * Creates a source definition based on the migrate entity.
   *
   * @param \Drupal\migrate\MigrationInterface $migration
   *   The migrate entity.
   *
   * @return \Drupal\tamper\SourceDefinition
   *   A source definition.
   */
  protected function getSourceDefinitionFromMigrateEntity(MigrationInterface $migration) {
    // Extract source fields.
    $fields = [];
    $source = $migration->get('source');
    if (!empty($source['fields'])) {
      foreach ($source['fields'] as $field_definition) {
        if (isset($field_definition['name']) && strlen(trim($field_definition['name']))) {
          $fields[$field_definition['name']] = $field_definition['name'];
        }
      }
    }

    return new SourceDefinition($fields);
  }

}
