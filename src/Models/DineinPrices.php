<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DineinPrices extends Model
{

    use SoftDeletes;

    protected $fillable = [
        'store_id',
        'dinein_category_id',
        'name',
        'price',
        'child_name',
        'child_price',
        'child_name_2',
        'child_price_2',
        'is_per_year',
        'min_age',
        'max_age',
        'image',
        'description',
        'meal_type',
        'al_a_carte_off',
        'adults_untill_id',
        'kids_untill_id',
        'kids2_untill_id'
    ];
    protected $appends = ['image_url', 'user_price', 'user_child_price', 'user_child_price_2'];

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return getS3File($this->image);
        } else {
            return asset('images/no_image.png');
        }
    }
    public function getUserPriceAttribute()
    {
        return number_format((float)$this->price, 2, ',', '');
    }
    public function getUserChildPriceAttribute()
    {
        return number_format((float)$this->child_price, 2, ',', '');
    }
    public function getUserChildPrice2Attribute()
    {
        return number_format((float)$this->child_price_2, 2, ',', '');
    }

    public function dineinCategory()
    {
        return $this->belongsTo(DineinPriceCategory::class, 'dinein_category_id', 'id');
    }

    public function dynamicPrices()
    {
        return $this->hasMany(DineinPriceClassChildPrice::class, 'dinein_price_id');
    }

    public function meal()
    {
        return $this->belongsTo(Meal::class, 'meal_type');
    }
}
