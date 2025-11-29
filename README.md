# Tripal Developer Tools

Provides tools to make development of Tripal extension modules easier!

## Requirements

 - Tripal 4.x
 - Drush 13.6+
 - PHP 8.2+

## Usage

This module is expected to be used in development only. We suggest using it with
a dockerized Tripal site for development of extension modules. That said, as long
as you have the above requirements you can install it on a local drupal site using
composer.

## Tools

### Drush integrated generators

This module provides a large number of file generators for Tripal plugins. They use Drush 12+ and Drush-Generator v3 (included in Drush 12).

The following commands are currently implemented:

| Category  | Command                      | Description |
|-----------|------------------------------|-------------|
| Fields    | tripal:field                 | Generates a Tripal Field Type, Widget and Formatter for fields not interacting with Chado. |
| Fields    | tripal:field-formatter       | Generates a Tripal Formatter to be used with an existing Tripal Field. |
| Fields    | tripal:field-type            | Generates a Tripal Field Type for developing Tripal fields with no interactiion with Chado. |
| Fields    | tripal:field-widget          | Generates a Tripal Widget to be used with an existing Tripal Field. |
| Fields    | tripal-chado:field           | Generates a Chado Field Type, Widget and Formatter for fields who store their data in Chado. |
| Fields    | tripal-chado:field-formatter | Generates a Chado Formatter to be used with an existing Chado Field. |
| Fields    | tripal-chado:field-type      | Generates a Chado Field Type for developing fields which store their data in chado. |
| Fields    | tripal-chado:field-widget    | Generates a Chado Field Widget to be used with an existing Chado Field. |
| Developer | tripal:extension-module      | Generates a Tripal Extension module and its associated files. |
| Developer | tripal-admin:readme-grid     | Generates the testing grid for the readme and associated MAIN-phpunit-VER.yml workflow files. |

#### Usage:

**Assumes you have set up your development environment as descriped under Docker Setup above.**

Now that you have linked the internal docker copy and your local copy you can run drush inside your docker to generate the files and see them appear locally!

```
docker exec -it CONTAINERNAME drush generate tripal-chado:field
```

And then answer the prompts. The generator then takes your answers, fills them into our templates and provides you with a set of files to work with!

## Docker Setup

If you are using TripalDocker to develop your extension module, then

1. Build a TripalDocker designed to develop your module and mount the directory your module in in to your local hard drive.

   For example, if your module is called `my_module` and your current working directory contains your local copy of this module, then your run command would be:

   ```
   docker run --publish=80:80 --name=CONTAINERNAME -tid \
     --volume=$(pwd):/var/www/drupal/web/modules/contrib/my_module tripalproject/tripaldocker:latest
   ```

   NOTE: If you are just starting, then your local folder can contain nothing and you can use this module to generate the initial files!

2. Install this module inside your docker container using the following command:

   ```
   docker exec --workdir=/var/www/drupal CONTAINERNAME composer require tripal/tripal_devtools
   ```

## Local installation

See the Dockerfile in this repository for commands.
