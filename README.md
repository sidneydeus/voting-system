# voting-system

Sistema de votação com Drupal 10.

## Pré-requisitos

- [Lando](https://docs.lando.dev/basics/installation.html)
- Docker

## Quick Start

```bash
lando start
lando composer install
lando drush site:install standard --yes
lando drush user-login
```

Acesse: **https://voting-system.lndo.site**

## Comandos

- `lando start` - Inicia
- `lando stop` - Para
- `lando restart` - Reinicia
- `lando drush` - Executa Drush
- `lando composer` - Executa Composer
- `lando logs` - Ver logs
