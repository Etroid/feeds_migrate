<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\feeds_migrate\MigrationHelper;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class MigrationMappingDeleteForm.
 *
 * @package Drupal\feeds_migrate_ui\Form
 */
class MigrationMappingDeleteForm extends EntityConfirmFormBase {

  /**
   * Manager for entity fields.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * Helper service for migration entity.
   *
   * @var \Drupal\feeds_migrate\MigrationHelper
   */
  protected $migrationHelper;

  /**
   * The destination field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $destinationField;

  /**
   * The destination key.
   *
   * This is filled out when we are not migrating into a standard Drupal field
   * instance (e.g. table column name, virtual field etc...)
   *
   * @var string
   */
  protected $destinationKey;

  /**
   * Field mapping for this migration.
   *
   * @var array
   */
  protected $mapping = [];

  /**
   * Creates a new MigrationMappingDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager $field_manager
   *   Field manager service.
   * @param \Drupal\feeds_migrate\MigrationHelper $migration_helper
   *   Helper service for migration entity.
   */
  public function __construct(EntityFieldManager $field_manager, MigrationHelper $migration_helper) {
    $this->fieldManager = $field_manager;
    $this->migrationHelper = $migration_helper;
  }

  /**
   * Gets the label for the destination field - if any.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The label of the field, or key if custom property.
   */
  public function getDestinationFieldLabel() {
    // Get field label.
    if (isset($this->destinationField)) {
      $label = $this->destinationField->getLabel();
    }
    else {
      $label = $this->destinationKey;
    }

    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the mapping for %destination_field for migration %migration?', [
      '%destination_field' => $this->getDestinationFieldLabel(),
      '%migration' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url("entity.migration.mapping.list", [
      'migration' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!isset($this->destinationKey)) {
      throw new NotFoundHttpException();
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;
    // Remove the mapping from the migration process array.
    $this->migrationHelper->deleteMapping($migration, $this->mapping);
    $migration->save();

    $this->messenger()->addMessage($this->t('Mapping for @destination_field deleted.', [
      '@destination_field' => $this->getDestinationFieldLabel(),
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
