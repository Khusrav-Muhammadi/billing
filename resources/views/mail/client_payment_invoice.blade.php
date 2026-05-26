<p>Здравствуйте{{ trim((string) ($recipientName ?? '')) !== '' ? ', ' . $recipientName : '' }}.</p>

<p>Во вложении счет на оплату SHAMCRM № {{ $payment->id ?? '' }}.</p>

<p>Спасибо.</p>
