<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Instance Registration & Landing Mode
    |--------------------------------------------------------------------------
    | registration_mode: "open" (hosted SaaS) or "invite_only" (self-hosted default)
    | default_herbarium: optional slug to showcase directly at "/"
    */
    'registration_mode' => env('APP_REGISTRATION_MODE', 'open'),
    'default_herbarium' => env('APP_DEFAULT_HERBARIUM') ?: null,

    /*
    |--------------------------------------------------------------------------
    | Media & Imaging
    |--------------------------------------------------------------------------
    */
    'media' => [
        'disk' => env('HERBARIUM_MEDIA_DISK', 'local'),
        'max_upload_kb' => (int) env('HERBARIUM_MAX_UPLOAD_KB', 51200), // 50 MB
        'thumb_width' => 400,
        'public_max_edge' => 3000,
        'webp_quality' => 84,
    ],

    /*
    |--------------------------------------------------------------------------
    | Geoprivacy
    |--------------------------------------------------------------------------
    | Grid cell size in degrees (~0.1 deg ≈ 10-11 km near mid-latitudes)
    */
    'geoprivacy' => [
        'grid_degrees' => (float) env('HERBARIUM_OBSCURE_GRID_DEG', 0.1),
        'min_obscured_uncertainty_m' => (int) env('HERBARIUM_OBSCURE_UNCERTAINTY_M', 10000),
    ],

    /*
    |--------------------------------------------------------------------------
    | External Taxonomic Backbone Providers
    |--------------------------------------------------------------------------
    */
    'taxonomy' => [
        'cache_ttl_seconds' => (int) env('HERBARIUM_TAXONOMY_CACHE_TTL', 86400),
        'gbif_base_url' => env('HERBARIUM_GBIF_URL', 'https://api.gbif.org/v1'),
        'powo_base_url' => env('HERBARIUM_POWO_URL', 'https://powo.science.kew.org/api/2'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional Institutional OAuth / OIDC Identity Providers
    |--------------------------------------------------------------------------
    */
    'auth_providers' => [
        'orcid' => [
            'enabled' => (bool) (env('ORCID_CLIENT_ID') && env('ORCID_CLIENT_SECRET')),
            'label' => 'ORCID',
            'client_id' => env('ORCID_CLIENT_ID'),
            'client_secret' => env('ORCID_CLIENT_SECRET'),
            'redirect' => env('ORCID_REDIRECT_URI'),
            'sandbox' => (bool) env('ORCID_SANDBOX', false),
        ],
        'oidc' => [
            'enabled' => (bool) (env('OIDC_ENABLED', false) && env('OIDC_ISSUER') && env('OIDC_CLIENT_ID')),
            'label' => env('OIDC_LABEL', 'Institution SSO'),
            'issuer' => env('OIDC_ISSUER'),
            'client_id' => env('OIDC_CLIENT_ID'),
            'client_secret' => env('OIDC_CLIENT_SECRET'),
            'redirect' => env('OIDC_REDIRECT_URI'),
            'scopes' => env('OIDC_SCOPES', 'openid profile email'),
        ],
    ],
];
