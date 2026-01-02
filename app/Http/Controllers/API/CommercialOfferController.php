<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommercialOfferRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
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

    /**
     * Generate PDF for simple commercial-offer page (query params: client, manager, date)
     */
    public function simple(Request $request): Response
    {
        $data = [
            'client' => $request->query('client', 'ИП "Расулов Амир Давронович"'),
            'manager' => $request->query('manager', 'Расулов Амир'),
            'date' => $request->query('date', now()->format('d.m.Y')),
        ];

        $pdf = Pdf::loadView('commercial-offer', $data)
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
            str_replace([' ', '"', '«', '»'], ['_', '', '', ''], $data['client']),
            str_replace('.', '-', $data['date'])
        );

        return $pdf->download($filename);
    }
}
