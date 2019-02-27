<?php

namespace Drupal\feeds_migrate\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a process annotation object.
 *
 * Plugin namespace: Plugin\migrate\process\form.
 *
 * @see \Drupal\migrate\ProcessPluginBase
 * @see \Drupal\migrate\ProcessPluginInterface
 * @see \Drupal\migrate\ProcessPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class ProcessForm extends Plugin {

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
   * The process plugin id the form is for.
   *
   * @var string
   */
  public $parent;

}
