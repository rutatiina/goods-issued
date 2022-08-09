<?php

namespace Rutatiina\GoodsIssued\Http\Controllers;

use PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;
use Rutatiina\Contact\Traits\ContactTrait;
use Rutatiina\GoodsIssued\Models\GoodsIssued;
use Rutatiina\GoodsIssued\Traits\Item as TxnItem;
use Rutatiina\GoodsIssued\Classes\Copy as TxnCopy;
use Rutatiina\GoodsIssued\Classes\Edit as TxnEdit;

use Rutatiina\GoodsIssued\Classes\Read as TxnRead;
use Rutatiina\GoodsIssued\Classes\Store as TxnStore;
use Rutatiina\GoodsIssued\Models\GoodsIssuedSetting;
use Rutatiina\GoodsIssued\Classes\Number as TxnNumber;
use Rutatiina\GoodsIssued\Classes\Update as TxnUpdate;
use Rutatiina\GoodsIssued\Services\GoodsIssuedService;
use Rutatiina\GoodsIssued\Classes\Approve as TxnApprove;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Rutatiina\FinancialAccounting\Traits\FinancialAccountingTrait;

class GoodsIssuedController extends Controller
{
    //use TenantTrait;
    use ContactTrait;
    use FinancialAccountingTrait;
    use TxnItem; // >> get the item attributes template << !!important

    private  $txnEntreeSlug = 'goods-issued-note';

    public function __construct()
    {
        $this->middleware('permission:goods-issued.view');
		$this->middleware('permission:goods-issued.create', ['only' => ['create','store']]);
		$this->middleware('permission:goods-issued.update', ['only' => ['edit','update']]);
		$this->middleware('permission:goods-issued.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson()) {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $per_page = ($request->per_page) ? $request->per_page : 20;

        $sort_by = [];

        if ($request->search_column) {
            $sort_by = [
                $request->search_column => $request->search_value
            ];
        }

        //return $sort_by;

        $txns = GoodsIssued::with('items')->latest()->paginate($per_page);

        return [
            'tableData' => $txns
        ];
    }

    private function nextNumber()
    {
        $txn = GoodsIssued::latest()->first();
        $settings = GoodsIssuedService::settings();

        return $settings->number_prefix.(str_pad((optional($txn)->number+1), $settings->minimum_number_length, "0", STR_PAD_LEFT)).$settings->number_postfix;
    }

    public function create()
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson()) {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $tenant = Auth::user()->tenant;

        $txnAttributes = (new GoodsIssued())->rgGetAttributes();

        $txnAttributes['number'] = $this->nextNumber();

        $txnAttributes['status'] = 'Approved';
        $txnAttributes['contact_id'] = '';
        $txnAttributes['contact'] = json_decode('{"currencies":[]}'); #required
        $txnAttributes['date'] = date('Y-m-d');
        $txnAttributes['base_currency'] = $tenant->base_currency;
        $txnAttributes['quote_currency'] = $tenant->base_currency;
        $txnAttributes['taxes'] = json_decode('{}');
        $txnAttributes['isRecurring'] = false;
        $txnAttributes['recurring'] = [
            'date_range' => [],
            'day_of_month' => '*',
            'month' => '*',
            'day_of_week' => '*',
        ];
        $txnAttributes['contact_notes'] = null;
        $txnAttributes['terms_and_conditions'] = null;
        $txnAttributes['items'] = [$this->itemCreate()];

        return [
            'pageTitle' => 'Create Goods Issued Note', #required
            'pageAction' => 'Create', #required
            'txnUrlStore' => '/goods-issued', #required
            'txnAttributes' => $txnAttributes, #required
        ];
    }

    public function store(Request $request)
    {
        $storeService = GoodsIssuedService::store($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => GoodsIssuedService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Goods issued note saved'],
            'number' => 0,
            'callback' => URL::route('goods-issued.show', [$storeService->id], false)
        ];
    }

    public function show($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson()) {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $txn = GoodsIssued::findOrFail($id);
        $txn->load('contact', 'items');
        $txn->setAppends([
            'number_string',
            'total_in_words',
        ]);

        return $txn->toArray();
    }

    public function edit($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $txnAttributes = GoodsIssuedService::edit($id);

        return [
            'pageTitle' => 'Edit Goods issued note', #required
            'pageAction' => 'Edit', #required
            'txnUrlStore' => '/goods-issued/' . $id, #required
            'txnAttributes' => $txnAttributes, #required
        ];
    }

    public function update(Request $request)
    {
        //print_r($request->all()); exit;

        $storeService = GoodsIssuedService::update($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => GoodsIssuedService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Goods issued note updated'],
            'number' => 0,
            'callback' => URL::route('goods-issued.show', [$storeService->id], false)
        ];
    }

    public function destroy($id)
	{
        $destroy = GoodsIssuedService::destroy($id);

        if ($destroy)
        {
            return [
                'status' => true,
                'messages' => ['Goods issued note deleted'],
                'callback' => URL::route('goods-issued.index', [], false)
            ];
        }
        else
        {
            return [
                'status' => false,
                'messages' => GoodsIssuedService::$errors
            ];
        }
    }

	#-----------------------------------------------------------------------------------

    public function approve($id)
    {
        $approve = GoodsIssuedService::approve($id);

        if ($approve == false)
        {
            return [
                'status' => false,
                'messages' => GoodsIssuedService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Goods issued note Approved'],
        ];
    }

    public function copy($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $txnAttributes = GoodsIssuedService::copy($id);

        return [
            'pageTitle' => 'Copy Goods issued note', #required
            'pageAction' => 'Copy', #required
            'txnUrlStore' => '/goods-issued', #required
            'txnAttributes' => $txnAttributes, #required
        ];
    }

    public function exportToExcel(Request $request)
	{
        $txns = collect([]);

        $txns->push([
            'DATE',
            'DOCUMENT#',
            'REFERENCE',
            'CUSTOMER',
            'STATUS',
            'EXPIRY DATE',
            'TOTAL',
            ' ', //Currency
        ]);

        foreach (array_reverse($request->ids) as $id) {
            $txn = Transaction::transaction($id);

            $txns->push([
                $txn->date,
                $txn->number,
                $txn->reference,
                $txn->contact_name,
                $txn->status,
                $txn->expiry_date,
                $txn->total,
                $txn->base_currency,
            ]);
        }

        $export = $txns->downloadExcel(
            'maccounts-goods-issued-export-'.date('Y-m-d-H-m-s').'.xlsx',
            null,
            false
        );

        //$books->load('author', 'publisher'); //of no use

        return $export;
    }

    public function routes()
    {
        return [
            'delete' => route('goods-issued.delete'),
            'approve' => route('goods-issued.approve'),
            'cancel' => route('goods-issued.cancel'),
        ];
    }

    public function delete(Request $request)
    {
        if (GoodsIssuedService::destroyMany($request->ids))
        {
            return [
                'status' => true,
                'messages' => [count($request->ids) . ' Goods issued note(s) deleted.'],
            ];
        }
        else
        {
            return [
                'status' => false,
                'messages' => GoodsIssuedService::$errors
            ];
        }
    }
}
