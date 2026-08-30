<?php

declare(strict_types=1);

/*
 * Identity of the institution this container serves.
 *
 * Injected by the provisioning pipeline when the tenant's container is
 * created. Read through config() rather than env() so the values survive
 * `config:cache`, which container images build with.
 */
return [

    'institution_name' => env('TENANT_INSTITUTION_NAME', 'FYP Portal'),

    /*
     | The institution's first account. Defaults to Coordinator — the role that
     | can set up templates and teams, and the one the provisioning flow hands
     | over at go-live.
     */
    'admin' => [
        'name' => env('TENANT_ADMIN_NAME', 'Coordinator'),
        'email' => env('TENANT_ADMIN_EMAIL', 'coordinator@example.test'),
        'role' => env('TENANT_ADMIN_ROLE', 'coordinator'),
    ],

];
