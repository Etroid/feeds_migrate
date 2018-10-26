<?php

namespace Drupal\Tests\feeds_migrate\Traits;

use Drupal\Core\Entity\EntityInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Provides methods useful for Kernel and Functional Feeds tests.
 *
 * This trait is meant to be used only by test classes.
 */
trait FeedsCommonTrait {

  /**
   * The node type to test with.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected $nodeType;

  /**
   * Creates a default node type called 'article'.
   */
  protected function setUpNodeType() {
    // Create a content type.
    $this->nodeType = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->nodeType->save();
  }

  /**
   * Creates a field and an associated field storage.
   *
   * @param string $field_name
   *   The name of the field.
   * @param array $config
   *   (optional) The field storage and instance configuration:
   *   - entity_type: (optional) the field's entity type. Defaults to 'node'.
   *   - bundle: (optional) the field's bundle. Defaults to 'article'.
   *   - type: (optional) the field's type. Defaults to 'text'.
   *   - label: (optional) the field's label. Defaults to the field's name +
   *     the string ' label'.
   *   - storage: (optional) additional keys for the field's storage.
   *   - field: (optional) additional keys for the field.
   */
  protected function createFieldWithStorage($field_name, array $config = []) {
    $config += [
      'entity_type' => 'node',
      'bundle' => 'article',
      'type' => 'text',
      'label' => $field_name . ' label',
      'storage' => [],
      'field' => [],
    ];

    FieldStorageConfig::create($config['storage'] + [
      'field_name' => $field_name,
      'entity_type' => $config['entity_type'],
      'type' => $config['type'],
      'settings' => [],
    ])->save();

    FieldConfig::create($config['field'] + [
      'entity_type' => $config['entity_type'],
      'bundle' => $config['bundle'],
      'field_name' => $field_name,
      'label' => $config['label'],
    ])->save();
  }

  /**
   * Asserts that the given number of nodes exist.
   *
   * @param int $expected_node_count
   *   The expected number of nodes in the node table.
   * @param string $message
   *   (optional) The message to assert.
   */
  protected function assertNodeCount($expected_node_count, $message = '') {
    if (!$message) {
      $message = '@expected nodes have been created (actual: @count).';
    }

    $node_count = $this->container->get('database')
      ->select('node')
      ->fields('node', [])
      ->countQuery()
      ->execute()
      ->fetchField();
    static::assertEquals($expected_node_count, $node_count, strtr($message, [
      '@expected' => $expected_node_count,
      '@count' => $node_count,
    ]));
  }

  /**
   * Returns the absolute path to the Drupal root.
   *
   * @return string
   *   The absolute path to the directory where Drupal is installed.
   */
  protected function absolute() {
    return realpath(getcwd());
  }

  /**
   * Returns the absolute directory path of the Feeds module.
   *
   * @return string
   *   The absolute path to the Feeds module.
   */
  protected function absolutePath() {
    return $this->absolute() . '/' . drupal_get_path('module', 'feeds_migrate');
  }

  /**
   * Returns the url to the Feeds resources directory.
   *
   * @return string
   *   The url to the Feeds resources directory.
   */
  protected function resourcesUrl() {
    return \Drupal::request()->getSchemeAndHttpHost() . '/' . drupal_get_path('module', 'feeds_migrate') . '/tests/resources';
  }

  /**
   * Returns the absolute directory path of the resources folder.
   *
   * @return string
   *   The absolute path to the resources folder.
   */
  protected function resourcesPath() {
    return $this->absolutePath() . '/tests/resources';
  }

}
