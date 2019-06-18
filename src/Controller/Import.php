<?php

namespace Drupal\feeds_migrate\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\feeds_migrate\FeedsMigrateExecutable;
use Drupal\feeds_migrate\FeedsMigrateImporterInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Row;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Import.
 *
 * @package Drupal\feeds_migrate\Controller
 */
class Import extends ControllerBase {

  /**
   * Manager for entity types.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationManager;

  /**
   * The migration plugin.
   *
   * @var \Drupal\migrate\Plugin\Migration
   */
  public $migration;

  /**
   * Migration message service.
   *
   * @var \Drupal\migrate\MigrateMessageInterface
   */
  protected $message;

  /**
   * Constructs a new Importer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_manager
   *   The migration plugin manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MigrationPluginManagerInterface $migration_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->migrationManager = $migration_manager;
    $this->message = new MigrateMessage();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.migration')
    );
  }

  /**
   * Run a feeds migrate import.
   *
   * @param \Drupal\feeds_migrate\FeedsMigrateImporterInterface $feeds_migrate_importer
   *   A Feeds Migrate Importer entity object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\migrate\MigrateException
   *   If the executable failed.
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function import(FeedsMigrateImporterInterface $feeds_migrate_importer) {
    $batch = $this->getBatch($feeds_migrate_importer);
    if (!is_array($batch)) {
      $this->messenger()
        ->addError($this->t('Import failed. See database logs for more details'));
      return [];
    }

    batch_set($batch);
    return batch_process();
  }

  /**
   * Retrieves the batch definition for a given importer.
   *
   * @param \Drupal\feeds_migrate\FeedsMigrateImporterInterface $feeds_migrate_importer
   *   The feeds migrate importer object.
   *
   * @return array
   *   An associative array defining the batch.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\migrate\MigrateException
   *   If the executable failed.
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  protected function getBatch(FeedsMigrateImporterInterface $feeds_migrate_importer) {
    /** @var \Drupal\feeds_migrate\FeedsMigrateExecutable $migrate_executable */
    $migrate_executable = $feeds_migrate_importer->getExecutable();
    $this->migration = $migrate_executable->getMigration();
    $source = $this->migration->getSourcePlugin();

    try {
      $source->rewind();
    }
    catch (Exception $e) {
      $this->message->display(
        $this->t('Migration failed with source plugin exception: @e', ['@e' => $e->getMessage()]), 'error');
      $this->migration->setStatus(MigrationInterface::STATUS_IDLE);
      return MigrationInterface::RESULT_FAILED;
    }

    $batch = [
      'title' => $this->t('Importing @label', ['@label' => $feeds_migrate_importer->label()]),
      'finished' => [static::class, 'batchFinished'],
      'operations' => [],
    ];

    while ($source->valid()) {
      $row = $source->current();
      $this->sourceIdValues = $row->getSourceIdValues();

      $batch['operations'][] = [
        [static::class, 'batchImportRow'],
        [$migrate_executable, $row],
      ];

      try {
        $source->next();
      }
      catch (Exception $e) {
        $this->message->display(
          $this->t('Migration failed with source plugin exception: @e',
            ['@e' => $e->getMessage()]), 'error');
        $this->migration->setStatus(MigrationInterface::STATUS_IDLE);
        return MigrationInterface::RESULT_FAILED;
      }
    }

    $feeds_migrate_importer->setLastRun(time());
    $feeds_migrate_importer->save();
    return $batch;
  }

  /**
   * Batch import a row.
   *
   * @param \Drupal\feeds_migrate\FeedsMigrateExecutable $migrate_executable
   * @param \Drupal\migrate\Row $row
   */
  public static function batchImportRow(FeedsMigrateExecutable $migrate_executable, Row $row, &$context) {
    $migrate_executable->importRow($row);
    $id_map = $row->getIdMap();
    $context['results'][$id_map['source_row_status']][] = $row;
  }

  /**
   * Finished callback for import batches.
   *
   * @param bool $success
   *   A boolean indicating whether the batch has completed successfully.
   * @param array $results
   *   The value set in $context['results'] by callback_batch_operation().
   * @param array $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   */
  public static function batchFinished(bool $success, array $results, array $operations) {
    $messenger = Drupal::messenger();

    if (empty($results)) {
      $messenger->addMessage(t('No items processed.'));
    }

    if (!empty($results[MigrateIdMapInterface::STATUS_IMPORTED]) || !empty($results[MigrateIdMapInterface::STATUS_NEEDS_UPDATE])) {
      $count = 0;
      if (!empty($results[MigrateIdMapInterface::STATUS_IMPORTED])) {
        $count = count($results[MigrateIdMapInterface::STATUS_IMPORTED]);
      }
      if (!empty($results[MigrateIdMapInterface::STATUS_NEEDS_UPDATE])) {
        $count += count($results[MigrateIdMapInterface::STATUS_NEEDS_UPDATE]);
      }
      $messenger->addMessage(t('Successfully imported %success items.', ['%success' => $count]));
    }

    if (!empty($results[MigrateIdMapInterface::STATUS_IGNORED])) {
      $messenger->addMessage(t('Skipped %ignored items.', ['%ignored' => count($results[MigrateIdMapInterface::STATUS_IGNORED])]));
    }

    if (!empty($results[MigrateIdMapInterface::STATUS_FAILED])) {
      $messenger->addMessage(t('Failed to import %failed items', ['%failed' => count($results[MigrateIdMapInterface::STATUS_FAILED])]));
    }
  }

}
