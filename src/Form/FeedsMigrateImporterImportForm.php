<?php

namespace Drupal\feeds_migrate\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class FeedsMigrateImporterImportForm.
 *
 * @package Drupal\feeds\Form
 */
class FeedsMigrateImporterImportForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to import the feed %feed?', ['%feed' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->entity->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Import');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\feeds_migrate\FeedsMigrateBatchExecutable $migrate_batch_executable */
    $migrate_batch_executable = $this->entity->getBatchExecutable();
    $migrate_batch_executable->batchImport();
  }

}
