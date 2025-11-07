<?php

declare(strict_types=1);

namespace Drupal\tripal_devtools\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Symfony\Component\Yaml\Yaml;

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
   * The main workflow file.
   *
   * @var string
   */
  private const WORKFLOW_FILE = 'ALL-phpunit.yml';

  /**
   * The main workflow directory.
   *
   * @var string
   */
  private const WORKFLOW_DIR = '.github/workflows';

  /**
   * Workflow YML keys.
   *
   * @var array
   */
  private const WORKFLOW_VER_KEY = [
    'drupal' => 'drupal-version',
    'php' => 'php-version',
    'pgsql' => 'pgsql-version',
  ];

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $ir = $this->createInterviewer($vars);

    $vars['machine_name'] = $ir->askMachineName();

    $module_path = \Drupal::service('module_handler')
      ->getModule($vars['machine_name'])
      ->getPath();

    $module_workflow = $module_path . DIRECTORY_SEPARATOR . self::WORKFLOW_DIR;
    if (is_dir($module_workflow)) {
      $assets = array_diff(scandir($module_workflow), ['.', '..']);
      $sample = array_filter($assets, function ($a) {
        return strpos($a, 'MAIN-phpunit') !== FALSE;
      });

      $conv = str_contains(current($sample), 'Grid') ? 'grid' : 'version';
    }

    // Confirm removal of existing grid.
    if ($ir->confirm('Ensure that you have deleted any existing workflow grid before running this command.')) {
      $parse_build = Yaml::parseFile($module_workflow . DIRECTORY_SEPARATOR . self::WORKFLOW_FILE);

      $strategy_matrix = $parse_build['jobs']['run-tests']['strategy']['matrix'];

      $php_stack = $strategy_matrix[self::WORKFLOW_VER_KEY['php']];
      $drupal_stack = $strategy_matrix[self::WORKFLOW_VER_KEY['drupal']];
      $pgsql_stack = $strategy_matrix[self::WORKFLOW_VER_KEY['pgsql']];

      $matrix = [];
      foreach ($php_stack as $include_php) {
        foreach ($drupal_stack as $include_drupal) {
          foreach ($pgsql_stack as $include_pgsql) {
            $matrix[$include_php . $include_drupal . $include_pgsql] = [
              $include_php,
              $include_drupal,
              $include_pgsql,
            ];
          }
        }
      }

      foreach ($strategy_matrix['exclude'] as $exclude) {
        $key = $exclude[self::WORKFLOW_VER_KEY['php']] . $exclude[self::WORKFLOW_VER_KEY['drupal']];

        if (isset($exclude[self::WORKFLOW_VER_KEY['pgsql']])) {
          unset($matrix[$key . $exclude[self::WORKFLOW_VER_KEY['pgsql']]]);
        }
        else {
          foreach ($pgsql_stack as $pgsql_ver) {
            if (isset($matrix[$key . $pgsql_ver])) {
              unset($matrix[$key . $pgsql_ver]);
            }
          }
        }
      }

      $i = 1;
      foreach ($matrix as $workflow) {
        $filename = ($conv == 'grid')
          ? 'MAIN-phpunit-Grid' . $i . chr(65 + (int) ($i - 1)) . '.yml'
          : 'MAIN-phpunit-php' . $workflow[0] . '_D' . $workflow[1] . '.yml';

        file_put_contents($module_workflow . DIRECTORY_SEPARATOR . 'TEMP' . $filename, 'ABC');
        $i++;
      }
    }
  }

}
