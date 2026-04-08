<?php

namespace App\Repositories;

use App\Enums\ModelHistoryStatuses;
use App\Enums\PartnerStatusEnum;
use App\Jobs\SendPartnerDataJob;
use App\Models\Account;
use App\Models\ChangeHistory;
use App\Models\ModelHistory;
use App\Models\Partner;
use App\Models\ProcentPartner;
use App\Models\PartnerProcent;
use App\Models\PartnerStatus;
use App\Models\PartnerStatusHistory;
use App\Models\User;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PartnerRepository implements PartnerRepositoryInterface
{
    private const PAYMENT_METHODS_DEFAULT = ['card', 'invoice'];
    private const PAYMENT_METHODS_ALLOWED = ['card', 'invoice', 'cash'];

    public function index(array $data)
    {
        $query = User::query()->where('role', 'partner');

        return $query->get();
    }

    public function store(array $data)
    {
        $tariffPercent = isset($data['procent_from_tariff']) ? (int) $data['procent_from_tariff'] : null;
        $packPercent = isset($data['procent_from_pack']) ? (int) $data['procent_from_pack'] : null;
        unset($data['procent_from_tariff'], $data['procent_from_pack']);
        $data['payment_methods'] = $this->normalizePaymentMethods($data['payment_methods'] ?? null);
        $data['status'] = $this->normalizePartnerStatus($data['status'] ?? null);

        $data['partner_status_id'] = PartnerStatus::first()->id;
        $data['login'] = $data['email'];
        $data['role'] = 'partner';
        $data['password'] = Hash::make($data['email']);

        $user = User::create($data);

        // Save current percent settings for quick access (requested table).
        ProcentPartner::updateOrCreate(
            ['partner_id' => $user->id],
            [
                'procent_from_tariff' => $tariffPercent,
                'procent_from_pack' => $packPercent,
            ]
        );

        // Also create the first history record (existing UI uses partner_procents).
        PartnerProcent::create([
            'partner_id' => $user->id,
            'date' => date('Y-m-d'),
            'procent_from_tariff' => $tariffPercent,
            'procent_from_pack' => $packPercent,
        ]);

        PartnerStatusHistory::create([
            'partner_id' => $user->id,
            'date' => date('Y-m-d'),
            'status' => $user->status,
            'author_id' => Auth::id(),
        ]);

        $this->recordHistory(
            model: $user,
            status: ModelHistoryStatuses::CREATED,
            changes: [
                'name' => ['previous_value' => null, 'new_value' => $user->name],
                'email' => ['previous_value' => null, 'new_value' => $user->email],
                'phone' => ['previous_value' => null, 'new_value' => $user->phone],
                'status' => ['previous_value' => null, 'new_value' => $this->statusLabel($user->status)],
                'payment_methods' => ['previous_value' => null, 'new_value' => $this->paymentMethodsLabel($user->payment_methods)],
                'account_id' => ['previous_value' => null, 'new_value' => $this->accountLabelById($user->account_id)],
                'procent_from_tariff' => ['previous_value' => null, 'new_value' => $tariffPercent],
                'procent_from_pack' => ['previous_value' => null, 'new_value' => $packPercent],
            ],
            userId: Auth::id()
        );

//        SendPartnerDataJob::dispatch($user, $password);
    }

    public function getManagers(int $partner_id)
    {
        return User::query()->where('role', 'manager')->where('partner_id', $partner_id)->get();
    }

    public function getCurators(int $partner_id)
    {
        return User::query()
            ->join('partner_curators', 'partner_curators.curator_id', '=', 'users.id')
            ->where('partner_curators.partner_id', $partner_id)
            ->where('users.role', 'manager')
            ->orderBy('users.name')
            ->select('users.*')
            ->get();
    }

    public function attachCurator(int $partner_id, int $curator_id): void
    {
        $now = now();

        DB::table('partner_curators')->updateOrInsert(
            [
                'partner_id' => $partner_id,
                'curator_id' => $curator_id,
            ],
            [
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function detachCurator(int $partner_id, int $curator_id): void
    {
        DB::table('partner_curators')
            ->where('partner_id', $partner_id)
            ->where('curator_id', $curator_id)
            ->delete();
    }

    public function getProcent(int $partner_id)
    {
        return PartnerProcent::where('partner_id', $partner_id)->get();
    }

    public function getStatusHistory(int $partner_id)
    {
        return PartnerStatusHistory::query()
            ->where('partner_id', $partner_id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }

    public function storeManager(array $data)
    {
        $password = $data['password'];

        $data['role'] = 'manager';
        $data['login'] = $data['email'];

        $user = User::create($data);
    }

    public function storeProcent(User $user, array $data)
    {
        $previous = PartnerProcent::query()
            ->where('partner_id', $user->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        PartnerProcent::create([
            'partner_id' => $user->id,
            'date' => $data['date'],
            'procent_from_tariff' => $data['procent_from_tariff'],
            'procent_from_pack' => $data['procent_from_pack'],
        ]);

        $changes = [];
        $prevTariff = $previous?->procent_from_tariff;
        $prevPack = $previous?->procent_from_pack;
        $prevDate = $previous?->date;

        if ((string) $prevTariff !== (string) $data['procent_from_tariff']) {
            $changes['procent_from_tariff'] = [
                'previous_value' => $prevTariff,
                'new_value' => (int) $data['procent_from_tariff'],
            ];
        }

        if ((string) $prevPack !== (string) $data['procent_from_pack']) {
            $changes['procent_from_pack'] = [
                'previous_value' => $prevPack,
                'new_value' => (int) $data['procent_from_pack'],
            ];
        }

        if ((string) $prevDate !== (string) $data['date']) {
            $changes['procent_date'] = [
                'previous_value' => $prevDate,
                'new_value' => (string) $data['date'],
            ];
        }

        if (!empty($changes)) {
            $this->recordHistory(
                model: $user,
                status: ModelHistoryStatuses::UPDATED,
                changes: $changes,
                userId: Auth::id()
            );
        }
    }

    public function storeStatus(User $user, array $data)
    {
        $previousStatus = (string) ($user->status ?? PartnerStatusEnum::PARTNER->value);

        PartnerStatusHistory::create([
            'partner_id' => $user->id,
            'date' => $data['date'],
            'status' => $data['status'],
            'author_id' => Auth::id(),
        ]);

        $user->update([
            'status' => $data['status'],
        ]);

        $changes = [];
        if ($previousStatus !== (string) $data['status']) {
            $changes['status'] = [
                'previous_value' => $this->statusLabel($previousStatus),
                'new_value' => $this->statusLabel((string) $data['status']),
            ];
        }

        $changes['status_date'] = [
            'previous_value' => null,
            'new_value' => (string) $data['date'],
        ];

        $this->recordHistory(
            model: $user,
            status: ModelHistoryStatuses::UPDATED,
            changes: $changes,
            userId: Auth::id()
        );
    }

    public function editProcent(PartnerProcent $procent, array $data)
    {
        $before = [
            'date' => $procent->date,
            'procent_from_tariff' => $procent->procent_from_tariff,
            'procent_from_pack' => $procent->procent_from_pack,
        ];

        $procent->update([
            'date' => $data['date'],
            'procent_from_tariff' => $data['procent_from_tariff'],
            'procent_from_pack' => $data['procent_from_pack'],
        ]);

        $changes = [];

        if ((string) $before['procent_from_tariff'] !== (string) $data['procent_from_tariff']) {
            $changes['procent_from_tariff'] = [
                'previous_value' => $before['procent_from_tariff'],
                'new_value' => (int) $data['procent_from_tariff'],
            ];
        }

        if ((string) $before['procent_from_pack'] !== (string) $data['procent_from_pack']) {
            $changes['procent_from_pack'] = [
                'previous_value' => $before['procent_from_pack'],
                'new_value' => (int) $data['procent_from_pack'],
            ];
        }

        if ((string) $before['date'] !== (string) $data['date']) {
            $changes['procent_date'] = [
                'previous_value' => $before['date'],
                'new_value' => (string) $data['date'],
            ];
        }

        if (!empty($changes)) {
            $user = User::query()->find($procent->partner_id);
            if ($user) {
                $this->recordHistory(
                    model: $user,
                    status: ModelHistoryStatuses::UPDATED,
                    changes: $changes,
                    userId: Auth::id()
                );
            }
        }
    }

    public function update(User $partner, array $data)
    {
        if (array_key_exists('payment_methods', $data)) {
            $data['payment_methods'] = $this->normalizePaymentMethods($data['payment_methods']);
        }
        if (array_key_exists('status', $data)) {
            $data['status'] = $this->normalizePartnerStatus($data['status']);
        }

        $before = [
            'name' => $partner->name,
            'email' => $partner->email,
            'phone' => $partner->phone,
            'address' => $partner->address,
            'status' => $partner->status,
            'payment_methods' => $partner->payment_methods,
            'account_id' => $partner->account_id,
        ];

        $partner->update($data);

        $changes = [];

        if ($before['name'] !== $partner->name) {
            $changes['name'] = ['previous_value' => $before['name'], 'new_value' => $partner->name];
        }
        if ($before['email'] !== $partner->email) {
            $changes['email'] = ['previous_value' => $before['email'], 'new_value' => $partner->email];
        }
        if ($before['phone'] !== $partner->phone) {
            $changes['phone'] = ['previous_value' => $before['phone'], 'new_value' => $partner->phone];
        }
        if ((string) $before['address'] !== (string) $partner->address) {
            $changes['address'] = ['previous_value' => $before['address'], 'new_value' => $partner->address];
        }
        if ((string) $before['status'] !== (string) $partner->status) {
            $changes['status'] = [
                'previous_value' => $this->statusLabel($before['status']),
                'new_value' => $this->statusLabel($partner->status),
            ];
        }

        $beforeMethods = $this->normalizePaymentMethods($before['payment_methods'] ?? []);
        $afterMethods = $this->normalizePaymentMethods($partner->payment_methods ?? []);
        if ($beforeMethods !== $afterMethods) {
            $changes['payment_methods'] = [
                'previous_value' => $this->paymentMethodsLabel($beforeMethods),
                'new_value' => $this->paymentMethodsLabel($afterMethods),
            ];
        }

        if ((string) ($before['account_id'] ?? '') !== (string) ($partner->account_id ?? '')) {
            $changes['account_id'] = [
                'previous_value' => $this->accountLabelById($before['account_id'] ?? null),
                'new_value' => $this->accountLabelById($partner->account_id),
            ];
        }

        if (!empty($changes)) {
            $this->recordHistory(
                model: $partner,
                status: ModelHistoryStatuses::UPDATED,
                changes: $changes,
                userId: Auth::id()
            );
        }
    }

    public function updateManager(User $user, array $data)
    {
        $user->update($data);
    }

    private function normalizePaymentMethods($methods): array
    {
        if (!is_array($methods)) {
            return self::PAYMENT_METHODS_DEFAULT;
        }

        $normalized = [];
        foreach ($methods as $method) {
            $code = strtolower(trim((string) $method));
            if (in_array($code, self::PAYMENT_METHODS_ALLOWED, true)) {
                $normalized[$code] = true;
            }
        }

        if (empty($normalized)) {
            return self::PAYMENT_METHODS_DEFAULT;
        }

        $ordered = [];
        foreach (self::PAYMENT_METHODS_ALLOWED as $allowedMethod) {
            if (isset($normalized[$allowedMethod])) {
                $ordered[] = $allowedMethod;
            }
        }

        return $ordered;
    }

    private function normalizePartnerStatus(?string $status): string
    {
        $value = strtolower(trim((string) $status));
        if (in_array($value, PartnerStatusEnum::values(), true)) {
            return $value;
        }

        return PartnerStatusEnum::PARTNER->value;
    }

    private function paymentMethodsLabel(array $methods): string
    {
        $map = [
            'card' => 'Карта',
            'invoice' => 'Счет',
            'cash' => 'Наличка',
        ];

        $labels = [];
        foreach ($methods as $method) {
            $labels[] = $map[$method] ?? $method;
        }

        return implode(', ', $labels);
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            PartnerStatusEnum::AGENT->value => 'Agent',
            default => 'Partner',
        };
    }

    private function accountLabelById($accountId): string
    {
        if (!$accountId) {
            return 'Не выбран';
        }

        $account = Account::query()
            ->with('currency:id,symbol_code,name')
            ->find((int) $accountId);

        if (!$account) {
            return 'Не выбран';
        }

        $currencyCode = strtoupper((string) optional($account->currency)->symbol_code);

        return trim($account->name . ($currencyCode !== '' ? ' (' . $currencyCode . ')' : ''));
    }

    private function recordHistory(User $model, ModelHistoryStatuses $status, array $changes = [], ?int $userId = null): void
    {
        $history = ModelHistory::create([
            'status' => $status->value,
            'user_id' => $userId,
            'model_id' => $model->id,
            'model_type' => User::class,
        ]);

        ChangeHistory::create([
            'model_history_id' => $history->id,
            'body' => json_encode($changes, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
