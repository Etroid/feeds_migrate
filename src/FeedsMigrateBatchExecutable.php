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

      $batch['operations'][] = [[$this, 'batchImportRow'], [$row]];

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
    return batch_process();
  }

  /**
   * Batch import a row.
   *
   * @param \Drupal\migrate\Row $row
   *   The row to be processed.
   * @param array $context
   *   The batch context.
   */
  public function batchImportRow(Row $row, array &$context) {
    $this->importRow($row);
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
