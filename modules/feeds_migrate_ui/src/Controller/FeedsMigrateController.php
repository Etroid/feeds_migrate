<?php

namespace Drupal\feeds_migrate_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\feeds_migrate\MigrationHelper;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FeedsMigrateController.
 */
class FeedsMigrateController extends ControllerBase {

  /**
   * Helper service for migration entity.
   *
   * @var \Drupal\feeds_migrate\MigrationEntityHelperManager
   */
  protected $migrationHelper;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new feeds migrate controller.
   *
   * @param \Drupal\feeds_migrate\MigrationHelper $migration_helper
   *   Helper service for migration entity.
   */
  public function __construct(MigrationHelper $migration_helper) {
    $this->migrationHelper = $migration_helper;
    $this->logger = $this->getLogger('feeds_migrate');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('feeds_migrate.migration_helper')
    );
  }

  /**
   * Loads the entity form for editing a mapping.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   * @param string $destination
   *   The destination field name or key.
   *
   * @return array
   *   The loaded entity form.
   */
  public function mappingEditForm(MigrationInterface $migration, $destination = NULL) {
    $operation = 'mapping-edit';

    $entity_form = $this->entityTypeManager()->getFormObject($migration->getEntityTypeId(), $operation);
    $entity_form->setEntity($migration);
    $mapping = $this->migrationHelper->getMapping($migration, $destination);
    $entity_form->setMapping($mapping);

    return $this->formBuilder()->getForm($entity_form);
  }

  /**
   * Loads the entity form for deleting a mapping.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   * @param string $destination
   *   The destination field name or key.
   *
   * @return array
   *   The loaded entity form.
   */
  public function mappingDeleteForm(MigrationInterface $migration, $destination = NULL) {
    $operation = 'mapping-delete';

    $entity_form = $this->entityTypeManager()->getFormObject($migration->getEntityTypeId(), $operation);
    $entity_form->setEntity($migration);
    $mapping = $this->migrationHelper->getMapping($migration, $destination);
    $entity_form->setMapping($mapping);

    return $this->formBuilder()->getForm($entity_form);
  }

}
