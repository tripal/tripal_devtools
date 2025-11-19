<?php

declare(strict_types=1);

namespace Drupal\tripal_devtools\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Generate README grid.
 */
#[Generator(
  name: 'tripal-admin:readme-grid',
  description: 'Generates the Drupal by PHP version compatibility grid for the README and associated GitHub workflow files.',
  templatePath: __DIR__ . '/../../../templates/generator/tripal_admin',
  type: GeneratorType::MODULE_COMPONENT,
)]
final class AdminReadmeGridGenerator extends BaseGenerator {

  /**
   * The test action to use.
   *
   * @var string
   */
  private const WORKFLOW_ACTION = 'tripal/test-tripal-action@v1.7';

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

    $module = \Drupal::service('module_handler')
      ->getModule($vars['machine_name']);
    if (!$module) {
      throw new \Exception('Module does not exist.');
    }

    $phpunit_yml = '%s' . DIRECTORY_SEPARATOR . self::WORKFLOW_DIR . DIRECTORY_SEPARATOR . self::WORKFLOW_FILE;

    $module_path = $module->getPath();
    $module_phpunit = sprintf($phpunit_yml, $module_path);

    $is_package = FALSE;

    if (!file_exists($module_phpunit)) {
      // No workflow file asset in the module directory. Move one directory
      // level up as it may likely be a package module.
      $module_path = dirname($module_path, $one_level_up = 1);
      $module_phpunit = sprintf($phpunit_yml, $module_path);

      if (!file_exists($module_phpunit)) {
        throw new \Exception('Failed to load the phpunit YML file of the module.');
      }

      $is_package = TRUE;
    }

    $vars['module_apply'][] = $module->getName();

    if ($is_package) {
      $sub_modules = [];

      foreach (scandir($module_path) as $sub_module) {
        $sub_dir = $module_path . DIRECTORY_SEPARATOR . $sub_module;

        // Exclude the module name entered in the beginning prompts.
        if (is_dir($sub_dir) && !in_array($sub_module, ['.', '..', $module->getName()])) {

          // Only directory with .info.yml (a module).
          foreach (scandir($sub_dir) as $file) {
            if (str_contains($file, '.info.yml')) {
              array_push($sub_modules, $sub_module);
              break;
            }
          }
        }
      }

      // Prompt to ask which sub-modules the workflow apply.
      $apply_all = $ir->confirm('The package module (Repository) has sub-modules. Apply workflow to all [' . implode(', ', $sub_modules) . '] (Yes) or select from list (No)', TRUE);

      foreach ($sub_modules as $sub_module) {
        $apply_to = '';

        if ($apply_all) {
          $apply_to = $sub_module;
        }
        else {
          if ($ir->confirm('Apply workflow to sub-module: ' . $sub_module . '?', TRUE)) {
            $apply_to = $sub_module;
          }
        }

        if ($apply_to) {
          $vars['module_apply'][] = $sub_module;
        }
      }
    }

    // Confirm removal of existing workflow files.
    if ($ir->confirm('Delete existing workflow files before running this command.', TRUE)) {

      $parse_build = Yaml::parseFile($module_phpunit);

      $strategy_matrix = $parse_build['jobs']['run-tests']['strategy']['matrix'];
      if (empty($strategy_matrix)) {
        throw new \Exception('Failed to load workflow strategy matrix information.');
      }

      $webserver_stack = [];

      // Create full tech stack.
      foreach ($strategy_matrix[self::WORKFLOW_VERSION['php']] as $php) {
        foreach ($strategy_matrix[self::WORKFLOW_VERSION['drupal']] as $drupal) {
          foreach ($strategy_matrix[self::WORKFLOW_VERSION['pgsql']] as $pgsql) {
            $webserver_stack[$php][$drupal][] = $pgsql;
          }
        }
      }

      // Apply tech stack exclusion.
      $vars['exclusion_note'] = [];

      foreach ($strategy_matrix['exclude'] as $exclude) {
        $php = $exclude[self::WORKFLOW_VERSION['php']] ?? 0;
        $drupal = $exclude[self::WORKFLOW_VERSION['drupal']];

        if (!$php) {
          // Short hand instruction without PHP, will exclude all Drupal version
          // for every PHP version in the strategy.

          foreach ($strategy_matrix[self::WORKFLOW_VERSION['php']] as $php) {
            foreach ($strategy_matrix[self::WORKFLOW_VERSION['pgsql']] as $pgsql) {
              unset($webserver_stack[$php][$drupal][$pgsql]);
            }

            $vars['exclusion_note'][] = '## PHP ' . $php . ' - Drupal ' . $drupal;
          }

          continue;
        }

        $pgsql = $exclude[self::WORKFLOW_VERSION['pgsql']] ?? 0;

        if (isset($webserver_stack[$php]) && isset($webserver_stack[$php][$drupal])) {
          if (isset($exclude[$pgsql])) {
            unset($webserver_stack[$php][$drupal][$pgsql]);
          }
          else {
            unset($webserver_stack[$php][$drupal]);
          }
        }
      }

      // Create workflow grid file.
      $grid_header = array_merge(['PHP\Drupal'], $strategy_matrix[self::WORKFLOW_VERSION['drupal']]);
      $grid_rows = [];

      foreach ($webserver_stack as $php => $workflow) {
        $row = [];
        $badge = [];

        $row[$grid_header[0]] = '**PHP' . $php . '**';

        foreach ($workflow as $drupal => $pgsql) {
            // Php PHP VER _D DRUPAL VER (ie. php8.1_D10.4.x-dev).
          $grid = '[Grid' . str_replace('.', '', $php) . '-' . str_replace(['.', 'x-dev'], '', $drupal) . '-Badge]';
          $filename = sprintf('MAIN-phpunit-%s.yml', 'php' . $php . '_D' . $drupal);

          $row[$drupal] = '!' . $grid;
          $badge[$drupal] = $grid . ' : ' . implode(DIRECTORY_SEPARATOR, [
            'https://github.com',
            $module->getName(),
            $module->getName(),
            'actions',
            'workflows',
            $filename ?? '',
            'badge.svg'
          ]);

          $assets->addFile(
            $module_path . DIRECTORY_SEPARATOR . self::WORKFLOW_DIR . DIRECTORY_SEPARATOR . $filename,
            'readme-grid-workflow.twig'
          );
        }

        $grid_rows['grid'][] = $row;
        $grid_rows['badge'][] = $badge;
      }

      // Table grid.
      // @see symfony.com/doc/current/components/console/helpers/table.html
      $this->io()->writeln(PHP_EOL . 'Copy and paste table grid below into README file.' . PHP_EOL);
      $table_grid = new Table($this->io()->getOutput());
      $table_grid
        ->setHeaders($grid_header)
        ->setRows($grid_rows['grid'])
        ->render();

      // Exclusion notes.
      if ($vars['exclusion_note']) {
        $this->io()->writeln(PHP_EOL);
        foreach ($vars['exclusion_note'] as $note) {
          $this->io()->writeln($note . PHP_EOL);
        }
      }

      // Grid badges.
      $this->io()->writeln(PHP_EOL);
      foreach ($grid_rows['badge'] as $rows) {
        foreach ($rows as $badge) {
          $this->io()->writeln($badge . PHP_EOL);
        }
      }

      //
    }
    else {
      $this->io()->writeln('Exited workflow grid generator.');
    }

    // Generate grid and workflow phpunit file.
  }

}
