<?php

namespace Drupal\feeds_migrate\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a data parser annotation object.
 *
 * Plugin namespace: Plugin\migrate_plus\data_parser\form.
 *
 * @see \Drupal\migrate_plus\DataParserPluginBase
 * @see \Drupal\migrate_plus\DataParserPluginInterface
 * @see \Drupal\migrate_plus\DataParserPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class DataParserForm extends Plugin {

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
   * The data_parser plugin id the form is for.
   *
   * @var string
   */
  public $parent;

}
