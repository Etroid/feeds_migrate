<?php

namespace Drupal\feeds_migrate\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\migrate_plus\Entity\Migration;

/**
 * Class FeedsMigrateImporterForm.
 *
 * @package Drupal\feeds_migrate\Form
 */
class FeedsMigrateImporterForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#description' => $this->t('Label for the @type.', [
        '@type' => $entity->getEntityType()->getLabel(),
      ]),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => '\\' . $entity->getEntityType()->getClass() . '::load',
        'replace_pattern' => '[^a-z0-9_]+',
        'replace' => '_',
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $sources = [];
    /** @var Migration $migration */
    foreach (Migration::loadMultiple() as $migration) {
      $sources[$migration->id()] = $migration->label();
    }
    $form['source'] = [
      '#type' => 'select',
      '#title' => $this->t('Migration Source'),
      '#options' => $sources,
      '#default_value' => $entity->sources,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl(Url::fromRoute('entity.feeds_migrate_importer.collection'));
    return parent::save($form, $form_state);
  }

}
