<?php

namespace Drupal\feeds_migrate\Plugin\migrate_plus\data_fetcher\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * The importer form for the file migrate data fetcher plugin.
 *
 * @MigrateForm(
 *   id = "http_importer_form",
 *   title = @Translation("Http Fetcher Plugin Importer Form"),
 *   form_type = "importer",
 *   parent_id = "http",
 *   parent_type = "data_fetcher"
 * )
 */
class HttpImporterForm extends DataFetcherFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $source = $this->migration->get('source');

    $form['urls'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File Location (single location only)'),
      '#default_value' => $source['urls'] ?: '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $entity->source['urls'] = [$form_state->getValue('urls')];
  }

}
