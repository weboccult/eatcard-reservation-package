<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class StoreSlot extends Model
{
	use SoftDeletes;

	protected $table = 'store_slots';
	protected $fillable = [
		'store_id',
		'from_time',
		'max_entries',
		'meal_id',
		'is_slot_disabled',
		'store_weekdays_id',
		'meal_group_id',
	];
    protected $appends = ['data_model','from_time'];

    public function getFromTimeAttribute()
    {
        return Carbon::parse($this->getRawOriginal('from_time'))->format('H:i');
    }

    public function getDataModelAttribute()
    {
        return "StoreSlot";
    }

	public function store_weekday()
	{
		return $this->hasOne(StoreWeekDay::class, 'id', 'store_weekdays_id');
	}

    public function store()
    {
        return $this->hasOne(Store::class, 'id', 'store_id');
	}

    public function meal()
    {
        return $this->hasOne(Meal::class, 'id', 'meal_id');
	}
}
