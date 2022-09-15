<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DineinPriceClassChildPrice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'dinein_price_id',
        'price',
        'name',
        'untill_id',
        'status'
    ];

    protected $appends = ['person'];

	public function getPersonAttribute()
	{
		return 0;
	}
}
