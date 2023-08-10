<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});



Route::group(['namespace'=>'Api'],function(){
    
    Route::any('/login','LoginController@login');
    Route::any('/contact','AccessTokenController@contact')->middleware('CheckUser');
    Route::any('/get_profile','AccessTokenController@get_profile')->middleware('CheckUser');
    Route::any('/update_profile','AccessTokenController@update_profile')->middleware('CheckUser');
    Route::any('/get_rtc_token','AccessTokenController@get_rtc_token') -> middleware('CheckUser');
    Route::any('/send_notice','AccessTokenController@send_notice') -> middleware('CheckUser');
    Route::any('/bind_fcmtoken','AccessTokenController@bind_fcmtoken') -> middleware('CheckUser');
    Route::any('/upload_photo','AccessTokenController@upload_photo') -> middleware('CheckUser');

    }
);


