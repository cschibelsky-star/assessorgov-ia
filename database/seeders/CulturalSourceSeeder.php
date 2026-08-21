<?php

namespace Database\Seeders;

use App\Models\CulturalSource;
use Illuminate\Database\Seeder;

class CulturalSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            [
                'key' => 'sp-cultura-proac-editais',
                'name' => 'Fomento CultSP / ProAC Editais',
                'owner' => 'Secretaria da Cultura, Economia e Industria Criativas do Estado de Sao Paulo',
                'scope' => 'state',
                'state' => 'SP',
                'url' => 'https://fomentocultsp.sp.gov.br/',
                'source_type' => 'official_web',
                'ingestion_mode' => 'html',
                'priority' => 10,
                'enabled' => true,
                'metadata' => ['programs' => ['ProAC Editais']],
            ],
            [
                'key' => 'sp-cultura-pnab',
                'name' => 'PNAB Sao Paulo',
                'owner' => 'Secretaria da Cultura, Economia e Industria Criativas do Estado de Sao Paulo',
                'scope' => 'state',
                'state' => 'SP',
                'url' => 'https://www.cultura.sp.gov.br/',
                'source_type' => 'official_web',
                'ingestion_mode' => 'html',
                'priority' => 20,
                'enabled' => true,
                'metadata' => ['programs' => ['PNAB']],
            ],
            [
                'key' => 'sp-capital-cultura-editais',
                'name' => 'Editais da Cultura - Cidade de Sao Paulo',
                'owner' => 'Secretaria Municipal de Cultura e Economia Criativa de Sao Paulo',
                'scope' => 'municipal',
                'state' => 'SP',
                'municipality' => 'Sao Paulo',
                'url' => 'https://prefeitura.sp.gov.br/web/cultura/',
                'source_type' => 'official_web',
                'ingestion_mode' => 'html',
                'priority' => 30,
                'enabled' => true,
                'metadata' => ['coverage' => 'municipal'],
            ],
        ];

        foreach ($sources as $source) {
            CulturalSource::query()->updateOrCreate(['key' => $source['key']], $source);
        }
    }
}
