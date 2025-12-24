<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommercialOfferRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

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
}
