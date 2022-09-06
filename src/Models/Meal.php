<?php

namespace Weboccult\EatcardReservation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Meal extends Model
{
    protected $fillable = ['name', 'store_id', 'order_id', 'meal_image', 'description', 'time_limit', 'is_meal_res', 'is_week_meal_res', 'status','payment_type', 'price', 'is_default'];
	protected $appends = ['time_limit_hour', 'user_payment_type'];

	public function getTimeLimitHourAttribute($value)
	{
		$hours = intdiv($this->time_limit, 60).':'. ($this->time_limit % 60);
		return $hours;
    }
	public function getUserPaymentTypeAttribute($value)
	{
		$paymentType = '';
		if($this->payment_type == 1) {
			$paymentType = __('messages.full_payment');
		}
		elseif ($this->payment_type == 3) {
			$paymentType = __('messages.partial_payment');
		}
		return $paymentType;
    }

    public function store()
    {
        return $this->hasOne(Store::class, 'id', 'store_id');
    }

	public function todayReservations()
	{
		$today = Carbon::now()->format('Y-m-d');
		return $this->hasMany(StoreReservation::class, 'meal_type')->where('res_date', $today);
    }
	public function last30DaysReservations()
	{
		$today = Carbon::now()->format('Y-m-d');
		$prev_date = Carbon::now()->subDays(30)->format('Y-m-d');
		return $this->hasMany(StoreReservation::class, 'meal_type')->where('res_date', '<=', $today)->where('res_date', '>=', $prev_date);
    }
}
