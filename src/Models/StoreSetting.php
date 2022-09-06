<?php

namespace  Weboccult\EatcardReservation\Models;

use  Weboccult\EatcardReservation\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class StoreSetting extends Model
{
    protected $fillable = [
        'store_id',
        'default_printer_name',
        'is_print_exclude_email',
        'is_print_category',
        'is_household_check',
        'household_check_msg',
        'household_check_required',
        'is_multi_household_check',
        'multi_household_check_msg',
        'display_custom_slot',
        'double_height',
        'double_width',
        'is_print_split',
        'is_print_cart_add',
        'print_custom_text',
        'is_print_product',
        'additional_fee',
        'is_online_payment',
        'is_pin',
        'enable_notification_sound',
        'login_pin_required',
        'discount_tags',
        'add_print_categories',
        'pre_order_name'
    ];

    protected $appends = ['user_additional_fee'];


	public function store()
	{
		return $this->hasOne(Store::class, 'id', 'store_id');
	}

	public function getUserAdditionalFeeAttribute()
	{
        return number_format((float)$this->additional_fee, 2, ',', '');
    }
}
