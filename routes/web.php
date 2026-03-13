<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::get('/compra', function () {
    return view('compra');
});

Route::get('/taquilla', function () {
    return view('taquilla');
});

Route::get('/barra', function () {
    return view('barra');
});

Route::get('/vendedor/barra', function () {
    return view('barra');
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

Route::get('/usuarios', function () {
    return view('usuarios');
});

Route::get('/barra-reportes', function () {
    return view('barra-reportes');
});
