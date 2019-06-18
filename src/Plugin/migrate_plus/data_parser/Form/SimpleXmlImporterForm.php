<?php

namespace Drupal\feeds_migrate\Plugin\migrate_plus\data_parser\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * The importer form for the json migrate data parser plugin.
 *
 * @MigrateForm(
 *   id = "simple_xml_importer_form",
 *   title = @Translation("Simple Xml Data Parser Plugin Importer Form"),
 *   form_type = "importer",
 *   parent_id = "simple_xml",
 *   parent_type = "data_parser"
 * )
 */
class SimpleXmlImporterForm extends DataParserFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $source = $this->migration->get('source');

    $form['item_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Simple XML Item Selector'),
      '#default_value' => $source['item_selector'] ?: '',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $entity->source['item_selector'] = $form_state->getValue('item_selector');
  }

}
