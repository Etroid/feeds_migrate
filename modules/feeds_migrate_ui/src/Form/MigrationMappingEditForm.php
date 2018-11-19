<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for editing mapping settings.
 *
 * @package Drupal\feeds_migrate\Form
 *
 * @todo consider moving this UX into migrate_tools module to allow editors
 * to create simple migrations directly from the admin interface
 */
class MigrationMappingEditForm extends MigrationMappingFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $migration = NULL, string $destination_key = NULL) {
    $this->destinationKey = $destination_key;
    $form = parent::buildForm($form, $form_state, $migration, $destination_key);

    return $form;
  }

}
