<?php

namespace Rutatiina\GoodsIssued\Services;

use Rutatiina\Tax\Models\Tax;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Rutatiina\GoodsIssued\Models\GoodsIssued;
use Rutatiina\GoodsIssued\Models\GoodsIssuedSetting;
use Rutatiina\GoodsIssued\Services\GoodsIssuedInventoryService;
use Rutatiina\FinancialAccounting\Services\AccountBalanceUpdateService;
use Rutatiina\FinancialAccounting\Services\ContactBalanceUpdateService;

class GoodsIssuedService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function settings()
    {
        return GoodsIssuedSetting::firstOrCreate([
            'tenant_id' => session('tenant_id'),
            'document_name' => 'Goods Issued Note',
            'document_type' => 'inventory',
            //'debit_financial_account_code' => 66, //sales person inventory
            //'credit_financial_account_code' => 130500, //Inventory
        ]);
    }

    public static function nextNumber()
    {
        $count = GoodsIssued::count();
        $settings = GoodsIssuedSetting::first();
        $nextNumber = $settings->minimum_number + ($count + 1);

        return $settings->number_prefix . (str_pad($nextNumber, $settings->minimum_number_length, "0", STR_PAD_LEFT)) . $settings->number_postfix;
    }

    public static function edit($id)
    {
        $taxes = Tax::all()->keyBy('code');

        $txn = GoodsIssued::findOrFail($id);
        $txn->load('contact', 'items');

        $attributes = $txn->toArray();

        //print_r($attributes); exit;

        $attributes['_method'] = 'PATCH';
        $attributes['contact'] = ($attributes['contact']) ? $attributes['contact'] : json_decode('{}');

        $attributes['taxes'] = json_decode('{}');

        foreach ($attributes['items'] as $key => $item)
        {
            $selectedItem = [
                'id' => $item['item_id'],
                'name' => $item['name'],
                'description' => $item['description'],
                'rate' => 0,
                'tax_method' => 'inclusive',
                'account_type' => null,
            ];

            $attributes['items'][$key]['selectedItem'] = $selectedItem; #required
            $attributes['items'][$key]['selectedTaxes'] = []; #required
            $attributes['items'][$key]['displayTotal'] = 0; #required

            $attributes['items'][$key]['rate'] = 0;
            $attributes['items'][$key]['quantity'] = floatval($item['quantity']);
            $attributes['items'][$key]['total'] = 0;
            $attributes['items'][$key]['displayTotal'] = 0; #required
        };

        return $attributes;
    }

    public static function store($requestInstance)
    {
        $data = GoodsIssuedValidateService::run($requestInstance);
        //print_r($data); exit;
        if ($data === false)
        {
            self::$errors = GoodsIssuedValidateService::$errors;
            return false;
        }

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = new GoodsIssued;
            $Txn->tenant_id = $data['tenant_id'];
            $Txn->created_by = Auth::id();
            $Txn->document_name = $data['document_name'];
            $Txn->number = $data['number'];
            $Txn->date = $data['date'];
            $Txn->contact_id = $data['contact_id'];
            $Txn->contact_name = $data['contact_name'];
            $Txn->contact_address = $data['contact_address'];
            $Txn->reference = $data['reference'];
            $Txn->branch_id = $data['branch_id'];
            $Txn->store_id = $data['store_id'];
            $Txn->status = $data['status'];

            $Txn->save();

            $data['id'] = $Txn->id;

            //print_r($data['items']); exit;

            //Save the items >> $data['items']
            GoodsIssuedItemService::store($data);

            //check status and update financial account and contact balances accordingly
            //update the status of the txn
            $Txn->status = (GoodsIssuedInventoryService::update($data)) ? 'approved' : 'draft';
            $Txn->save();

            DB::connection('tenant')->commit();

            return $Txn;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to save Goods Delivered to database');
            Log::critical($e);

            //print_r($e); exit;
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1690)
            {
                self::$errors[] = 'Oops: Item inventory / stock is not enough';
            }
            elseif (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to save Goods Delivered to database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {

                self::$errors[] = 'Fatal Internal Error: Failed to save Goods Delivered to database. Please contact Admin';
            }

            return false;
        }
        //*/

    }

    public static function update($requestInstance)
    {
        $data = GoodsIssuedValidateService::run($requestInstance);
        //print_r($data); exit;
        if ($data === false)
        {
            self::$errors = GoodsIssuedValidateService::$errors;
            return false;
        }

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $originalTxn = GoodsIssued::with('items')->findOrFail($data['id']);

            $Txn = $originalTxn->duplicate();

            GoodsIssuedInventoryService::reverse($Txn->toArray());

            //Delete affected relations
            $Txn->items()->delete();
            $Txn->comments()->delete();

            $Txn->parent_id = $originalTxn->id;
            $Txn->tenant_id = $data['tenant_id'];
            $Txn->created_by = Auth::id();
            $Txn->document_name = $data['document_name'];
            $Txn->number = $data['number'];
            $Txn->date = $data['date'];
            $Txn->contact_id = $data['contact_id'];
            $Txn->contact_name = $data['contact_name'];
            $Txn->contact_address = $data['contact_address'];
            $Txn->reference = $data['reference'];
            $Txn->branch_id = $data['branch_id'];
            $Txn->store_id = $data['store_id'];
            $Txn->contact_notes = $data['contact_notes'];

            $Txn->save();

            $data['id'] = $Txn->id;

            //print_r($data['items']); exit;

            //Save the items >> $data['items']
            GoodsIssuedItemService::store($data);

            //check status and update financial account and contact balances accordingly
            $approval = GoodsIssuedInventoryService::update($data);

            //update the status of the txn
            if ($approval)
            {
                $Txn->status = 'approved';
                $Txn->save();
            }

            $originalTxn->update(['status' => 'edited']);

            DB::connection('tenant')->commit();

            return $Txn;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to update GoodsIssued in database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to update GoodsIssued in database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to update GoodsIssued in database. Please contact Admin';
            }

            return false;
        }

    }

    public static function destroy($id)
    {
        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = GoodsIssued::with('items')->findOrFail($id);

            GoodsIssuedInventoryService::reverse($Txn->toArray());

            //Delete affected relations
            $Txn->items()->delete();
            $Txn->comments()->delete();
            $Txn->delete();

            DB::connection('tenant')->commit();

            return true;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to delete GoodsIssued from database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to delete GoodsIssued from database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to delete GoodsIssued from database. Please contact Admin';
            }

            return false;
        }
    }

    public static function cancel($id)
    {
        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = GoodsIssued::with('items')->findOrFail($id);

            GoodsIssuedInventoryService::reverse($Txn->toArray());

            $Txn->canceled = 1;
            $Txn->save();

            DB::connection('tenant')->commit();

            return true;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to cancel GoodsIssued from database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to cancel GoodsIssued from database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to cancel GoodsIssued from database. Please contact Admin';
            }

            return false;
        }
    }

    public static function destroyMany($ids)
    {
        foreach($ids as $id)
        {
            if(!self::destroy($id)) return false;
        }
        return true;
    }

    public static function cancelMany($ids)
    {
        foreach($ids as $id)
        {
            if(!self::cancel($id)) return false;
        }
        return true;
    }

    public static function copy($id)
    {
        $taxes = Tax::all()->keyBy('code');

        $txn = GoodsIssued::findOrFail($id);
        $txn->load('contact', 'items');

        $attributes = $txn->toArray();

        #reset some values
        $attributes['number'] = self::nextNumber();
        $attributes['date'] = date('Y-m-d');
        $attributes['due_date'] = '';
        $attributes['expiry_date'] = '';
        #reset some values

        $attributes['contact']['currency'] = $attributes['contact']['currency_and_exchange_rate'];
        $attributes['contact']['currencies'] = $attributes['contact']['currencies_and_exchange_rates'];

        $attributes['taxes'] = json_decode('{}');

        foreach ($attributes['items'] as &$item)
        {
            $selectedItem = [
                'id' => $item['item_id'],
                'name' => $item['name'],
                'description' => $item['description'],
                'rate' => $item['rate'],
                'tax_method' => 'inclusive',
                'account_type' => null,
            ];

            $item['selectedItem'] = $selectedItem; #required
            $item['selectedTaxes'] = []; #required
            $item['displayTotal'] = 0; #required
            $item['rate'] = floatval($item['rate']);
            $item['quantity'] = floatval($item['quantity']);
            $item['total'] = floatval($item['total']);
            $item['displayTotal'] = $item['total']; #required

            foreach ($item['taxes'] as $itemTax)
            {
                $item['selectedTaxes'][] = $taxes[$itemTax['tax_code']];
            }
        };
        unset($item);

        return $attributes;
    }

    public static function approve($id)
    {
        $Txn = GoodsIssued::with(['items'])->findOrFail($id);

        if (!in_array($Txn->status, config('financial-accounting.approvable_status')))
        {
            self::$errors[] = $Txn->status . ' GoodsIssued cannot be approved';
            return false;
        }

        $data = $Txn->toArray();

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $data['status'] = 'approved';
            $Txn->status = (GoodsIssuedInventoryService::update($data)) ? 'approved' : 'draft';
            $Txn->save();

            DB::connection('tenant')->commit();

            return true;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();
            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'DB Error: Failed to approve GoodsIssued.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to approve GoodsIssued. Please contact Admin';
            }

            return false;
        }
    }

}
