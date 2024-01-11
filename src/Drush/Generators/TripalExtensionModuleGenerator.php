<?php declare(strict_types = 1);

namespace Drupal\tripal_devtools\Drush\Generators;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\ModuleExtensionList;
use DrupalCodeGenerator\Application;
use DrupalCodeGenerator\Asset\AssetCollection;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use DrupalCodeGenerator\Validator\Required;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Generator(
  name: 'tripal_extension_module',
  description: 'Generates a Tripal extension module',
  templatePath: __DIR__ . '/../../../templates/generator/tripal_extension_module',
  type: GeneratorType::MODULE,
)]
final class TripalExtensionModuleGenerator extends BaseGenerator implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    private readonly ModuleExtensionList $moduleList,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('extension.list.module'));
  }

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, AssetCollection $assets): void {
    $ir = $this->createInterviewer($vars);

    $vars['name'] = $ir->askName();
    $vars['machine_name'] = $ir->askMachineName();

    $vars['description'] = $ir->ask('Module description', 'Provides additional functionality for the site.', new Required());
    $vars['package'] = $ir->ask('Package', 'Tripal Extension');

    $dependencies = $ir->ask('Dependencies (comma separated)', 'tripal, tripal_chado');
    // @todo Clean-up and test.
    $vars['dependencies'] = $this->buildDependencies($dependencies);

    $vars['class_prefix'] = '{machine_name|camelize}';

    $assets->addFile('{machine_name}/{machine_name}.info.yml', 'module.info.yml.twig');

    if ($ir->confirm('Would you like to create module file?', TRUE)) {
      $assets->addFile('{machine_name}/{machine_name}.module', 'module.module.twig');
    }

    if ($ir->confirm('Would you like to create install file?', TRUE)) {
      $assets->addFile('{machine_name}/{machine_name}.install', 'module.install.twig');
    }

    if ($ir->confirm('Would you like to create libraries.yml file?', TRUE)) {
      $assets->addFile('{machine_name}/{machine_name}.libraries.yml', 'module.libraries.yml.twig');
    }

    if ($ir->confirm('Would you like to create permissions.yml file?', TRUE)) {
      $assets->addFile('{machine_name}/{machine_name}.permissions.yml', 'module.permissions.yml.twig');
    }

    // @todo Create an event subscriber? see https://github.com/Chi-teck/drupal-code-generator/blob/985d8343a143437050b89a36c0d20ff1fc10f8bf/src/Command/Module.php

    if ($vars['controller'] = $ir->confirm('Would you like to create a controller?', TRUE)) {
      $assets->addFile("{machine_name}/src/Controller/{class_prefix}Controller.php")
        ->template('ExampleController.php.twig');
    }

    if ($vars['form'] = $ir->confirm('Would you like to create settings form?', TRUE)) {
      $assets->addFile('{machine_name}/src/Form/SettingsForm.php.twig')
        ->template('SettingsForm.php.twig');
      $assets->addFile('{machine_name}/config/schema/{machine_name}.schema.yml')
        ->template('module.schema.yml.twig');
      $assets->addFile('{machine_name}/{machine_name}.links.menu.yml')
        ->template('module.links.menu.twig');
    }

    if ($vars['controller'] || $vars['form']) {
      $assets->addFile('{machine_name}/{machine_name}.routing.yml')
        ->template('module.routing.yml.twig');
    }
  }

  /**
   * Builds array of dependencies from comma-separated string.
   */
  private function buildDependencies(?string $dependencies_encoded): array {

    $dependencies = $dependencies_encoded ? \explode(',', $dependencies_encoded) : [];

    foreach ($dependencies as &$dependency) {
      $dependency = \str_replace(' ', '_', \trim(\strtolower($dependency)));
      // Check if the module name is already prefixed.
      if (\str_contains($dependency, ':')) {
        continue;
      }
      // Dependencies should be namespaced in the format {project}:{name}.
      $project = $dependency;
      try {
        // The extension list is internal for extending not for instantiating.
        // @see \Drupal\Core\Extension\ExtensionList
        /** @psalm-suppress InternalMethod */
        $package = $this->moduleList->getExtensionInfo($dependency)['package'] ?? NULL;
        if ($package === 'Core') {
          $project = 'drupal';
        }
      }
      catch (UnknownExtensionException) {

      }
      $dependency = $project . ':' . $dependency;
    }

    $dependency_sorter = static function (string $a, string $b): int {
      // Core dependencies go first.
      $a_is_drupal = \str_starts_with($a, 'drupal:');
      $b_is_drupal = \str_starts_with($b, 'drupal:');
      if ($a_is_drupal xor $b_is_drupal) {
        return $a_is_drupal ? -1 : 1;
      }
      return $a <=> $b;
    };
    \uasort($dependencies, $dependency_sorter);

    return $dependencies;
  }

}