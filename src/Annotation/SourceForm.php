<?php

namespace Drupal\feeds_migrate\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an source annotation object.
 *
 * Plugin namespace: Plugin\migrate\source\form.
 *
 * @see \Drupal\migrate\SourcePluginBase
 * @see \Drupal\migrate\SourcePluginInterface
 * @see \Drupal\migrate\SourcePluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class SourceForm extends Plugin {

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
   * The type of form.
   *
   * @var string
   */
  public $type;

  /**
   * The source plugin id the form is for.
   *
   * @var string
   */
  public $parent;

}
