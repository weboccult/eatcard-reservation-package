<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;

class GiftPurchaseOrder extends Model
{
    protected $fillable = [
        'order_id',
        'store_id',
        'user_id',
        'gift_card_id',
        'qr_code',
        'first_name',
        'last_name',
        'email',
        'contact_no',
        'is_friend_send',
        'is_specific_date',
        'friend_first_name',
        'friend_last_name',
        'friend_email',
        'friend_comment',
        'date',
        'time',
        'quantity',
        'total_price',
        'remaining_price',
        'expire_at',
        'is_multi_usage',
        'status',
        'payment_method_type',
        'method',
        'mollie_payment_id',
        'multisafe_payment_id',
        'ccv_payment_ref',
        'ccv_customer_receipt',
        'checkout_no',
        'paid_on',
    ];

    protected $appends = ['full_name', 'friend_full_name', 'total_user_price'];

    public function getFullNameAttribute()
    {
        return $this->first_name. ' '. $this->last_name;
    }

    public function getFriendFullNameAttribute()
    {
        return $this->friend_first_name. ' '. $this->friend_last_name;
    }

    public function getTotalUserPriceAttribute()
    {
        return number_format((float)$this->total_price, 2, ',', '');
    }
}
