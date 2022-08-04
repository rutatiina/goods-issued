<?php

namespace Rutatiina\GoodsIssued\Services;

use Rutatiina\GoodsIssued\Models\GoodsIssuedItem;
use Rutatiina\GoodsIssued\Models\GoodsIssuedItemTax;

class GoodsIssuedItemService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function store($data)
    {
        //print_r($data['items']); exit;

        //Save the items >> $data['items']
        foreach ($data['items'] as &$item)
        {
            $item['goods_issued_id'] = $data['id'];

            GoodsIssuedItem::create($item);

        }
        unset($item);

    }

}
