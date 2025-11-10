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

      $conv = str_contains(strtolower(current($sample)), 'grid') ? 'grid' : 'version';

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
            $matrix[$include_php][$include_drupal][] = $include_pgsql;
          }
        }
      }

      foreach ($strategy_matrix['exclude'] as $exclude) {
        $php = $exclude[self::WORKFLOW_VER_KEY['php']];
        $drupal = $exclude[self::WORKFLOW_VER_KEY['drupal']];
        $pgsql = $exclude[self::WORKFLOW_VER_KEY['pgsql']] ?? 0;

        if (isset($matrix[$php]) && isset($matrix[$php][$drupal])) {
          if (isset($exclude[$pgsql])) {
            unset($matrix[$php][$drupal][$pgsql]);
          }
          else {
            unset($matrix[$php][$drupal]);
          }
        }
      }
    }

    $seq_num = 1;
    foreach ($matrix as $php_ver => $workflow) {
      $seq_char = 0;

      foreach ($workflow as $drupal_ver => $_) {
        if ($conv == 'grid') {
          $filename = 'MAIN-phpunit-Grid' . $seq_num . chr(65 + (int) $seq_char) . '.yml';
          $seq_char++;
        }
        else {
          $filename = 'MAIN-phpunit-php' . $php_ver . '_D' . $drupal_ver . '.yml';
        }

        file_put_contents($module_workflow . DIRECTORY_SEPARATOR . 'TEMP' . $filename, 'ABC');
      }

      $seq_num++;
    }
  }

}
