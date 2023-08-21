# Tripal Developer Tools

Provides tools to make development of Tripal extension modules easier!

**Note: This module is soon to be included by default in the core Tripal Docker**

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

#### Usage:

If you are using TripalDocker to develop your extension module, then ensure that when creating the container using docker run you mount your local copy of your module code inside the docker.

For example, if your module is called `my_module` and your current working directory contains your local copy of this module, then your run command would be:

```
docker run --publish=9000:80 --name=t4 -tid --volume=$(pwd):/var/www/drupal9/web/modules/contrib/my_module tripalproject/tripaldocker:latest
```

Now that you have linked the internal docker copy and your local copy you can run drush inside your docker to generate the files and see them appear locally!

```
docker exec -it t4 drush generate tripal-chado:field
```

And then answer the prompts. The generator then takes your answers, fills them into our templates and provides you with a set of files to work with!
