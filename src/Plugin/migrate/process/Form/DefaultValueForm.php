<?php

namespace Drupal\feeds_migrate\Plugin\migrate\process\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginBase;

/**
 * The configuration form for entity destinations.
 *
 * @MigrateForm(
 *   id = "default_value_form",
 *   title = @Translation("Default Value"),
 *   form_type = "configuration",
 *   parent_id = "default_value",
 *   parent_type = "process"
 * )
 */
class DefaultValueForm extends MigrateFormPluginBase {

  /**
   * {@inheritdoc}
   *
   * @See \Drupal\migrate\Plugin\migrate\process\DefaultValue
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'default_value' => NULL,
      'strict' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary = $this->t('Default value: %default_value', [
      '%default_value' => $this->configuration['default_value'],
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['default_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default value'),
      '#description' => $this->t('The fixed default value to apply.'),
      '#default_value' => $this->configuration['default_value'],
    ];

    $form['strict'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use strict value checking'),
      '#description' => $this->t('When checked, applies default value when input value is NULL.'),
      '#default_value' => $this->configuration['strict'],
    ];

    return $form;
  }

}
