<?php

use App\Models\CulturalSource;
use App\Services\Cultura\CulturalRadarImporter;
use App\Services\Cultura\CulturalSourceIngestor;
use Illuminate\Support\Facades\Artisan;

Artisan::command('assessorgov:status', function () {
    $this->info('AssessorGov IA operational foundation is available.');
})->purpose('Check the AssessorGov IA application foundation');

Artisan::command('cultura:sources', function () {
    $rows = CulturalSource::query()
        ->orderBy('priority')
        ->get()
        ->map(fn (CulturalSource $source) => [
            $source->key,
            $source->enabled ? 'yes' : 'no',
            $source->scope,
            $source->last_status ?? '-',
            optional($source->last_success_at)?->toDateTimeString() ?? '-',
        ]);

    $this->table(['source', 'enabled', 'scope', 'status', 'last_success'], $rows);
})->purpose('List configured official cultural opportunity sources');

Artisan::command('cultura:fetch-sources {--source=}', function () {
    $query = CulturalSource::query()->where('enabled', true)->orderBy('priority');

    if ($key = $this->option('source')) {
        $query->where('key', $key);
    }

    $sources = $query->get();

    if ($sources->isEmpty()) {
        $this->warn('No enabled cultural sources matched.');
        return 1;
    }

    $ingestor = app(CulturalSourceIngestor::class);
    $errors = 0;

    foreach ($sources as $source) {
        try {
            $result = $ingestor->ingest($source);
            $this->info(sprintf('%s fetched (%d bytes, %s)', $source->key, strlen($result['body']), $result['content_type'] ?: 'unknown content type'));
        } catch (Throwable $e) {
            $errors++;
            $this->error($source->key.': '.$e->getMessage());
        }
    }

    return $errors === 0 ? 0 : 2;
})->purpose('Fetch enabled official cultural sources and record health metadata');

Artisan::command('cultura:import {--source=}', function () {
    $query = CulturalSource::query()->where('enabled', true)->orderBy('priority');

    if ($key = $this->option('source')) {
        $query->where('key', $key);
    }

    $sources = $query->get();
    if ($sources->isEmpty()) {
        $this->warn('No enabled cultural sources matched.');
        return 1;
    }

    $importer = app(CulturalRadarImporter::class);
    $errors = 0;

    foreach ($sources as $source) {
        try {
            $result = $importer->import($source);
            $this->info(sprintf('%s: %d discovered, %d created, %d updated', $result['source'], $result['discovered'], $result['created'], $result['updated']));
        } catch (Throwable $e) {
            $errors++;
            $this->error($source->key.': '.$e->getMessage());
        }
    }

    return $errors === 0 ? 0 : 2;
})->purpose('Fetch, parse, normalize and persist cultural opportunities for Radar Cultural SP');
