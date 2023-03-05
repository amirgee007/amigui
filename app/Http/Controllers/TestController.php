<?php

namespace App\Http\Controllers;

use App\Exports\ApiLogsExcelFileExport;
use App\Jobs\UpdateStockAndShopifyFilesCreateJob;
use App\Mail\GlobalEmailAll;
use App\Models\ApiErrorLog;
use App\Models\Setting;
use App\Services\Shopify\HttpApiRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class TestController extends Controller
{
    public function index2()
    {
        ini_set('max_execution_time', 30000000); //300 seconds = 5 minutes


        ini_set('memory_limit', '-1');

        try {

            $setting = Setting::where('key', 'tax')->first();
            $settingTags = Setting::where('key', 'tags')->first();

            $tags = $settingTags ? ',' . $settingTags->value : '';

            $categoryArray = $categoryParents = $brandsArray = [];
            $result_size = 5;

            $files = Storage::allFiles('public/shopify-images/');

            $totalImagesFound = 0;
            $totalImagesNotFound = 0;
            $totalProductProcessed = 0;

            if ($totalImagesFound = count($files)) {
                $totalImagesFound = (int)($totalImagesFound / 2);
            }

            $page_count = 1;

            $max_pages = (env('APP_ENV') == 'local') ? 5 : 1000;

            $taxPercentage = $setting->value;

            $allDataArrStock = $allDataArrSHopify = [];

            do {

                Log::critical($page_count .' Done into DO while LOOP its is...!');

                if($page_count == 1)
                    $typeWithParams = "producto/?estado=A";
                else
                    $typeWithParams = "producto/?estado=A&page=$page_count";

                $data = HttpApiRequest::getContificoApi($typeWithParams);

                #"count" => 0 "next" => null "previous" => null "results" => []
                #"count" => 90878 "next" => "" "previous" => null "results" => array:100

                $page_count++;

                if (count($data['results']) > 1) {
                    foreach ($data['results'] as $row) {
                        Log::alert($page_count.' createStockFileShopify Created successfully....! '.$row['codigo']);

                    }
                }


            } while ($data['next']);

            dd($data , $typeWithParams);
            $errors = ApiErrorLog::get()->toArray();

            $pathStock = 'temp/PVP-2.xlsx';
            $pathShopify = 'temp/Shopify-OUTPUT-FILE-Ready-to-Import123.xlsx';
            #$pathApiError = 'temp/Api-Error-Logs.xlsx';

            Excel::store(new StockFileExport($allDataArrStock), $pathStock);
            Excel::store(new ShopifyImportFileExport($allDataArrSHopify), $pathShopify);
            #Excel::store(new ApiLogsExcelFileExport($errors), $pathApiError);

            Setting::updateOrCreate(['key' => 'last-change'], ['value' => now()->toDateTimeString()]);

            SyncJob::where('type', 'stock-export')->update(['status' => 'completed']);



            $result_size = 500;
            $page_count = 1;

            $typeWithParams = "producto?result_size=$result_size&result_page=$page_count";

            $msg = " and Api is WORKING fine last checked at ." . now()->toDateTimeString();
            $data = HttpApiRequest::getContificoApi($typeWithParams);

            if (is_null($data))
                $msg = " and Api is NOT WORKING fine at " . now()->toDateTimeString();

            $content = 'Hi, Your Photos Uploaded to Laravel has been processed' . $msg;

            $email = Setting::where('key', 'adminEmail')->first();

            $counter = [
                $totalProductProcessed,
                $totalImagesFound,
                $totalImagesNotFound,
            ];

            if(1){
                \Mail::to([['email' => $email ? $email->value : 'amirgee007@yahoo.com', 'name' => 'Amir'],
                ])->bcc('amirgee007@yahoo.com')->send(new GlobalEmailAll("Photos Uploaded to Laravel.", $content, $counter));
            }

            Log::emergency(now()->toDateTimeString() . ' Finish updated JOB now for all the things...!New April202');

            SyncJob::truncate();


        } catch (\Exception $ex) {

            dd($ex);
            Log::error(' JOB FAILED createStockFileShopify. ' . $ex->getMessage() . $ex->getLine());
            SyncJob::where('type', 'stock-export')->update(['status' => 'failed']);
        }

        #(new UpdateStockAndShopifyFilesCreateJob(1,1))->handle();

        $counter = [
            123,
            120,
            11,
        ];

        $content = 'Hi, Your Photos Uploaded to Laravel and API is working fine checked at '.now()->toDateTimeString();

        $email = Setting::where('key','adminEmail')->first();

        \Mail::to([[ 'email' => $email ? $email->value : 'amir@infcompany.com' , 'name' => 'Client' ],
        ])->bcc('amir@infcompany.com')->send(new GlobalEmailAll("Photos Uploaded to Laravel. TESTING EMAIL", $content, $counter));


        dd('ok email sent');

        ini_set('memory_limit', -1);

    }
}
