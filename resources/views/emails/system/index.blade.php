@component('mail::message')
Hi, Andres

@component('mail::panel')
    {!! $content ?? '' !!}
@endcomponent

@component('mail::button', ['url' => route('download.stock.excel' , @$user->id), 'color' => 'green'])
    Download Stock File V2
@endcomponent

@component('mail::button', ['url' => route('download.shopify.import.excel' , @$user->id), 'color' => 'red'])
    Download Shopify File V2
@endcomponent

{{--$counter $totalProductProcessed, $totalImagesFound, $totalImagesNotFound,--}}
@component('mail::table')
    |Product Processed       | Images Found         | Images Not Found  |
    | ------------- |:-------------:| --------:|
    | {{@$counter[0]}}    | {{@$counter[1]}}      | {{@$counter[2]}}      |
@endcomponent

###Images not found is due to no INVENTORY.
{{--<a target="_blank" href="{{route('download.erroLogs.excel')}}">HERE</a>--}}

<br>
User: **{{@$user->name}}** <br>
URL: **{{env('APP_URL')}}**
@endcomponent
