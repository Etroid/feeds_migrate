<?php

namespace Drupal\feeds_migrate\Plugin\migrate_plus\data_fetcher\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds_migrate\FeedsMigrateImporterInterface;
use Drupal\migrate\Plugin\Migration;
use Drupal\file\Entity\File as FileEntity;

/**
 * The configuration form for the file migrate data fetcher plugin.
 */
class FileForm extends DataFetcherPluginFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File Upload Directory'),
      '#default_value' => 'public://migrate',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $fids = $form_state->getValue([
      'dataFetcherSettings',
      $this->getPluginId(),
      'file',
    ]);

    if (empty($fids)) {
      $form_state->setError($form['dataFetcherSettings'][$this->getPluginId()]['file'], $this->t('File is required'));
      return;
    }

    if ($file = FileEntity::load(reset($fids))) {
      $file->setPermanent();
      $file->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterMigration(FeedsMigrateImporterInterface $importer, Migration $migration) {
    if (!empty($importer->getFetcherSettings($this->getPluginId()))) {
      $fids = $importer->getFetcherSettings($this->getPluginId());


      if ($file = FileEntity::load(reset($fids['file']))) {

        $source_config = $migration->getSourceConfiguration();
        $source_config['urls'] = file_create_url($file->getFileUri());
        $migration->set('source', $source_config);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getParserData(array $form, FormStateInterface $form_state) {
    return $form_state->getValue([$this->getPluginId(), 'url']);
  }

}
