<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Table extends Model
{
	protected $fillable = [
		'dining_area_id',
		'name',
		'no_of_min_seats',
		'no_of_seats',
		'qr_code',
		'status',
        'online_status'
	];

    public function reservations()
	{
		return $this->hasMany(ReservationTable::class, 'table_id');
	}

	public function diningArea()
	{
		return $this->belongsTo(DiningArea::class, 'dining_area_id');
	}
}
