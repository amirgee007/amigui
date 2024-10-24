<?php

namespace App\Console\Commands;

use App\Exports\ApiLogsExcelFileExport;
use App\Jobs\UpdateStockAndShopifyFilesCreateJob;
use App\Mail\GlobalEmailAll;
use App\Models\AlreadyExistProduct;
use App\Models\ApiErrorLog;
use App\Models\ShopifySizeColor;
use App\Models\SyncJob;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

use App\Exports\ShopifyImportFileExport;
use App\Exports\StockFileExport;
use App\Models\Setting;
use App\Services\Shopify\HttpApiRequest;

use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;


class UpdateStockAndShopifyFIlesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updateStockShopifyFiles:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update all files hourly';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public $monthsSpanish = [
        'January' => 'January',
        'February' => 'Febrero',
        'March' => 'Marzo',
        'April' => 'Abril',
        'May' => 'Mayo',
        'June' => 'Junio',
        'July' => 'Julio',
        'August' => 'Agosto',
        'September' => 'Septiembre',
        'October' => 'Octubre',
        'November' => 'Noviembre',
        'December' => 'Diciembre',
    ];

    public function handle()
    {

        $userClicked = \auth()->id();
        $nameJOB = 'stock-export-' . $userClicked;

        # check if there is product sync job
        $activeJob = SyncJob::activeStatus($nameJOB)->first();

        if (!$activeJob) {

            Log::emergency(now()->toDateTimeString() . ' started updated JOB now for all the things...!New April202');

            $this->createStockShopifyOutPutExcelFile();

            Log::emergency(now()->toDateTimeString() . ' Finish updated JOB now for all the things...!New April2022');

            SyncJob::truncate();

        } else {
            Log::warning('Already running job so its skipped NOW...!');
        }

    }

    public function createStockShopifyOutPutExcelFile($btnClick = 0, $userClicked = 1)
    {

        ini_set('memory_limit', '-1');

        ApiErrorLog::truncate();

        try {

            $setting = Setting::where('key', 'tax')->first();
            $settingTags = Setting::where('key', 'tags')->first();

            $tags = $settingTags ? ',' . $settingTags->value : '';

            $categoryArray = $categoryParents = $brandsArray = [];

            $categoriesResponse = HttpApiRequest::getContificoApi('categoria', 'v1');
            $brandsResponse = HttpApiRequest::getContificoApi('marca', 'v1');

            foreach ($categoriesResponse as $category) {

                if ($category['padre_id'])
                    $categoryParents[$category['id']] = $category['padre_id'];

                $categoryArray[$category['id']] = trim($category['nombre']);
            }

            foreach ($brandsResponse as $brand)
                $brandsArray[$brand['id']] = trim($brand['nombre']);

            $result_size = 500;

            $files = Storage::allFiles("public/shopify-images/$userClicked");

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

            Log::info('getContificoApi gets started at this time NOW....!' . now()->toDateTimeString());

            do {

                #Log::debug($page_count. ' Done createStockShopifyOutPutExcelFile count here api');
                #$typeWithParams = "producto?result_size=$result_size&result_page=$page_count";

                if ($page_count == 1)
                    $typeWithParams = "producto/?estado=A";
                else
                    $typeWithParams = "producto/?estado=A&page=$page_count";

                $data = HttpApiRequest::getContificoApi($typeWithParams);

                #"count" => 0 "next" => null "previous" => null "results" => []
                #"count" => 90878 "next" => "" "previous" => null "results" => array:100

                $page_count++;

                if ($data['count'] > 0 && count($data['results']) > 1) {
                    foreach ($data['results'] as $row) {

                        Log::critical(@$row['codigo'] . ' processed in today JOB and make sure its also in the file too.');
                        $imagesExistArray = [];  // Reset images array for each product

                        $totalProductProcessed++;

                        $response = $this->getStockFileRow($row);
                        if ($response) {
                            $allDataArrStock[] = $response;  // Only push fresh data
                        }

                        $matching = ltrim(trim($row['codigo']), '0');
                        $existingProduct = AlreadyExistProduct::where('codigo', 'like', "%$matching%")->count();

                        $filenamePath = ("public/shopify-images/$userClicked/" . trim($row['codigo']));
                        $imagesExistArray = Storage::files($filenamePath);

                        if (count($imagesExistArray) == 0) {
                            $totalImagesNotFound++;
                            continue;
                        }

                        $response123 = $this->getShopifyFileRow($row, $categoryArray, $categoryParents, $brandsArray, $imagesExistArray, $tags);

                        if (is_array($response123)) {
                            $allDataArrSHopify[] = $response123;
                        }
                    }
                }

                Log::debug(@$data['count'] . ' Still working and running in the WHILE LOOP....!!!!');

            } while ($data['next']);

            Log::info('getContificoApi gets ENDED at this time NOW....!' . now()->toDateTimeString());

            Storage::deleteDirectory("temp/$userClicked");

            $pathStock = "temp/$userClicked/PVP-2.xlsx";
            $pathShopify = "temp/$userClicked/Shopify-OUTPUT-FILE-Ready-to-Import-V2.xlsx";
            #$pathApiError = 'temp/Api-Error-Logs.xlsx';
            #$path = storage_path("app/temp/$userId/Shopify-OUTPUT-FILE-Ready-to-Import123.xlsx");


            Excel::store(new StockFileExport($allDataArrStock), $pathStock);
            Excel::store(new ShopifyImportFileExport($allDataArrSHopify), $pathShopify);
            #Excel::store(new ApiLogsExcelFileExport($errors), $pathApiError);

            Setting::updateOrCreate([
                'key' => 'last-change',
                'label' => $userClicked
            ], [
                    'value' => now()->toDateTimeString(),
                    'label' => auth()->id(),
                ]
            );

            $userClicked = \auth()->id();
            $nameJOB = 'stock-export-' . $userClicked;

            SyncJob::where('type', $nameJOB)->update(['status' => 'completed']);

            Log::alert('createStockFileShopify Created successfully....!');

            $typeWithParams = "producto/?estado=A";

            $msg = " and Api is WORKING fine last checked at ." . now()->toDateTimeString();
            $data = HttpApiRequest::getContificoApi($typeWithParams);

            if ($data['count'] == 0)
                $msg = " and Api is NOT WORKING fine at " . now()->toDateTimeString();

            $content = 'Hi, Your Photos Uploaded to Laravel has been processed' . $msg;

            $email = Setting::where('label', $userClicked)->where('key', 'adminEmail')->first();

            $counter = [
                $totalProductProcessed,
                $totalImagesFound,
                $totalImagesNotFound,
            ];

            $user = User::find($userClicked);
            $subject = "Photos Uploaded to Laravel";

            $name = 'Null';

            if ($user) {
                $subject = $subject . " By " . strtoupper($user->name);
                $name = strtoupper($user->name);
            }

            if ($btnClick) {
                \Mail::to([['email' => $email ? $email->value : 'amirseersol@gmail.com', 'name' => 'XYZ'],])
                    ->bcc('amirseersol@gmail.com')
                    ->send(new GlobalEmailAll($subject, $content, $counter, $user));
            }

            Log::emergency(now()->toDateTimeString() . ' Finish updated JOB now for all the things...!New March 2024');

            SyncJob::truncate();

        } catch (\Exception $ex) {
            Log::error(' JOB FAILED createStockFileShopify. ' . $ex->getMessage() . $ex->getLine());
            SyncJob::where('type', 'stock-export-' . $userClicked)->update(['status' => 'failed']);
        }

    }

    public function getStockFileRow($singleRow)
    {
        try {

            $taxPercentage = $singleRow['porcentaje_iva'] ? $singleRow['porcentaje_iva'] : 0;
            $priceWithTax = $singleRow['pvp1'] + (($taxPercentage / 100) * $singleRow['pvp1']);

            return [
                'Handle' => $singleRow['codigo_barra'],
                'Variant Price' => round($priceWithTax, 2),
                'Variant Taxable' => false, #not using it
                'Stock' => $singleRow['cantidad_stock'],
            ];
        } catch (\Exception $ex) {
            Log::error($singleRow['codigo'] . ' codigo single row error.' . $ex->getMessage() . $ex->getLine());
            return null;
        }

    }

    public function getShopifyFileRow($singleRow, $categoriesArray, $parentCategory, $brandsArrayHere, $images, $tags)
    {
        try {

            $subCategory = $fatherCategory = $brand = $pCellBrand = $gender = $brandForTags = '';  // Ensure all are reset

            $taxPercentage = $singleRow['porcentaje_iva'] ? $singleRow['porcentaje_iva'] : 0;

            $priceWithTax = $singleRow['pvp1'] + (($taxPercentage / 100) * $singleRow['pvp1']);

            if ($singleRow['categoria_id']) {
                $subCategory = @$categoriesArray[$singleRow['categoria_id']];
                $fatherCategory = @$categoriesArray[@$parentCategory[$singleRow['categoria_id']]];
            }

            if ($singleRow['marca_id']) {
                $brand = @$brandsArrayHere[$singleRow['marca_id']];
            }

            $iCellCategory = $categoryWithoutGender = $subCategory;
            #$hCell = self::getCat2Data($subCategory);

            $brand = str_replace('#', '', $brand);

            $brand = str_replace('Studio', 'Studio F', $brand);

            #Also brand bebe chage it to Bebe -》*Bebe*
            $brand = str_replace('bebe', 'Bebe', $brand);

            if (!empty($brand) && strlen($brand) > 1) {
                $checkLastCharB = substr(trim($brand), -2, 1);

                if (ctype_space($checkLastCharB)) {
                    $pCellBrand = substr_replace(trim($brand), "", -2);

                    // Check again after modifying $pCellBrand, only if needed
                    if (strlen($pCellBrand) > 1) {
                        $checkLastCharB = substr(trim($pCellBrand), -2, 1);

                        if (ctype_space($checkLastCharB)) {
                            $pCellBrand = substr_replace(trim($pCellBrand), "", -2);
                        }
                    }
                } else {
                    $pCellBrand = trim($brand);  // No spaces found, just trim the brand
                }
            } else {
                $pCellBrand = '';  // Handle empty or short brand
            }

            $brandForTags = $pCellBrand ? (',' . $pCellBrand) : '';

            $edadAge = self::isValidDate($singleRow['descripcion']);

            Log::notice($singleRow['descripcion'] . ' its DATE here and ');

            $edadDatePatternColumn = $edadAge ? (',' . $this->monthsSpanish[$edadAge->format('F')] . '-' . $edadAge->format('y')) : '';

            $sColumnBrandLen = strlen($pCellBrand);
            #$tColumnTypeLen = strlen($fatherCategory);
            #$priceWithTax = $singleRow['pvp1'] + (($taxPercentage / 100) * $singleRow['pvp1']);

            //$titleCellBefore = $iCellCategory .' '. ($tColumnTypeLen<10 ? @$fatherCategory : '') .' '.$hCell.' '.($sColumnBrandLen > 2 ? $pCellBrand : '');

            #if last char is simple CHAR then remove here
            $checkLastChar = substr(trim($iCellCategory), -2, 1);

            if (ctype_space($checkLastChar)) {

                $lastChar = substr($iCellCategory, -1);
                $categoryWithoutGender = substr_replace(trim($iCellCategory), "", -2);

                $gender = ($lastChar == 'M') ? ' Masculino' : ' Femenino';
                $iCellCategory = substr_replace(trim($iCellCategory), $gender, -2);
            }

            $titleCell = ($iCellCategory . ($sColumnBrandLen > 2 ? (' ' . $pCellBrand) : ''));

            #remove single char from the TYPE too
            $checkLastChar = substr(trim($fatherCategory), -2, 1);
            if (ctype_space($checkLastChar)) {
                $fatherCategory = substr_replace(trim($fatherCategory), "", -2);
            }

            if (strpos($fatherCategory, ' F ') !== false)
                $fatherCategory = str_replace(" F ", " Femenino ", $fatherCategory);

            elseif (strpos($fatherCategory, ' M ') !== false)
                $fatherCategory = str_replace(" M ", " Masculino ", $fatherCategory);


            if (strpos($titleCell, ' F ') !== false)
                $titleCell = str_replace(" F ", " Femenino ", $titleCell);

            elseif (strpos($titleCell, ' M ') !== false)
                $titleCell = str_replace(" M ", " Masculino ", $titleCell);


            $titleCell = trim($titleCell);

            #$sizeColor = ShopifySizeColor::where('codigo', $singleRow['codigo'])->first();

            #new two fields added here

            $newTagsPersonalizado = $singleRow['personalizado1'] ? (',' . $singleRow['personalizado1'] . ',' . $singleRow['personalizado2']) : '';

            //$newTagsPersonalizado = $sizeColor ? (',' . $sizeColor->size . ',' . $sizeColor->color) : '';

            $fatherCategoryTag = $fatherCategory ? (',' . $fatherCategory) : '';
            $tagsCell = $categoryWithoutGender . $fatherCategoryTag . $brandForTags . $newTagsPersonalizado . $edadDatePatternColumn;

            $vendor = $sColumnBrandLen > 2 ? $pCellBrand : 'ND';

            $older = ["SN", "sin marca", "ND", "Amigui", "amigui"];
            $replace = "-";
            $newer = [$replace, $replace, $replace, $replace, $replace];
            #brand "sin marca" should be "-"  This should be fixed in tags and brand also VENDOR

            $vendorColumn = str_replace($older, $newer, $vendor);

            $images = array_map(function ($value) {
                return url(str_replace("public", "storage", $value));
            },
                $images);

            if (!(substr($singleRow['codigo'], 0, 1) === "0"))
                $singleRow['codigo'] = '0' . $singleRow['codigo'];

            $finalTags = rtrim($tagsCell, ',') . $tags;

            // Display replaced string
            $finalTags = str_replace("sin marca", "-", $finalTags);
            $finalTags = str_replace("Sin Marca", "-", $finalTags);

            $finalTags = str_replace("sin Marca", "-", $finalTags);
            $finalTags = str_replace("Sin Marca", "-", $finalTags);

            #Log::info($singleRow['codigo']. ' SKU its price NO '.$priceWithTax);

            Log::info("Processing fproduct: {$singleRow['codigo']}, Brand: {$brand}, Category: {$iCellCategory}");

            return [

                'Handle' => $singleRow['codigo'], #done
                'Title' => $titleCell,
                'Body' => $titleCell,
                'Vendor' => $vendorColumn,
                'Type' => $fatherCategory,
                'Tags' => $finalTags,

                'Published' => false, #not too important
                'Option1_Name' => 'Talla',
                'Option1_Value' => $singleRow['personalizado1'],
                'Option2_Name' => 'Color',
                'Option2_Value' => $singleRow['personalizado2'],

                'Variant_SKU' => $singleRow['codigo_barra'],
                'V_Inventory_Tracker' => 'shopify',
                'V_Inventory_Qty' => $singleRow['cantidad_stock'], #done
                'V_Inventory_Policy' => 'deny',
                'V_Price' => number_format($priceWithTax, 2, ',', ''),
//                'V_Price' => round($priceWithTax, 2),
                'V_Requires_Shipping' => true,
                'V_Taxable' => true,
                'imagen_Calc' => implode(';', $images)
            ];


        } catch (\Exception $ex) {
            Log::error($singleRow['codigo'] . ' codigo single row error.' . $ex->getMessage() . $ex->getLine());

            $dt = [
                'codigo_number' => $singleRow['codigo'],
                'message' => 'Some error during logic ' . $ex->getMessage(),
            ];

            ApiErrorLog::updateOrCreate($dt, $dt);

            return null;
        }
    }

    public static function isValidDate($dateInput)
    {

        try {

            $date = null;

            $dateFound = explode('-', $dateInput);

            $monthsShort = [
                'Jan' => 'ENE',
                'Feb' => 'FEB',
                'Mar' => 'MAR',
                'Apr' => 'ABR',
                'May' => 'MAYO',
                'June' => 'JUN',
                'July' => 'JUL',
                'Aug' => 'AGOSTO',
                'Sept' => 'SET',
                'Oct' => 'OCT',
                'Nov' => 'NOV',
                'Dec' => 'DIC',
            ];

            if (isset($dateFound[0]) && in_array($dateFound[0], $monthsShort)) {
                $month = array_search($dateFound[0], $monthsShort);
                $date = Carbon::createFromFormat('M-Y', $month . '-' . $dateFound[1]);
            }

            #Log::info($date . ' this date is invalid for the APPLICATION so need to check something');
            return $date;

        } catch (\Exception $e) {
            #Log::warning($date . ' this date is invalid for the APPLICATION so need to check something');
            return null;
        }
    }

    public static function getCat2Data($string)
    {

        try {
            preg_match('#\((.*?)\)#', $string, $match);
            $fOrM = substr($string, -1);

            return isset($match[1]) ? trim($match[1]) : ($fOrM == 'M' || $fOrM == 'F' ? $fOrM : '');

        } catch (\Exception $e) {
            return '';
        }


    }

}
