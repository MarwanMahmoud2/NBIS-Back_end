<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Services\SystemSettingsService;
use Symfony\Component\HttpFoundation\Response;

class SetLanguage
{
    protected $settingsService;

    public function __construct(SystemSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language');

        if ($locale && in_array($locale, ['ar', 'en', 'fr'])) {
            App::setLocale($locale);
        } else {
            $dbLocale = $this->settingsService->getSystemLanguage();
            App::setLocale($dbLocale);
        }

        return $next($request);
    }
}