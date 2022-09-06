<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class StoreSlotModified extends Model
{
	use SoftDeletes;

	protected $table = 'store_slot_modified';
	protected $fillable = [
		'store_id',
		'store_date',
		'from_time',
		'max_entries',
		'meal_id',
		'description',
		'is_available',
		'deleted_by',
		'is_slot_disabled',
		'is_day_meal',
		'meal_group_id',
		'group_id',
	];

    protected $appends = ['data_model'];

    public function getDataModelAttribute()
    {
        return "StoreSlotModified";
    }

	public function storePivot()
	{
		return $this->belongsTo(StoreOwner::class, 'store_id');
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
