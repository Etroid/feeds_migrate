<?php

namespace Drupal\migrate_tamper\Adapter;

use Drupal\migrate\Row;
use Drupal\tamper\TamperableItemInterface;

/**
 * Provides an adapter to use the migrate row as a tamperable item.
 */
class TamperableMigrateRowAdapter implements TamperableItemInterface {

  /**
   * A migrate row.
   *
   * @var \Drupal\migrate\Row
   */
  protected $row;

  /**
   * Creates a new instance of the adapter.
   *
   * @param \Drupal\migrate\Row $row
   *   A migrate row.
   */
  public function __construct(Row $row) {
    $this->row = $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    return $this->row->getSource();
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceProperty($property, $data) {
    $this->row->setSourceProperty($property, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceProperty($property) {
    $this->row->getSourceProperty($property);
  }

}
