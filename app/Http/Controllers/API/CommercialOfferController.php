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
     * Generate commercial offer PDF with new design
     */
    public function generateOffer(Request $request): Response
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'manager_name' => 'required|string|max:255',
            'date' => 'nullable|date_format:d.m.Y',

            'tariff' => 'required|array',
            'tariff.name' => 'required|string|max:255',
            'tariff.period_months' => 'required|integer|min:1',
            'tariff.monthly_price' => 'required|numeric|min:0',

            // Что входит в тариф (страница 2)
            'tariff_features' => 'nullable|array',
            'tariff_features.*.name' => 'required|string|max:255',
            'tariff_features.*.value' => 'nullable|string|max:255', // если null - галочка

            'additional_users' => 'nullable|array',
            'additional_users.quantity' => 'nullable|integer|min:0',
            'additional_users.price_per_user' => 'nullable|numeric|min:0',

            'modules' => 'required|array',
            'modules.*.name' => 'required|string|max:255',
            'modules.*.status' => 'required|in:included,selected,not_available',
            'modules.*.price' => 'nullable|numeric|min:0',

            // Единоразовые услуги внедрения (страница 4)
            'one_time_services' => 'nullable|array',
            'one_time_services.*.name' => 'required|string|max:255',
            'one_time_services.*.status' => 'nullable|in:included,selected', // included = галочка, selected = показать цену
            'one_time_services.*.price' => 'nullable|numeric|min:0',
            'one_time_services.*.value' => 'nullable|string|max:255', // текстовое значение (10 канала)

            'contacts' => 'nullable|array',
            'contacts.phone' => 'nullable|string|max:50',
            'contacts.website' => 'nullable|string|max:100',
            'contacts.telegram' => 'nullable|string|max:100',

            'validity_days' => 'nullable|integer|min:1|max:365',
        ]);

        $periodMonths = $validated['tariff']['period_months'];

        $tariffTotal = $validated['tariff']['monthly_price'] * $periodMonths;

        $usersTotal = 0;
        if (isset($validated['additional_users']) && $validated['additional_users']['quantity'] > 0) {
            $usersTotal = $validated['additional_users']['quantity']
                * $validated['additional_users']['price_per_user']
                * $periodMonths;
        }

        // Modules total (only selected modules)
        $modulesTotal = 0;
        foreach ($validated['modules'] as $module) {
            if ($module['status'] === 'selected' && isset($module['price'])) {
                $modulesTotal += $module['price'] * $periodMonths;
            }
        }

        // One-time total (сумма услуг со статусом selected)
        $oneTimeTotal = 0;
        if (isset($validated['one_time_services'])) {
            foreach ($validated['one_time_services'] as $service) {
                if (isset($service['status']) && $service['status'] === 'selected' && isset($service['price'])) {
                    $oneTimeTotal += $service['price'];
                }
            }
        }

        // Grand total
        $grandTotal = $tariffTotal + $usersTotal + $modulesTotal + $oneTimeTotal;

        // Validity date
        $validityDays = $validated['validity_days'] ?? 30;
        $validityDate = now()->addDays($validityDays)->format('d.m.Y');

        // Prepare view data
        $viewData = [
            'isPdf' => true,
            'client' => $validated['client_name'],
            'manager' => $validated['manager_name'],
            'date' => $validated['date'] ?? now()->format('d.m.Y'),
            'tariff' => $validated['tariff'],
            'tariff_features' => $validated['tariff_features'] ?? [],
            'additional_users' => $validated['additional_users'] ?? ['quantity' => 0, 'price_per_user' => 0],
            'modules' => $validated['modules'],
            'one_time_services' => $validated['one_time_services'] ?? [],
            'contacts' => array_merge([
                'phone' => '+998 78 555 7416',
                'website' => 'shamcrm.com',
                'telegram' => '@shamcrm_uz',
            ], $validated['contacts'] ?? []),
            'validity_date' => $validityDate,
            'calculations' => [
                'tariff_total' => $tariffTotal,
                'users_total' => $usersTotal,
                'modules_total' => $modulesTotal,
                'one_time_total' => $oneTimeTotal,
                'grand_total' => $grandTotal,
            ],
        ];

        $html = view('commercial-offer', $viewData)->render();

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

        $filename = sprintf(
            'КП_%s_%s.pdf',
            str_replace([' ', '"', '«', '»'], ['_', '', '', ''], $validated['client_name']),
            str_replace('.', '-', $viewData['date'])
        );

        // Сохраняем PDF в storage
        $storagePath = 'commercial-offers/' . date('Y/m') . '/' . $filename;
        Storage::disk('public')->put($storagePath, $pdf);

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
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
        $viewData = $this->getTestData();

        $html = view('commercial-offer', $viewData)->render();

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
        return view('commercial-offer', $this->getTestData());
    }

    /**
     * Get test data for preview/simple methods
     */
    private function getTestData(): array
    {
        $periodMonths = 6;
        $tariffMonthly = 1700000;
        $usersQty = 30;
        $userPrice = 25800;

        // Что входит в тариф (страница 2)
        $tariffFeatures = [
            ['name' => 'Количество пользователей', 'value' => '30 шт.'],
            ['name' => 'Дашборд'],
            ['name' => 'Интеграция Instagram', 'value' => '10 канал'],
            ['name' => 'Интеграция Telegram', 'value' => '10 канал'],
            ['name' => 'Интеграция WhatsApp', 'value' => '10 канал'],
            ['name' => 'Интеграция Messenger', 'value' => '10 канал'],
            ['name' => 'Мобильное приложение'],
            ['name' => 'Контроль доступ'],
            ['name' => 'Управление задачами'],
            ['name' => 'Календарь'],
            ['name' => 'SMS - рассылка'],
        ];

        // Дополнительные модули (страница 3)
        $modules = [
            ['name' => 'Mini-app B2B (партнёры, дилеры)', 'status' => 'included', 'price' => 0],
            ['name' => 'Mini-app B2C (клиенты, заявки)', 'status' => 'included', 'price' => 0],
            ['name' => 'IP-Телефония (Сипуну)', 'status' => 'included', 'price' => 0],
            ['name' => 'Подключение IP-телефонии', 'status' => 'included', 'price' => 0],
            ['name' => 'Интернет-магазин', 'status' => 'selected', 'price' => 399000],
            ['name' => 'Подключение интернет-магазина', 'status' => 'not_available', 'price' => 0],
            ['name' => 'Дополнительные каналы соцсети', 'status' => 'selected', 'price' => 129000],
            ['name' => 'Дополнительная воронка', 'status' => 'selected', 'price' => 129000],
            ['name' => 'SMS - Рассылка', 'status' => 'included', 'price' => 0],
            ['name' => 'Складской учет и касса', 'status' => 'not_available', 'price' => 129000],
            ['name' => 'Интеграция с 1С', 'status' => 'not_available', 'price' => 0],
        ];

        // Единоразовые услуги внедрения (страница 4)
        $oneTimeServices = [
            ['name' => 'Анализ бизнес-процессов', 'status' => 'included', 'price' => 0],
            ['name' => 'Настройка воронок продаж', 'status' => 'included', 'price' => 0],
            ['name' => 'Настройка доступа пользователей', 'status' => 'included', 'price' => 0],
            ['name' => 'Обучение сотрудников', 'status' => 'included', 'price' => 0],
            ['name' => 'Консультационная поддержка', 'status' => 'included', 'price' => 0],
            ['name' => 'Интеграция Instagram', 'status' => 'included', 'value' => '10 канала'],
            ['name' => 'Интеграция Telegram', 'status' => 'included', 'value' => '10 канала'],
            ['name' => 'Интеграция WhatsApp', 'status' => 'included', 'value' => '10 канала'],
            ['name' => 'Интеграция Messenger', 'status' => 'included', 'value' => '10 канала'],
        ];

        // Calculate totals
        $tariffTotal = $tariffMonthly * $periodMonths;
        $usersTotal = 0; // в тарифе уже 30 пользователей

        $modulesTotal = 0;
        foreach ($modules as $module) {
            if ($module['status'] === 'selected') {
                $modulesTotal += $module['price'] * $periodMonths;
            }
        }

        // Подсчет единоразовых услуг
        $oneTimeTotal = 0;
        foreach ($oneTimeServices as $service) {
            if (isset($service['status']) && $service['status'] === 'selected' && isset($service['price'])) {
                $oneTimeTotal += $service['price'];
            }
        }
        // Если все included - ставим фиксированную сумму для демо
        if ($oneTimeTotal === 0) {
            $oneTimeTotal = 36000000;
        }

        return [
            'isPdf' => true,
            'client' => 'ООО Рога и копыта',
            'manager' => 'Иван Иванов',
            'date' => now()->format('d.m.Y'),
            'tariff' => [
                'name' => 'VIP',
                'period_months' => $periodMonths,
                'monthly_price' => $tariffMonthly,
            ],
            'tariff_features' => $tariffFeatures,
            'additional_users' => [
                'quantity' => 0,
                'price_per_user' => $userPrice,
            ],
            'modules' => $modules,
            'one_time_services' => $oneTimeServices,
            'contacts' => [
                'phone' => '+998 78 555 7416',
                'website' => 'shamcrm.com',
                'telegram' => '@shamcrm_uz',
            ],
            'validity_date' => now()->addDays(30)->format('d.m.Y'),
            'calculations' => [
                'tariff_total' => $tariffTotal,
                'users_total' => $usersTotal,
                'modules_total' => $modulesTotal,
                'one_time_total' => $oneTimeTotal,
                'grand_total' => $tariffTotal + $usersTotal + $modulesTotal + $oneTimeTotal,
            ],
        ];
    }
}
