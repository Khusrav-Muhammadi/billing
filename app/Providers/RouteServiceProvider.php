<?php

namespace App\Providers;

use App\Http\Middleware\VerifyPartnersPortalToken;
use App\Support\CrmHttp;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // Межсерверные вызовы (CRM, партнёрский портал) идут с одного IP. Общий лимит
            // 60/мин по IP резал их: в 01:00 и 13:00 все тенанты CRM шлют client/activity,
            // проходили ~60 из ~540, остальные получали 429 и «последняя активность»
            // клиентов в биллинге застывала. С верным сервисным токеном общий лимит не
            // применяем; свои лимиты маршрутов (throttle:N,1) продолжают действовать.
            if ($this->hasValidServiceToken($request)) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Additional API routes for payment-related endpoints.
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/payments.php'));

            // Партнёрский портал partners.shamcrm.com (server-to-server, X-Partners-Token).
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/partner_program.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /** X-Service-Token (CRM) или X-Partners-Token (портал) совпадает с секретом из .env. */
    private function hasValidServiceToken(Request $request): bool
    {
        $pairs = [
            [CrmHttp::HEADER, (string) config('services.sham.crm_token', '')],
            [VerifyPartnersPortalToken::HEADER, (string) config('partners_portal.token', '')],
        ];

        foreach ($pairs as [$header, $expected]) {
            $expected = trim($expected);
            $provided = trim((string) $request->header($header, ''));
            if ($expected !== '' && $provided !== '' && hash_equals($expected, $provided)) {
                return true;
            }
        }

        return false;
    }
}
