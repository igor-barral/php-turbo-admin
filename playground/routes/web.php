<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

// Login mínimo do playground (o php-turbo-admin usa a autenticação do próprio app).
Route::get('/login', fn () => view('login'))->name('login')->middleware('guest');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);

    if (! Auth::attempt($credentials, $request->boolean('remember'))) {
        return back()->withErrors(['email' => 'Credenciais inválidas.'])->onlyInput('email');
    }

    $request->session()->regenerate();

    return redirect()->intended('/admin');
})->middleware(['guest', 'throttle:30,1']);

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->noContent();
})->middleware('auth');
