<?php

namespace Drupal\feeds_migrate\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;

/**
 * Provides form discovery capabilities for plugins.
 */
class MigrateFormPluginFactory {

  /**
   * Returns whether or not the plugin implements a form for the given type.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The Migrate plugin.
   * @param string $operation
   *   The type of form to check for, which can be for example:
   *   - configuration
   *     Displayed when configuring the feed type.
   *   - feed
   *     Displayed on the feed add/edit form.
   *   - option
   *     A small form to appear on the plugin select box. The entity processor
   *     plugins use this to display a form for selecting an entity bundle.
   *
   * @return bool
   *   True if the plugin implements a form of the given type. False otherwise.
   */
  public function hasForm(PluginInspectionInterface $plugin, $operation) {
    $definition = $plugin->getPluginDefinition();

    return !empty($definition['feeds_migrate']['form'][$operation]);
  }

  /**
   * Creates a form instance for the plugin.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The Feeds plugin.
   * @param string $plugin_type
   *   The type of plugin (e.g. source, process, destination etc...).
   * @param string $operation
   *   The type of form to create. See ::hasForm above for possible types.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface|null $migration
   *   The migration context in which the plugin will run.
   *
   * @return \Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface
   *   A form for the plugin.
   *
   * @throws \LogicException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createInstance(PluginInspectionInterface $plugin, string $plugin_type, string $operation, MigrationInterface $migration) {
    $definition = $plugin->getPluginDefinition();
    $form_plugin_id = $definition['feeds_migrate']['form'][$operation];

    // If the form specified is the plugin itself, use it directly.
    if ($plugin->getPluginId() === $form_plugin_id) {
      /** @var \Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface $form_plugin */
      $form_plugin = $plugin;
    }
    else {
      /* @var \Drupal\feeds_migrate\Plugin\MigrateFormPluginManager $manager */
      $manager = \Drupal::service("plugin.manager.feeds_migrate.migrate.{$plugin_type}_form");
      /** @var \Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface $form_plugin */
      $form_plugin = $manager->createInstance($form_plugin_id, [], $plugin, $migration);
    }

    // Ensure the resulting object is a migrate plugin form.
    if (!$form_plugin instanceof MigrateFormPluginInterface) {
      throw new \LogicException($plugin->getPluginId(), sprintf('The "%s" plugin did not specify a valid "%s" form class, must implement \Drupal\feeds_migrate\PluginMigrateFormPluginInterface', $plugin->getPluginId(), $operation));
    }

    return $form_plugin;
  }

}
