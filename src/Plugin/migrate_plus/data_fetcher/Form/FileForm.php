<?php

namespace Drupal\feeds_migrate\Plugin\migrate_plus\data_fetcher\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * The configuration form for the file migrate data fetcher plugin.
 *
 * @MigrateForm(
 *   id = "file",
 *   title = @Translation("File Data Fetcher Plugin Form"),
 *   form = "configuration",
 *   parent_id = "file",
 *   parent_type = "data_fetcher"
 * )
 */
class FileForm extends DataFetcherFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $source = $this->entity->get('source');

    $form['directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File Upload Directory'),
      // @todo move this to defaultConfiguration
      '#default_value' => $source['data_fetcher']['directory'] ?: 'public://migrate',
      '#access' => $this->getContext() === self::CONTEXT_MIGRATION,
    ];

    $fids = [];
    if (!empty($source['urls'])) {
      foreach ($source['urls'] as $file_uri) {
        if (!empty($file_uri)) {
          /** @var \Drupal\file\FileInterface[] $file */
          $files = \Drupal::entityTypeManager()
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
      '#upload_location' => $source['data_fetcher']['directory'] ?: 'public://migrate',
      '#required' => TRUE,
      '#access' => $this->getContext() === self::CONTEXT_IMPORTER,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if ($this->getContext() === self::CONTEXT_IMPORTER) {
      $fids = $form_state->getValue('urls');

      if (empty($fids)) {
        $form_state->setErrorByName('urls', $this->t('File is required'));
        return;
      }

      // Save the uploaded file.
      if ($file = File::load(reset($fids))) {
        $file->setPermanent();
        $file->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $entity->source['data_fetcher']['directory'] = $form_state->getValue('directory');

    // Handle file uploads.
    $fids = $form_state->getValue(['urls']);
    if ($form_state->isSubmitted() && !empty($fids)) {
      foreach ($fids as $fid) {
        $file = File::load($fid);
        $file_uri = $file->getFileUri();
        $entity->source['urls'][] = $file_uri;
      }
    }
  }

}
