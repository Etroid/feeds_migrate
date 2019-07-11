<?php

namespace Drupal\migrate_preview;

use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_tools\MigrateExecutable as MigrateExecutableBase;

/**
 * Defines a migrate executable for previewing.
 *
 * @todo prevent a migration from changing status.
 */
class MigratePreviewExecutable extends MigrateExecutableBase {

  /**
   * Returns the source.
   *
   * Makes sure source is initialized based on migration settings.
   *
   * @return \Drupal\migrate\Plugin\MigrateSourceInterface
   *   The source.
   */
  protected function getSource() {
    if (!isset($this->source)) {
      $this->source = $this->migration->getSourcePlugin();
    }
    return $this->source;
  }

  /**
   * Get the ID map from the current migration.
   *
   * @return \Drupal\migrate\Plugin\MigrateIdMapInterface
   *   The ID map.
   */
  protected function getIdMap() {
    return $this->migration->getIdMap();
  }

  /**
   *
   */
  public function preview() {
    $this->getEventDispatcher()->dispatch(MigrateEvents::PRE_IMPORT, new MigrateImportEvent($this->migration, $this->message));

    // Knock off migration if the requirements haven't been met.
    try {
      $this->migration->checkRequirements();
    }
    catch (RequirementsException $e) {
      $this->message->display(
        $this->t(
          'Migration @id did not meet the requirements. @message @requirements',
          [
            '@id' => $this->migration->id(),
            '@message' => $e->getMessage(),
            '@requirements' => $e->getRequirementsString(),
          ]
        ),
        'error'
      );

      return [];
    }

    $source = $this->getSource();
    $id_map = $this->getIdMap();
    $id_map->prepareUpdate();
    $this->migration->set('requirements', []);

    try {
      $source->rewind();
    }
    catch (\Exception $e) {
      $this->message->display(
        $this->t('Migration failed with source plugin exception: @e', ['@e' => $e->getMessage()]), 'error');
      return [];
    }

    $rows = [];
    while ($source->valid()) {
      $row = $source->current();
      $this->sourceIdValues = $row->getSourceIdValues();

      try {
        $this->processRow($row);
        $rows[] = $row;
        $this->itemLimitCounter++;

        if ($this->itemLimit && ($this->itemLimitCounter) >= $this->itemLimit) {
          break;
        }
      }
      catch (MigrateException $e) {
        // @todo show message to user.
        //$this->getIdMap()->saveIdMapping($row, [], $e->getStatus());
        //$this->saveMessage($e->getMessage(), $e->getLevel());
      }
      catch (MigrateSkipRowException $e) {
        // @todo show message to user.
//        if ($e->getSaveToMap()) {
//          $id_map->saveIdMapping($row, [], MigrateIdMapInterface::STATUS_IGNORED);
//        }
//        if ($message = trim($e->getMessage())) {
//          $this->saveMessage($message, MigrationInterface::MESSAGE_INFORMATIONAL);
//        }
      }

      try {
        $source->next();
      }
      catch (\Exception $e) {
        $this->message->display(
          $this->t('Migration failed with source plugin exception: @e',
            ['@e' => $e->getMessage()]), 'error');
        return $rows;
      }
    }

    return $rows;
  }

}
