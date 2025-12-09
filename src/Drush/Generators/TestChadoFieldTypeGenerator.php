<?php

namespace Drupal\tripal_devtools\Drush\Generators;

use Drupal\field\Entity\FieldConfig;
use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Symfony\Component\Yaml\Yaml;
use Drupal\tripal\Entity\TripalEntityType;

/**
 * Generates test files to test a specific field type.
 */
#[Generator(
  name: 'tripal-chado:test-field-type',
  description: 'Generates a PHPUnit Kernel Test to test a specific Chado field type.',
  templatePath: __DIR__ . '/../../../templates/generator/chado_field',
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
   * The bundle this test is generated for.
   *
   * @var \Drupal\tripal\Entity\TripalEntityType
   */
  protected TripalEntityType $bundle;

  /**
   * The field definitions added to the system under test.
   *
   * @var Drupal\Core\Field\FieldDefinitionInterface[]
   *   The array of field definitions for the bundle, keyed by field name.
   */
  protected array $field_definitions;

  /**
   * The field type definitions added to the system under test.
   *
   * @var array
   *   The array field type definitions keyed by field type id. The value is
   *   the array version of the field type definition.
   */
  protected array $field_type_definitions;

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $this->prompt = $this->createInterviewer($vars);

    // Module Machine Name.
    $vars['machine_name'] = $this->prompt->askMachineName();

    // Now start building the test information yaml file.
    $test_info_yaml = [
      'system-under-test' => [
        'chado_version' => '',
        'bundle' => [],
        'fields' => [],
      ],
      'scenarios' => [],
    ];

    // Chado Version.
    $vars['chado_version'] = $this->prompt->ask('Chado Version', '1.3.3.013');
    $test_info_yaml['system-under-test']['chado_version'] = $vars['chado_version'];

    // Bundle Information.
    $vars['bundle_id'] = $this->prompt->ask('Existing Tripal Content Type to test fields on', 'organism');
    $this->addBundleInfo($vars['bundle_id'], $test_info_yaml);

    // For each field attached to this bundle, ask if they want to add it to
    // the system under test.
    $dont_prompt_fields = $this->prompt->confirm('Do you want to exclude some fields for this content type', FALSE);
    foreach ($this->field_definitions as $field_name => $field_defn) {
      if (get_class($field_defn) == 'Drupal\field\Entity\FieldConfig') {
        if (($dont_prompt_fields === FALSE) or $this->prompt->confirm("\tAdd $field_name to the system-under-test?")) {
          print "\tAdding $field_name to the system-under-test.\n";
          $this->addFieldInfo($vars['bundle_id'], $field_defn, $test_info_yaml);
        }
      }
    }

    // Now add a scenario based on the default values.
    $this->addDefaultScenario($vars, $test_info_yaml);

    // Ask what file to save the test in.
    $vars['test_class'] = $this->prompt->ask('What should be the class name for the generated test (must end with "Test")', 'BaseFieldTest');
    $vars['test_yml'] = trim($vars['test_class'], 'Test') . '-' . $vars['bundle_id'] . '-TestInfo.yml';
    $vars['test_path'] = $this->prompt->ask('Where should the test files be created (relative to module directory)', 'tests/src/Kernel/Plugin/ChadoField/FieldType');

    // We are now done generating the yaml file so lets create that.
    $yaml_file = $assets->addFile('{test_path}/{test_yml}');
    $yaml_file->content(Yaml::dump($test_info_yaml, 8, 2, Yaml::DUMP_COMPACT_NESTED_MAPPING));
    // Then lets create the test file.
    $assets->addFile('{test_path}/{test_class}.php', 'chado-field-type-test.twig');
  }

  /**
   * Looks-up the bundle and adds information about it to the system under test.
   *
   * NOTE: This also populates the field_definitions and field_type_definitions
   * properties to allow field information to be accessed easily from other
   * methods.
   *
   * @param string $bundle_id
   *   The ID of an existing TripalEntityType (e.g. organism).
   * @param array $test_info_yaml
   *   The current test information yaml array from the generate method.
   *
   * @return \Drupal\tripal\Entity\TripalEntityType
   *   The bundle object used to fill out the test info yaml array.
   */
  protected function addBundleInfo(string $bundle_id, array &$test_info_yaml): TripalEntityType {

    // First we need to get the bundle object.
    $bundle = \Drupal::entityTypeManager()->getStorage('tripal_entity_type')->load($bundle_id);
    if (!is_object($bundle)) {
      throw new \UnexpectedValueException('The Tripal Content Type must already exist in the current site. Make sure you have imported the type collection.');
    }
    $this->bundle = $bundle;

    // Get all the fields for this bundle.
    $this->field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('tripal_entity', $bundle_id);
    // - We need the Field Type Plugin Manager to get the class.
    $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
    $this->field_type_definitions = $field_type_manager->getDefinitions();

    // Add the bundle info to the yaml array.
    $test_info_yaml['system-under-test']['bundle']['label'] = $bundle->getLabel();
    $test_info_yaml['system-under-test']['bundle']['termIdSpace'] = $bundle->getTermIdSpace();
    $test_info_yaml['system-under-test']['bundle']['termAccession'] = $bundle->getTermAccession();
    $test_info_yaml['system-under-test']['bundle']['id'] = $bundle->getID();
    $test_info_yaml['system-under-test']['bundle']['settings'] = $bundle->getThirdPartySettings('tripal');

    return $bundle;
  }

  /**
   * Adds field to the system under test after confirmation.
   *
   * @param string $bundle_id
   *   The ID of an existing TripalEntityType (e.g. organism).
   * @param \Drupal\field\Entity\FieldConfig $field_defn
   *   The definition of the field you would like to add to the config.
   * @param array $test_info_yaml
   *   The current test information yaml array from the generate method.
   */
  protected function addFieldInfo(string $bundle_id, FieldConfig $field_defn, array &$test_info_yaml) {

    $field_name = $field_defn->getName();
    $field_storage_defn = $field_defn->getFieldStorageDefinition();
    $field_yaml = [];

    // Get the field type information.
    $field_type = $field_defn->getType();
    $field_type_defn = $this->field_type_definitions[$field_type];

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
    $test_info_yaml['system-under-test']['fields'][] = $field_yaml;

  }

  /**
   * Adds the default scenario to the yaml based on the system-under-test.
   *
   * @param array $vars
   *   The variables already set by the command.
   * @param array $test_info_yaml
   *   The current test information yaml array from the generate method.
   */
  protected function addDefaultScenario(array $vars, array &$test_info_yaml) {

    $scenario = [
      'label' => 'Default Values Only',
      'description' => 'Creates a page using only default values for each field.',
    ];

    foreach (['create', 'edit'] as $first_lvl) {
      $scenario[$first_lvl] = [
        'user_input' => [],
        'expected' => [],
      ];
      foreach ($test_info_yaml['system-under-test']['fields'] as $field_yml) {
        $field_name = $field_yml['name'];
        $field_class = $field_yml['type_class'];
        // Get each field to generate some sample values for testing.
        $sample_value = $field_class::generateSampleValue($this->field_definitions[$field_name]);
        if ($sample_value === NULL) {
          $sample_value = [];
        }
        // Now set the sample value for both our input and expected.
        $scenario[$first_lvl]['user_input'][$field_name] = $sample_value;
        $scenario[$first_lvl]['expected'][$field_name] = $sample_value;
        // We also need to update the record_id to match expectations.
        if ($first_lvl == 'create') {
          $user_input_record_id = 0;
          $expected_record_id = 1;
        }
        else {
          $user_input_record_id = 1;
          $expected_record_id = 1;
        }
        // If single value field then set record_id directly.
        if (array_key_exists('record_id', $scenario[$first_lvl]['user_input'][$field_name])) {
          $scenario[$first_lvl]['user_input'][$field_name]['record_id'] = $user_input_record_id;
          $scenario[$first_lvl]['expected'][$field_name]['record_id'] = $expected_record_id;
        }
        // If multi-value field then set record_id for each item.
        elseif (array_key_exists(0, $scenario[$first_lvl]['user_input'][$field_name]) and array_key_exists('record_id', $scenario[$first_lvl]['user_input'][$field_name][0])) {
          foreach (array_keys($scenario[$first_lvl]['user_input'][$field_name]) as $delta) {
            $scenario[$first_lvl]['user_input'][$field_name][$delta]['record_id'] = $user_input_record_id;
            $scenario[$first_lvl]['expected'][$field_name][$delta]['record_id'] = $expected_record_id;
          }
        }
      }
    }

    $test_info_yaml['scenarios'][] = $scenario;

  }

}
