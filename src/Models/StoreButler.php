<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class StoreButler extends Model
{
    protected $fillable = [
        'store_id',
        'from_time',
        'to_time',
        'is_authentication',
        'is_check_authentication',
        'dine_in_auto_preparing',
        'is_la_carte',
        'is_buffet',
        'max_orders',
        'max_rounds',
        'dine_in_allow_booking',
        'next_round_time',
        'is_extra_wasabi',
        'is_extra_gember',
        'is_extra_servetjes',
        'is_extra_sojasaus',
        'is_bestek_aub',
        'is_hulpstokjes_aub',
        'hide_checkout',
        'hide_dinein_phone',
        'warning_time',
        'dinein_select_package',
        'dinein_package_change_person',
        'al_a_carte_off',
        'show_product_code',
        'autocheckout_after_payment',
        'is_dinein_deposit'
    ];
	protected static function boot()
	{
		parent::boot();
		static::created(function ($model) {
			$user=getUserDetail();
			$store_name = (isset($model->store) && isset($model->store->store_name)) ? $model->store->store_name : '';
			$msg = 'butler setting Created: ' . $user['role'] . ' (' . $user['name'] . '-' . $user['id'] . ') created a setting ' . $model->name . ' | id-' . $model->id . ' for the store ' . $store_name . '';
			Log::info($msg. ', IP address : '.request()->ip(). ', browser : '. request()->header('User-Agent'));
		});
		static::deleted(function ($model) {
			$user=getUserDetail();
			$store_name = (isset($model->store) && isset($model->store->store_name)) ? $model->store->store_name : '';
			$msg = 'butler setting Deleted: ' . $user['role'] . ' (' . $user['name'] . '-' . $user['id'] . ') delete a setting ' . $model->name . ' | id-' . $model->id . ' for the store ' . $store_name . '';
			Log::info($msg. ', IP address : '.request()->ip(). ', browser : '. request()->header('User-Agent'));
		});
		static::updated(function ($model) {
			$dirty = $model->getDirty();
			if (!empty($dirty)) {
				$user=getUserDetail();
				$store_name = (isset($model->store) && isset($model->store->store_name)) ? $model->store->store_name : '';
				foreach ($dirty as $field => $newdata) {
					$olddata = $model->getOriginal($field);
					$oldData['old'][$field] = $olddata;
					$newData['new'][$field] = $newdata;
				}
				$msg = 'butler setting Updated: ' . $user['role'] . ' (' . $user['name'] . '-' . $user['id'] . ') updated a setting ' . $model->name . ' | id-' . $model->id . ' for the store ' . $store_name . '. ' . print_r($oldData, true) . ' => ' . print_r($newData, true) . '';
				Log::info($msg. ', IP address : '.request()->ip(). ', browser : '. request()->header('User-Agent'));
			}
		});
	}

	public function store()
    {
        return $this->hasOne(Store::class, 'id', 'store_id');
    }
}
