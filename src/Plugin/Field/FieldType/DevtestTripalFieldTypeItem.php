<?php declare(strict_types = 1);

namespace Drupal\tripal_devtools\Plugin\Field\FieldType;

use Drupal\tripal\TripalField\TripalFieldItemBase;
// Make sure to include the Property type class you are going to create
// in your addTypes() method below.
use Drupal\tripal\TripalStorage\VarCharStoragePropertyType;
use Drupal\tripal\TripalStorage\StoragePropertyValue;
use Drupal\core\Form\FormStateInterface;
use Drupal\core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the 'devtest_tripal_field' field type.
 *
 * @FieldType(
 *   id = "devtest_tripal_field",
 *   label = @Translation("DevTest Tripal Field"),
 *   description = @Translation("AUTO GENERATED by Tripal DevTools for Testing."),
 *   default_widget = "devtest_tripal_field_widget",
 *   default_formatter = "devtest_tripal_field_formatter"
 * )
 */
class DevtestTripalFieldTypeItem extends TripalFieldItemBase {

  public static $id = "devtest_tripal_field";

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $settings = parent::defaultStorageSettings();
    $settings['storage_plugin_id'] = 'drupal_sql_storage';
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function tripalTypes($field_definition) {

    // Use the field settings to extract information we need to make generic fields.
    $entity_type_id = $field_definition->getTargetEntityTypeId();
    $storage_settings = $field_definition->getSettings();
    $termIdSpace = 'rdfs';
    $termAccession = 'type';

    // Here we set the max length to the typical size of a Drupal postgresql varchar column.
    $max_length = 255;

    return [
      // Example Property Type for strings.
      new VarCharStoragePropertyType($entity_type_id, self::$id, "value", $termIdSpace . ':' . $termAccession, $max_length),
    ];
  }

}

