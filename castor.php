<?php

use Castor\Attribute\AsTask;

use function Castor\guard_min_version;
use function Castor\import;
use function Castor\io;
use function Castor\notify;
use function docker\about;
use function docker\build;
use function docker\docker_compose_exec;
use function docker\docker_compose_run;
use function docker\generate_certificates;
use function docker\up;

guard_min_version('1.5.0');

import(__DIR__ . '/.castor');

/**
 * @return array{project_name: string, root_domain: string, extra_domains: string[], php_version: string}
 */
function create_default_variables(): array
{
    return [
        'project_name' => 'observability',
        'root_domain' => 'observability.test',
        'extra_domains' => [],
        'php_version' => '8.5',
    ];
}

#[AsTask(description: 'Builds and starts the infrastructure, then install the application (composer, ...)')]
function start(): void
{
    io()->title('Starting the stack');

    generate_certificates(force: false);
    build();
    up();
    install();
    migrate();

    notify('The stack is now up and running.');
    io()->success('The stack is now up and running.');

    about();
}

#[AsTask(description: 'Installs the application (composer, ...)', namespace: 'app', aliases: ['install'])]
function install(): void
{
    io()->title('Installing the application');

    docker_compose_run(['composer', 'install', '-n', '--prefer-dist', '--optimize-autoloader']);

    qa\install();
}

#[AsTask(description: 'Clears the application cache', namespace: 'app', aliases: ['cache-clear'])]
function cache_clear(): void
{
    io()->title('Clearing the application cache');

    docker_compose_run(['rm', '-rf', 'var/cache/']);
    docker_compose_run(['bin/console', 'cache:warmup']);
}

#[AsTask(description: 'Migrates database schema', namespace: 'app:db', aliases: ['migrate'])]
function migrate(): void
{
    io()->title('Migrating the database schema');

    docker_compose_run(['bin/console', 'doctrine:database:create', '--if-not-exists']);
    docker_compose_run(['bin/console', 'doctrine:migration:migrate', '-n', '--allow-no-migration']);
    docker_compose_exec(['bin/docker-entrypoint', 'create_db'], service: 'redash');
    docker_compose_exec(['clickhouse-client', '-q', 'CREATE DATABASE IF NOT EXISTS app'], service: 'clickhouse');
    docker_compose_exec(['clickhouse-client', '-q', 'CREATE TABLE IF NOT EXISTS app.logs (message String) ENGINE = MergeTree() ORDER BY tuple()'], service: 'clickhouse');
}
