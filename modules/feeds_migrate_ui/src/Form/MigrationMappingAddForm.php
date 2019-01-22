<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;

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
  public function buildForm(array $form, FormStateInterface $form_state, MigrationInterface $migration = NULL, string $key = NULL) {
    // Retrieve the destination key of the migration mapping.
    if ($key = $form_state->getValue('destination_field', FALSE)) {
      if ($key === self::CUSTOM_DESTINATION_KEY) {
        $key = $form_state->getValue('destination_key');
      }

      // Stub out a new mapping.
      $mapping = $this->migrationEntityHelper()->getDefaultMapping($key);
      if ($key !== self::CUSTOM_DESTINATION_KEY) {
        $destination_field = $this->migrationEntityHelper()->getMappingField($key);
        $mapping['#destination']['#type'] = $destination_field->getType();
        $mapping['#destination']['#field'] = $destination_field;
      }

      $this->key = $key;
      $this->mapping = $mapping;
    }

    return parent::buildForm($form, $form_state, $migration, $key);
  }

  /**
   * Validates the mapping before saving it to the migration entity.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Ensure the key does not already exist.
    if ($this->migrationEntityHelper()->mappingExists($this->key)) {
      if ($this->key === self::CUSTOM_DESTINATION_KEY) {
        $form_state->setErrorByName('destination_key', $this->t('A mapping for this field already exists.'));
        return;
      }

      $form_state->setErrorByName('destination_field', $this->t('A mapping with the destination key %destination_key already exists.', [
        '%destination_key' => $this->key,
      ]));
      return;
    }
  }

}
