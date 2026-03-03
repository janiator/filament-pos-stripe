<?php

use Leek\FilamentWorkflows\Models\WorkflowMetric;
use Leek\FilamentWorkflows\Models\WorkflowRun;
use Leek\FilamentWorkflows\Models\WorkflowRunStep;
use Leek\FilamentWorkflows\Models\WorkflowTemplate;

return [
    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the Eloquent models used by the workflow system. You can
    | extend these models in your application and reference them here.
    |
    */
    'models' => [
        'workflow' => \App\Models\Workflow::class,
        'workflow_run' => WorkflowRun::class,
        'workflow_run_step' => WorkflowRunStep::class,
        'workflow_secret' => \App\Models\WorkflowSecret::class,
        'workflow_metric' => WorkflowMetric::class,
        'workflow_template' => WorkflowTemplate::class,

        // User model for actions like SendEmail and SendNotification
        // Set to your application's User model class
        'user' => env('WORKFLOWS_USER_MODEL', 'App\\Models\\User'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the queue settings for async workflow execution.
    |
    */
    'queue' => [
        // The queue name for workflow execution jobs
        'name' => env('WORKFLOWS_QUEUE_NAME', 'workflows'),

        // The connection to use for the queue
        'connection' => env('WORKFLOWS_QUEUE_CONNECTION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting to prevent runaway workflow execution.
    |
    */
    'rate_limiting' => [
        // Maximum concurrent runs per workflow
        'max_concurrent_runs' => env('WORKFLOWS_MAX_CONCURRENT_RUNS', 10),

        // Maximum runs per minute per workflow
        'max_runs_per_minute' => env('WORKFLOWS_MAX_RUNS_PER_MINUTE', 60),

        // Global maximum concurrent runs across all workflows
        'global_max_concurrent' => env('WORKFLOWS_GLOBAL_MAX_CONCURRENT', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Configuration
    |--------------------------------------------------------------------------
    |
    | Configure workflow execution behavior and retry settings.
    |
    */
    'execution' => [
        // Default failure strategy: 'stop' or 'continue'
        'default_failure_strategy' => env('WORKFLOWS_FAILURE_STRATEGY', 'stop'),

        // Default max retries for failed workflows
        'default_max_retries' => env('WORKFLOWS_DEFAULT_MAX_RETRIES', 3),

        // Retry backoff intervals in seconds [1min, 5min, 15min]
        'retry_backoff' => [60, 300, 900],

        // Maximum workflow chaining depth (RunWorkflowAction)
        'max_chain_depth' => env('WORKFLOWS_MAX_CHAIN_DEPTH', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance metrics tracking for workflow execution.
    | Metrics include execution time, success rates, and run counts.
    |
    */
    'metrics' => [
        // Enable or disable metrics collection
        'enabled' => env('WORKFLOWS_METRICS_ENABLED', true),

        // Maximum duration samples to store for P95 calculation
        'max_duration_samples' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Templates Configuration
    |--------------------------------------------------------------------------
    |
    | Configure workflow templates - pre-built workflows that users can
    | select and customize to quickly create new workflows.
    |
    */
    'templates' => [
        // Enable or disable the templates feature
        'enabled' => env('WORKFLOWS_TEMPLATES_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | Configure multi-tenancy support for workflows. When enabled,
    | all workflow data will be scoped to the current tenant.
    |
    */
    'tenancy' => [
        // Enable or disable multi-tenancy support (scope workflows to current Filament tenant = Store)
        'enabled' => env('WORKFLOWS_TENANCY_ENABLED', true),

        // The column name used for tenant identification (Store = store_id)
        'column' => env('WORKFLOWS_TENANCY_COLUMN', 'store_id'),

        // The tenant model (Filament panel uses Store)
        'model' => env('WORKFLOWS_TENANT_MODEL', App\Models\Store::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Roles
    |--------------------------------------------------------------------------
    |
    | Available roles for the SendNotification action's "Users with Role" option.
    | Key is the role identifier, value is the display label.
    |
    */
    'notification_roles' => [
        // 'admin' => 'Administrator',
        // 'manager' => 'Manager',
        // 'user' => 'User',
    ],

    /*
    |--------------------------------------------------------------------------
    | Triggerable Models
    |--------------------------------------------------------------------------
    |
    | List of Eloquent model classes that can trigger workflows.
    | Leave empty to allow any model (requires manual observer registration).
    |
    */
    'triggerable_models' => [
        App\Models\User::class,
        App\Models\WebhookLog::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Discovery
    |--------------------------------------------------------------------------
    |
    | Settings for automatic discovery of triggerable models.
    | When enabled, scans configured paths for models with HasWorkflowTriggers trait.
    |
    */
    'discovery' => [
        // Enable automatic model discovery
        'enabled' => env('WORKFLOWS_DISCOVERY_ENABLED', false),

        // Paths to scan for models (when discovery is enabled)
        'paths' => [
            app_path('Models'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    |
    | Control optional integrations with other packages.
    | Each integration is auto-detected via class_exists() and can be
    | disabled here even when the package is installed.
    |
    */
    'integrations' => [
        'decision_tables' => env('WORKFLOWS_DECISION_TABLES_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Triggerable Events
    |--------------------------------------------------------------------------
    |
    | List of Laravel event classes that can trigger workflows.
    | Leave empty to auto-discover events from app/Events directory.
    |
    */
    'triggerable_events' => [
        // App\Events\UserRegistered::class,
        // App\Events\OrderCreated::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Discovery
    |--------------------------------------------------------------------------
    |
    | Configure automatic event discovery for event-triggered workflows.
    | When enabled, the WorkflowEventSubscriber listens for Laravel events
    | and triggers matching workflows.
    |
    */
    'event_discovery' => [
        // Enable the wildcard event subscriber
        'enabled' => env('WORKFLOWS_EVENT_DISCOVERY_ENABLED', true),

        // Paths to scan for event classes
        'paths' => [
            app_path('Events'),
        ],

        // Curated vendor events to include (class => label)
        // Set to null to use the built-in defaults (auth, mail, notification, queue events)
        'vendor_events' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Introspection
    |--------------------------------------------------------------------------
    |
    | Configure model introspection for variable autocomplete and
    | field discovery in workflow builders.
    |
    */
    'introspection' => [
        // Columns to exclude from model field listings
        'excluded_columns' => ['id', 'ulid', 'created_at', 'updated_at', 'deleted_at'],

        // Column name patterns that reference user records
        'user_field_patterns' => [
            'user_id',
            'created_by',
            'updated_by',
            'assigned_to',
            'assigned_by',
            'approved_by',
            'supervisor_id',
        ],

        // Maximum depth for relationship traversal
        'max_relationship_depth' => 2,
    ],
];
