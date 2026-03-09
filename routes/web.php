<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::get('/compra', function () {
    return view('compra');
});

Route::get('/imprimir', function () {
    return view('imprimir');
});

Route::get('/validador', function () {
    return view('validador');
});

Route::get('/admin', function () {
    return view('admin');
});

Route::get('/vendedor', function () {
    return view('admin');
});

Route::get('/reportes', function () {
    return view('reportes');
});

Route::get('/vendedor/reportes', function () {
    return view('reportes');
});
