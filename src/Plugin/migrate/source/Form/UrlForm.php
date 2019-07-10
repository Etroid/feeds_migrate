<?php

namespace Drupal\feeds_migrate\Plugin\migrate\source\Form;

/**
 * The configuration form for the url migrate source plugin.
 *
 * @MigrateForm(
 *   id = "url_form",
 *   title = @Translation("Url Source Plugin Form"),
 *   form_type = "configuration",
 *   parent_id = "url",
 *   parent_type = "source",
 * )
 */
class UrlForm extends UrlFormBase {

}
