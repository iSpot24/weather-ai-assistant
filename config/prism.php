<?php

return [
    'providers' => [
        'openai' => [
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 500),
            'max_steps' => env('OPENAI_MAX_STEPS', 2),
            'conversation_context' => env('OPENAI_CONV_CONTEXT_LENGTH')
        ]
    ]
];
