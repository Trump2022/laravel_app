<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    Route::redirect('/', '/front/admin/dashboard');
});

Route::view('/front/{path}', 'vue-learning')->where('path', '.*');
