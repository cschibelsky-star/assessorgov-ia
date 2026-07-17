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
                'name' => 'Essencial',
                'slug' => 'essencial',
                'description' => 'Operacao inicial para equipes publicas de pequeno porte.',
                'price' => 297.00,
                'billing_cycle' => 'monthly',
                'features' => ['assistente_ia', 'documentos', 'painel_basico'],
                'is_active' => true,
            ],
            [
                'name' => 'Profissional',
                'slug' => 'profissional',
                'description' => 'Gestao ampliada com automacoes, auditoria e multiplos usuarios.',
                'price' => 697.00,
                'billing_cycle' => 'monthly',
                'features' => ['assistente_ia', 'documentos', 'automacoes', 'auditoria', 'multiusuario'],
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Implantacao personalizada para orgaos e estruturas complexas.',
                'price' => 1500.00,
                'billing_cycle' => 'monthly',
                'features' => ['todos_modulos', 'integracoes', 'suporte_prioritario', 'implantacao_assistida'],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
