# AGENTS.md

Instructions for AI agents working on this project.

## Fundamental rule: never run PHP/Composer on the host machine

Everything goes through the `builder` container, via Castor:

```bash
castor builder -- bin/console cache:clear    # one-off command
castor builder -- bin/console make:migration # one-off command
```

Host prerequisites only: Docker, Bash, Castor.

## Essential commands

```bash
castor start                                 # build + install + up + migrate + provision
castor stop                                  # stop the stack
castor logs [--service=service]              # logs (frontend, postgres, vector, ...)
castor app:install                           # composer install + qa:install
castor app:db:migrate                        # Doctrine migrations (alias: castor migrate)
castor app:provision                         # (re)provisions the Kibana/Grafana/Redash dashboards
castor pg -- select 1                        # one-off query against PostgreSQL (alias: castor postgres)
castor about                                 # list every URL exposed by the stack
```

Docker:

```bash
castor docker:build [--service=service]
castor docker:up [--service=service]
```

## Castor contexts

The context changes how tasks are executed (`APP_ENV`, compose files, etc.):

```bash
castor --context=test qa:phpunit             # APP_ENV=test, for tests
castor --context=ci ...                      # like test, tuned for CI
```

Always run tests and anything touching the database with `--context=test`.
Without option, the `default` context applies. The test database needs
migrating once (after a fresh checkout, or after a new migration is added):
`castor --context=test app:db:migrate`.

## Stack

- Symfony app at the repo root (docroot = `public/`)
- PostgreSQL 16: user/pass/db = `app`/`app`, `DATABASE_URL` already configured
- nginx + php-fpm (service `frontend`), Traefik router, HTTPS on `<root_domain>` (see `castor.php`)
- Observability stack: Vector collects every log record and fans it out to
  Elasticsearch (+ Kibana), Loki (+ Grafana) and ClickHouse (+ Redash)
- Each of Kibana/Grafana/Redash has a pre-provisioned "Observability overview"
  dashboard: Grafana is file-based provisioning
  (`infrastructure/docker/services/grafana/`), Kibana and Redash are
  provisioned via `bin/console app:provision-dashboards`
  (`src/Command/ProvisionDashboardsCommand.php`, idempotent), run inside the
  `builder` container by `castor app:provision` so it can reach them by
  their plain Docker hostname (no `/etc/hosts`, no router, no TLS — needed
  for this to work in CI). Grafana and Kibana need no login; Redash:
  `admin@observability.test` / `observability`. The homepage's "Explore"
  links point straight at these dashboards, not at the tools' bare root.

## Frontend

Assets are managed by Symfony AssetMapper, not Node/yarn: the entrypoint is
`assets/app.js`, wired into `templates/base.html.twig` via
`{{ importmap('app') }}`. To add a JS/CSS package:

```bash
castor builder -- bin/console importmap:require <package>
```

## QA — before considering a task done

Tools run inside the builder.

```bash
castor qa:all                                # everything: cs + phpstan + twig-cs + phpunit
castor qa:cs [--dry-run]                     # PHP-CS-Fixer (.php-cs-fixer.php)
castor qa:phpstan [-b]                       # PHPStan level 6 (phpstan.neon)
castor qa:twig-cs [--dry-run]                # Twig-CS-Fixer
castor qa:phpunit                            # PHPUnit (needs the test DB migrated, see above)
```

After any PHP/Twig code change: `castor qa:cs --dry-run`, `castor qa:twig-cs --dry-run`,
`castor qa:phpstan`, then `castor --context=test qa:phpunit`.

## Conventions

1. **Never invoke `docker compose` by hand**: use the `docker_compose()` /
   `docker_compose_run()` functions from `.castor/docker.php` to write new tasks.
2. **Never hardcode ports or project names**: git worktree support automatically
   isolates project/volumes/ports. Use `variable('project_name')` etc.
3. New recurring task? Make it a Castor task (`castor.php` or `.castor/*.php`),
   not a shell script.
4. QA tool dependencies live in `tools/<tool>/composer.json`
   (not in the root `composer.json`).
