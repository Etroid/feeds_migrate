<?php

namespace Drupal\feeds_migrate\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;

/**
 * Provides form discovery capabilities for plugins.
 */
class MigrateFormPluginFactory {

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * Construct a new PluginFormFactory object.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   */
  public function __construct(ClassResolverInterface $class_resolver) {
    $this->classResolver = $class_resolver;
  }

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

    if (empty($definition['feeds_migrate']['form'][$operation])) {
      return FALSE;
    }

    $class = $definition['feeds_migrate']['form'][$operation];

    return class_exists($class) && is_subclass_of($class, MigrateFormPluginInterface::class);
  }

  /**
   * Creates a form instance for the plugin.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The Feeds plugin.
   * @param string $operation
   *   The type of form to create. See ::hasForm above for possible types.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface|null $migration
   *   The migration context in which the plugin will run.
   *
   * @return \Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface
   *   A form for the plugin.
   */
  public function createInstance(PluginInspectionInterface $plugin, $operation, MigrationInterface $migration) {
    $definition = $plugin->getPluginDefinition();

    // If the form specified is the plugin itself, use it directly.
    if (get_class($plugin) === ltrim($definition['feeds_migrate']['form'][$operation], '\\')) {
      $form_object = $plugin;
    }
    else {
      $form_object = $this->classResolver->getInstanceFromDefinition($definition['feeds_migrate']['form'][$operation]);
    }

    // Ensure the resulting object is a migrate plugin form.
    if (!$form_object instanceof MigrateFormPluginInterface) {
      throw new \LogicException($plugin->getPluginId(), sprintf('The "%s" plugin did not specify a valid "%s" form class, must implement \Drupal\Core\Plugin\MigrateFormPluginInterface', $plugin->getPluginId(), $operation));
    }

    // Set parent plugin.
    $form_object->setPlugin($plugin);
    // Set migration entity.
    $form_object->setEntity($migration);

    return $form_object;
  }

}
