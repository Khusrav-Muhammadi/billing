<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Services\Mailing\BrevoMailService;
use App\Services\Mailing\MailText;
use App\Services\Mailing\SafeBrevoMailService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoAccessEmailTest extends TestCase
{
    public function test_hyphenated_demo_url_stays_complete_in_html_and_text(): void
    {
        config()->set('services.sham.domain', 'shamcrm.com');

        $client = new Client([
            'name' => 'Demo Client',
            'sub_domain' => 'wogir62844slotbeercom-new',
        ]);

        $this->assertSame('https://wogir62844slotbeercom-new.shamcrm.com/', $client->crmUrl());

        $html = view('mail.send_site_data', [
            'client' => $client,
            'password' => 'secret',
            'id' => '17882337030494',
        ])->render();

        $this->assertStringContainsString('href="https://wogir62844slotbeercom-new.shamcrm.com/"', $html);
        $this->assertStringContainsString('Открыть CRM', $html);
        $this->assertStringContainsString('white-space: nowrap', $html);
        $this->assertStringNotContainsString('word-break: break-all', $html);

        $text = view('mail.send_site_data_text', [
            'client' => $client,
            'password' => 'secret',
            'id' => '17882337030494',
        ])->render();

        $this->assertStringContainsString('<https://wogir62844slotbeercom-new.shamcrm.com/>', $text);
    }

    public function test_mail_text_wraps_hyphenated_urls(): void
    {
        $text = MailText::fromHtml('<p>Ссылка: https://wogir62844slotbeercom-new.shamcrm.com/</p>');

        $this->assertSame('Ссылка: <https://wogir62844slotbeercom-new.shamcrm.com/>', $text);
    }

    public function test_brevo_uses_text_template_with_full_url(): void
    {
        config()->set('services.brevo', [
            'api_key' => 'test-api-key',
            'mail_from' => 'support@shamcrm.com',
            'mail_from_name' => 'shamCRM',
        ]);
        config()->set('services.sham.domain', 'shamcrm.com');

        Http::fake([
            BrevoMailService::ENDPOINT => Http::response(['messageId' => 'message-id'], 201),
        ]);

        $client = new Client([
            'name' => 'Demo Client',
            'sub_domain' => 'wogir62844slotbeercom-new',
        ]);

        $this->assertInstanceOf(SafeBrevoMailService::class, app(BrevoMailService::class));

        $sent = app(BrevoMailService::class)->sendWithView(
            to: 'client@example.com',
            subject: 'Подключение к системе "shamCRM"',
            view: 'mail.send_site_data',
            data: [
                'client' => $client,
                'password' => 'secret',
                'id' => '17882337030494',
            ]
        );

        $this->assertTrue($sent);
        Http::assertSent(fn (Request $request): bool =>
            str_contains((string) $request['htmlContent'], 'href="https://wogir62844slotbeercom-new.shamcrm.com/"')
            && str_contains((string) $request['textContent'], '<https://wogir62844slotbeercom-new.shamcrm.com/>')
        );
    }
}
