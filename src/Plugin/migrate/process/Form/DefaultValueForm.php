<?php

namespace Drupal\feeds_migrate\Plugin\migrate\process\form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginBase;

/**
 * The configuration form for entity destinations.
 */
class DefaultValueForm extends MigrateFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // @ TODO
  }


  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // @TODO
  }

}
