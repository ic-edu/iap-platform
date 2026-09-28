<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Question Generation Provider
    |--------------------------------------------------------------------------
    |
    | Supported: "null", "openai", "fake"
    |
    | Production defaults to "null" (fail-closed) unless explicitly configured.
    | "fake" is strictly for testing/development environments.
    |
    */

    'provider' => env('QUESTION_GENERATION_PROVIDER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the OpenAI question generation provider adapter.
    |
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_GENERATION_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('OPENAI_GENERATION_TIMEOUT', 30),
        'connect_timeout' => (int) env('OPENAI_GENERATION_CONNECT_TIMEOUT', 10),
    ],

];
