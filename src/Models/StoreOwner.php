<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;

class StoreOwner extends Model
{
    protected $table = 'store_owners';
    protected $fillable = [  
    			'store_id',
				'user_id',
				];

    public $timestamps = false;


	public function user()
	{
		return $this->hasOne('App\Models\User','id','user_id');
	}

	public function store()
	{
		return $this->hasOne(Store::class,'id','store_id');
	}
}
