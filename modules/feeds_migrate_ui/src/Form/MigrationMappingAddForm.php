<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for adding mapping settings.
 *
 * @package Drupal\feeds_migrate\Form
 *
 * @todo consider moving this UX into migrate_tools module to allow editors
 * to create simple migrations directly from the admin interface
 */
class MigrationMappingAddForm extends MigrationMappingFormBase {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $destination = $form_state->getValue('destination');
    $migration = $this->getEntity();

    // TODO save off new destination key to $entity['process'][$key]

    // Redirect the user to the edit form.
    $form_state->setRedirect('entity.migration.mapping.edit_form', [
      'migration' => $migration->id(),
      'key' => $destination,
    ]);
  }

}
