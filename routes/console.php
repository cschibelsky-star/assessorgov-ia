<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('assessorgov:status', function () {
    $this->info('AssessorGov IA operational foundation is available.');
})->purpose('Check the AssessorGov IA application foundation');
