<?php

namespace Drupal\feeds_migrate\Plugin\migrate_plus\data_fetcher\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * The configuration form for the file migrate data fetcher plugin.
 *
 * @MigrateForm(
 *   id = "http",
 *   title = @Translation("Http Fetcher Plugin Form"),
 *   form = "configuration",
 *   parent_id = "http",
 *   parent_type = "data_fetcher"
 * )
 */
class HttpForm extends DataFetcherFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $entity */
    $entity = $this->entity;
    $source = $entity->get('source');

    $form['urls'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File Location (single location only)'),
      '#default_value' => $source['urls'] ?: 'public://migrate/data.xml',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // The urls property goes right into source, not under data_fetcher.
    $entity->source['urls'] = $form_state->getValue('urls');
  }

}
