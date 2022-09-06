<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;

class DineinPriceCategory extends Model
{
    protected $fillable = [
        'store_id',
        'category_name',
        'from_day',
        'to_day',
        'from_time',
        'to_time',
    ];
    public function prices()
    {
        return $this->hasMany(DineinPrices::class, 'dinein_category_id');
    }

    public function ayceSettings()
    {
        return $this->hasOne(AllYouCanEatClassSetting::class, 'dinein_category_id');
    }

    public function dineinAyceHideCategories()
    {
        return $this->hasMany(DineinAyceHideCategory::class, 'dinein_category_id');
    }
}
