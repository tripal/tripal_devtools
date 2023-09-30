ARG drupalversion='10.1.x-dev'
ARG phpversion='8.2'
FROM tripalproject/tripaldocker:drupal${drupalversion}-php${phpversion}-pgsql13-noChado

## Ensures that we don't make assumptions about the name of Chado
## And sets us up to test multiple Chado instances.
ARG chadoschema='chado1'

WORKDIR /var/www/drupal9/web

## Install and Prepare Chado with the name set above.
RUN service postgresql restart \
  && drush trp-install-chado --schema-name=${chadoschema} \
  && drush trp-prep-chado --schema-name=${chadoschema} \
  && service postgresql stop

## Install and Enable Tripal DevTools
RUN service postgresql restart \
  && composer require --dev --working-dir=/var/www/drupal9 \
          tripal/tripal:4.x-dev tripal/tripal_devtools \
  && drush en tripal_devtools --yes
