<?php

namespace App\Http\Controllers;

use App\Console\Commands\UpdateStockAndShopifyFIlesCommand;
use App\Exports\ProductSkuRenamedListExport;
use App\Imports\ProductSkuListImport;
use App\Jobs\UpdateStockAndShopifyFilesCreateJob;
use App\Models\Setting;
use App\Models\SyncJob;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;


class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function logout(Request $request) {
        Auth::logout();
        return redirect('/');
    }

    public static function getStorageBackupPath($for , $ext = '.xls'){

        $user = '-by-'.auth()->user()->name;

        $now = now()->toDateTimeString();
        $folder = 'public/backups/'.$for.'/'.now()->year.'/'.strtolower(now()->format('M')).'/';

        return $folder.str_slug($now).$user.$ext;
    }

    public function renameFilesSku(Request $request){

        try {

            ini_set('max_execution_time', 18000);

            ini_set('max_execution_time', 300000); //300 seconds = 5 minutes
            ini_set('max_memory_limit', -1); //300 seconds = 5 minutes
            ini_set('memory_limit', '4096M');

            $v = Validator::make($request->all(), [
                'images_zip' => 'required|mimes:zip',
                'sku_file' => 'required|mimes:xlsx',
            ]);

            $userId = \auth()->id();

            if($v->fails()) {
                return back()->withErrors($v);
            }
            else
            {

                $namePathSKU = self::getStorageBackupPath('sku_file' , '.xlsx');
                Storage::put($namePathSKU,file_get_contents($request->file('sku_file')->getRealPath()));

                $namePathZIP = self::getStorageBackupPath('imagesZip' , '.zip');
                Storage::put($namePathZIP,file_get_contents($request->file('images_zip')->getRealPath()));


                $sku_file = "public/$userId/sku_file.xlsx";

                Log::emergency('renameFilesSku Started and file saved public/sku_file.xlsx');

                Storage::put($sku_file, file_get_contents($request->file('sku_file')->getRealPath()));

                $readd = 'app/'.$sku_file;
                $products = Excel::toArray(new ProductSkuListImport(), storage_path($readd));
                $skusFinal = [];

                if(isset($products[0])){
                    foreach ($products[0] as $product){
                        $skusFinal[] = $product[0];
                    }
                }

                else
                {
                    return Redirect::back()->withErrors('Your imported ZIP file is invalid please try again.');
                }

                $file = new Filesystem;
                $file->cleanDirectory("files/$userId/imageOriginal");
                $file->cleanDirectory("files/$userId/imageRenamed");

                Log::emergency('renameFilesSku Started and directory created NOW');

                $zip = new \ZipArchive();
                $file = $request->file('images_zip');

                if ($zip->open($file->path()) === TRUE) {
                    $zip->extractTo("files/$userId/imageOriginal");
                    $zip->close();

                } else {
                    Log::error("Order Products Inventories error UNABLE TO READ the zip file.");
                    return Redirect::back()->withErrors('Your imported ZIP file is invalid please try again.');
                }

                $path = public_path("files/$userId/imageOriginal");

                $files = File::allFiles($path);

                $pathNew = public_path("files/$userId/imageRenamed");

                Log::emergency('renameFilesSku Started and imageRenamed NOW');

                File::makeDirectory($path, $mode = 0777, true, true);
                File::makeDirectory($pathNew, $mode = 0777, true, true);

                $skuChoose = $newName = $namesFinalExcel= null;

                $index = 0;
                foreach ($files as $counter => $file){

                   if($counter%2 == 0){
                       $skuChoose = @$skusFinal[$index];
                       $newName = $skuChoose.'.jpg';
                       $index++;
                   }
                   else{
                       $newName = $skuChoose.'-2.jpg';
                   }

                   $namesFinalExcel [] = $newName;

                   File::move($file, $pathNew.'/'.$newName);
                }

                Log::emergency('renameFilesSku Started and RenameImagesFiles and downloading zip file...!');

                $zip_file = 'RenameImagesFiles.zip';
                $zip = new \ZipArchive();
                $zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pathNew));
                foreach ($files as $name => $file)
                {
                    // We're skipping all subfolders
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $fileName = $file->getFileName();

                        $zip->addFile($filePath, $fileName);
                    }
                }
                $zip->close();
                return response()->download($zip_file);
            }

        } catch (\Exception $ex) {
            Log::error("Your imported excel file is invalid please try again RENAMING images " .$ex->getMessage().'-'.$ex->getLine());
            return Redirect::back()->withErrors('Your imported excel file is invalid please try again.');
        }
    }
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $tax = Setting::mine()->where('key','tax')->first();
        $tags = Setting::mine()->where('key','tags')->first();
        $email = Setting::mine()->where('key','adminEmail')->first();

        $lastUpdate = Setting::mine()->where('key','last-change')->first();
        $files = [];

        if(\request('is_sku')){
            $userId = \auth()->id();
            $folder = request('is_sku');
            $filenamePath = ("public/shopify-images/$userId/".$folder);
            $files = Storage::files($filenamePath);
        }

        return view('home' ,compact('tax' ,'lastUpdate' ,'files' ,'tags' , 'email'));
    }

    public function resetAllImages(){

        $userId = \auth()->id();
        $file = new Filesystem;
        $file->cleanDirectory("storage/shopify-images/$userId");

        session()->flash('app_message', 'Your all images in the PROJECT are removed please upload again.');

        return back();
    }

    public function saveSettings(Request $request){

        $data = array_slice($request->all(), 1, 1, true);

        Setting::updateOrCreate([
            'key' => key($data),
            'label' => \auth()->id(),
        ], [
            'value' => reset($data),
        ]);

        session()->flash('app_message', 'Settings has been updated successfully.');

        return back();
    }

    public function updateTax(Request $request){

       Setting::updateOrCreate([
           'key' => 'tax',
           'label' => \auth()->id(),
       ], ['value' => $request->tax]);
       session()->flash('app_message', 'Tax has been updated successfully.');

       return back();
    }

    public function ajaxProdImageUpload(Request $request){

        ini_set('max_execution_time', 1800);

        if ($request->hasFile('file')) {

            $imgUpload = $request->file('file');{

                $filename = $imgUpload->getClientOriginalName();
                $extension = $imgUpload->getClientOriginalExtension();

                $validextensions = array("jpeg", "jpg", "png");

                // Check extension
                if (in_array(strtolower($extension), $validextensions)) {
                    try {

                        $withoutExtension = pathinfo($filename, PATHINFO_FILENAME);

                        $names = (explode('-', $withoutExtension));

                        if (isset($names[1]) && is_numeric($names[1])) {
                            $filenameWithExt = $names[1] . '.' . $extension;
                            $folder = $names[0];
                        } else {
                            $filenameWithExt = 1 . '.' . $extension;
                            $folder = $names[0];
                        }

                        $userId = \auth()->id();
                        $filenamePath = ("public/shopify-images/$userId/" . $folder . '/' . $filenameWithExt);

                        \Storage::disk('local')->put($filenamePath, file_get_contents($imgUpload->getRealPath()));

                    } catch (\Exception $ex) {
                        Log::warning($filename . ' error ' . $ex->getMessage());
                    }

                }
            }
        }
    }

    public function downloadStockExcelFIle(){
        try {

            $userId = \auth()->id();
            $path = storage_path("app/temp/$userId/PVP-2.xlsx");

            return response()->download($path);
        } catch (\Exception $ex) {

            session()->flash('app_error', 'No file found please try to generate file or contact admin.');
            return back();
        }
    }

