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

        $user = auth()->user();

        $content = 'Hi, Your Photos Uploaded to Laravel and API is working v2 April 2023 '.now()->toDateTimeString();

        \Mail::to([[ 'email' => 'amirseersol@gmail.com' , 'name' => 'Client' ],
        ])->bcc('amirgee007@yahoo.com')->send(new GlobalEmailAll("Photos Uploaded to Laravel. V3", $content, [] , $user));

        dd('ok email sent');

        ini_set('memory_limit', -1);

    }
}
