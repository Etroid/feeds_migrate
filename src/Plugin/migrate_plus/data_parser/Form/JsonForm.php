<?php

namespace Drupal\feeds_migrate\Plugin\migrate_plus\data_parser\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * The configuration form for the json migrate data parser plugin.
 *
 * @MigrateForm(
 *   id = "json",
 *   title = @Translation("Json Data Parser Plugin Form"),
 *   form = "configuration",
 *   parent_id = "json",
 *   parent_type = "data_parser"
 * )
 */
class JsonForm extends DataParserFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $entity */
    $entity = $this->entity;
    $source = $entity->get('source');

    $form['item_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JSON Item Selector'),
      // @todo move this to defaultConfiguration
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
