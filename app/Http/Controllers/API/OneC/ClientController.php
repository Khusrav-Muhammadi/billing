<?php

namespace App\Http\Controllers\API\OneC;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ChangeTariffRequest;
use App\Http\Requests\Client\GetBalanceRequest;
use App\Http\Requests\Client\StoreRequest;
use App\Http\Requests\Client\TransactionRequest;
use App\Http\Requests\Client\UpdateRequest;
use App\Jobs\SubDomainJob;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\Pack;
use App\Models\Partner;
use App\Models\Sale;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\OrganizationRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{

    public function __construct(public ClientRepositoryInterface $repository) { }

    public function activation(Request $request)
    {
        // Строгое сравнение без утечки по времени; пустой токен в .env закрывает роут.
        $expected = trim((string) config('constants.internal_api_token', ''));
        $provided = (string) $request->header('X-Internal-Token', '');
        if ($expected === '' || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'reject_cause' => 'nullable',
            'sub_domain' => ['required', Rule::exists('clients','sub_domain')]
        ]);

        $client = Client::where('sub_domain', $data['sub_domain'])->first();


        $this->repository->activation($client, $data);

        return response()->json(['success' => true]);
    }

}
