<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationServeRequest extends Model
{
    use HasFactory;

    protected $fillable = ['reservation_id', 'table_id', 'serve_request_id', 'is_served'];

	public function serve_request() {
	    return $this->belongsTo(ServeRequest::class, 'serve_request_id');
    }

	public function reservation()
	{
		return $this->belongsTo(StoreReservation::class, 'reservation_id');
	}
}
