<?php

return [
    'enabled'   => env('KNOWLEDGE_ENABLED', false),
    'tenant_id' => env('KNOWLEDGE_TENANT_ID', 'kolabri'),

    'qdrant_url'        => env('QDRANT_URL', 'http://qdrant:6333'),
    'qdrant_api_key'    => env('QDRANT_API_KEY', ''),
    'qdrant_collection' => env('QDRANT_COLLECTION', 'kolabri_knowledge'),

    // Ollama (local AI) — OpenAI-compatible API
    'ollama_url'             => env('OLLAMA_URL', 'http://ollama:11434'),
    'ollama_embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
    'ollama_chat_model'      => env('OLLAMA_CHAT_MODEL', 'qwen2.5:3b'),

    'paperless_url'       => env('PAPERLESS_URL', 'http://paperless:8000'),
    'paperless_api_token' => env('PAPERLESS_API_TOKEN', ''),

    'bookstack_url'          => env('BOOKSTACK_URL', 'https://wiki.kolabri.de'),
    'bookstack_token_id'     => env('BOOKSTACK_TOKEN_ID', ''),
    'bookstack_token_secret' => env('BOOKSTACK_TOKEN_SECRET', ''),
];
