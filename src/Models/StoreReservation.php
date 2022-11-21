<?php

namespace Weboccult\EatcardReservation\Models;

use App\Services\PushNotification\OneSignal\Facades\OneSignalService;
use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Thread;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use function Weboccult\EatcardReservation\Helper\getDutchDate;
use function Weboccult\EatcardReservation\Helper\getDutchDateTable;

class StoreReservation extends Model
{
    protected $table = 'store_reservations';
    protected $fillable = [
        'store_id',
        'slot_id',
        'res_date',
        'from_time',
        'user_id',
        'status',
        'payment_type',
        'company',
        // form fields
        'person',
        'voornaam',
        'achternaam',
        'email',
        'geboortedatum',
        'gsm_no',
        'meal_type',
        'gastnaam',
        'gast_email',
        'comments',
        'gastpin',
        'reservation_id',
//        OTher
        'reservation_sent',
        'thread_id',
        'cancelled_by', // who  cancelled reservations
        'is_canceled', // if 1 means showing reservations
        'is_show', // for chat only hide entry api , 1 = show , 0 = not show
        'group_id',
        'is_seated',
        'by_owner',
        'res_origin',
        'occasions',
        'occasions_name',
        'check_in_points',
        'is_points_claimed',
        'check_in_points_expired',
        'res_time',
        'is_checkout',
        'end_time',
        'checked_in_at',
        'is_review_mail_send',
        'method',
        'mollie_payment_id',
        'paid_on',
        'payment_status',
        'local_payment_status',
        'reservation_type',
        'total_price',
        'coupon_price',
        'gift_purchase_id',
        'original_total_price',
        'section_id',
        'is_refunded',
        'gift_card_code',
        'refund_price',
        'refund_price_date',
        'is_dine_in',
        'serve_req',
        'owner_comments',
        'is_household_check',
        'household_person',
        'payment_method_type',
        'multisafe_payment_id',
        'is_qr_scan',
        'is_second_scan',
        'dinein_price_id',
        'all_you_eat_data',
        'household_person',
        'is_google_res',
        'created_from',
        'is_manually_cancelled',
        'res_status',
        'slot_model',
        'title_prefix',
        'is_company_selected',
        'country_code',
        'birth_date',
    ];
    protected $appends = ['dutch_date','reservation_date'/*, 'res_dutch_date'*/];


    protected static function boot()
    {
        parent::boot();
        static::created(function ($model) {
            $user=getUserDetail();
            $msg = 'Store Reservation Created: (' . $user['role'] . ' (' . $user['name'] . '-' . $user['id'] . ') created a Store Reservation ' . $model->voornaam . ' ' . $model->achternaam . ' | id-' . $model->id . ' for the store ' . $model->store_id . '';
            Log::info($msg . ', IP address : ' . request()->ip(). ', browser : '. request()->header('User-Agent'));
        });
        static::deleted(function ($model) {
            $user=getUserDetail();
            $msg = 'Store Reservation Deleted: (' . $user['role'] . ' (' . $user['name'] . '-' . $user['id'] . ') delete a Store Reservation ' . $model->voornaam . ' ' . $model->achternaam . ' | id-' . $model->id . ' for the store ' . $model->store_id . '';
            Log::info($msg);
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
                $msg = 'Store Reservation Updated: (' . $user['role'] . ' (' . $user['name'] . '-' . $user['id'] . ') updated a Store Reservation ' . $model->voornaam . ' ' . $model->achternaam . ' | id-' . $model->id . ' for the store ' . $model->store_id . '. ' . print_r($oldData, true) . ' => ' . print_r($newData, true) . '';
                Log::info($msg);
            }
        });
    }

	public function getReservationDateAttribute()
	{
		return $this->getRawOriginal('res_date');
	}

    public function scopeSearch($query, $search)
    {
        $search = trim($search); // Clean up white space

        return $query->where(function ($query) use ($search) {
            $query->where('reservation_id', 'LIKE', "%$search%")
                ->orWhere('voornaam', 'LIKE', "%$search%")
                ->orWhere('achternaam', 'LIKE', "%$search%")
                ->orWhere(\DB::raw('CONCAT_WS(" ", voornaam, achternaam)'), 'LIKE', "%$search%")
                ->orWhere('email', 'LIKE', "%$search%");

        });
    }

	public function getResDutchDateAttribute()
	{
		return getDutchDateTable($this->res_date);
	}

	public function getResDateAttribute($value)
	{
		return getDutchDate($value);
	}
	public function getDutchDateAttribute($value)
	{
		return getDutchDateTable($this->created_at);
	}

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function store_id()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function reservation_history()
    {
        return $this->hasMany(StoreReservationHistory::class, 'reservation_id');
    }

    public function meal()
    {
        return $this->hasOne(Meal::class, 'id', 'meal_type');
    }
    public function tables()
    {
        return $this->hasMany(ReservationTable::class, 'reservation_id');
    }

    public function thread()
    {
        $data = $this->hasManyThrough(Message::class, Thread::class, 'id', 'id', 'thread_id', 'id')->with(['participants' => function($q) {
            $q->where('user_id', auth()->id());
        }])->get();

        $new =  $data->filter(function ($message) use ($data) {
            return $message->updated_at->gt($message->participants[0]->last_read);
        });
        return $new;

//        $data = $this->hasOne(Thread::class, 'id', 'thread_id')->get();
//        foreach ($data->get() as $g) {
//            dd($g->UnreadForUser(auth()->id())->get());
////            dd($g->userUnreadMessagesCount(auth()->id()));
//             $g->unread_msg_count = $g->userUnreadMessagesCount(auth()->id());
//        }
//        return $data;
    }
    public function messages()
    {
        return $this->hasMany(Message::class, 'thread_id', 'thread_id');
    }

    public function reservation_serve_requests() {
        return $this->hasMany(ReservationServeRequest::class, 'reservation_id');
    }


}
