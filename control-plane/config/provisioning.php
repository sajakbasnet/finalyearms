<?php

declare(strict_types=1);

return [

    /*
     | Tenants are reached at <slug>.<base_domain>.
     */
    'base_domain' => env('BASE_DOMAIN', 'supervisex.test'),

    'image_tag' => env('TENANT_IMAGE_TAG', 'latest'),

    /*
     | Where tenant databases are created. One server, one database and one
     | scoped login role per tenant — a container per tenant does not require a
     | database server per tenant.
     */
    'database' => [
        'driver' => env('TENANT_DB_DRIVER', 'pgsql'),
        'host' => env('TENANT_DB_HOST', 'postgres'),
        'port' => env('TENANT_DB_PORT', 5432),

        // Superuser DSN used only to create databases and roles. Never handed
        // to a tenant container.
        'admin_dsn' => env('POSTGRES_ADMIN_DSN'),
    ],

    'compose_file' => env('COMPOSE_FILE', base_path('../infra/compose.yaml')),

    'health' => [
        'attempts' => env('PROVISION_HEALTH_ATTEMPTS', 30),
        'delay_seconds' => env('PROVISION_HEALTH_DELAY', 2),
    ],

];
