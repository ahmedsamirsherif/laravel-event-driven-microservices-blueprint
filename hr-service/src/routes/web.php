<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/employees');

Route::view('/employees', 'employees.index');
Route::view('/employees/create', 'employees.form', [
    'mode' => 'create',
    'employeeId' => null,
]);

Route::get('/employees/{id}/edit', function (int $id) {
    return view('employees.form', [
        'mode' => 'edit',
        'employeeId' => $id,
    ]);
})->whereNumber('id');
