<?php

namespace Drupal\feeds_migrate\Plugin\migrate_plus\data_fetcher\form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * The configuration form for the file migrate data fetcher plugin.
 *
 * @DataFetcherForm(
 *   id = "file",
 *   title = @Translation("File Data Fetcher Plugin Form"),
 *   type = "configuration",
 *   parent = "file"
 * )
 */
class FileForm extends DataFetcherFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $entity */
    $entity = $this->entity;
    $source = $entity->get('source');

    $form['directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File Upload Directory'),
      '#default_value' => $source['data_fetcher']['directory'] ?: 'public://migrate',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $entity->source['data_fetcher']['directory'] = $form_state->getValue('directory');
  }

}
