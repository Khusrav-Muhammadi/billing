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
        $goUrl = URL::temporarySignedRoute('payment.verification.go', $expiresAt, [
            'provider' => $provider,
            'payment' => $payment->id,
        ]);

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
            ->where('payment_id', $payment->id)
            ->first(['id', 'partner_id', 'payment_link']);

        $checkoutUrl = trim((string) ($offer?->payment_link ?? ''));
        if ($checkoutUrl === '') {
            abort(404, 'Ссылка на оплату не найдена.');
        }

        $consent = filter_var($request->input('consent'), FILTER_VALIDATE_BOOLEAN);
        if (!$consent) {
            $expiresTs = (int) $request->query('expires', 0);
            $expiresAt = $expiresTs > 0 ? Carbon::createFromTimestamp($expiresTs) : now()->addDays(7);
            $showUrl = URL::temporarySignedRoute('payment.verification.show', $expiresAt, [
                'provider' => $provider,
                'payment' => $payment->id,
            ]);

            $message = $offer?->partner_id
                ? 'Подтвердите, что вы ознакомили клиента с условиями перед оплатой.'
                : 'Подтвердите согласие с условиями оферты перед оплатой.';

            return redirect()->to($showUrl)->withErrors([
                'consent' => $message,
            ]);
        }

        return redirect()->away($checkoutUrl);
    }
}
