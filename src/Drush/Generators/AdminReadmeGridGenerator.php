<?php

declare(strict_types=1);

namespace Drupal\tripal_devtools\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;

/**
 * Generate README grid.
 */
#[Generator(
  name: 'tripal-admin:readme-grid',
  description: 'Generates the Drupal by PHP version compatibility grid for the README and associated GitHub workflow files.',
  templatePath: __DIR__ . '/../../../templates/generator/admin',
  type: GeneratorType::MODULE_COMPONENT,
)]
final class AdminReadmeGridGenerator extends BaseGenerator {

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $ir = $this->createInterviewer($vars);

    $vars['machine_name'] = $ir->askMachineName();
    $vars['git_workflow'] = '.github/workflows/ALL-phpunit.yml';

    // Confirm removal of existing grid.
    if ($ir->confirm('Ensure that you have deleted any existing workflow grid before running this command.')) {
      $module_path = \Drupal::service('module_handler')
        ->getModule($vars['machine_name'])
        ->getPath();

      if (is_file($module_path . '/' . $vars['git_workflow'])) {

        // $assets->addFile('src/{class}.php', 'readme-grid.twig');
      }
    }
  }

}
