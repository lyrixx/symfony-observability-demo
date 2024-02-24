<?php

use Castor\Attribute\AsTask;

use function Castor\import;
use function Castor\io;
use function Castor\notify;
use function docker\about;
use function docker\build;
use function docker\docker_compose_exec;
use function docker\docker_compose_run;
use function docker\generate_certificates;
use function docker\up;

import(__DIR__ . '/.castor');

/**
 * @return array<string, string>
 */
function create_default_variables(): array
{
    return [
        'project_name' => 'observability',
        'root_domain' => 'observability.test',
    ];
}

#[AsTask(description: 'Builds and starts the infrastructure, then install the application (composer, yarn, ...)')]
function start(): void
{
    generate_certificates(force: false);
    build();
    up();
    cache_clear();
    install();
    migrate();

    notify('The stack is now up and running.');
    io()->success('The stack is now up and running.');

    about();
}

#[AsTask(description: 'Installs the application (composer, yarn, ...)', namespace: 'app', aliases: ['install'])]
function install(): void
{
    docker_compose_run('composer install -n --prefer-dist --optimize-autoloader');

    qa\install();
}

#[AsTask(description: 'Clear the application cache', namespace: 'app', aliases: ['cache-clear'])]
function cache_clear(): void
{
    docker_compose_run('rm -rf var/cache/ && bin/console cache:warmup');
}

#[AsTask(description: 'Migrates database schema', namespace: 'app:db', aliases: ['migrate'])]
function migrate(): void
{
    docker_compose_run('bin/console doctrine:database:create --if-not-exists');
    docker_compose_run('bin/console doctrine:migration:migrate -n --allow-no-migration');
    docker_compose_exec('bin/docker-entrypoint create_db', service: 'redash');
    docker_compose_exec('clickhouse-client -q "CREATE DATABASE IF NOT EXISTS app"', service: 'clickhouse');
    docker_compose_exec('clickhouse-client -q "CREATE TABLE IF NOT EXISTS  app.logs (message String) ENGINE = MergeTree() ORDER BY tuple()"', service: 'clickhouse');
}
