<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';
    protected $guarded = [];


    public function scopeMine($query){
        return $query->where('label', auth()->id());
    }

}
