<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export migration configuration.
 */
class MigrationExportForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['yaml'] = [
      '#type' => 'textarea',
      '#title' => $this->t("Here is your migration's configuration:"),
      '#description' => $this->t('Filename: %file', ['%file' => $this->getConfigName() . '.yml']),
      '#rows' => 25,
      '#prefix' => '<div id="edit-export-wrapper">',
      '#suffix' => '</div>',
      '#required' => TRUE,
      '#default_value' => $this->getYaml(),
    ];

    // Retrieve and add the form actions array.
    $actions = $this->actionsElement($form, $form_state);
    if (!empty($actions)) {
      $form['actions'] = $actions;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions['download'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download'),
      '#submit' => ['::submitForm', '::download'],
    ];

    return $actions;
  }

  /**
   * Download the current migration's configuration as a .yml file.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function download(array $form, FormStateInterface $form_state) {
    $content = $this->getYaml();
    $filename = $this->getConfigName() . '.yml';
    $headers = [
      'Content-Type' => 'text/yaml',
      'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
    ];
    $response = new Response($content, 200, $headers);
    $form_state->setResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Remove button and internal Form API values from submitted values.
    $form_state->cleanValues();
  }

  /**
   * Get the migration's raw data.
   *
   * @return string
   *   The migration's raw data.
   */
  protected function getYaml() {
    $config_name = $this->getConfigName();
    /** @var \Drupal\Core\Config\ImmutableConfig $config */
    $config = $this->config($config_name);
    $data = $config->getRawData();
    $yaml = Yaml::encode($data);
    return $yaml;
  }

  /**
   * Get the migration's config file name (without *.yml).
   *
   * @return string
   *   The migration's config file name (without *.yml).
   */
  protected function getConfigName() {
    /** @var \Drupal\Core\Entity\EntityInterface $migration */
    $migration = $this->entity;
    $definition = $this->entityTypeManager->getDefinition($migration->getEntityTypeId());
    return $definition->getConfigPrefix() . '.' . $migration->getConfigTarget();
  }

}
