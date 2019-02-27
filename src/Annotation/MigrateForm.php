<?php

namespace Drupal\feeds_migrate\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a migrate form plugin annotation object.
 *
 * @Annotation
 */
class MigrateForm extends Plugin {

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
   * Examples:
   *  - "configuration"
   *  - "option"
   *
   * @var string
   */
  public $form_type;

  /**
   * The migrate plugin id the form is for.
   *
   * @var string
   */
  public $parent_id;

  /**
   * The migrate plugin type.
   *
   * Examples:
   *  - "source"
   *  - "destination"
   *  - "process"
   *  - "authentication"
   *  - "data_fetcher"
   *  - "data_parser"
   *
   * @var string
   */
  public $parent_type;

}
