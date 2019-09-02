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
 *   title = @Translation("Default Processor"),
 *   fields = {}
 * )
 */
class DefaultFieldForm extends MappingFieldFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $mapping = $this->configuration;
    $field = $mapping['#destination']['#field'] ?? FALSE;

    if ($field && $field instanceof FieldConfigInterface) {
      return $this->buildContentFieldForm($form, $form_state, $field);
    }

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * Build a form for a content field.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   * @param FieldConfigInterface $field
   *   A field config entity.
   *
   * @return array
   *   Configuration form array.
   */
  protected function buildContentFieldForm(array $form, FormStateInterface $form_state, FieldConfigInterface $field) {
    $mapping = $this->configuration;
    /** @var  $field_properties */
    $field_properties = $this->getFieldProperties($field);

    foreach ($field_properties as $property => $info) {
      $element = [
        '#title' => $this->t('Mapping for field property %property.', ['%property' => $info->getName()]),
        '#type' => 'details',
        '#group' => 'plugin_settings',
        '#open' => TRUE,
      ];

      $element['source'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Source'),
        '#default_value' => $mapping['#properties'][$property]['source'] ?? '',
      ];

      $this->buildProcessPluginsConfigurationForm($element, $form_state);

      $form['properties'][$property] = $element;
    }

    return $form;
  }

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
