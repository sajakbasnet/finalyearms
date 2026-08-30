<?php

declare(strict_types=1);

/*
 * Team formation and project rules.
 *
 * Read through config() rather than env() so the values survive `config:cache`,
 * which container images build with. Each tenant runs its own container, so a
 * per-institution override is an env var at provisioning time — no schema.
 */
return [

    'team' => [
        // A team project needs at least this many and at most this many
        // members, the lead included.
        'min_members' => env('PROJECT_TEAM_MIN', 4),
        'max_members' => env('PROJECT_TEAM_MAX', 6),
    ],

    /*
     | An individual project is exactly one student. Kept explicit so the rule
     | reads the same way as the team one rather than being implied by a 1.
     */
    'individual' => [
        'members' => 1,
    ],

    // A student may hold one active project per academic session.
    'max_active_projects_per_student' => env('PROJECT_MAX_ACTIVE_PER_STUDENT', 1),

    'invitations' => [
        // Pending invitations lapse so a stale one cannot be accepted into a
        // team that has since filled up or been disbanded.
        'expires_after_days' => env('PROJECT_INVITE_EXPIRY_DAYS', 14),
    ],

    /*
     | The cross-institution title registry, hosted by the control plane.
     |
     | The token is a narrow write credential: it lets this tenant append its
     | own approved titles and nothing else. It is NOT database access — the
     | rule that a tenant never holds control-plane database credentials still
     | holds. Injected at provisioning.
     |
     | With no base URL configured the registry is simply skipped, so a tenant
     | running standalone still works.
     */
    'registry' => [
        'url' => env('TITLE_REGISTRY_URL'),
        'token' => env('TITLE_REGISTRY_TOKEN'),
        'timeout' => env('TITLE_REGISTRY_TIMEOUT', 5),
    ],

    'duplicates' => [
        /*
         | Similarity above which a proposal is flagged. A warning only — an
         | overlap with earlier work is often legitimate, and blocking it would
         | make the check something to work around rather than read.
         */
        'title_threshold' => env('PROPOSAL_TITLE_SIMILARITY', 0.75),
        'abstract_threshold' => env('PROPOSAL_ABSTRACT_SIMILARITY', 0.55),
        'max_matches' => 5,
    ],

];
