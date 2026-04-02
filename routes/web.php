<?php

use Illuminate\Support\Facades\Route;

// Redirect everything to Filament admin panel
Route::redirect('/', '/admin');
Route::redirect('/dashboard', '/admin');
Route::redirect('/login', '/admin/login');
