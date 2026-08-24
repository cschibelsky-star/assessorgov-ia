<?php

namespace Database\Seeders;

use App\Models\CulturalOpportunity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CulturalOpportunitySeeder extends Seeder
{
    public function run(): void
    {
        $checkedAt = Carbon::parse('2026-08-24 20:30:00', 'America/Sao_Paulo');

        $opportunities = [
            [
                'source_name' => 'Secretaria Municipal de Cultura e Economia Criativa de Sao Paulo',
                'source_type' => 'official_web',
                'source_url' => 'https://prefeitura.sp.gov.br/web/cultura/w/secretaria-de-cultura-e-economia-criativa-abre-inscri%C3%A7%C3%B5es-para-11%C2%BA-edital-de-fomento-%C3%A0-periferia',
                'external_id' => 'smc-sp-fomento-periferia-11-2026',
                'title' => '11a Edicao do Programa de Fomento a Cultura da Periferia',
                'summary' => 'Apoio a projetos culturais de coletivos artisticos e culturais em andamento em distritos ou bolsoes com altos indices de vulnerabilidade social no municipio de Sao Paulo.',
                'organization' => 'Secretaria Municipal de Cultura e Economia Criativa de Sao Paulo',
                'opportunity_type' => 'fomento',
                'state' => 'SP',
                'municipalities' => ['Sao Paulo'],
                'cultural_areas' => ['Musica', 'Artes Cenicas', 'Danca', 'Audiovisual', 'Literatura', 'Artes Visuais', 'Cultura Popular', 'Patrimonio', 'Circo'],
                'eligible_legal_profiles' => ['Coletivo'],
                'funding_min' => 158350.37,
                'funding_max' => 475051.11,
                'opens_at' => Carbon::parse('2026-08-18 00:00:00', 'America/Sao_Paulo'),
                'closes_at' => Carbon::parse('2026-09-06 23:59:59', 'America/Sao_Paulo'),
                'eligibility_rules' => [
                    'Atuacao em distritos ou bolsoes com altos indices de vulnerabilidade social no municipio de Sao Paulo.',
                    'Projeto cultural de coletivo artistico ou cultural em andamento.',
                ],
                'required_documents' => [],
                'metadata' => [
                    'verified' => true,
                    'investment_total' => 14000000,
                    'estimated_selected_projects' => 30,
                    'radar_stage' => 'aberto',
                ],
                'status' => 'active',
                'source_checked_at' => $checkedAt,
            ],
            [
                'source_name' => 'Secretaria Municipal de Cultura e Economia Criativa de Sao Paulo',
                'source_type' => 'official_web',
                'source_url' => 'https://prefeitura.sp.gov.br/web/cultura/w/secretaria-municipal-de-cultura-e-economia-criativa-de-s%C3%A3o-paulo-abre-inscri%C3%A7%C3%B5es-para-a-4%C2%AA-edi%C3%A7%C3%A3o-do-edital-de-fomento-%C3%A0-capoeira',
                'external_id' => 'smc-sp-fomento-capoeira-4-2026',
                'title' => '4a Edicao do Programa Municipal de Fomento a Capoeira',
                'summary' => 'Programa municipal para fomentar, apoiar e reconhecer a importancia historica e cultural da capoeira, com modulos de memoria/preservacao e de formacao/difusao.',
                'organization' => 'Secretaria Municipal de Cultura e Economia Criativa de Sao Paulo',
                'opportunity_type' => 'fomento',
                'state' => 'SP',
                'municipalities' => ['Sao Paulo'],
                'cultural_areas' => ['Cultura Popular'],
                'eligible_legal_profiles' => [],
                'funding_min' => 100000.00,
                'funding_max' => 150000.00,
                'opens_at' => Carbon::parse('2026-08-18 00:00:00', 'America/Sao_Paulo'),
                'closes_at' => Carbon::parse('2026-09-06 23:59:59', 'America/Sao_Paulo'),
                'eligibility_rules' => [
                    'Projetos relacionados a capoeira na cidade de Sao Paulo.',
                    'Modulo 1: memoria e preservacao.',
                    'Modulo 2: formacao e difusao.',
                ],
                'required_documents' => [],
                'metadata' => [
                    'verified' => true,
                    'investment_total' => 2500000,
                    'max_selected_projects' => 20,
                    'module_1_amount' => 150000,
                    'module_2_amount' => 100000,
                    'radar_stage' => 'aberto',
                ],
                'status' => 'active',
                'source_checked_at' => $checkedAt,
            ],
        ];

        foreach ($opportunities as $opportunity) {
            CulturalOpportunity::query()->updateOrCreate(
                [
                    'source_name' => $opportunity['source_name'],
                    'external_id' => $opportunity['external_id'],
                ],
                $opportunity
            );
        }
    }
}
