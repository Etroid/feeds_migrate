<?php

namespace Drupal\feeds_migrate\Plugin\migrate\destination\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds_migrate\Plugin\ExternalPluginFormBase;

/**
 * The configuration form for entity destinations.
 */
class EntityProcessorOptionForm extends ExternalPluginFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $entity_type = $this->getEntityType();
    if ($entity_type && $bundle_key = $entity_type->getKey('bundle')) {
      $form['default_bundle'] = [
        '#type' => 'select',
        '#options' => $this->bundleOptions($entity_type),
        '#title' => $entity_type->getBundleLabel(),
        '#required' => TRUE,
        '#default_value' => $this->getSetting('default_bundle'),
        //'#disabled' => $this->plugin->isLocked(),
      ];
    }

    return $form;
  }

  /**
   * Returns entity type definition.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface|null
   *   The entity type definition, if there is one.
   */
  protected function getEntityType() {
    // Remove "entity:" from plugin ID.
    $entity_type_id = substr($this->plugin->getPluginId(), 7);

    // @todo Remove hack.
    return \Drupal::entityTypeManager()->getDefinition($entity_type_id);
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
   * @return array
   *   A keyed array of bundle => label.
   */
  public function bundleOptions($entity_type) {
    $options = [];

    // @todo Remove hack.
    $bundle_info = \Drupal::service('entity_type.bundle.info');
    foreach ($bundle_info ->getBundleInfo($entity_type->id()) as $bundle => $info) {
      if (!empty($info['label'])) {
        $options[$bundle] = $info['label'];
      }
      else {
        $options[$bundle] = $bundle;
      }
    }

    return $options;
  }

}
