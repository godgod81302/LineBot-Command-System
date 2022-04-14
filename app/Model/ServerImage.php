<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ServerImage extends Model
{
    //
    protected $fillable = ['server_id','images_info'];
    public $timestamps = false;
}
