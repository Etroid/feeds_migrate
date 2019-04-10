<?php

namespace Drupal\feeds_migrate\Plugin\feeds_migrate\data_fetcher;

use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\feeds_migrate\DataFetcherFormPluginBase;
use Drupal\feeds_migrate\FeedsMigrateImporterInterface;
use Drupal\migrate\Plugin\Migration;
use Drupal\file\Entity\File as FileEntity;
use League\Container\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides basic authentication for the HTTP resource.
 *
 * @DataFetcherForm(
 *   id = "file",
 *   title = @Translation("File"),
 *   parent = "file"
 * )
 */
class File extends DataFetcherFormPluginBase implements ContainerFactoryPluginInterface {

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FileSystem $fileSystem) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileSystem = $fileSystem;
  }

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
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\feeds_migrate\Entity\FeedsMigrateImporter $importer */
    $importer = $form_state->getBuildInfo()['callback_object']->getEntity();
    return [
      'file' => [
        '#type' => 'managed_file',
        '#title' => $this->t('File Upload'),
        '#default_value' => method_exists($importer, 'getFetcherSettings') ? $importer->getFetcherSettings($this->pluginId)['file'] : NULL,
        '#upload_validators' => [
          'file_validate_extensions' => ['xml csv json'],
        ],
        '#upload_location' => 'public://',
      ],
    ];
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
        $source_config['urls'] = $this->fileSystem->realpath($file->getFileUri());
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
