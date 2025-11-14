<?php

declare(strict_types=1);

namespace Drupal\tripal_devtools\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Symfony\Component\Console\Helper\Table;
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
   * The module to work on.
   *
   * @var Module Handler object
   */
  private $module;

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
   * Workflow stack version YML keys.
   *
   * @var array
   */
  private const WORKFLOW_VERSION = [
    'php' => 'php-version',
    'drupal' => 'drupal-version',
    'pgsql' => 'pgsql-version',
  ];

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {

    $ir = $this->createInterviewer($vars);
    $vars['machine_name'] = $ir->askMachineName();

    $this->module = \Drupal::service('module_handler')
      ->getModule($vars['machine_name']);
    if (!$this->module) {
      throw new \Exception('Module does not exist.');
    }

    $workflow_dir = $this->module->getPath() . DIRECTORY_SEPARATOR . self::WORKFLOW_DIR;
    if (!is_dir($workflow_dir)) {
      throw new \Exception('Failed to load workflow directory.');
    }

    // Confirm removal of existing workflow files.
    if ($ir->confirm('Delete existing workflow files before running this command.')) {

      $parse_build = Yaml::parseFile($workflow_dir . DIRECTORY_SEPARATOR . self::WORKFLOW_FILE);
      $strategy_matrix = $parse_build['jobs']['run-tests']['strategy']['matrix'];
      if (empty($strategy_matrix)) {
        throw new \Exception('Failed to load workflow strategy matrix information.');
      }

      $stack_matrix = [];

      // Create full technology stack.
      foreach ($strategy_matrix[self::WORKFLOW_VERSION['php']] as $php) {
        foreach ($strategy_matrix[self::WORKFLOW_VERSION['drupal']] as $drupal) {
          foreach ($strategy_matrix[self::WORKFLOW_VERSION['pgsql']] as $pgsql) {
            $stack_matrix[$php][$drupal][] = $pgsql;
          }
        }
      }

      // Apply technology stack exclusions.
      foreach ($strategy_matrix['exclude'] as $exclude) {
        $php = $exclude[self::WORKFLOW_VERSION['php']];
        $drupal = $exclude[self::WORKFLOW_VERSION['drupal']];
        $pgsql = $exclude[self::WORKFLOW_VERSION['pgsql']] ?? 0;

        if (isset($stack_matrix[$php]) && isset($stack_matrix[$php][$drupal])) {
          if (isset($exclude[$pgsql])) {
            unset($stack_matrix[$php][$drupal][$pgsql]);
          }
          else {
            unset($stack_matrix[$php][$drupal]);
          }
        }
      }

      // Create workflow grid file.
      $filename_scheme = 'MAIN-phpunit-%s.yml';

      $grid_header = ['PHP/Drupal'] + $strategy_matrix[self::WORKFLOW_VERSION['drupal']];
      $grid_rows = [];

      $seq_num = 1;
      foreach ($stack_matrix as $php => $workflow) {
        $row = [];
        $row[$grid_header[0]] = '**PHP' . $php . '**';

        $seq_char = 0;
        foreach ($workflow as $drupal => $pgsql) {
          if ($this->module->getName() == 'tripal') {
            // Php PHP VER _D DRUPAL VER (ie. php8.1_D10.4.x-dev).
            $grid = str_replace('.', '', $php) . '-' . str_replace(['.', 'x-dev'], '', $drupal);
            $filename = sprintf($filename_scheme, 'php' . $php . '_D' . $drupal);
          }
          else {
            // Grid SEQUENCE # SEQUENCE CHAR A-Z (ie. Grid1A).
            $grid = $seq_num . chr(65 + (int) $seq_char);
            $filename = sprintf($filename_scheme, $grid);
            $seq_char++;
          }

          file_put_contents(
            $workflow_dir . DIRECTORY_SEPARATOR . $filename,
            $this->composeWorkflowFileConfig($php, $drupal, max($pgsql))
          );

          $row[$drupal] = '![Grid' . $grid . '-Badge]';
        }

        $grid_rows[] = $row;
        $seq_num++;
      }

      // Output the grid.
      // @see symfony.com/doc/current/components/console/helpers/table.html
      $this->io()->writeln('Copy and paste table grid below into README file.');
      $table_grid = new Table($this->io()->getOutput());
      $table_grid
        ->setHeaders($grid_header)
        ->setRows($grid_rows)
        ->render();
    }

    // Existed the command.
    $this->io()->writeln('Exited workflow grid generator.');
  }

  /**
   * Create a workflow test job YML entries.
   *
   * @param string $php
   *   The version of PHP.
   * @param string $drupal
   *   The version of Drupal.
   * @param string $pgsql
   *   The version of PostgreSQL.
   *
   * @return string
   *   Workflow grid YML configuration.
   */
  public function composeWorkflowFileConfig(string $php, string $drupal, string $pgsql): string {

    $branches = 'g0.88-updateTestingMatrix';
    $uses = 'g0.88-updateTestingMatrix';

    $module_name = $this->module->getName();
    $module_base = basename($this->module->getPath());

    $workflow_config = <<<WORKFLOW_CONFIG
    name: PHPUnit
    on:
      push:
        branches:
          - 4.x
          - {$branches}
      workflow_dispatch:
      schedule:
        - cron: '0 4 * * *'
    jobs:
      running-tests:
        name: "Drupal {$drupal} - PHP {$php} - PostgreSQL {$pgsql}"
        runs-on: ubuntu-latest
        steps:
          - name: Checkout Repository
            uses: actions/checkout@v4
          - name: Run Automated testing
            uses: {$uses}
            with:
              directory-name: '{$module_base}'
              modules: '{$module_name}'
              build-image: TRUE
              dockerfile: 'Dockerfile'
              php-version: '{$php}'
              pgsql-version: '{$pgsql}'
              drupal-version: '{$drupal}'
    WORKFLOW_CONFIG;

    return $workflow_config;
  }

}
