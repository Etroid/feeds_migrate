<?php

namespace Drupal\feeds_migrate\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface for objects that are aware of a plugin.
 */
interface PluginAwareInterface {

  /**
   * Sets the plugin for this object.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin.
   */
  public function setPlugin(PluginInspectionInterface $plugin);

}
