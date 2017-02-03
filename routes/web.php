<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

Route::get('/', function () {
    $url      = "https://facebook.de";
    $password = md5(env('PROXY_PASSWORD') . $url);
    $target   = urlencode(base64_encode(str_rot13($url)));
    return "<a href=\"" . action('ProxyController@proxyPage', ['password' => $password, 'url' => $target]) . "\">$url</a>";
    return md5(env('PROXY_PASSWORD') . "https://metager.de") . "<br>\n" . urlencode(base64_encode(str_rot13('https://metager.de')));
    #return redirect('https://metager.de');
});

Route::get('{password}/{url}', 'ProxyController@proxyPage')->middleware('checkpw');

Route::group(['prefix' => 'proxy/{password}', 'middleware' => ['checkpw:true']], function ($password) {

    Route::get('{url}', 'ProxyController@proxy')->where(['url' => '.*']);

});
