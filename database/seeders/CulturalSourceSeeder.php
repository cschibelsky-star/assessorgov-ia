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
                'key' => 'sp-cultura-fomento',
                'name' => 'Fomento CultSP - Editais e PNAB',
                'owner' => 'Secretaria da Cultura, Economia e Industria Criativas do Estado de Sao Paulo',
                'scope' => 'state',
                'state' => 'SP',
                'url' => 'https://www.cultura.sp.gov.br/sec_cultura/Fomento/Fomento_Editais_e_PNAB/',
                'source_type' => 'official_web',
                'ingestion_mode' => 'html',
                'priority' => 10,
                'enabled' => true,
                'metadata' => [
                    'programs' => ['ProAC Editais', 'PNAB', 'Cultura Viva'],
                    'canonical' => true,
                ],
            ],
            [
                'key' => 'sp-cultura-pnab',
                'name' => 'PNAB Sao Paulo - fonte redundante desativada',
                'owner' => 'Secretaria da Cultura, Economia e Industria Criativas do Estado de Sao Paulo',
                'scope' => 'state',
                'state' => 'SP',
                'url' => 'https://www.cultura.sp.gov.br/sec_cultura/Fomento/Fomento_Editais_e_PNAB/',
                'source_type' => 'official_web',
                'ingestion_mode' => 'html',
                'priority' => 20,
                'enabled' => false,
                'metadata' => ['reason' => 'covered_by_sp-cultura-fomento'],
            ],
            [
                'key' => 'sp-capital-cultura-editais',
                'name' => 'Editais da Cultura - Cidade de Sao Paulo',
                'owner' => 'Secretaria Municipal de Cultura e Economia Criativa de Sao Paulo',
                'scope' => 'municipal',
                'state' => 'SP',
                'municipality' => 'Sao Paulo',
                'url' => 'https://prefeitura.sp.gov.br/web/cultura/editais',
                'source_type' => 'official_web',
                'ingestion_mode' => 'html',
                'priority' => 30,
                'enabled' => true,
                'metadata' => ['coverage' => 'municipal', 'canonical' => true],
            ],
        ];

        foreach ($sources as $source) {
            CulturalSource::query()->updateOrCreate(['key' => $source['key']], $source);
        }

        CulturalSource::query()
            ->where('key', 'sp-cultura-proac-editais')
            ->update(['enabled' => false, 'last_status' => 'replaced']);
    }
}
