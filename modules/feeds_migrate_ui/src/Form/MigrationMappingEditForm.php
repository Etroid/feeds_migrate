<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a form for editing a single migration mapping.
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
  public function buildForm(array $form, FormStateInterface $form_state, MigrationInterface $migration = NULL, string $key = NULL) {
    if (!isset($key)) {
      throw new NotFoundHttpException();
    }

    $this->key = $key;
    $this->mapping = $this->migrationEntityHelper()->getMapping($key);

    $form = parent::buildForm($form, $form_state, $migration, $key);

    return $form;
  }

}
