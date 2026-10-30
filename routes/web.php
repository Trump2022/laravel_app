<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::redirect('/', '/manage/admin/dashboard');
Route::view('/manage/{path}', 'vue-learning')->where('path', '.*');
