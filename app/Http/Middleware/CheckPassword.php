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
            $targetUrl = str_rot13(base64_decode($request->route('url')));
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
