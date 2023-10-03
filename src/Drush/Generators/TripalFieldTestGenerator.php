<?php declare(strict_types = 1);

namespace Drupal\tripal_devtools\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use DrupalCodeGenerator\Validator\RegExp;
use DrupalCodeGenerator\Validator\Required;
use DrupalCodeGenerator\Utils;

#[Generator(
  name: 'tripal:field-test',
  description: 'Generates PHPUnit Tests Tripal Field Type, Widget and Formatter.',
  templatePath: __DIR__ . '/../../../templates/generator',
  type: GeneratorType::MODULE_COMPONENT,
)]
final class TripalFieldTestGenerator extends BaseGenerator {

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $prompt = $this->createInterviewer($vars);

    // Module Machine Name
    $vars['machine_name'] = $prompt->askMachineName();

    // Validators
    $id_validator = new RegExp('/^[a-z][a-z0-9_]*[a-z0-9]$/', 'The value must consist of only lower case alphanumeric characters and underscores. It should start with a letter and not end with an underscore.');
    $label_validator = new RegExp('/^[a-zA-Z][a-zA-Z0-9- ]*[a-zA-Z0-9]$/', 'The value must be alphanumeric. We suggest focusing on a title-case human-readable name for your field.');
    $term_validator = new RegExp('/:/', 'The value must be an ID Space and Accession defining the term with a : separating them (e.g. rdfs:type).');

    $vars['field_id'] = $prompt->ask('Field Type | ID', NULL, $id_validator);

    $assets->addFile('tests/src/Kernel/Plugin/Field/{field_id}Test.php', 'tripal-field-type-test.twig');
  }

}
