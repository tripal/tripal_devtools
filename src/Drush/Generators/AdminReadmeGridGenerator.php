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
  templatePath: __DIR__ . '/../../../templates/generator/tripal_admin',
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
   * Workflow configuration values.
   *
   * Each key corresponds to a config key in the template file.
   *
   * @see /templates/tripal_admin/readme-grid-workflow.twig
   *
   * @var string
   */
  private const WORKFLOW_OPTION = [
    'name' => 'PHPUnit',
    'branches' => [
      '4.x',
    ],
    'cron' => '0 6 * * *',
    'test' => 'running-tests',
    'checkout' => 'actions/checkout@v4',
    'run' => 'tripal/test-tripal-action@v1.7',
  ];

  /**
   * The workflow template file.
   *
   * @var string
   */
  private const WORKFLOW_TEMPLATE = 'readme-grid-workflow.twig';

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {

    $ir = $this->createInterviewer($vars);
    $machine_name = $ir->askMachineName();

    // Must have this key.
    $vars['machine_name'] = $machine_name;

    $module = \Drupal::service('module_handler')
      ->getModule($machine_name);
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

    $apply_module = [];
    $apply_module[] = $module->getName();

    if ($is_package) {
      // Confirm that it is a package.
      $confirm_is_package = $ir->confirm('The module is a package, and the generator located the GitHub Workflow in the parent directory: ' . $module_path . ' and not in ' . $module->getPath() . '. Is the module a package?', TRUE);

      if (!$confirm_is_package) {
        throw new \Exception('Could not find the workflow directory. Ensure that you have setup the directory .github/workflows/ in the module path and retry the command.');
      }

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
          $apply_module[] = $sub_module;
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
      $exclusion_note = [];

      foreach ($strategy_matrix['exclude'] as $exclude) {
        $php = $exclude[self::WORKFLOW_VERSION['php']] ?? 0;
        $drupal = $exclude[self::WORKFLOW_VERSION['drupal']];
        $pgsql = $exclude[self::WORKFLOW_VERSION['pgsql']] ?? 0;

        if (!$php) {
          // Short hand instruction without PHP, will exclude all Drupal version
          // for every PHP version in the strategy.
          foreach ($strategy_matrix[self::WORKFLOW_VERSION['php']] as $php) {
            unset($webserver_stack[$php][$drupal][$pgsql]);
            $exclusion_note[] = '## PHP ' . $php . ' - Drupal ' . $drupal . ' - PostgreSQL ' . $pgsql;
          }

          continue;
        }

        if (isset($webserver_stack[$php]) && isset($webserver_stack[$php][$drupal])) {
          if ($pgsql) {
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

      // Setup workflow template static values.
      $vars['module_directory_name'] = basename($module->getPath());
      $vars['apply_module'] = implode(', ', $apply_module);

      foreach (self::WORKFLOW_OPTION as $key => $value) {
        $vars[$key] = $value;
      }

      if (!file_exists($this->getTemplatePath() . DIRECTORY_SEPARATOR . self::WORKFLOW_TEMPLATE)) {
        throw new \Exception('Workflow template file does not exist.');
      }

      foreach ($webserver_stack as $php => $drupal_pgsql) {
        $row = [];
        $badge = [];

        $row[$grid_header[0]] = '**PHP' . $php . '**';

        foreach ($strategy_matrix[self::WORKFLOW_VERSION['drupal']] as $drupal) {
          if (!isset($drupal_pgsql[$drupal])) {
            $row[$drupal] = '';
            continue;
          }

          // Php PHP VER _D DRUPAL VER (ie. php81_D104.x-dev).
          $grid = '[Grid' . str_replace('.', '', (string) $php) . '-' . str_replace(['.', 'x-dev'], '', (string) $drupal) . '-Badge]';
          // Php PHP VER _D DRUPAL VER (ie. php.1_D104x).
          $filename = sprintf('MAIN-phpunit-%s.yml', 'php' . $php . '_D' . str_replace(['.', '-dev'], '', (string) $drupal));

          $row[$drupal] = '!' . $grid;
          $badge[$drupal] = $grid . ' : ' . implode(DIRECTORY_SEPARATOR, [
            'https://github.com',
            $module->getName(),
            $module->getName(),
            'actions',
            'workflows',
            $filename,
            'badge.svg',
          ]);

          // In twig - stack[filename].php/drupal/pgsql.
          // @see assets loop below about filename.
          $vars['stack'][$filename] = [
            'php' => $php,
            'drupal' => $drupal,
            'pgsql' => max($drupal_pgsql[$drupal]),
          ];

          $assets->addFile(
            (($is_package) ? '../' : '/') . self::WORKFLOW_DIR . DIRECTORY_SEPARATOR . $filename,
            self::WORKFLOW_TEMPLATE,
          );
        }

        $grid_rows['grid'][] = $row;
        $grid_rows['badge'][] = $badge;
      }

      // Inject the filename into the vars for each addFile() call.
      // The filename is then used to reference which php-drupal-pgsql combo
      // to encode into the yml file.
      foreach ($assets as $value) {
        $temp_vars = $value->getVars();
        $temp_vars['filename'] = trim(str_replace([self::WORKFLOW_DIR, '/', '..'], '', $value->getPath()));
        $value->vars($temp_vars);
      }

      // Allow the removal of specific grid column (Drupal header).
      $header_choices = $grid_header;
      $header_choices[0] = 'none - Keep all columns';
      $col_remove = (int) $ir->choice(
        'Select grid column header to remove',
        array_values($header_choices),
        $header_choices[0]
      );

      if ($col_remove) {
        unset($grid_header[$col_remove]);

        for ($i = 0; $i < count($grid_rows['grid']); $i++) {
          unset($grid_rows['grid'][$i][$header_choices[$col_remove]]);
        }
      }

      // Table grid.
      // @see symfony.com/doc/current/components/console/helpers/table.html
      $this->io()->writeln(PHP_EOL . 'Copy and paste table grid below into README file.' . PHP_EOL);
      $table_grid = new Table($this->io()->getOutput());
      $table_grid
        ->setHeaders($grid_header)
        ->setRows($grid_rows['grid'])
        ->render();

      // Exclude notes.
      $this->io()->writeln(PHP_EOL);
      if ($exclusion_note) {
        $this->io()->writeln('Exclude notes:' . PHP_EOL);
        foreach ($exclusion_note as $note) {
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

      // End confirm to continue.
    }
    else {

      $this->io()->writeln('Exited workflow grid generator.');
    }
  }

}
