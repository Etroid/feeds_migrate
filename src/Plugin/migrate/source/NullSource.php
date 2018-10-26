<?php

namespace Drupal\feeds_migrate\Plugin\migrate\source;

use Drupal\migrate_plus\Plugin\migrate\source\SourcePluginExtension;

/**
 * Placeholder source.
 *
 * @MigrateSource(
 *   id = "null"
 * )
 */
class NullSource extends SourcePluginExtension {

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return 'null';
  }

  /**
   * Returns the initialized data parser plugin.
   *
   * @return \Drupal\migrate_plus\DataParserPluginInterface
   *   The data parser plugin.
   */
  public function getDataParserPlugin() {
    if (!isset($this->dataParserPlugin)) {
      $this->dataParserPlugin = \Drupal::service('plugin.manager.migrate_plus.data_parser')->createInstance($this->configuration['data_parser_plugin'], $this->configuration + [
        'urls' => [],
      ]);
    }
    return $this->dataParserPlugin;
  }

  /**
   * Creates and returns a filtered Iterator over the documents.
   *
   * @return \Iterator
   *   An iterator over the documents providing source rows that match the
   *   configured item_selector.
   */
  protected function initializeIterator() {
    return $this->getDataParserPlugin();
  }

}
