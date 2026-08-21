<?php

return [
    'state' => 'SP',

    'plans' => [
        'gratuito' => [
            'included_users' => 1,
            'radar_limit' => 5,
            'draft_projects_limit' => 0,
            'active_monitoring_limit' => 0,
            'ai_project' => false,
            'ai_analysis' => false,
        ],
        'profissional' => [
            'included_users' => 1,
            'radar_limit' => 20,
            'draft_projects_limit' => 3,
            'active_monitoring_limit' => 2,
            'ai_project' => true,
            'ai_analysis' => true,
        ],
        'premium' => [
            'included_users' => 1,
            'radar_limit' => 50,
            'draft_projects_limit' => 10,
            'active_monitoring_limit' => 5,
            'ai_project' => true,
            'ai_analysis' => true,
        ],
        'enterprise' => [
            'included_users' => 1,
            'radar_limit' => null,
            'draft_projects_limit' => null,
            'active_monitoring_limit' => null,
            'ai_project' => true,
            'ai_analysis' => true,
        ],
    ],
];
