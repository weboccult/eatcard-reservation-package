<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;

class StoreWeekDay extends Model
{
	protected $table = 'store_weekdays';
    protected $fillable = [
  		'name', 'is_active', 'store_id', 'is_week_day_meal'
		];

	public function weekSlots()
	{
		return $this->hasMany(StoreSlot::class, 'store_weekdays_id');
    }
}
