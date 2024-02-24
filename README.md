# Symfony Demo - Observability - Log

Read the [slides (in french)](https://s.lyrixx.info/log) to know more about this
project and monolog.

## Running the application locally

### Requirements

A Docker environment is provided and requires you to have these tools available:

 * Docker
 * [Castor](https://github.com/jolicode/castor#installation)

### Docker environment

The Docker infrastructure provides a web stack with:
 - NGINX
 - PHP
 - PostgreSQL
 - Elasticsearch
 - Kibana
 - ClickHouse
 - Loki
 - Grafana
 - Vector
 - Traefik
 - A container with some tooling:
   - Composer

### Domain configuration (first time only)

Before running the application for the first time, ensure your domain names
point the IP of your Docker daemon by editing your `/etc/hosts` file.

This IP is probably `127.0.0.1` unless you run Docker in a special VM (like docker-machine for example).

> [!NOTE]
> The router binds port 80 and 443, that's why it will work with `127.0.0.1`

```
echo '127.0.0.1 observability.test clickhouse.observability.test elasticsearch.observability.test grafana.observability.test kibana.observability.test loki.observability.test redash.observability.test vector.observability.test ' | sudo tee -a /etc/hosts
```

### Starting the stack

Launch the stack by running this command:

```bash
castor start
```

> [!NOTE]
> the first start of the stack should take a few minutes.

The site is now accessible at https://observability.test (you may need to accept
self-signed SSL certificate if you do not have mkcert installed on your computer
- see below).

### Other tasks

Checkout `castor` to have the list of available tasks.
