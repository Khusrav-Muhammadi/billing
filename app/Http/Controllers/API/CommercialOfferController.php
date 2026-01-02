<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommercialOfferRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class CommercialOfferController extends Controller
{
    /**
     * Generate and download commercial offer PDF
     */
    public function generate(CommercialOfferRequest $request): Response
    {
        $data = $request->getOfferData();


        $pdf = Pdf::loadView('offer', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'dejavu sans',
                'dpi' => 96,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
            ]);

        $filename = sprintf(
            'КП_%s_%s.pdf',
            str_replace([' ', '"', '«', '»'], ['_', '', '', ''], $data['client_name']),
            str_replace('.', '-', $data['date'])
        );

        // Сохраняем PDF в storage
        $storagePath = 'commercial-offers/' . date('Y/m') . '/' . $filename;
        Storage::disk('public')->put($storagePath, $pdf->output());

        return $pdf->download($filename);
    }

    /**
     * Preview commercial offer in browser
     */
    public function preview(CommercialOfferRequest $request): Response
    {
        $data = $request->getOfferData();

        $pdf = Pdf::loadView('pdf.commercial-offer', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'dejavu sans',
                'dpi' => 150,
            ]);

        return $pdf->stream('preview.pdf');
    }

    /**
     * Generate PDF for simple commercial-offer page (query params: client, manager, date)
     */
    public function simple(Request $request): Response
    {
        $html = view('commercial-offer', [
            'isPdf' => true,
            'client' => 'ООО Рога и копыта',
            'manager' => 'Иван Иванов',
            'date' => now()->format('d.m.Y'),
        ])->render();

        $pdf = Browsershot::html($html)
            ->setNodeBinary('/usr/bin/node')
            ->setNpmBinary('/usr/bin/npm')
            ->setChromePath('/usr/bin/google-chrome')
            ->noSandbox()
            ->format('A4')
            ->showBackground()
            ->setOption('args', ['--no-sandbox', '--print-to-pdf-no-header'])
            ->emulateMedia('screen')
            ->pdf();

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="offer.pdf"');
    }
    /**
     * Preview page for PDF generation
     */
    public function previewPage()
    {
        return view('commercial-offer', [
            'isPdf' => true,
            'client' => 'ООО Рога и копыта',
            'manager' => 'Иван Иванов',
            'date' => now()->format('d.m.Y'),
        ]);
    }
}
