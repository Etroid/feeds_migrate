<?php

namespace Drupal\feeds_migrate\Plugin\migrate\destination\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The configuration form for entity destinations.
 */
class EntityContentOptionForm extends DestinationFormPluginBase {

  /**
   * Manager for entity types.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Manager for content entity bundles.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleManager;

  /**
   * EntityContentOptionForm constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   *   The plugin manager for config entity-based migrations.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_manager
   *   The bundle manager service.
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $bundle_manager) {
    parent::__construct($migration_plugin_manager);
    $this->entityTypeManager = $entity_type_manager;
    $this->bundleManager = $bundle_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $entity_type = $this->getEntityType();
    if ($entity_type && $bundle_key = $entity_type->getKey('bundle')) {
      $form['default_bundle'] = [
        '#type' => 'select',
        '#options' => $this->getBundleOptionsList($entity_type->id()),
        '#title' => $entity_type->getBundleLabel(),
        '#required' => TRUE,
        '#default_value' => $this->getSetting('default_bundle'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $entity_type = $this->getEntityType();
    if ($entity_type && $bundle_key = $entity_type->getKey('bundle')) {
      $entity->destination['default_bundle'] = $form_state->getValue('default_bundle');
    }
    else {
      unset($entity->destination['default_bundle']);
    }
  }

  /**
   * Provides a list of bundle options for use in select lists.
   *
   * @param string $entity_type_id
   *   The entity type for which to retrieve all available bundles.
   *
   * @return array
   *   A keyed array of bundle => label.
   */
  public function getBundleOptionsList($entity_type_id) {
    $options = [];

    foreach ($this->bundleManager->getBundleInfo($entity_type_id) as $bundle => $info) {
      if (!empty($info['label'])) {
        $options[$bundle] = $info['label'];
      }
      else {
        $options[$bundle] = $bundle;
      }
    }

    return $options;
  }

  /**
   * Get entity type definition.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface|null
   *   The entity type definition for the current plugin, if there is one.
   */
  protected function getEntityType() {
    // Remove "entity:" from plugin ID.
    $entity_type_id = substr($this->plugin->getPluginId(), 7);

    return $this->entityTypeManager->getDefinition($entity_type_id);
  }

}
