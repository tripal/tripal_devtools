ARG drupalversion='11.x-dev'
ARG phpversion='8.3'
ARG postgresqlversion='17'
FROM tripalproject/tripaldocker:drupal${drupalversion}-php${phpversion}-pgsql${postgresqlversion}-noChado

## Ensures that we don't make assumptions about the name of Chado
## And sets us up to test multiple Chado instances.
ARG chadoschema='teapot'

WORKDIR /var/www/drupal/web

COPY ./ /var/www/drupal/web/modules/contrib/tripal_devtools

## Install and Prepare Chado with the name set above.
RUN service postgresql restart \
  && drush trp-install-chado --schema-name=${chadoschema} \
  && drush trp-prep-chado --schema-name=${chadoschema} \
  && service postgresql stop

## Install and Enable Tripal DevTools
RUN service postgresql restart \
  && drush en tripal_devtools --yes \
  && service postgresql stop
