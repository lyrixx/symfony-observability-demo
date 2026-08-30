<?php

namespace provision;

use Castor\Attribute\AsTask;

use function docker\docker_compose_run;

/**
 * The actual provisioning logic lives in
 * src/Command/ProvisionDashboardsCommand.php, run through the "builder"
 * container: it needs to reach Kibana and Redash, and doing that from
 * inside the stack means plain HTTP to their Docker network hostnames --
 * no /etc/hosts entry, no router, no TLS. Doing this from the host (e.g. a
 * plain Castor HTTP call) would need all three, and CI has none of them.
 */
#[AsTask(description: 'Provisions the Kibana and Redash dashboards', namespace: 'app')]
function provision(): void
{
    docker_compose_run(['bin/console', 'app:provision-dashboards']);
}
