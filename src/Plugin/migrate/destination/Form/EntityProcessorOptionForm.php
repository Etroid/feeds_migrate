<?php

namespace Drupal\feeds_migrate\Plugin\migrate\destination\Form;

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
    // Remove "entity:" from plugin ID.
    $entity_type_id = substr($this->plugin->getPluginId(), 7);

    // @todo Remove hack.
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);

    if ($bundle_key = $entity_type->getKey('bundle')) {
      $form['values'][$bundle_key] = [
        '#type' => 'select',
        '#options' => $this->bundleOptions($entity_type),
        //'#title' => $this->plugin->bundleLabel(),
        '#required' => TRUE,
        //'#default_value' => $this->plugin->bundle() ?: key($this->plugin->bundleOptions()),
        //'#disabled' => $this->plugin->isLocked(),
      ];
    }

    return $form;
  }

  /**
   * Provides a list of bundle options for use in select lists.
   *
   * @return array
   *   A keyed array of bundle => label.
   */
  public function bundleOptions($entity_type) {
    $options = [];

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
