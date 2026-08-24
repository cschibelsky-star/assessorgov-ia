<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Gratuito',
                'slug' => 'gratuito',
                'description' => 'Descoberta de oportunidades culturais e qualificacao inicial do lead.',
                'price' => 0.00,
                'billing_cycle' => 'monthly',
                'features' => [
                    'included_users' => 1,
                    'radar_limit' => 5,
                    'draft_projects_limit' => 0,
                    'active_monitoring_limit' => 0,
                    'ai_project' => false,
                    'ai_analysis' => false,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Profissional',
                'slug' => 'profissional',
                'description' => 'Para fazedores de cultura que querem participar de editais com apoio de IA e monitorar projetos aprovados.',
                'price' => 0.00,
                'billing_cycle' => 'monthly',
                'features' => [
                    'included_users' => 1,
                    'radar_limit' => 20,
                    'draft_projects_limit' => 3,
                    'active_monitoring_limit' => 2,
                    'ai_project' => true,
                    'ai_analysis' => true,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Para operacao cultural ampliada com mais projetos e monitoramentos simultaneos.',
                'price' => 0.00,
                'billing_cycle' => 'monthly',
                'features' => [
                    'included_users' => 1,
                    'radar_limit' => 50,
                    'draft_projects_limit' => 10,
                    'active_monitoring_limit' => 5,
                    'ai_project' => true,
                    'ai_analysis' => true,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Capacidades e implantacao personalizadas.',
                'price' => 0.00,
                'billing_cycle' => 'monthly',
                'features' => [
                    'included_users' => 1,
                    'radar_limit' => null,
                    'draft_projects_limit' => null,
                    'active_monitoring_limit' => null,
                    'ai_project' => true,
                    'ai_analysis' => true,
                ],
                'is_active' => true,
            ],
        ];

        Plan::query()->where('slug', 'essencial')->update(['is_active' => false]);

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
