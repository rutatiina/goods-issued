<?php

namespace Rutatiina\GoodsIssued\Services;

use Rutatiina\FinancialAccounting\Services\AccountBalanceUpdateService;
use Rutatiina\FinancialAccounting\Services\ContactBalanceUpdateService;

trait GoodsIssuedApprovalService
{
    public static function run($data)
    {
        if ($data['status'] != 'approved')
        {
            //can only update balances if status is approved
            return false;
        }

        if (isset($data['balances_where_updated']) && $data['balances_where_updated'])
        {
            //cannot update balances for task already completed
            return false;
        }

        //inventory checks and inventory balance update if needed
        //$this->inventory(); //currently inventory update for estimates is disabled -< todo update the inventory here

        return true;
    }

}
