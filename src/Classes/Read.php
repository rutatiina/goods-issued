<?php

namespace Rutatiina\GoodsIssued\Classes;

use Rutatiina\GoodsIssued\Models\GoodsIssued;

use Rutatiina\GoodsIssued\Traits\Init as TxnTraitsInit;

class Read
{
    use TxnTraitsInit;

    public function __construct()
    {}

    public function run($id)
    {
        $Txn = GoodsIssued::find($id);

        if ($Txn) {
            //txn has been found so continue normally
        } else {
            $this->errors[] = 'Transaction not found';
            return false;
        }

        $Txn->load('contact', 'debit_account', 'credit_account', 'items');

        foreach ($Txn->items as &$item) {

            if (empty($item->name)) {
                $txnDescription[] = $item->description;
            }
            else {
                $txnDescription[] = (empty($item->description)) ? $item->name : $item->name . ': ' . $item->description;
            }

        }

        $Txn->description = implode(',', $txnDescription);

        $f = new \NumberFormatter( locale_get_default(), \NumberFormatter::SPELLOUT );
        $Txn->total_in_words = ucfirst($f->format($Txn->total));

        return $Txn->toArray();

    }

}
