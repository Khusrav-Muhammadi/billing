<?php

namespace App\Http\Controllers;

use App\Models\CommercialOffer;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class PaymentVerificationController extends Controller
{
    public function show(Request $request, string $provider, Payment $payment)
    {
        $provider = strtolower(trim($provider));

        if (!in_array($provider, ['alif', 'octo'], true)) {
            abort(404);
        }

        if (strtolower((string) $payment->payment_type) !== $provider) {
            abort(404);
        }

        $offer = CommercialOffer::query()
            ->with([
                'organization:id,name',
                'partner:id,name',
            ])
            ->where('payment_id', $payment->id)
            ->latest('id')
            ->first();

        $items = $payment->paymentItems()->get(['service_name', 'price']);

        $expiresTs = (int) $request->query('expires', 0);
        $expiresAt = $expiresTs > 0 ? Carbon::createFromTimestamp($expiresTs) : now()->addDays(7);
        $goUrl = URL::temporarySignedRoute('payment.verification.go', $expiresAt, array_filter([
            'provider' => $provider,
            'payment' => $payment->id,
            'return_url' => $this->trustedReturnUrlFromRequest($request),
        ]));

        return view('public.payment-verification', [
            'provider' => $provider,
            'providerLabel' => $provider === 'alif' ? 'Alif' : 'Octo Bank',
            'payment' => $payment,
            'offer' => $offer,
            'items' => $items,
            'goUrl' => $goUrl,
        ]);
    }

    public function go(Request $request, string $provider, Payment $payment)
    {
        $provider = strtolower(trim($provider));

        if (!in_array($provider, ['alif', 'octo'], true)) {
            abort(404);
        }

        if (strtolower((string) $payment->payment_type) !== $provider) {
            abort(404);
        }

        $offer = CommercialOffer::query()
            ->with([
                'partner:id,name,email',
                'organization:id,name,email,client_id',
                'organization.client:id,name,email',
            ])
            ->where('payment_id', $payment->id)
            ->first(['id', 'organization_id', 'partner_id', 'client_email', 'client_name', 'partner_email', 'partner_name', 'payment_link', 'payable_currency', 'currency']);

        $checkoutUrl = trim((string) ($offer?->payment_link ?? ''));
        if ($checkoutUrl === '') {
            abort(404, 'Ссылка на оплату не найдена.');
        }

        $consent = filter_var($request->input('consent'), FILTER_VALIDATE_BOOLEAN);
        if (!$consent) {
            $expiresTs = (int) $request->query('expires', 0);
            $expiresAt = $expiresTs > 0 ? Carbon::createFromTimestamp($expiresTs) : now()->addDays(7);
            $showUrl = URL::temporarySignedRoute('payment.verification.show', $expiresAt, array_filter([
                'provider' => $provider,
                'payment' => $payment->id,
                'return_url' => $this->trustedReturnUrlFromRequest($request),
            ]));

            return redirect()->to($showUrl)->withErrors([
                'consent' => 'Подтвердите согласие с условиями оферты перед оплатой.',
            ]);
        }

        return redirect()->away($checkoutUrl);
    }

    private function signedShowUrl(string $provider, Payment $payment, Request $request): string
    {
        $expiresTs = (int) $request->query('expires', 0);
        $expiresAt = $expiresTs > 0 ? Carbon::createFromTimestamp($expiresTs) : now()->addDays(7);

        return URL::temporarySignedRoute('payment.verification.show', $expiresAt, array_filter([
            'provider' => $provider,
            'payment' => $payment->id,
            'return_url' => $this->trustedReturnUrlFromRequest($request),
        ]));
    }

    private function trustedReturnUrlFromRequest(Request $request): ?string
    {
        $url = trim((string)$request->query('return_url', ''));
        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        $allowedHosts = array_filter([
            parse_url((string)config('app.url'), PHP_URL_HOST),
            parse_url((string)config('partners.url'), PHP_URL_HOST),
            'partners.shamcrm.com',
        ]);

        return in_array($host, $allowedHosts, true) ? $url : null;
    }
}
