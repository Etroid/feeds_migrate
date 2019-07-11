<?php

namespace Drupal\migrate_preview\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_plus\Entity\MigrationGroupInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Drupal\migrate_preview\MigratePreviewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for migrate_tools migration view routes.
 */
class MigrationController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Plugin manager for migration plugins.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * Constructs a new MigrationController object.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   *   The plugin manager for config entity-based migrations.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   *   The current route match.
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, CurrentRouteMatch $currentRouteMatch) {
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->currentRouteMatch = $currentRouteMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('current_route_match')
    );
  }

  /**
   * Displays a preview of the source.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationGroupInterface $migration_group
   *   The migration group.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The $migration.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function preview(MigrationGroupInterface $migration_group, MigrationInterface $migration) {
    $migration_plugin = $this->migrationPluginManager->createInstance($migration->id(), $migration->toArray());
    $migrateMessage = new MigrateMessage();
    $executable = new MigratePreviewExecutable($migration_plugin, $migrateMessage, [
      'limit' => 10,
      'update' => TRUE,
      'force' => TRUE,
    ]);

    // Compose header.
    $header = [];
    $source = $migration_plugin->getSourcePlugin();
    foreach ($source->fields($migration_plugin) as $machine_name => $description) {
      $header[$machine_name] = Xss::filterAdmin($description);
    }

    $migrate_rows = $executable->preview();
    return [
      'source' => $this->buildTable($header, $migrate_rows, 'source'),
      'destination' => $this->buildTable($header, $migrate_rows, 'destination'),
    ];
  }

  /**
   * Builds a table from the given result.
   *
   * @param array $headers
   *   The expected headers.
   * @param \Drupal\migrate\Row[] $migrate_rows
   *   A list of migrate rows.
   *
   * @return array
   *   The rows for in the table.
   */
  protected function buildTable(array $headers, array $migrate_rows, $method = 'source') {
    if (empty($migrate_rows)) {
      return [
        '#plain_text' => $this->t('No data.'),
      ];
    }

    // Add keys from first item as additional headers.
    switch ($method) {
      case 'source':
        $item = reset($migrate_rows)->getSource();
        break;

      case 'destination':
        $item = reset($migrate_rows)->getRawDestination();
        break;
    }
    $keys = array_merge(array_keys($headers), array_keys($item));
    foreach (array_keys($item) as $key) {
      if (!isset($headers[$key])) {
        $headers[$key] = Xss::filterAdmin($key);
      }
    }

    $rows = [];
    $index = 0;
    foreach ($migrate_rows as $migrate_row) {
      switch ($method) {
        case 'source':
          $row = $migrate_row->getSource();
          break;

        case 'destination':
          $row = $migrate_row->getRawDestination();
          break;
      }
      $row += array_fill_keys($keys, NULL);

      foreach ($keys as $column) {
        $rows[$index][$column] = $this->buildValue($row[$column]);
      }

      $index++;
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'overflow: scroll;',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $headers,
      ] + $rows,
    ];
  }

  protected function buildValue($value) {
    $row_value = [
      '#plain_text' => $value,
    ];

    if (is_string($value) && strlen($value) > 255) {
      $value = substr($value, 0, 255) . '...';
    }

    if (is_scalar($value)) {
      $row_value['#plain_text'] = $value;
    }
    elseif (is_array($value)) {
      foreach ($value as $value_index => &$subvalue) {
        if (is_string($subvalue)) {
          if (strlen($subvalue) > 255) {
            $subvalue = substr($subvalue, 0, 255) . '...';
          }
        }
        if (!is_scalar($subvalue)) {
          $subvalue = print_r($subvalue, TRUE);
          $value[$value_index] = $this->buildValue($subvalue)['#plain_text'];
        }
      }

      $row_value = [
        '#theme' => 'item_list',
        '#items' => $value,
      ];
    }

    return $row_value;
  }

}
