<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ReservationTable extends Model
{
	protected $fillable = [
		'reservation_id',
		'table_id'
	];

	protected static function boot(){

        parent::boot();
	     static::created(function($model)
	     {
	         $msg = 'Store Reservation table assigned:(ID : '.$model->id.') res_id: '.$model->reservation_id. ' table_id: '.$model->table_id;
	         Log::info($msg);
	     });

	     static::deleted(function($model)
	     {
		     $msg = 'Store Reservation table deleted:(ID : '.$model->id.') res_id: '.$model->reservation_id. ' table_id: '
			     .$model->table_id;
	         Log::info($msg);
	     });

	     static::saved(function($model)
	     {
	         $dirty = $model->getDirty();
	         if (!empty($dirty)) {
	             foreach ($dirty as $field => $newdata) {
	                 $olddata = $model->getRawOriginal($field);
	                 $oldData['old'][$field] = $olddata;
	                 $newData['new'][$field] = $newdata;
	             }
                 $user=getUserDetail();
	             $msg = 'Store Reservation table Updated: '.$user['role'].' ('.$user['name'].'-'.$user['id'].')'.' updated (ID : '.$model->id.') '.print_r($oldData, true).' => '.print_r($newData, true).'';
	             Log::info($msg);
	         }
	     });
    }
    
	public function table()
	{
		return $this->belongsTo(Table::class, 'table_id');
	}

	public function reservation()
	{
		return $this->belongsTo(StoreReservation::class, 'reservation_id');
	}
}
