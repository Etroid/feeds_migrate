<?php

namespace Drupal\migrate_tamper\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipProcessException;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\migrate_tamper\Adapter\TamperableMigrateRowAdapter;
use Drupal\tamper\Exception\SkipTamperDataException;
use Drupal\tamper\Exception\SkipTamperItemException;
use Drupal\tamper\SourceDefinition;
use Drupal\tamper\TamperManagerInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides tamper plugins as process plugins.
 *
 * @MigrateProcessPlugin(
 *   id = "tamper",
 *   deriver = "Drupal\migrate_tamper\Plugin\Derivative\TamperProcessPluginDeriver"
 * )
 */
class Tamper extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The tamper plugin manager.
   *
   * @var \Drupal\tamper\TamperManagerInterface
   */
  protected $tamperManager;

  /**
   * Flag indicating whether there are multiple values.
   *
   * @var bool
   */
  protected $multiple = FALSE;

  /**
   * Constructs a new Tamper object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\tamper\TamperManagerInterface $tamper_manager
   *   The tamper plugin manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TamperManagerInterface $tamper_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->tamperManager = $tamper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.tamper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Instantiate tamper plugin.
    $tamper = $this->createTamperInstance($migrate_executable);

    // Create tamperable item.
    $tamperable_item = new TamperableMigrateRowAdapter($row);

    // And apply tamper!
    try {
      $value = $tamper->tamper($value, $tamperable_item);
      $this->multiple = $tamper->multiple();
      return $value;
    }
    catch (SkipTamperDataException $e) {
      throw new MigrateSkipProcessException();
    }
    catch (SkipTamperItemException $e) {
      throw new MigrateSkipRowException();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return $this->multiple;
  }

  /**
   * Creates a tamper instance.
   *
   * @param \Drupal\migrate\MigrateExecutableInterface
   *   The migrate executable.
   *
   * @return \Drupal\tamper\TamperInterface
   *   A tamper instance.
   */
  protected function createTamperInstance(MigrateExecutableInterface $migrate_executable) {
    return $this->tamperManager->createInstance($this->pluginDefinition['tamper_plugin_id'], $this->configuration + [
      'source_definition' => $this->getSourceDefinitionFromMigrateExecutable($migrate_executable),
    ]);
  }

  /**
   * Creates a source definition based on the migrate executable.
   *
   * @param \Drupal\migrate\MigrateExecutableInterface
   *   The migrate executable.
   *
   * @return \Drupal\tamper\SourceDefinition
   *   A source definition.
   */
  protected function getSourceDefinitionFromMigrateExecutable(MigrateExecutableInterface $migrate_executable) {
    // We need to use reflection since getSource() is protected.
    $class = new ReflectionClass(get_class($migrate_executable));
    $method = $class->getMethod('getSource');
    $method->setAccessible(TRUE);

    return new SourceDefinition($method->invoke($migrate_executable)->fields());
  }

}
