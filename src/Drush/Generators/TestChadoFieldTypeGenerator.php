<?php

namespace Drupal\tripal_devtools\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;

/**
 * Generates test files to test a specific field type.
 */
#[Generator(
  name: 'tripal-chado:test-field-type',
  description: 'Generates a PHPUnit Kernel Test to test a specific Chado field type.',
  templatePath: __DIR__ . '/../../../templates/generator',
  type: GeneratorType::MODULE_COMPONENT,
)]
class TestChadoFieldTypeGenerator extends BaseGenerator {

  /**
   * The interviewer created in the drush generate command.
   *
   * @var object
   */
  protected object $prompt;

  /**
   * The field definitions added to the system under test.
   *
   * @var array
   */
  protected array $field_definitions;

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $this->prompt = $this->createInterviewer($vars);

    // Module Machine Name.
    $vars['machine_name'] = $this->prompt->askMachineName();

    // Now start building the test information yaml file.
    $test_info_yaml = [
      'chado_version' => '',
      'bundle' => [],
      'fields' => [],
      'scenarios' => [],
    ];

    // Chado Version.
    $vars['chado_version'] = $this->prompt->ask('Chado Version', '1.3.3.013');
    $test_info_yaml['chado_version'] = $vars['chado_version'];

    // Bundle Information.
    $vars['bundle_id'] = $this->prompt->ask('Existing Tripal Content Type to test fields on', 'organism');
    $this->addBundleInfo($vars['bundle_id'], $test_info_yaml);

    // For each field attached to this bundle, ask if they want to add it to
    // the system under test.
    // @todo currently we add all without asking, implement asking ;-p.
    $this->addFieldInfo($vars['bundle_id'], $test_info_yaml);

    print_r($test_info_yaml);
  }

  /**
   * Looks-up the bundle and adds information about it to the system under test.
   *
   * @param string $bundle_id
   *   The ID of an existing TripalEntityType (e.g. organism).
   * @param array $test_info_yaml
   *   The current test information yaml array from the generate method.
   *
   * @return TripalEntityType
   *   The bundle object used to fill out the test info yaml array.
   */
  protected function addBundleInfo(string $bundle_id, array &$test_info_yaml) {

    // First we need to get the bundle object.
    $bundle = \Drupal::entityTypeManager()->getStorage('tripal_entity_type')->load($bundle_id);
    if (!is_object($bundle)) {
      throw new \UnexpectedValueException('The Tripal Content Type must already exist in the current site. Make sure you have imported the type collection.');
    }

    $test_info_yaml['bundle']['label'] = $bundle->getLabel();
    $test_info_yaml['bundle']['termIdSpace'] = $bundle->getTermIdSpace();
    $test_info_yaml['bundle']['termAccession'] = $bundle->getTermAccession();
    $test_info_yaml['bundle']['id'] = $bundle->getID();
    $test_info_yaml['bundle']['settings'] = $bundle->getThirdPartySettings('tripal');

    return $bundle;
  }

  /**
   * Adds field to the system under test after confirmation.
   *
   * @param string $bundle_id
   *   The ID of an existing TripalEntityType (e.g. organism).
   * @param array $test_info_yaml
   *   The current test information yaml array from the generate method.
   */
  protected function addFieldInfo(string $bundle_id, array &$test_info_yaml) {

    // Get all the fields for this bundle.
    $this->field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('tripal_entity', $bundle_id);

    // We need the Field Type Plugin Manager to get the class.
    $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
    $type_definitions = $field_type_manager->getDefinitions();

    // For each field, add the details to the system under test.
    foreach ($this->field_definitions as $field_name => $field_defn) {
      if (get_class($field_defn) == 'Drupal\field\Entity\FieldConfig') {
        $field_storage_defn = $field_defn->getFieldStorageDefinition();
        $field_yaml = [];

        // Get the field type information.
        $field_type = $field_defn->getType();
        $field_type_defn = $type_definitions[$field_type];

        // Basic field type info.
        $field_yaml['name'] = $field_name;
        $field_yaml['type'] = $field_defn->getType();
        $field_yaml['type_class'] = $field_type_defn['class'];
        $field_yaml['cardinality'] = $field_storage_defn->getCardinality();

        // Field Widget and formatter information.
        $field_yaml['widget'] = $field_type_defn['default_widget'];
        $field_yaml['formatter'] = $field_type_defn['default_formatter'];

        // Field settings such as term and storage info.
        $field_settings = $field_defn->getSettings();
        $field_yaml['termIdSpace'] = $field_settings['termIdSpace'];
        $field_yaml['termAccession'] = $field_settings['termAccession'];
        $field_yaml['settings'] = $field_settings;
        $test_info_yaml['fields'][] = $field_yaml;
      }
    }
  }

}
