<?php

namespace App\Repositories;

use App\Jobs\SendPartnerDataJob;
use App\Models\Partner;
use App\Models\ProcentPartner;
use App\Models\PartnerProcent;
use App\Models\PartnerStatus;
use App\Models\User;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class PartnerRepository implements PartnerRepositoryInterface
{
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

//        SendPartnerDataJob::dispatch($user, $password);
    }

    public function getManagers(int $partner_id)
    {
        return User::query()->where('role', 'manager')->where('partner_id', $partner_id)->get();
    }

    public function getProcent(int $partner_id)
    {
        return PartnerProcent::where('partner_id', $partner_id)->get();
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
        PartnerProcent::create([
            'partner_id' => $user->id,
            'date' => $data['date'],
            'procent_from_tariff' => $data['procent_from_tariff'],
            'procent_from_pack' => $data['procent_from_pack'],
        ]);
    }

    public function editProcent(PartnerProcent $procent, array $data)
    {
        $procent->update([
            'date' => $data['date'],
            'procent_from_tariff' => $data['procent_from_tariff'],
            'procent_from_pack' => $data['procent_from_pack'],
        ]);
    }

    public function update(User $partner, array $data)
    {
        $partner->update($data);
    }

    public function updateManager(User $user, array $data)
    {
        $user->update($data);
    }
}
