<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\migrate_plus\Entity\MigrationInterface;

/**
 * Class MigrationMappingDeleteForm.
 *
 * @package Drupal\feeds_migrate_ui\Form
 */
class MigrationMappingDeleteForm extends EntityConfirmFormBase {

  /**
   * @var MigrationInterface
   */
  protected $migration;

  /**
   * @var string
   */
  protected $destinationKey;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, MigrationInterface $migration = NULL, $destination_key = NULL) {
    $this->migration = $migration;
    $this->destinationKey = $destination_key;

    $form = parent::buildForm($form, $form_state);
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the mapping for %name?', [
      '%name' => $this->destinationKey,
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Remove mapping from migration entity
    // TODO move this deletion logic.
    unset($this->migration->process[$this->destinationKey]);

    drupal_set_message($this->t('@target deleted.', [
      '@target' => $this->destinationKey,
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
