<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Order extends Model
{
    use SoftDeletes;
	protected $fillable = [
	    'parent_id',
		'store_id',
		'user_id',
		'total_price',
		'first_name',
		'last_name',
		'email',
		'contact_no',
		'status',
		'method',
		'comment',
		'order_date',
		'order_id',
		'order_time',
		'order_status',
		'delivery_address',
		'delivery_postcode',
		'delivery_place',
		'delivery_latitude',
		'delivery_longitude',
		'paid_on',
		'order_type',
		'sub_total',
		'total_tax',
		'total_alcohol_tax',
		'delivery_fee',
		'mollie_payment_id',
		'discount',
		'discount_type',
		'discount_amount',
        'is_picked_up',
        'table_name',
        'is_takeaway_mail_send',
        'done_on',
        'alcohol_sub_total',
        'normal_sub_total',
        'dine_in_type',
        'payment_method_type',
        'dine_in_type',
        'ccv_payment_ref',
        'ccv_customer_receipt',
        'checkout_no',
        'kiosk_id',
        'is_untill_order',
        'multisafe_payment_id',
        'is_refunded',
        'coupon_price',
        'gift_purchase_id',
        'payment_split_type',
        'payment_split_persons',
        'additional_fee',
        'created_by',
        'thusibezorgd_order_id',
        'thusibezorgd_res_id',
        'cash_paid',
        'plastic_bag_fee',
        'uber_eats_order_id',
        'plastic_bag_fee',
        'worldline_ssai',
        'worldline_customer_receipt',
        'is_delivered',
        'is_uncertain_status',
	    'is_asap',
        'discount_inc_tax',
        'is_future_order_print_pending',
        'created_from',
        'is_ignored',
        'all_you_eat_data',
        'statiege_deposite_total',
        'undo_checkout'
	];
	protected $appends = ['full_name'];

	protected static function boot()
	{
		parent::boot();
		static::created(function ($model) {
			$user=getUserDetail();
			$msg = 'Order Created: (' . $user['role'] . ' (' . $user['name'] . '-' . $user['id'] . ') created a Order ' . $model->name . ' | id-' . $model->id . ' for the store ' . $model->store_id . '';
			Log::info($msg. ', IP address : '.request()->ip(). ', browser : '. request()->header('User-Agent'));
		});
		static::deleted(function ($model) {
			$user=getUserDetail();
			$msg = 'Order Deleted: (' . $user['role'] . ' (' . $user['name'] . '-' . $user['id'] . ') delete a Order ' . $model->name . ' | id-' . $model->id . ' for the store ' . $model->store_id . '';
			Log::info($msg. ', IP address : '.request()->ip(). ', browser : '. request()->header('User-Agent'));
		});
		static::updated(function ($model) {
			$dirty = $model->getDirty();
			if (!empty($dirty)) {
				$user=getUserDetail();
				foreach ($dirty as $field => $newdata) {
					$olddata = $model->getRawOriginal($field);
					$oldData['old'][$field] = $olddata;
					$newData['new'][$field] = $newdata;
				}
				$msg = 'Order Updated: (' . $user['role'] . ' (' . $user['name'] . '-' . $user['id'] . ') updated a Order ' . $model->name . ' | id-' . $model->id . ' for the store ' . $model->store_id . '. ' . print_r($oldData, true) . ' => ' . print_r($newData, true) . '';
				Log::info($msg. ', IP address : '.request()->ip(). ', browser : '. request()->header('User-Agent'));
			}
		});
	}
	public function getFullNameAttribute()
	{
		return $this->first_name. ' '. $this->last_name;
	}
	public function orderItems()
	{
		return $this->hasMany(OrderItem::class, 'order_id');
	}

	public function subOrders()
    {
        return $this->hasMany(SubOrder::class, 'parent_order_id');
    }

    public function driver()
    {
        return $this->belongsToMany(Driver::class, 'order_delivery_trips', 'order_id', 'driver_id')->where('order_status', 'Confirmed')->orderBy('order_delivery_trips.created_at', 'desc');
    }
    public function order_delivery_detail()
    {
        return $this->hasOne(OrderDeliveryDetails::class, 'order_id');
    }

    public function pickedup()
    {
        return $this->hasMany(OrderDeliveryTrip::class, 'order_id')->where('order_status', 'Picked Up');
    }

	public function kiosk(){
        return $this->belongsTo(KioskDevice::class,'kiosk_id','id');
    }
	public function reservation(){
        return $this->belongsTo(StoreReservation::class,'parent_id');
    }
}
