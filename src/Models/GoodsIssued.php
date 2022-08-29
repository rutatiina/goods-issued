<?php

namespace Rutatiina\GoodsIssued\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Rutatiina\Tenant\Scopes\TenantIdScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Bkwld\Cloner\Cloneable;

class GoodsIssued extends Model
{
    use SoftDeletes;
    use LogsActivity;
    use Cloneable;

    protected static $logName = 'Txn';
    protected static $logFillable = true;
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['updated_at'];
    protected static $logOnlyDirty = true;

    protected $connection = 'tenant';

    protected $table = 'rg_goods_issued';

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected $casts = [
        'contact_id' => 'integer',
    ];

    protected $cloneable_relations = ['items', 'comments'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    protected $appends = [
        'number_string',
        'total_in_words',
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

        static::addGlobalScope('ancient', function (Builder $builder) {
            $builder->whereNotIn('status', ['edited']);
        });

        self::deleting(function($txn) { // before delete() method call this
             $txn->items()->each(function($row) {
                $row->delete();
             });
             $txn->comments()->each(function($row) {
                $row->delete();
             });
        });

    }

    public function rgGetAttributes()
    {
        $attributes = [];
        $describeTable =  \DB::connection('tenant')->select('describe ' . $this->getTable());

        foreach ($describeTable  as $row) {

            if (in_array($row->Field, ['id', 'created_at', 'updated_at', 'deleted_at', 'tenant_id', 'user_id'])) continue;

            if (in_array($row->Field, ['currencies', 'taxes'])) {
                $attributes[$row->Field] = [];
                continue;
            }

            if ($row->Default == '[]') {
                $attributes[$row->Field] = [];
            } else {
                $attributes[$row->Field] = ''; //$row->Default; //null affects laravel validation
            }
        }

        //add the relationships
        $attributes['type'] = [];
        $attributes['debit_account'] = [];
        $attributes['credit_account'] = [];
        $attributes['items'] = [];
        $attributes['ledgers'] = [];
        $attributes['comments'] = [];
        $attributes['contact_id'] = [];
        $attributes['recurring'] = [];

        return $attributes;
    }

    public function getContactAddressArrayAttribute()
    {
        return preg_split("/\r\n|\n|\r/", $this->contact_address);
    }

    public function getNumberStringAttribute()
    {
        return $this->number_prefix.(str_pad(($this->number), $this->number_length, "0", STR_PAD_LEFT)).$this->number_postfix;
    }

    public function getTotalInWordsAttribute()
    {
        $f = new \NumberFormatter( locale_get_default(), \NumberFormatter::SPELLOUT );
        return ucfirst($f->format($this->total));
    }
    
    public function items()
    {
        return $this->hasMany('Rutatiina\GoodsIssued\Models\GoodsIssuedItem', 'goods_issued_id')->orderBy('id', 'asc');
    }

    public function comments()
    {
        return $this->hasMany('Rutatiina\GoodsIssued\Models\GoodsIssuedComment', 'goods_issued_id')->latest();
    }

    public function contact()
    {
        return $this->hasOne('Rutatiina\Contact\Models\Contact', 'id', 'contact_id');
    }

}
