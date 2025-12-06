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
    foreach ($this->field_definitions as $field_name => $field_defn) {
      if (get_class($field_defn) == 'Drupal\field\Entity\FieldConfig') {
        if ($this->prompt->confirm("Add $field_name to the system-under-test?")) {
          $this->addFieldInfo($vars['bundle_id'], $field_defn, $test_info_yaml);
        }
      }
    }

    // Ask what field type you would like to test.
    $vars['chado_field'] = $this->prompt->ask('Existing field type which you would like to test', 'ChadoPropertyType');

    // We are now done generating the yaml file so lets create that.
    $yaml_file = $assets->addFile($vars['chado_field'] . '-' . $vars['bundle_id'] . '-TestInfo.yml');
    $yaml_file->content(Yaml::dump($test_info_yaml, 8, 2, Yaml::DUMP_COMPACT_NESTED_MAPPING));
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
    $test_info_yaml['fields'][] = $field_yaml;

  }

}
