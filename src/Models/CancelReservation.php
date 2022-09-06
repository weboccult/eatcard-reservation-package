<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancelReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'reservation_id',
        'reservation_front_data',
        'reason',
    ];

}
