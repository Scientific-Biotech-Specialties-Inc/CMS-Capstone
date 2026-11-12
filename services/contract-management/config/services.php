<?php

return [
    'prs' => [
        'inbound_key' => env('PRS_TO_CMS_INTEGRATION_KEY'),
        'catalog_networks' => env('PRS_CATALOG_ALLOWED_NETWORKS', ''),
        'catalog_public_read' => env('PRS_CATALOG_PUBLIC_READ', false),
        'institutions_url' => env('PRS_INSTITUTIONS_URL'),
        'institution_partners' => json_decode((string) env('PRS_INSTITUTION_PARTNERS', '{}'), true) ?: [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://meilisearch:7700'),
        'key' => env('MEILISEARCH_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | high_risk_approval_gate_enabled: US-023's mandatory approval gate (block
    | "Approved" for High/Critical AI Risk Assessment results until a
    | rationale'd decision is recorded). Defaults to OFF — the AI Risk
    | Assessment is currently advisory only: it surfaces a flag/warning on
    | the contract but never prevents approval. All of the gate's supporting
    | code (HighRiskApprovalGateService, ContractApproval model/migration,
    | ContractApprovalController, the SLA escalation command) is left in
    | place, inert behind this flag, so it can be re-enabled later by simply
    | flipping HIGH_RISK_APPROVAL_GATE_ENABLED=true without further code changes.
    |
    */

    'features' => [
        'high_risk_approval_gate_enabled' => env('HIGH_RISK_APPROVAL_GATE_ENABLED', false),

        /*
        | enforce_sod: Four-eyes Separation of Duties on contract approval.
        | Rejects approval or rejection if the acting user is also the contract
        | creator. Default true in production; false in local/testing to allow
        | single-user seeders and dev tests.
        */
        'enforce_sod' => env('ENFORCE_SOD', !in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),

        /*
        | workflow_engine_enabled: Phase 5 of the dynamic approval workflow
        | engine plan. Defaults to OFF — with the flag off, contract
        | creation/approval behaves exactly as before (the legacy single
        | manager-approval flow), and no ApprovalInstance/ApprovalTask rows
        | are ever created. When ON, a contract whose category has an
        | ACTIVE workflow (see Workflow::status) is routed through that
        | chain instead of the legacy immediate Approved/Pending path.
        | Categories with no active workflow always use the legacy flow
        | regardless of this flag (decision #9's fallback behavior).
        */
        'workflow_engine_enabled' => env('WORKFLOW_ENGINE_ENABLED', false),
    ],

];
