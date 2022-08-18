<?php

namespace App\Http\Controllers;

use App\Exports\ApiLogsExcelFileExport;
use App\Jobs\UpdateStockAndShopifyFilesCreateJob;
use App\Mail\GlobalEmailAll;
use App\Models\ApiErrorLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class TestController extends Controller
{
    public function index2()
    {
        ini_set('max_execution_time', 30000000); //300 seconds = 5 minutes


        $totalImagesFound = 0;
        $totalImagesNotFound = 0;
        $totalProductProcessed = 0;


        #(new UpdateStockAndShopifyFilesCreateJob(1,1))->handle();

        $counter = [
            123,
            120,
            11,
        ];

        $content = 'Hi, Your Photos Uploaded to Laravel and API is working fine checked at '.now()->toDateTimeString();

        $email = Setting::where('key','adminEmail')->first();

        \Mail::to([[ 'email' => $email ? $email->value : 'amir@infcompany.com' , 'name' => 'Client' ],
        ])->cc('amirgee007@yahoo.com')->send(new GlobalEmailAll("Photos Uploaded to Laravel. TESTING EMAIL", $content, $counter));


        dd('ok email sent');

        ini_set('memory_limit', -1);

    }
}
