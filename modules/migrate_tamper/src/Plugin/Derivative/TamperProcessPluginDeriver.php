<?php

namespace Drupal\migrate_tamper\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\tamper\TamperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Tamper plugins as Migrate process plugins.
 *
 * @see \Drupal\migrate_tamper\Plugin\migrate\process\Tamper
 */
class TamperProcessPluginDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The tamper plugin manager.
   *
   * @var \Drupal\tamper\TamperManagerInterface
   */
  protected $tamperManager;

  /**
   * Constructs new TamperProcessPluginDeriver.
   *
   * @param \Drupal\tamper\TamperManagerInterface $tamper_manager
   *   The tamper plugin manager.
   */
  public function __construct(TamperManagerInterface $tamper_manager) {
    $this->tamperManager = $tamper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('plugin.manager.tamper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->tamperManager->getDefinitions() as $tamper_id => $tamper_definition) {
      $this->derivatives[$tamper_id] = $base_plugin_definition + $tamper_definition;
      $this->derivatives[$tamper_id]['handle_multiples'] = $tamper_definition['handle_multiples'];
      $this->derivatives[$tamper_id]['provider'] = $tamper_definition['provider'];
      $this->derivatives[$tamper_id]['tamper_plugin_id'] = $tamper_id;
    }
    return $this->derivatives;
  }

}
