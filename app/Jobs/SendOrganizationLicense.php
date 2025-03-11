<?php

namespace App\Jobs;

use App\Mail\SendLicenseMail;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpWord\TemplateProcessor;

class SendOrganizationLicense implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $organization;
    protected $clientEmail;

    /**
     * Create a new job instance.
     */
    public function __construct(Organization $organization)
    {
        $this->organization = $organization;
        $this->clientEmail = $organization->client->email;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $templatePath = storage_path('app/templates/organization-license.docx');
        $outputPath = storage_path('app/templates/organization-license_' . $this->organization->id . '.docx');

        $templateProcessor = new TemplateProcessor($templatePath);

        $templateProcessor->setValue('COMPANY_NAME', $this->organization->name);
        $templateProcessor->setValue('ADDRESS', $this->organization->address);
        $templateProcessor->setValue('INN', $this->organization->INN);
        $templateProcessor->setValue('ID', $this->organization->id);
        $templateProcessor->setValue('TARIFF', $this->organization->client->tariff->name);

        $templateProcessor->saveAs($outputPath);

        Mail::to($this->clientEmail)->send(new SendLicenseMail($outputPath));
    }
}
