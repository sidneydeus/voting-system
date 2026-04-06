# Voting System

This project contains a Drupal 10 custom voting module called `simple_voting`.

The module allows administrators to create voting questions and answer options, and allows authenticated users to vote through the website or through the external JSON API.

## Installation

Start the project and install dependencies:

```bash
lando start
lando composer install
```

Install Drupal:

```bash
lando drush site:install standard --yes
```

Enable the module:

```bash
lando drush en simple_voting -y
```

Open the site:

```bash
lando drush user-login
```

## Administration

The module includes an admin area where administrators can:

- create questions;
- create answer options;
- enable or disable questions;
- enable or disable voting globally.

Main admin pages:

- `/admin/content/simple-voting`
- `/admin/config/content/simple-voting`

## External API

The module exposes a small external JSON API for voting integration.

Available endpoints:

- `GET /api/simple-voting/questions`
  Returns the list of available questions.
- `GET /api/simple-voting/questions/{identifier}`
  Returns one question and its answer options.
- `POST /api/simple-voting/questions/{identifier}/vote`
  Registers a vote for the authenticated user.
- `GET /api/simple-voting/questions/{identifier}/results`
  Returns the voting results for the selected question.

## Postman Collection

A Postman collection is available in:

`collection/Simple Voting API.postman_collection.json`
