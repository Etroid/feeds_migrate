<?php

namespace Drupal\feeds_migrate\Plugin\migrate_plus\data_fetcher\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\file\Entity\File;

/**
 * The importer form for the file migrate data fetcher plugin.
 *
 * @MigrateForm(
 *   id = "file_importer_form",
 *   title = @Translation("File Data Fetcher Plugin Importer Form"),
 *   form_type = "importer",
 *   parent_id = "file",
 *   parent_type = "data_fetcher"
 * )
 */
class FileImporterForm extends DataFetcherFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $source = $this->migration->get('source');

    $form['directory'] = [
      '#type' => 'textfield',
      '#attributes' => ['disabled' => TRUE],
      '#title' => $this->t('File Upload Directory'),
      // @todo move this to defaultConfiguration
      '#default_value' => $source['data_fetcher_directory'] ?: 'public://migrate',
    ];

    $fids = [];
    if (!empty($source['urls'])) {
      foreach ($source['urls'] as $file_uri) {
        if (!empty($file_uri)) {
          /** @var \Drupal\file\FileInterface[] $file */
          $files = $this->entityTypeManager
            ->getStorage('file')
            ->loadByProperties(['uri' => $file_uri]);
          if (!empty($files)) {
            /** @var \Drupal\file\FileInterface $file */
            $file = reset($files);
            $fids[] = $file->id();
          }
        }
      }
    }

    $form['urls'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File Upload'),
      '#default_value' => $fids,
      '#upload_validators' => [
        // @todo add validation based on data parser?
        'file_validate_extensions' => ['xml csv json'],
      ],
      '#upload_location' => $source['data_fetcher_directory'] ?: 'public://migrate',
      '#required' => TRUE,
      '#multiple' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!empty($fids)) {
      $fids = $form_state->getValue('urls', []);
      /** @var \Drupal\file\FileInterface[] $file */
      $files = $this->entityTypeManager
        ->getStorage('file')
        ->loadMultiple($fids);

      // Save the uploaded files.
      foreach ($files as $file) {
        $file->setPermanent();
        $file->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Handle file uploads.
    unset($entity->source['urls']);
    $fids = $form_state->getValue('urls');
    if (!empty($fids)) {
      /** @var \Drupal\file\FileInterface[] $file */
      $files = $this->entityTypeManager
        ->getStorage('file')
        ->loadMultiple($fids);
      foreach ($files as $file) {
        $file_uri = $file->getFileUri();
        $entity->source['urls'][] = $file_uri;
      }
    }
  }

}
