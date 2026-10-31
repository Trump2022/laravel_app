<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::view('/', 'vue-learning');
Route::view('/web/{path?}', 'vue-learning')->where('path', '.*');
Route::view('/admin/{path?}', 'vue-learning')->where('path', '.*');
// Route::view('/{path}', 'vue-learning')->where('path', '.*');//(如果没有其它规则匹配)任意路径，返回 vue-learning 视图

// Route::fallback(function () {
//     return view('vue-learning');
// });
Route::fallback(fn() => view('vue-learning'));// 处理所有未匹配的路由，返回 vue-learning 视图, 与上面注释掉的闭包写法完全等同
