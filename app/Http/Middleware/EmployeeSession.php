<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeeSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->has('employee_id')) {
            return redirect('/timeclock');
        }

        return $next($request);
    }
}