//    public function downloadErrorLogsFIle(){
//        try{
//            $path = storage_path('app/temp/Api-Error-Logs.xlsx');
//            return response()->download($path);
//        }
//        catch (\Exception $ex){
//
//            session()->flash('app_error', 'No file found please try to generate file or contact admin.');
//            return back();
//        }
//
//    }

    public function downloadShopifyOutPutExcelFile(){

        try{

            $userId = \auth()->id();
            $path = storage_path("app/temp/$userId/Shopify-OUTPUT-FILE-Ready-to-Import123.xlsx");

            return response()->download($path);
        }
        catch (\Exception $ex){

            session()->flash('app_error', 'No file found please try to generate file or contact admin.');
            return back();
        }
    }

    public function processImagesIntoExcelFile($btnClick = 0){

        ini_set('max_execution_time', 4000); //900 seconds = 30 minutes

        $userClicked = \auth()->id();
        $nameJOB = 'stock-export-'.$userClicked;

        session()->flash('app_message', 'Your cron job has been scheduled and starting soon please wait.');

        # check if there is product sync job
        $activeJob = SyncJob::activeStatus($nameJOB)->first();

        if (!$activeJob) {
            $newSyncJob = SyncJob::create(['type' => 'stock-export']);
            UpdateStockAndShopifyFilesCreateJob::dispatch($newSyncJob->id, $newSyncJob->type , $btnClick , $userClicked);

            session()->flash('app_message', 'Your cron job has been scheduled and starting soon please wait.');
        }

        return \redirect()->route('home');
    }


}
