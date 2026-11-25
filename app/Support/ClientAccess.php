<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Кто из вошедших по API (Bearer, портал партнёров) видит какого клиента.
 *
 * admin — сотрудник shamCRM, видит всех. partner — только своих (clients.partner_id).
 * manager — сотрудник партнёра, только клиентов, где он ответственный (clients.manager_id).
 * Остальные роли, как и раньше, без ограничений.
 *
 * Раньше карточки клиента и организации, их правка и удаление открывались по id любому
 * одобренному партнёру: можно было перебрать id и читать или менять чужих клиентов.
 */
final class ClientAccess
{
    /** Свои демо без партнёра в своей стране партнёр видит в списке демо (organizations-demo-v2). */
    private const DEMO_FULL_ACCESS_USER_IDS = [1, 11];

    public static function role(?User $user): string
    {
        return mb_strtolower(trim((string) ($user?->role ?? '')));
    }

    public static function isAdmin(?User $user): bool
    {
        return self::role($user) === 'admin';
    }

    /** Ограничить запрос по clients (Builder модели Client). */
    public static function scopeClients(Builder $query, ?User $user): Builder
    {
        return match (self::role($user)) {
            'partner' => $query->where('partner_id', (int) $user->id),
            'manager' => $query->where('manager_id', (int) $user->id),
            '' => $query->whereRaw('1 = 0'),
            default => $query,
        };
    }

    /** Ограничить запрос по organizations через клиента. */
    public static function scopeOrganizations(Builder $query, ?User $user): Builder
    {
        $role = self::role($user);
        if (!in_array($role, ['partner', 'manager', ''], true)) {
            return $query;
        }

        return $query->whereHas('client', fn (Builder $q) => self::scopeClients($q, $user));
    }

    /** Свой клиент: читать, править. */
    public static function ownsClient(?User $user, ?Client $client): bool
    {
        if (!$user || !$client) {
            return false;
        }

        return match (self::role($user)) {
            'partner' => (int) $client->partner_id === (int) $user->id,
            'manager' => (int) $client->manager_id === (int) $user->id,
            '' => false,
            default => true,
        };
    }

    /**
     * Можно открыть карточку организации: своя или демо без партнёра в стране партнёра
     * (их партнёр видит в списке демо и может «Взять клиента»).
     */
    public static function canViewOrganization(?User $user, Organization $organization): bool
    {
        $client = $organization->client;
        if (self::ownsClient($user, $client)) {
            return true;
        }

        if (self::role($user) !== 'partner' || !$client || $client->partner_id !== null || $client->nfr) {
            return false;
        }

        if ($organization->connections()->exists()) {
            return false;
        }

        return in_array((int) $user->id, self::DEMO_FULL_ACCESS_USER_IDS, true)
            || ((int) ($user->country_id ?? 0) > 0 && (int) $client->country_id === (int) $user->country_id);
    }

    public static function canManageOrganization(?User $user, Organization $organization): bool
    {
        return self::ownsClient($user, $organization->client);
    }
}
