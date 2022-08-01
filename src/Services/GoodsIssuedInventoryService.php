<?php

namespace Rutatiina\GoodsIssued\Services;

use Rutatiina\Inventory\Models\Inventory;
use Rutatiina\FinancialAccounting\Services\AccountBalanceUpdateService;
use Rutatiina\FinancialAccounting\Services\ContactBalanceUpdateService;

class GoodsIssuedInventoryService
{
    private static function record($transaction, $item)
    {
        $inventory = Inventory::whereDate('date', '<=', $transaction['date'])
            ->where([
                'tenant_id' => $item['tenant_id'], 
                'project_id' => @$transaction['project_id'], 
                'item_id' => $item['item_id'],
                'batch' => $item['batch'],
            ])
            ->orderBy('date', 'desc')
            ->first();

        //var_dump($accountBalance); exit;
        //Log::info('>>Last account balance entry for account id::'.$ledger['financial_account_code'].' in '.$currency.' date: '.$ledger['date'].': '.$ledger['effect'].' '.$ledger['total']);
        //Log::info($ledger);
        //Log::info($accountBalance);

        if ($inventory)
        {
            //create a new row with the last balances
            if ($transaction['date'] == $inventory->date)
            {
                //do nothing because the records for this dates balances already exists
            }
            else
            {
                $inventoryModel = new Inventory;
                $inventoryModel->tenant_id = $item['tenant_id'];
                $inventoryModel->date = $transaction['date'];
                $inventoryModel->item_id = $item['item_id'];
                $inventoryModel->batch = $item['batch'];
                $inventoryModel->units_received = $inventory->units_received;
                $inventoryModel->units_delivered = $inventory->units_delivered;
                $inventoryModel->units_issued = $inventory->units_issued;
                $inventoryModel->units_returned = $inventory->units_returned;
                $inventoryModel->units_available = $inventory->units_available;
                $inventoryModel->save();
            }

        }
        else
        {

            //create a new balance record
            $inventoryModel = new Inventory;
            $inventoryModel->tenant_id = $item['tenant_id'];
            $inventoryModel->date = $transaction['date'];
            $inventoryModel->item_id = $item['item_id'];
            $inventoryModel->batch = $item['batch'];
            $inventoryModel->save();
        }

        return Inventory::whereDate('date', '>=', $transaction['date'])
            ->where([
                'tenant_id' => $item['tenant_id'], 
                'project_id' => @$transaction['project_id'], 
                'item_id' => $item['item_id'],
                'batch' => $item['batch'],
            ]);
    }

    public static function update($data)
    {
        if ($data['status'] != 'approved')
        {
            //can only update balances if status is approved
            return false;
        }
        
        //Update the inventory summary
        foreach ($data['items'] as &$item)
        {
            $inventory = self::record($data, $item);

            //increase the 
            $inventory->increment('units_issued', $item['units']);
            $inventory->decrement('units_available', $item['units']);

        }

        //inventory checks and inventory balance update if needed
        //$this->inventory(); //currently inventory update for estimates is disabled -< todo update the inventory here

        return true;
    }
    
    public static function reverse($data)
    {
        if ($data['status'] != 'approved')
        {
            //can only update balances if status is approved
            return false;
        }
        
        //Update the inventory summary
        foreach ($data['items'] as &$item)
        {
            $inventory = self::record($data, $item);

            //increase the 
            $inventory->increment('units_issued', $item['units']);
            $inventory->decrement('units_available', $item['units']);

        }

        //inventory checks and inventory balance update if needed
        //$this->inventory(); //currently inventory update for estimates is disabled -< todo update the inventory here

        return true;
    }

}
