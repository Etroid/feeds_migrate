<?php

namespace Drupal\Tests\feeds_migrate\Kernel\MigrationMappingForm;

use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds_migrate\Plugin\feeds_migrate\mapping_field\DefaultFieldForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Plugin\MigratePluginManagerInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Drupal\user\Entity\User;

/**
 * Tests MigrateMappingForm validation and functionality.
 *
 * @group feeds_migrate
 */
class MigrationMappingFormTest extends KernelTestBase implements FormInterface {

  /**
   * User for testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * Mapping form for testing.
   *
   * @var \Drupal\feeds_migrate\Plugin\feeds_migrate\mapping_field\DefaultFieldForm
   */
  protected $defaultMappingForm;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'user',
    'migrate',
    'feeds_migrate',
    'migrate_plus',
  ];

  /**
   * Sets up the test.
   */
  protected function setUp() {
    parent::setUp();
    $this->installSchema('system', ['sequences', 'key_value_expire']);
    $this->installEntitySchema('user');
    \Drupal::service('router.builder')->rebuild();
    /** @var \Drupal\user\RoleInterface $role */
    $this->testUser = User::create([
      'name' => 'foobar',
      'mail' => 'foobar@example.com',
    ]);
    $this->testUser->save();
    \Drupal::service('current_user')->setAccount($this->testUser);

    $this->defaultMappingForm = $this->createDefaultMappingForm();

  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'test_migration_mapping_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $destFieldOptions = ['destinationfield' => 'Destination Field'];

    $form['general'] = [
      '#type' => 'details',
    ];

    $form['general']['destination_field'] = [
      '#type' => 'select',
      '#options' => $destFieldOptions,
    ];

    $form = [
      '#type' => 'details',
      '#group' => 'plugin_settings',
    ];

    $form['source'] = [
      '#type' => 'textfield',
      '#default_value' => 'sourcefield',
    ];

    $form['is_unique'] = [
      '#type' => 'checkbox',
      '#default_value' => FALSE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Tests that unique fields are added to the ids source property.
   */
  public function testUnique() {
    $form_state = (new FormState())
      ->setValues([
        'destination_field' => 'title',
        'source' => 'title',
        'is_unique' => TRUE,
      ]);
    $form_builder = $this->container->get('form_builder');
    $form_builder->submitForm($this, $form_state);

    $form = $this->buildForm([], $form_state);
    $actual = $this->defaultMappingForm->isUnique($form, $form_state);
    $this->assertTrue($actual, 'Should be unique');
  }

  /**
   * Tests that unique fields are added to the ids source property.
   */
  public function testNotUnique() {
    $form_state = (new FormState())
      ->setValues([
        'destination_field' => 'title',
        'source' => 'title',
        'is_unique' => FALSE,
      ]);
    $form_builder = $this->container->get('form_builder');
    $form_builder->submitForm($this, $form_state);

    $form = $this->buildForm([], $form_state);
    $actual = $this->defaultMappingForm->isUnique($form, $form_state);
    $this->assertFalse($actual, 'Should be unique');
  }

  /**
   * Tests that a field maps without properties.
   */
  public function testMappingWithoutProperties() {
    $form_state = (new FormState())
      ->setValues([
        'destination_field' => 'destinationfield',
        'source' => 'sourcefield',
        'is_unique' => FALSE,
      ]);
    $form_builder = $this->container->get('form_builder');
    $form_builder->submitForm($this, $form_state);

    $form = $this->buildForm([], $form_state);
    $actual = $this->defaultMappingForm->getConfigurationFormMapping($form, $form_state);

    $this->assertArrayNotHasKey('#properties', $actual, 'Should not have #properties');
    $this->assertArrayHasKey('plugin', $actual, 'Should have destination plugin');
    $this->assertEquals('get', $actual['plugin'], 'Should have get plugin');
    $this->assertArrayHasKey('source', $actual, 'Should have destination plugin');
    $this->assertEquals('sourcefield', $actual['source'], 'Should have source');
    $this->assertArrayHasKey('#process', $actual, 'Should have destination plugin');
    $this->assertEmpty($actual['#process'], 'Should have empty processes');

  }

  /**
   * Builds a default mapping form for testing.
   *
   * @return \Drupal\feeds_migrate\Plugin\feeds_migrate\mapping_field\DefaultFieldForm
   *   A default mapping form.
   */
  protected function createDefaultMappingForm() {
    $configuration = [];
    $plugin_id = 'test';
    $plugin_definition = [];
    $fieldTypePluginManager = $this->prophesize(FieldTypePluginManager::class)
      ->reveal();
    $migrationPluginManager = $this->prophesize(MigratePluginManagerInterface::class)
      ->reveal();
    $migration = $this->prophesize(MigrationInterface::class)
      ->reveal();

    $mappingForm = new DefaultFieldForm($configuration, $plugin_id, $plugin_definition, $fieldTypePluginManager, $migrationPluginManager, $migration);
    return $mappingForm;
  }

}
