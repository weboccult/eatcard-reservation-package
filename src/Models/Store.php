<?php

namespace Weboccult\EatcardReservation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

    protected $table = 'stores';
    protected $fillable = [

        'created_by',
        'store_name',
        'store_slug',
        'company_name',
        'kvk_number',
        'btw_number',
        'address',
        'zipcode',
        'store_street',
        'store_region',
        'store_country',
        'store_email',
        'store_phone',
        'point_value',
        'per_point',
        'is_inactive_mail',
        'inactive_mail_message',
        'is_birthday_notification',
        'birthday_notification_message',
        'package_id',
        'status',
        'page_logo',
        'banner_img',
        'store_city',
        'inactive_days',
        'privacy_url',
        'is_reservation',
        'max_per_table',
        'reservation_msg',
        'reservation_off',
        'booking_off_time',
        'is_booking_enable',
        'booking_till_bfre_msg',
        'booking_till_aftr_msg',
        'free_point_on_reg',
        'reminder_time',
        'is_email_reminder_enable',
        'booking_default_msg',
        'on_date_default_msg',
        'reservation_off_chkbx',
        'auto_approval',
        'auto_approve_members',
        'auto_approve_condition',// for checkbox feature on pause reservation
        'website_url',
        'is_check_mailchimp',
        'mailchimp_api_key',
        'mailchimp_list_id',
        'display_canceled_res',
        'expired_on', // store expired date,
        'is_loyalty_enabled',
        'is_table_mgt_enabled',
        'person_per_point',
        'free_point_on_res',
        'is_check_in_mail',
        'is_claim_point_expire',
        'claim_point_expire_hour',
        'is_auto_claim_points',
        'is_last_round',
        'last_round_min',
        'is_bill_time',
        'bill_time_min',
        'booking_frame_color',
        'double_check_email',
        'on_res_success_msg',
        'is_booking_till_bfre_msg',
        'is_booking_default_msg',
        'is_date_default_msg',
        'is_res_success_msg',
        'is_user_cancel_res',
        'user_cancel_res_time',
        'owner_max_per_table',
        'display_not_checkin_res',
        'is_smart_res',
        'is_smart_fit',
        'auto_approve_booking_with_comment',
        'allow_auto_group',
        'allow_review',
        'review_hour',
        'facebook_url',
        'instagram_url',
        'store_latitude',
        'store_longitude',
        'google_place_id',
        'is_check_takeaway',
        'is_auto_print_takeaway',
        'is_check_mollie',
        'mollie_api_key',
        'is_manual_check',
        'manual_check_msg',
        'manual_check_required',
        'display_end_time',
        'display_section_frontend',
        'takeaway_review_hour',
        'allow_butler',
        'butler_hour',
        'is_reservation_info_enable',
        'reservation_info',
        'allow_qr_code',
        'qr_code',
        'is_notification',
        'is_take_out',
        'is_dine_in',
        'is_cash_payment',
        'is_pin_payment',
        'app_pos_print',
        'is_kiosk_enable',
        'kiosk_data',
        'butler_data',
        'is_menu_enable',
        'ignore_arrangement_time',
        'ignore_table_availability'
    ];

    protected $appends = [
        /*'total_cards_count', 'total_activated_cards_count', 'email_page_logo'*/
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function storeSetting()
    {
        return $this->hasOne(StoreSetting::class, 'store_id', 'id');
    }

    public function storeButler()
    {
        return $this->hasOne(StoreButler::class, 'store_id', 'id');
    }

    public function multiSafe()
    {
        return $this->hasOne(MultiSafePay::class, 'store_id', 'id');
    }

	/**
	 * @param $store : Object
	 * @return array
	 * @Description Get store off dates
	 */
	public function getReservationOffDates($store)
    {
        if ($store->reservation_off != 0) {

            $today = Carbon::now();
            $from = Carbon::createFromDate($today->format('Y'), $today->format('m'), $today->format('d'));

            $tillDay = Carbon::now()->addDay($store->reservation_off - 1);
            $to = Carbon::createFromDate($tillDay->format('Y'), $tillDay->format('m'), $tillDay->format('d'));

            return $this->generateDateRange($from, $to);
        }
        return [];
    }

    private function generateDateRange(Carbon $start_date, Carbon $end_date)
    {
        $dates = [];
        for ($date = $start_date; $date->lte($end_date); $date->addDay()) {
            $dates[] = $date->format('Y-m-d');
        }
        return $dates;
    }

}
