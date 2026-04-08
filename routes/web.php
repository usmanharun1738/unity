<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:admin|department-staff'])
    ->group(function (): void {
        Route::livewire('departments', 'pages::departments.index')->name('departments.index');
        Route::livewire('departments/{department}', 'pages::departments.show')->name('departments.show');

        Route::livewire('subjects', 'pages::subjects.index')->name('subjects.index');
        Route::livewire('subjects/{course}', 'pages::subjects.show')->name('subjects.show');

        Route::livewire('faculty', 'pages::faculty.index')->name('faculty.index');
        Route::livewire('faculty/{facultyProfile}', 'pages::faculty.show')->name('faculty.show');

        Route::livewire('classes', 'pages::courses.index')->name('courses.index');
        Route::livewire('classes/{course}', 'pages::courses.show')->name('courses.show');
    });

Route::middleware(['auth', 'verified'])
    ->group(function (): void {
        Route::livewire('enrollments', 'pages::enrollments.index')->name('enrollments.index');
    });

require __DIR__.'/settings.php';
