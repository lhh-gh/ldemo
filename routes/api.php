<?php

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/tasks', function () {
    return Task::query()
        ->latest()
        ->get();
});

Route::post('/tasks', function (Request $request) {
    $attributes = $request->validate([
        'title' => ['required', 'string', 'max:255'],
    ]);

    return response()->json(Task::create($attributes), 201);
});
