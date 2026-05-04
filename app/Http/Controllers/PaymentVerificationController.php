<?php

namespace App\Http\Controllers;

use App\Models\CommercialOffer;
use App\Models\Payment;
use App\Services\Mailing\ResendMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    public function go(Request $request, string $provider, Payment $payment, ResendMailService $mailService)
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

            $message = $offer?->partner_id
                ? 'Подтвердите, что вы ознакомили клиента с условиями перед оплатой.'
                : 'Подтвердите согласие с условиями оферты перед оплатой.';

            return redirect()->to($showUrl)->withErrors([
                'consent' => $message,
            ]);
        }

        $recipients = $this->paymentLinkRecipients($offer, $payment);

        if ($recipients === []) {
            return redirect()->to($this->signedShowUrl($provider, $payment, $request))->withErrors([
                'email' => 'Не найдена почта клиента или партнёра для отправки ссылки на оплату.',
            ]);
        }

        $sent = 0;
        foreach ($recipients as $recipient) {
            try {
                $ok = $mailService->sendWithView(
                    to: $recipient['email'],
                    subject: 'Ссылка на оплату SHAMCRM',
                    view: 'mail.payment_checkout_link',
                    data: [
                        'recipientName' => $recipient['name'],
                        'payment' => $payment,
                        'offer' => $offer,
                        'providerLabel' => $provider === 'alif' ? 'Alif' : 'Octo Bank',
                        'checkoutUrl' => $checkoutUrl,
                        'currency' => strtoupper((string)($offer?->payable_currency ?: $offer?->currency ?: '')),
                    ],
                    sendInternalCopy: false,
                    logContext: [
                        'organization_id' => $offer?->organization_id,
                        'client_id' => $offer?->organization?->client_id,
                        'commercial_offer_id' => $offer?->id,
                        'action' => $recipient['type'] === 'partner'
                            ? 'payment_link_email_partner'
                            : 'payment_link_email_client',
                    ]
                );

                if ($ok) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::error('PaymentVerificationController: failed to send payment link email', [
                    'payment_id' => $payment->id,
                    'offer_id' => $offer?->id,
                    'recipient' => $recipient['email'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($sent === 0) {
            return redirect()->to($this->signedShowUrl($provider, $payment, $request))->withErrors([
                'email' => 'Не удалось отправить ссылку на оплату. Проверьте почту клиента/партнёра и настройки Resend.',
            ]);
        }

        return redirect()
            ->to($this->afterSendRedirectUrl($provider, $payment, $request))
            ->with('payment_link_sent', 'Ссылка на оплату отправлена клиенту' . ($offer?->partner_id ? ' и партнёру.' : '.'));
    }

    private function paymentLinkRecipients(?CommercialOffer $offer, Payment $payment): array
    {
        $recipients = [];

        $clientEmail = trim((string)($offer?->client_email ?: $offer?->organization?->client?->email ?: $offer?->organization?->email ?: $payment->email));
        if ($clientEmail !== '') {
            $recipients[] = [
                'type' => 'client',
                'email' => $clientEmail,
                'name' => trim((string)($offer?->client_name ?: $offer?->organization?->client?->name ?: $payment->name ?: '')),
            ];
        }

        $partnerEmail = trim((string)($offer?->partner_email ?: $offer?->partner?->email));
        if ($offer?->partner_id && $partnerEmail !== '' && !in_array(mb_strtolower($partnerEmail), array_map(fn ($row) => mb_strtolower($row['email']), $recipients), true)) {
            $recipients[] = [
                'type' => 'partner',
                'email' => $partnerEmail,
                'name' => trim((string)($offer?->partner_name ?: $offer?->partner?->name ?: '')),
            ];
        }

        return $recipients;
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

    private function afterSendRedirectUrl(string $provider, Payment $payment, Request $request): string
    {
        return $this->trustedReturnUrlFromRequest($request) ?: $this->signedShowUrl($provider, $payment, $request);
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
