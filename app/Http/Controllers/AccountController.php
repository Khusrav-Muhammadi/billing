<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\StoreRequest;
use App\Http\Requests\Account\UpdateRequest;
use App\Models\Account;
use App\Models\Currency;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::query()->with('currency')->orderBy('id')->get();
        $currencies = Currency::query()->orderBy('name')->get();

        return view('admin.accounts.index', compact('accounts', 'currencies'));
    }

    public function store(StoreRequest $request)
    {
        Account::query()->create($request->validated());

        return redirect()->route('account.index');
    }

    public function update(Account $account, UpdateRequest $request)
    {
        $account->update($request->validated());

        return redirect()->route('account.index');
    }

    public function destroy(Account $account)
    {
        $account->delete();

        return redirect()->back();
    }
}
