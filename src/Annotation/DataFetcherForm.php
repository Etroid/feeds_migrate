<?php

namespace Drupal\feeds_migrate\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a data fetcher annotation object.
 *
 * Plugin namespace: Plugin\migrate_plus\data_fetcher\form.
 *
 * @see \Drupal\migrate_plus\DataFetcherPluginBase
 * @see \Drupal\migrate_plus\DataFetcherPluginInterface
 * @see \Drupal\migrate_plus\DataFetcherPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class DataFetcherForm extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The title of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The data_fetcher plugin id the form is for.
   *
   * @var string
   */
  public $parent;

}
