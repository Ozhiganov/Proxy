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

Route::post('/{url}', function ($url) {
    abort(405);
});

Route::get('/', function () {
    $url = "https://metager.de";
    $password = md5(env('PROXY_PASSWORD') . $url);
    $url = base64_encode(str_rot13($url));
    $target = urlencode(str_replace("/", "<<SLASH>>", $url));
    return "<a href=\"" . action('ProxyController@proxyPage', ['password' => $password, 'url' => $target]) . "\">$url</a>";
    return md5(env('PROXY_PASSWORD') . "https://metager.de") . "<br>\n" . urlencode(base64_encode(str_rot13('https://metager.de')));
    #return redirect('https://metager.de');
});

Route::get('{password}/{url}', 'ProxyController@proxyPage')->middleware('checkpw');

Route::get('proxy/{password}/{url}', 'ProxyController@proxy')->middleware('checkpw:true');
