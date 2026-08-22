<?php

use App\Http\Controllers\CulturaDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'application' => config('app.name', 'AssessorGov IA'),
        'status' => 'ok',
        'solutions' => [
            'assessorgov' => 'Compras Publicas',
            'cultura' => 'Cultura e Editais',
            'terceiro_setor' => 'Projetos Sociais e Captacao de Recursos',
        ],
    ]);
});

Route::get('/cultura', CulturaDashboardController::class)->name('cultura.dashboard');
