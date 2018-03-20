<?php

namespace App\Http\Middleware;

use Closure;

class CheckPassword
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $timed = "false")
    {

        $password = $request->route('password');
        if ($timed === "true") {
            $checkPw = md5(env('PROXY_PASSWORD') . date('dmy'));
            if ($checkPw === $password) {
                return $next($request);
            }
        } else {
            $targetUrl = str_replace("<<SLASH>>", "/", $request->route('url'));
            $targetUrl = str_rot13(base64_decode($targetUrl));
            
            // FIXME temporary check for ban list
            if (md5($targetUrl) === "34ad9f915d09506dd41912507a63de1b") {
                return abort(403);
            }
            
            // Check Password:
            $checkPw  = md5(env('PROXY_PASSWORD') . $targetUrl);
            $password = $request->route('password');
            if ($checkPw === $password) {
                return $next($request);
            }
        }
        return abort(403);
    }
}
