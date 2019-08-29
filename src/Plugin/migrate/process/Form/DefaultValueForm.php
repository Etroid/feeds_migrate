<?php

namespace Drupal\feeds_migrate\Plugin\migrate\process\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginBase;

/**
 * The configuration form for entity destinations.
 *
 * @MigrateForm(
 *   id = "default_value_form",
 *   title = @Translation("Default Value Process Plugin Form"),
 *   form_type = "configuration",
 *   parent_id = "default_value",
 *   parent_type = "process"
 * )
 */
class DefaultValueForm extends MigrateFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // @TODO
  }

}
