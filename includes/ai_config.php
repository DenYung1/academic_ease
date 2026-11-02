<?php
/**
 * AI Configuration Settings
 * 
 * This file contains configuration settings for AI services used in Academic Ease
 */

return [
    'openrouter' => [
        'api_key' => $_ENV['OPENROUTER_API_KEY'] ?? $_SERVER['OPENROUTER_API_KEY'] ?? 'your_openrouter_api_key_here', // Try multiple environment variable sources
        'default_model' => 'anthropic/claude-haiku-4.5',
        'fallback_models' => [
            'meta-llama/llama-3.1-8b-instruct',
            'google/gemini-2.0-flash-001'
        ],
        'models' => [
            // General purpose models
            'anthropic/claude-sonnet-4' => [
                'name' => 'Claude Sonnet 4',
                'description' => 'Most powerful model for complex tasks',
                'context_length' => 200000,
                'multimodal' => true
            ],
            'anthropic/claude-haiku-4.5' => [
                'name' => 'Claude Haiku 4.5',
                'description' => 'Balanced performance and speed',
                'context_length' => 180000,
                'multimodal' => true
            ],
            'google/gemini-2.0-flash-001' => [
                'name' => 'Gemini 2.0 Flash',
                'description' => 'Fast responses for simple tasks',
                'context_length' => 150000,
                'multimodal' => true
            ],
            'meta-llama/llama-3.1-8b-instruct' => [
                'name' => 'Llama 3.1 8B',
                'description' => 'Google\'s advanced model',
                'context_length' => 30000,
                'multimodal' => false
            ],
            
            // Specialized models
            'deepseek/deepseek-math' => [
                'name' => 'DeepSeek Math',
                'description' => 'Math & Science specialist',
                'context_length' => 32000,
                'multimodal' => false
            ],
            
        ],
        'task_mapping' => [
            'essay_writing' => 'anthropic/claude-haiku-4.5',
            'math_problem' => 'anthropic/claude-haiku-4.5',
            'science_question' => 'anthropic/claude-haiku-4.5',
            'programming' => 'anthropic/claude-haiku-4.5',
            'general_question' => 'anthropic/claude-haiku-4.5'
        ]
    ]
];
