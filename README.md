# Tripal Developer Tools

Provides tools to make development of Tripal extension modules easier!

## Requirements

 - Tripal 4.x
 - Drush 12+
 - PHP 8.2+

## Usage

This module is expected to be used in development only. We suggest using it with
a dockerized Tripal site for development of extension modules. That said, as long
as you have the above requirements you can install it on a local drupal site using
composer.

### Docker Setup

If you are using TripalDocker to develop your extension module, then ensure that when creating the container using docker run you mount your local copy of your module code inside the docker.

For example, if your module is called `my_module` and your current working directory contains your local copy of this module, then your run command would be:

```
docker run --publish=80:80 --name=CONTAINERNAME -tid --volume=$(pwd):/var/www/drupal9/web/modules/contrib/my_module tripalproject/tripaldocker-devtools:latest
docker exec CONTAINERNAME service postgresql restart
```

### Local installation

See the Dockerfile in this repository for commands.


## Tools

### Drush integrated generators

This module provides a large number of file generators for Tripal plugins. They use Drush 12+ and Drush-Generator v3 (included in Drush 12).

The following commands are currently implemented:

| Command                      | Description |
|------------------------------|-------------|
| tripal:field                 | Generates a Tripal Field Type, Widget and Formatter for fields not interacting with Chado. |
| tripal:field-formatter       | Generates a Tripal Formatter to be used with an existing Tripal Field. |
| tripal:field-type            | Generates a Tripal Field Type for developing Tripal fields with no interactiion with Chado. |
| tripal:field-widget          | Generates a Tripal Widget to be used with an existing Tripal Field. |
| tripal-chado:field           | Generates a Chado Field Type, Widget and Formatter for fields who store their data in Chado. |
| tripal-chado:field-formatter | Generates a Chado Formatter to be used with an existing Chado Field. |
| tripal-chado:field-type      | Generates a Chado Field Type for developing fields which store their data in chado. |
| tripal-chado:field-widget    | Generates a Chado Field Widget to be used with an existing Chado Field. |
| tripal:extension_module      | Generates a Tripal Extension module and its associated files. |

#### Usage:

**Assumes you have set up your development environment as descriped under Docker Setup above.**

Now that you have linked the internal docker copy and your local copy you can run drush inside your docker to generate the files and see them appear locally!

```
docker exec -it CONTAINERNAME drush generate tripal-chado:field
```

And then answer the prompts. The generator then takes your answers, fills them into our templates and provides you with a set of files to work with!
