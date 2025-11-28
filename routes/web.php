<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/web-stories/top-programming-languages', function () {
    return view('stories.top-programming-languages');
});
