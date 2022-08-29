<?php

Route::group(['middleware' => ['web', 'auth', 'tenant', 'service.accounting']], function() {

	Route::prefix('goods-issued')->group(function () {

        Route::post('routes', 'Rutatiina\GoodsIssued\Http\Controllers\GoodsIssuedController@routes')->name('goods-issued.routes');
        //Route::get('summary', 'Rutatiina\GoodsIssued\Http\Controllers\GoodsIssuedController@summary');
        Route::post('export-to-excel', 'Rutatiina\GoodsIssued\Http\Controllers\GoodsIssuedController@exportToExcel');
        Route::post('{id}/approve', 'Rutatiina\GoodsIssued\Http\Controllers\GoodsIssuedController@approve')->name('goods-issued.approve');
        //Route::post('contact-estimates', 'Rutatiina\GoodsIssued\Http\Controllers\Sales\ReceiptController@estimates');
        Route::get('{id}/copy', 'Rutatiina\GoodsIssued\Http\Controllers\GoodsIssuedController@copy');
        Route::delete('delete', 'Rutatiina\GoodsIssued\Http\Controllers\GoodsIssuedController@delete')->name('goods-issued.delete');
        Route::delete('cancel', 'Rutatiina\GoodsIssued\Http\Controllers\GoodsIssuedController@cancel')->name('goods-issued.cancel');

    });

    Route::resource('goods-issued/settings', 'Rutatiina\GoodsIssued\Http\Controllers\GoodsIssuedSettingsController');
    Route::resource('goods-issued', 'Rutatiina\GoodsIssued\Http\Controllers\GoodsIssuedController');

});
