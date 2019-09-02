<?php

namespace Drupal\feeds_migrate\Plugin\feeds_migrate\mapping_field;

use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds_migrate\MappingFieldFormBase;

/**
 * Class Default Mapping Field Form.
 *
 * @MappingFieldForm(
 *   id = "default",
 *   title = @Translation("Default Field Processor"),
 *   fields = {}
 * )
 */
class DefaultFieldForm extends MappingFieldFormBase {

  /**
   * {@inheritdoc}
   */
  public function getConfigurationFormMapping(array &$form, FormStateInterface $form_state) {
    $mapping = [];
    $properties = $form_state->getValue('properties');

    if (!$properties) {
      $mapping = parent::getConfigurationFormMapping($form, $form_state);
    }
    else {
      // Handle nested field properties.
      $properties = $form_state->getValue('properties');
      foreach ($properties as $property => $info) {
        $mapping['#properties'][$property] = [
          'plugin' => 'get',
          'source' => $info['source'],
          '#process' => [], // todo get process lines from each plugin (i.e. tamper)
        ];
      }
    }

    return $mapping;
  }

}
