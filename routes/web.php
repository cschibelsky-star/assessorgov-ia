<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CulturaDashboardController;
use App\Http\Controllers\CulturalOpportunityController;
use App\Http\Controllers\CulturalProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'cultura.landing')->name('home');
Route::view('/cultura/conheca', 'cultura.landing')->name('cultura.landing');

Route::get('/status', function () {
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

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->name('login.store');
    Route::get('/cadastro', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/cadastro', [AuthController::class, 'register'])->name('register.store');
});

Route::post('/sair', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('cultura')->name('cultura.')->group(function () {
    Route::get('/', CulturaDashboardController::class)->name('dashboard');
    Route::get('/perfil', [CulturalProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [CulturalProfileController::class, 'update'])->name('profile.update');
    Route::get('/oportunidades/{opportunity}', [CulturalOpportunityController::class, 'show'])->name('opportunities.show');
});
