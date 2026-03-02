<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active company for the current request.
 *
 * Resolution order:
 *   1. Session key 'company_id'  – set when the user explicitly switches company
 *   2. First active company in the database (fallback for single-company setups)
 *   3. null – no company configured yet (new installation)
 *
 * The resolved company is bound in the IoC container under the abstract
 * 'current_company' so that services can inject it:
 *
 *   $company = app('current_company');   // Company|null
 *
 * This middleware is applied to the admin route group only.
 * POS API routes resolve the company differently (per-token or per-request header).
 */
class CompanyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $request->session()->get('company_id');

        $company = $companyId
            ? Company::find($companyId)
            : Company::where('active', true)->orderBy('id')->first();

        // Bind so that controllers/services can retrieve it via app('current_company').
        // NOTE: App::instance() uses isset() internally, which returns false for null,
        // causing make() to fail with BindingResolutionException. We therefore use
        // bind() (closure-based) which correctly handles null values.
        App::bind('current_company', static function () use ($company) {
            return $company;
        });

        // Also make it available in all Blade views
        if (function_exists('view')) {
            view()->share('currentCompany', $company);
        }

        return $next($request);
    }
}
