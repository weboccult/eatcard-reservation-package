<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class MultiSafePay extends Model
{
    protected $fillable = [
        'store_id',
        'is_check_multisafe',
        'api_key',
        'MAESTRO',
        'BANKTRANS',
        'ALIPAY',
        'DIRECTBANK',
        'GIROPAY',
        'MISTERCASH',
        'EPS',
        'IDEAL',
        'TRUSTLY',
        'MASTERCARD',
        'APPLEPAY',
        'VISA'
    ];

    public function store()
    {
        return $this->hasOne(Store::class, 'id', 'store_id');
    }
}
