<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/setup', function () {

    $credentials = [
        'first_name' => 'admin',
        'last_name' => 'admin',
        'email' => 'admin@gmail.com',
        'password' => 'password',
    ];

    if (! Auth::attempt($credentials)) {
        $user = new App\Models\User;
        $user->first_name = $credentials['first_name'];
        $user->last_name = $credentials['last_name'];
        $user->email = $credentials['email'];
        $user->password_hash = Hash::make($credentials['password']);
        $user->save();

    }
    if (Auth::attempt($credentials)) {
        $user = Auth::user();

        $adminToken = $user->createToken('admin-token', ['create', 'update', 'delete'])->plainTextToken;
        $updateToken = $user->createToken('update-token', ['create', 'update'])->plainTextToken;
        $basicToken = $user->createToken('basic-token')->plainTextToken;

        return [
            'admin' => $adminToken,
            'update' => $updateToken,
            'basic' => $basicToken,
        ];
    }
});
