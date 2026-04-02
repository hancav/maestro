<?php

use App\Http\Controllers\OrchestrationController;
use Illuminate\Support\Facades\Route;

Route::post('/orchestrate', [OrchestrationController::class, 'orchestrate'])
    ->name('api.orchestrate');
