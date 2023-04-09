<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('abc/test2', 'TestController@index2');

Auth::routes(['register' => false]);

Route::get('/home', 'HomeController@index')->name('home');
Route::post('/save-settings', 'HomeController@saveSettings')->name('save.settings'); #ok with new logic

Route::get('logout', 'HomeController@logout')->name('logOutCustom');


Route::group(['middleware' => 'auth'], function () {


    Route::post('/ajaxProdImageUpload', 'HomeController@ajaxProdImageUpload')->name('add_img');
    Route::post('/ajaxProdImageDelete', 'HomeController@ajaxProdImageDelete')->name('remove_img');

    Route::get('/reset-all-images', [
        'as' => 'reset.all.images',
        'uses' => 'HomeController@resetAllImages'
    ]);#ok with new logic

    Route::post('/rename-files-sku', [
        'as' => 'rename.files.sku',
        'uses' => 'HomeController@renameFilesSku'
    ]);

    Route::get('/process-images-into-shopify/{btnClick?}', [
        'as' => 'process.images.files.excel',
        'uses' => 'HomeController@processImagesIntoExcelFile'
    ]); #ok with new logic

    Route::get('/download-shopify-import-file/{user?}', [
        'as' => 'download.shopify.import.excel',
        'uses' => 'HomeController@downloadShopifyOutPutExcelFile'
    ]); #ok with new logic

    Route::get('/download-stock-excel/{user?}', [
        'as' => 'download.stock.excel',
        'uses' => 'HomeController@downloadStockExcelFIle'
    ]); #ok with new logic



    Route::group(['middleware' => 'admin'], function() {
        Route::resource('users', 'UserController')->names([
            'index' => 'users.index',
            'create' => 'users.create',
            'store' => 'users.store',
            'edit' => 'users.edit',
            'update' => 'users.update',
            'destroy' => 'users.destroy',
        ]);

        Route::get('admin-login/{id}', [
            'as' => 'user.admin.login',
            'uses' => 'UserController@adminLogin'
        ]);
    });
});
