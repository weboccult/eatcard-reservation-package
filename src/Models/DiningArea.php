<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class DiningArea extends Model
{
	protected $fillable = [
		'name',
		'store_id',
		'priority',
		'is_automatic',
		'order_id',
		'status',
		'display_booking_frame'
	];

	public function tables()
	{
		return $this->hasMany(Table::class, 'dining_area_id');
	}

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
