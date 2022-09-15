<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class StoreReservationHistory extends Model
{

    protected $table = 'store_reservation_history';
    protected $fillable = [
        'reservation_id',
        'comment',
        'status',
        'user_id',
    ];

    protected static function boot()
    {
        parent::boot();
        static::created(function($model)
        {
            $store_id = isset($model->reservation->store->id) ? $model->reservation->store->id : 'NULL';
            $store_name = isset($model->reservation->store->store_name) ? $model->reservation->store->store_name : 'NULL';
            $user=getUserDetail();
            if (auth()->check()) {
                $msg = 'Store Reservation History Created: '.$user['role'].' ('.$user['name'].'-'.$user['id'].') created a reservation history for a store '.$store_name.' | store id-'.$store_id.' | id: '.$model->id.' | reservation_id: '.$model->reservation_id.' | comment: '.$model->comment.' | status: '.$model->status.'';
             } else {
                $msg = 'Store Reservation History Created: Store customer created a reservation history for a store '.$store_name.' | store id-'.$store_id.' | id: '.$model->id.' | reservation_id: '.$model->reservation_id.' | comment: '.$model->comment.' | status: '.$model->status.'';
            }
            Log::info($msg);
        });

        static::deleted(function($model)
        {
            $user=getUserDetail();
            $store_id = isset($model->reservation->store->id) ? $model->reservation->store->id : 'NULL';
            $store_name = isset($model->reservation->store->store_name) ? $model->reservation->store->store_name : 'NULL';
            $msg = 'Store Reservation History Deleted: '.$user['role'].' ('.$user['name'].'-'.$user['id'].') deleted a reservation history for a store '.$store_name.' | store id-'.$store_id.' | id: '.$model->id.' | reservation_id: '.$model->reservation_id.' | comment: '.$model->comment.' | status: '.$model->status.'';
            Log::info($msg);
        });
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function reservation()
    {
        return $this->hasOne(StoreReservation::class, 'id', 'reservation_id');
    }

}
