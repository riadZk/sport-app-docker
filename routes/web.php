<?php

use Illuminate\Support\Facades\Route;
use App\Events\MessageSent;
use App\Models\User;
use App\Services\NotificationService;
use App\Notifications\JoinTeamNotification;

Route::get('/', function () {
    return view('welcome');
});



if (app()->environment('local')) {
    Route::get('/test-reverb', function () {
        return view('test-reverb');
    });

    Route::get('/test-notifications', function () {
        return view('test-notifications');
    });
}

