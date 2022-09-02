<?php

namespace Rutatiina\GoodsIssued\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Rutatiina\Tenant\Scopes\TenantIdScope;

class GoodsIssuedItem extends Model
{
    use LogsActivity;

    protected static $logName = 'TxnItem';
    protected static $logFillable = true;
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['updated_at'];
    protected static $logOnlyDirty = true;

    protected $connection = 'tenant';

    protected $table = 'rg_goods_issued_items';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    protected $appends = [
        'inventory_tracking',
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new TenantIdScope);
    }

    public function item()
    {
        return $this->belongsTo('Rutatiina\Item\Models\Item', 'item_id');
    }

    public function getInventoryTrackingAttribute()
    {
        return optional($this->item)->inventory_tracking;
    }

}
