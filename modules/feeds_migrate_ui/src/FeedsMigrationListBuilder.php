<?php

namespace Drupal\feeds_migrate_ui;

use Drupal\migrate_tools\Controller\MigrationListBuilder;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

// Temporary.
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Url;

/**
 * Class FeedsMigrateImporterListBuilder.
 *
 * @package Drupal\feeds_migrate
 */
class FeedsMigrationListBuilder extends MigrationListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {

    // Override migrate_tools migration list to include edit and delete links.
    $row = $this->migrateToolsBuildRow($entity);

    $edit_delete_ops = ConfigEntityListBuilder::buildRow($entity);

    if (is_array($row['operations'])) {
      // migrate_tools is giving us execute button, so add edit and delete.
      $row['operations']['data']['#links'] = array_merge(
        $row['operations']['data']['#links'],
        $edit_delete_ops['operations']['data']['#links']
      );
    }
    else {
      // migrate_tools is giving us N/A, so wipe that and add edit and delete.
      $row['operations'] = $edit_delete_ops['operations'];
    }

    return $row;
  }

  /**
   * Temporary overrides \Drupal\migrate_tools\Controller\MigrationListBuilder::buildRow()
   * to avoid fatal error caused by `DataParserPluginBase::nextSource()`.
   */
  public function migrateToolsBuildRow(EntityInterface $migration_entity) {
    try {
      /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
      $migration = $this->migrationPluginManager->createInstance($migration_entity->id());
      $migration_group = $migration->get('migration_group');
      if (!$migration_group) {
        $migration_group = 'default';
      }
      $route_parameters = [
        'migration_group' => $migration_group,
        'migration' => $migration->id(),
      ];
      $row['label'] = [
        'data' => [
          '#type' => 'link',
          '#title' => $migration->label(),
          '#url' => Url::fromRoute("entity.migration.overview", $route_parameters),
        ],
      ];
      $row['machine_name'] = $migration->id();
      $row['status'] = $migration->getStatusLabel();
    }
    catch (PluginException $e) {
      $this->logger->warning('Migration entity id %id is malformed: %orig', ['%id' => $migration_entity->id(), '%orig' => $e->getMessage()]);
      return NULL;
    }

    try {
      // Derive the stats.
      $source_plugin = $migration->getSourcePlugin();
      $row['total'] = 0;
      $map = $migration->getIdMap();
      $row['imported'] = $map->importedCount();
      // -1 indicates uncountable sources.
      if ($row['total'] == -1) {
        $row['total'] = $this->t('N/A');
        $row['unprocessed'] = $this->t('N/A');
      }
      else {
        $row['unprocessed'] = $row['total'] - $map->processedCount();
      }
      $row['messages'] = [
        'data' => [
          '#type' => 'link',
          '#title' => $map->messageCount(),
          '#url' => Url::fromRoute("migrate_tools.messages", $route_parameters),
        ],
      ];
      $migrate_last_imported_store = \Drupal::keyValue('migrate_last_imported');
      $last_imported = $migrate_last_imported_store->get($migration->id(), FALSE);
      if ($last_imported) {
        /** @var \Drupal\Core\Datetime\DateFormatter $date_formatter */
        $date_formatter = \Drupal::service('date.formatter');
        $row['last_imported'] = $date_formatter->format($last_imported / 1000,
          'custom', 'Y-m-d H:i:s');
      }
      else {
        $row['last_imported'] = '';
      }

      $row['operations']['data'] = [
        '#type' => 'dropbutton',
        '#links' => [
          'simple_form' => [
            'title' => $this->t('Execute'),
            'url' => Url::fromRoute('migrate_tools.execute', [
              'migration_group' => $migration_group,
              'migration' => $migration->id(),
            ]),
          ],
        ],
      ];
    }
    catch (PluginException $e) {
      // Derive the stats.
      $row['status'] = $this->t('No data found');
      $row['total'] = $this->t('N/A');
      $row['imported'] = $this->t('N/A');
      $row['unprocessed'] = $this->t('N/A');
      $row['messages'] = $this->t('N/A');
      $row['last_imported'] = $this->t('N/A');
      $row['operations'] = $this->t('N/A');
    }

    return $row;
  }

}
