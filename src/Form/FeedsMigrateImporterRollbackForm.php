<?php

namespace Drupal\feeds_migrate\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class FeedsMigrateImporterRollbackForm.
 *
 * @package Drupal\feeds_migrate\Form
 */
class FeedsMigrateImporterRollbackForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Rollback %label?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Rollback %label items?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url("entity.feeds_migrate_importer.collection");
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\feeds_migrate\FeedsMigrateImporterInterface $entity */
    $entity = $this->entity;
    $entity->setLastRun(0);
    $entity->save();

    /** @var \Drupal\feeds_migrate\FeedsMigrateExecutable $migrate_executable */
    $migrate_executable = $entity->getExecutable();
    $migrate_executable->rollback();
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
