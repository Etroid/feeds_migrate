<?php

namespace Drupal\feeds_migrate\Plugin\migrate\source\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * The importer form for the url migrate source plugin.
 *
 * @MigrateForm(
 *   id = "url_importer_form",
 *   title = @Translation("Url Source Plugin Importer Form"),
 *   form_type = "importer",
 *   parent_id = "url",
 *   parent_type = "source",
 * )
 */
class UrlImporterForm extends UrlFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $plugins = $this->getPlugins();
    foreach ($plugins as $plugin_type => $plugin_id) {
      // Disable the plugin selection field.
      $form[$plugin_type . '_wrapper']['id']['#attributes']['disabled'] = TRUE;
    }

    return $form;
  }

}
