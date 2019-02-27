<?php

namespace Drupal\feeds_migrate\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an destination annotation object.
 *
 * Plugin namespace: Plugin\migrate\destination\form.
 *
 * @see \Drupal\migrate\DestinationPluginBase
 * @see \Drupal\migrate\DestinationPluginInterface
 * @see \Drupal\migrate\DestinationPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class DestinationForm extends Plugin {

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
   * The destination plugin id the form is for.
   *
   * @var string
   */
  public $parent;

}
