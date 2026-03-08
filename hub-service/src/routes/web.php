<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/USA/dashboard');

Route::get('/{country}', function (string $country) {
    return redirect('/' . strtoupper($country) . '/dashboard');
})->where('country', 'USA|DEU|usa|deu');

Route::get('/{country}/{step}', function (string $country, string $step) {
    $country = strtoupper($country);

    return view('hub', [
        'country' => $country,
        'step' => $step,
    ]);
})->where([
    'country' => 'USA|DEU|usa|deu',
    'step' => 'dashboard|employees|checklist|documentation',
]);
