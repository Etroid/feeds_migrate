<?php

namespace Drupal\feeds_migrate\Plugin\feeds_migrate\mapping_field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds_migrate\MappingFieldFormBase;

/**
 * Class Text Area Mapping Field Form.
 *
 * @MappingFieldForm(
 *   id = "text_area",
 *   title = @Translation("Field mapping for text areas"),
 *   fields = {
 *     "text_long",
 *     "text_with_summary",
 *   }
 * )
 */
class TextAreaFieldForm extends MappingFieldFormBase {

  /**
   * {@inheritdoc}
   */
  protected function buildProcessPluginsConfigurationForm(array $form, FormStateInterface $form_state, $property = NULL) {
    if ($property === 'format') {
      $test = 1;
    }
    else {
      parent::buildProcessPluginsConfigurationForm($form, $form_state);
    }
  }

}
