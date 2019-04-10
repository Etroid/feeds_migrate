<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\feeds_migrate\MigrationEntityHelper;
use Drupal\feeds_migrate\MigrationEntityHelperManager;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class MigrationMappingDeleteForm.
 *
 * @package Drupal\feeds_migrate_ui\Form
 */
class MigrationMappingDeleteForm extends EntityConfirmFormBase {

  /**
   * @var \Drupal\feeds_migrate\MigrationEntityHelperManager
   */
  protected $migrationEntityHelperManager;

  /**
   * Manager for entity fields.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * The key of the destination field.
   *
   * @var string
   */
  protected $key;

  /**
   * Get the normalized process pipeline configuration describing the process
   * plugins, keyed by the destination field.
   *
   * @var array
   */
  protected $mapping;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('feeds_migrate.migration_entity_helper')
    );
  }

  /**
   * MigrationMappingFormBase constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager $field_manager
   * @param \Drupal\feeds_migrate\MigrationEntityHelperManager $migration_entity_helper_manager
   */
  public function __construct(EntityFieldManager $field_manager, MigrationEntityHelperManager $migration_entity_helper_manager) {
    $this->fieldManager = $field_manager;
    $this->migrationEntityHelperManager = $migration_entity_helper_manager;
  }

  /**
   * Returns the helper for a migration entity.
   */
  protected function migrationEntityHelper() {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;

    return $this->migrationEntityHelperManager->get($migration);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the mapping for %destination_field for migration %migration?', [
      '%destination_field' => $this->migrationEntityHelper()->getMappingFieldLabel($this->key),
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
  public function buildForm(array $form, FormStateInterface $form_state, MigrationInterface $migration = NULL, $key = NULL) {
    if (!isset($key)) {
      throw new NotFoundHttpException();
    }

    $this->key = $key;
    $this->mapping = $this->migrationEntityHelper()->getMapping($key);

    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Remove the mapping from the migration process array.
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;
    $processed_mapping = $this->migrationEntityHelper()->processMapping($this->mapping);
    $process_key = key($processed_mapping);
    $process = $migration->get('process');
    unset($process[$process_key]);
    $migration->set('process', $process);
    $migration->save();

    $this->messenger()->addMessage($this->t('Mapping for @destination_field deleted.', [
      '@destination_field' => $this->migrationEntityHelper()->getMappingFieldLabel($this->key),
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
