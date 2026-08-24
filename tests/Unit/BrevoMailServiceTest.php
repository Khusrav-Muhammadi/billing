<?php

namespace Tests\Unit;

use App\Services\Mailing\BrevoMailService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrevoMailServiceTest extends TestCase
{
    public function test_it_sends_email_with_brevo_contract_and_attachment(): void
    {
        config()->set('services.brevo', [
            'api_key' => 'test-api-key',
            'mail_from' => 'support@shamcrm.com',
            'mail_from_name' => 'shamCRM',
        ]);

        Http::fake([
            BrevoMailService::ENDPOINT => Http::response(['messageId' => 'message-id'], 201),
        ]);

        $sent = app(BrevoMailService::class)->send(
            'client@example.com',
            'Subject',
            '<p>Hello</p>',
            [[
                'filename' => 'invoice.pdf',
                'content' => 'pdf-content',
            ]]
        );

        $this->assertTrue($sent);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === BrevoMailService::ENDPOINT
            && $request->hasHeader('api-key', 'test-api-key')
            && !$request->hasHeader('Authorization')
            && $request['sender'] === [
                'email' => 'support@shamcrm.com',
                'name' => 'shamCRM',
            ]
            && $request['to'] === [['email' => 'client@example.com']]
            && $request['subject'] === 'Subject'
            && $request['htmlContent'] === '<p>Hello</p>'
            && $request['attachment'] === [[
                'name' => 'invoice.pdf',
                'content' => base64_encode('pdf-content'),
            ]]
        );
    }
}
