<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServeRequest extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'image', 'is_active', 'active_image'];
}
