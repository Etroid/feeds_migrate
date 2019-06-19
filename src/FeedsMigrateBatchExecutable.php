<?php

namespace Drupal\feeds_migrate;

use Drupal;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Exception;

/**
 * Defines a migrate batch executable class.
 */
class FeedsMigrateBatchExecutable extends FeedsMigrateExecutable {

  /**
   * Run a feeds migrate batch import.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function batchImport() {
    $migration = $this->getMigration();
    $source = $migration->getSourcePlugin();

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
      'title' => $this->t('Importing @label', ['@label' => $this->importer->label()]),
      'finished' => [static::class, 'batchImportFinished'],
      'operations' => [],
    ];

    while ($source->valid()) {
      $row = $source->current();
      $this->sourceIdValues = $row->getSourceIdValues();

      $batch['operations'][] = [[static::class, 'batchImportRow'], [$this->importer->id(), $row],
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

    $this->importer->setLastRun(time());
    $this->importer->save();

    if (!is_array($batch)) {
      $this->messenger()
        ->addError($this->t('Import failed. See database logs for more details'));
      return [];
    }

    batch_set($batch);
  }

  /**
   * Batch import a row.
   *
   * @param string $importer_id
   *   The id of the feeds migrate importer.
   * @param \Drupal\migrate\Row $row
   *   The row to be processed.
   * @param array $context
   *   The batch context.
   *
   * @throws \Drupal\migrate\MigrateException
   *   If the executable failed.
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public static function batchImportRow($importer_id, Row $row, array &$context) {
    /** @var \Drupal\feeds_migrate\FeedsMigrateImporterInterface $feeds_migrate_importer */
    $feeds_migrate_importer = \Drupal::service('entity_type.manager')->getStorage('feeds_migrate_importer')
      ->load($importer_id);
    $migrate_executable = $feeds_migrate_importer->getBatchExecutable();
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
  public static function batchImportFinished(bool $success, array $results, array $operations) {
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
