<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for adding mapping settings.
 *
 * @package Drupal\feeds_migrate\Form
 */
class MigrationMappingAddForm extends MigrationMappingFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Retrieve the destination key of the migration mapping.
    $destination_field = $form_state->getValue('destination_field', NULL);

    if (isset($destination_field)) {
      $destination_key = $destination_field;
      if ($destination_field === self::CUSTOM_DESTINATION_KEY) {
        $destination_key = $form_state->getValue('destination_key');
      }

      /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
      $migration = $this->entity;
      $mapping = [
        'destination' => [
          'key' => $destination_key,
          'field' => $this->migrationHelper->getDestinationField($migration, $destination_key),
        ],
      ];
      $this->setMapping($mapping);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;

    // Retrieve the destination key of the migration mapping.
    $destination_field = $form_state->getValue('destination_field', NULL);
    $destination_key = $destination_field;
    if ($destination_field === self::CUSTOM_DESTINATION_KEY) {
      $destination_key = $form_state->getValue('destination_key');
    }

    // Ensure the key does not already exist.
    if (!empty($this->migrationHelper->getMapping($migration, $destination_key))) {
      if ($destination_field === self::CUSTOM_DESTINATION_KEY) {
        $form_state->setErrorByName('destination_key', $this->t('A mapping for this field already exists.'));
        return;
      }

      $form_state->setErrorByName('destination_field', $this->t('A mapping with the destination key %destination_key already exists.', [
        '%destination_key' => $this->destinationKey,
      ]));
      return;
    }

    parent::validateForm($form, $form_state);
  }

}
