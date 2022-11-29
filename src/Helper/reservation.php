<?php

namespace Weboccult\EatcardReservation\Helper;

use Mollie\Laravel\Facades\Mollie;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Weboccult\EatcardReservation\Classes\Multisafe;
use Weboccult\EatcardReservation\EatcardReservation;
use Weboccult\EatcardReservation\Models\CancelReservation;
use Weboccult\EatcardReservation\Models\DiningArea;
use Weboccult\EatcardReservation\Models\GiftPurchaseOrder;
use Weboccult\EatcardReservation\Models\Meal;
use Weboccult\EatcardReservation\Models\Order;
use Weboccult\EatcardReservation\Models\ReservationJob;
use Weboccult\EatcardReservation\Models\ReservationTable;
use Weboccult\EatcardReservation\Models\Store;
use Weboccult\EatcardReservation\Models\StoreOwner;
use Weboccult\EatcardReservation\Models\StoreReservation;
use Weboccult\EatcardReservation\Models\StoreSlot;
use Weboccult\EatcardReservation\Models\StoreSlotModified;
use Weboccult\EatcardReservation\Models\StoreWeekDay;
use Weboccult\EatcardReservation\Models\Table;
use Cmgmyr\Messenger\Models\Thread;
use Cmgmyr\Messenger\Models\Participant;
use Illuminate\Support\Facades\Redis as LRedis;


if (!function_exists('eatcardReservation')) {
    /**
     *
     * Access EatcardReservation through Helper Function
     *
     * @return EatcardReservation
     */
    function eatcardReservation()
    {
        return app(EatcardReservation::class);
    }
}

if (!function_exists('eatcardgetSlots')) {
    /**
     * @return EatcardReservation
     */
    function eatcardgetSlots()
    {
        return app(EatcardReservation::class);
    }
}

if (!function_exists('specificDateSlots')) {
    /**
     * @param $store
     * @param $specific_date
     * @param $slot_time
     * @param $slot_model
     * @return array
     * @descriptin Fetch the Specific Date Wise Slots only
     */
    function specificDateSlots($store, $specific_date, $slot_time, $slot_model)
    {
        $activeSlots = [];
        $dateMealSlotsData = [];

        //Based on selected date fetch slots
        $dateMealSlotsData = StoreSlotModified::query()
            ->where('store_id', $store->id)
            ->where('is_day_meal', 0)
            ->where('store_date', $specific_date)
            ->where('is_available', 1)
//            ->when($specific_date == Carbon::now()->format('Y-m-d'),function ($q){
//              $q->where('is_slot_disabled', 0);
//            })
            ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id');

        if (!is_null($slot_time)) {
            $dateMealSlotsData = $dateMealSlotsData->where('from_time', $slot_time)->get()->toArray();
        } else {
            $dateMealSlotsData = $dateMealSlotsData->orderBy('from_time', 'ASC')->get()->toArray();
        }

        if (is_null($slot_model)) {
            $dateMealSlotsData = superUnique($dateMealSlotsData, 'from_time');
        }

        $meals = Meal::query()
            ->where('store_id', $store->id)
            ->where('status', 1)
            ->get();

        $storeWeekMeal = [];
        $storeIsMeal = [];
        $dateDayMealSlots = [];

        foreach ($meals as $meal) {
            if (!$meal->is_meal_res && $meal->is_week_meal_res) {
                $storeWeekMeal[] = $meal->id;
            } elseif ($meal->is_meal_res) {
                $storeIsMeal[] = $meal->id;
            }
        }

        foreach ($storeIsMeal as $dateMeal) {
            $dateDayMealSlots = StoreSlotModified::query()
                ->where('store_id', $store->id)
                ->where('meal_id', $dateMeal)
                ->where('store_date', $specific_date)
                ->when($specific_date == Carbon::now()->format('Y-m-d'),function ($q){
                    $q->where('is_slot_disabled', 0);
                })
                ->where('is_available', 1)
                ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id');

            if(empty($dateDayMealSlots)){
                continue;
            }

            if (!is_null($slot_time)) {
                $dateDayMealSlots = $dateDayMealSlots->where('from_time', $slot_time)->get()
                    ->toArray();
            } else {
                $dateDayMealSlots = $dateDayMealSlots->orderBy('from_time', 'ASC')->get()
                    ->toArray();

            }
            if ($dateDayMealSlots == null) {
                $dateDayMealSlots = [];
            } else {
                $dateMealSlotsData = superUnique($dateDayMealSlots, 'from_time');
            }
        }
        $activeSlots = $dateMealSlotsData;
        Log::info("Reservation : Specific Date wise slots - ", $activeSlots);
        return $activeSlots;
    }
}

if (!function_exists('specificDaySlots')) {
    /**
     * @param $store
     * @param $getDayFromUser
     * @param null $slot_time
     * @return array
     * @description Fetch the Selected Specific day wise slots
     */
    function specificDaySlots($store, $getDayFromUser, $slot_time = null,$specific_date = null)
    {
        $activeSlots = [];

        //Specific Day wise slots
        $daySlot = StoreSlot::query()
            ->where('store_id', $store->id)
            ->where('store_weekdays_id', '!=', null)
            ->whereHas('store_weekday', function ($q) use($getDayFromUser) {
                $q->where('is_active', 1)
                    ->where('name', $getDayFromUser)
                    ->where('is_week_day_meal', 0);
            })
//            ->when(!empty($specific_date) && $specific_date == Carbon::now()->format('Y-m-d'),function ($q){
//                $q->where('is_slot_disabled', 0);
//            })
            ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id');

        if (!is_null($slot_time)) {
            $daySlot = $daySlot->where('from_time', $slot_time)->get()->toArray();
        } else {
            $daySlot = $daySlot->orderBy('from_time', 'ASC')->get()->toArray();
        }
        $activeSlots = superUnique($daySlot, 'from_time');
        Log::info("Reservation : Specific Day wise slots - ", $activeSlots);
        return $activeSlots;
    }
}

if (!function_exists('generalSlots')) {
    /**
     * @param $store
     * @param null $slot_time
     * @return array
     * @Description Fetch the all General sots from admin
     */
    function generalSlots($store, $slot_time = null, $specific_date = null)
    {
        $activeSlots = [];
        $generalSlot = StoreSlot::query()
            ->where('store_id', $store->id)
//            ->when(!empty($specific_date) && $specific_date == Carbon::now()->format('Y-m-d'),function ($q){
//                $q->where('is_slot_disabled', 0);
//            })
            ->doesntHave('store_weekday')
            ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id');

        if (!is_null($slot_time)) {
            $generalSlot = $generalSlot->where('from_time', $slot_time)->get()->toArray();
        } else {
            $generalSlot = $generalSlot->orderBy('from_time', 'ASC')->get()->toArray();
        }

        $activeSlots = superUnique($generalSlot, 'from_time');
        Log::info("Reservation : General slots - " . json_encode($activeSlots) . " | Store Id " . $store->id . " | Store Slug " . $store->store_slug);
        return $activeSlots;
    }
}

if (!function_exists('mealSlots')) {
    /**
     * @param $store
     * @return array
     * @description Fetch on meal available slots
     */
    function mealSlots($store, $slot_time = null, $specific_date = null)
    {
        $activeSlots = [];
        $storeIsMeal = [];
        $storeWeekMeal = [];

        $meals = Meal::query()
            ->where('store_id', $store->id)
            ->where('status', 1)
            ->get();

        foreach ($meals as $meal) {
            if (!$meal->is_meal_res && $meal->is_week_meal_res) {
                $storeWeekMeal[] = $meal->id;
                Log::info("storeWeekMeal IDS : " , [$meal->id]);
            } elseif ($meal->is_meal_res) {
                $storeIsMeal[] = $meal->id;
            }
        }
        if ($storeWeekMeal == [] && $storeIsMeal == []) {
            Log::info("No any meal for now " . " | Store Id " . $store->id . " | Store Slug " . $store->store_slug);
            return [];
        }
        $getDayFromDate = date('l', strtotime($specific_date));
        $dateDayMealSlots = [];
        foreach ($storeWeekMeal as $weekMeal) {
            $dateCurrentDayMealSlots = StoreSlot::query()
                ->where('store_id', $store->id)
                ->where('meal_id', $weekMeal)
//                ->when(!empty($specific_date) && $specific_date == Carbon::now()->format('Y-m-d'),function ($q){
//                    $q->where('is_slot_disabled', 0);
//                })
                ->whereHas('store_weekday', function ($q1) use($getDayFromDate){
                    $q1->where('name', $getDayFromDate)
                    ->where('is_week_day_meal', 1)
                    ->where('is_active', 1);
                })
                ->where('store_weekdays_id', '!=', null)
                ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id');

            if (!is_null($slot_time)) {
                $dateCurrentDayMealSlots = $dateCurrentDayMealSlots->where('from_time', $slot_time)->get()->toArray();
            } else {
                $dateCurrentDayMealSlots = $dateCurrentDayMealSlots->orderBy('from_time', 'ASC')->get()->toArray();
            }

            if (!empty($dateCurrentDayMealSlots)) {
                if(empty($dateDayMealSlots)){
                    $dateDayMealSlots = superUnique($dateCurrentDayMealSlots, 'from_time');
                } else {
                    $dateDayMealSlots = array_merge($dateDayMealSlots, superUnique($dateCurrentDayMealSlots, 'from_time'));
                }
            }
        }
        if (!empty($dateDayMealSlots)) {
            $activeSlots = $dateDayMealSlots;
            Log::info("Reservation : Admin setting add meals slots list - ", $activeSlots);
        }
        return $activeSlots;
    }
}

if (!function_exists('dataModelSlots')) {
    /**
     * @param $data_model
     * @param $slot_id
     * @return \Weboccult\EatcardReservation\Facade\EatcardReservation
     * @description On specific slot model based return slot
     */
    function dataModelSlots($data_model, $from_time, $res_date, $meal_id)
    {
	    $checkDay = Carbon::parse($res_date)->format('l');

	    //Check store slot and week day wise active or not
        if ($data_model == 'StoreSlot') {
	        Log::info("Store slot slots");
	        $slot = StoreSlot::query()
		        ->where('from_time', $from_time)
		        ->where('meal_id', $meal_id)
		        ->whereNotNull('store_weekdays_id')
		        ->first();
	        if(!empty($slot)){
		        Log::info("Store week day slot details " . $slot);
		        $store_weekday = StoreWeekDay::query()
			        ->where('name', $checkDay)
			        ->find($slot->store_weekdays_id);
		        Log::info("Store week day info : " . $store_weekday);
		        if ($store_weekday && $store_weekday->is_active != 1) {
			        Log::info("Store weekday is not active | dataModelSlots function");
			        return [
				        'status' => 'error',
				        'error' => 'error_weekday_frame',
				        'code'  =>  400,
			        ];
		        }
	        }else{
		        $slot = StoreSlot::query()
			        ->where('from_time', $from_time)
			        ->where('meal_id', $meal_id)
			        ->whereNull('store_weekdays_id')
			        ->first();
	        }
        } else {
	        Log::info("Store slot modified slots");
	        $slot = StoreSlotModified::query()
		        ->where('from_time', $from_time)
		        ->where('meal_id', $meal_id)
		        ->first();
        }
        return $slot;
    }
}
if (!function_exists('isValidReservation')) {
    /**
     * @param $data
     * @param $store
     * @param $slot
     * @return bool
     * @description Check modified slot available or not based on admin setting
     */
    function isValidReservation($data, $store, $slot)
    {

        $meal = Meal::query()->where('id', $data['meal_type'])->first();
        $day_meal = ($meal->is_meal_res) ? 1 : 0;
        $slot_modified = StoreSlotModified::query()->where('store_id', $store->id)
            ->where('store_date', $data['res_date'])
            ->where('from_time', $slot->from_time)
            ->where('is_day_meal', $day_meal)
            ->first();
        if ($slot_modified && $slot_modified->is_available != 1) { // slot time is off for reservation
            return false;
        } else {
            return true;
        }
    }
}

if (!function_exists('getAnotherMeetingUsingIgnoringArrangementTime')) {
    /**
     * @param $reservation
     * @param $item
     * @return bool
     * @description Check From time with reservation details time
     */
    function getAnotherMeetingUsingIgnoringArrangementTime($reservation, $item)
    {
        return strtotime($item->from_time) == strtotime($reservation->from_time);
    }
}

if (!function_exists('generateRandomNumberV2')) {
    /**
     * @param int $length
     * @return int
     * @Description Generate Random number value
     */
    function generateRandomNumberV2($length = 4)
    {
        return rand(pow(10, $length - 1) - 1, pow(10, $length) - 1);
    }
}

if (!function_exists('generateReservationId')) {
    /**
     * @return string
     * @description Generate Random reservation ID
     */
    function generateReservationId()
    {
        $id = rand(1111111, 9999999);
        $id = (string)$id; //convert it into string because of query optimize, here in db reservation_id datatype is a string
        $exist = StoreReservation::query()->where('reservation_id', $id)->first();
        if ($exist) {
            return generateReservationId();
        } else {
            return $id;
        }
    }
}

if (!function_exists('createNewReservation')) {
    /**
     * @param $meal
     * @param $data
     * @param $data_model
     * @param $store
     * @return array
     * @description Create a new Reservation with all fetch data
     */
    function createNewReservation($meal, $data, $data_model, $store)
    {
        if ($storeNewReservation = StoreReservation::query()
            ->create($data)) {
            $data['data_model'] = $data_model;
            $reservation_data['store_id'] = $storeNewReservation->store_id;
            $reservation_data['reservation_id'] = $storeNewReservation->id;
            $reservation_data['attempt'] = 0;
            $reservation_data['reservation_front_data'] = json_encode($data, true);
            $reservation_data['title_prefix'] = $data['title_prefix'];

            $new_reservation = ReservationJob::query()
                ->where('attempt', 2)
                ->where('in_queue', 0)
                ->where('is_completed', 0)
                ->where('is_failed', 1)
                ->get()
                ->first();
            if (!isset($new_reservation)) {
                $new_reservation = ReservationJob::query()
                    ->where('attempt', 2)
                    ->where('in_queue', 0)
                    ->where('is_completed', 0)
                    ->where('is_failed', 1)
                    ->get()
                    ->first();
            }
//            $time_difference = 0;
//            if (isset($first_reservation->created_at)) {
//                $current_time = Carbon::now();
//                $end_time = Carbon::parse($first_reservation->created_at);
//                $time_difference = $current_time->diffInSeconds($end_time);
//            }
//            if ($time_difference > 90) {
//                $check_time = $this->reservation_job
//                    ->whereNotNull('id')
//                    ->update(['is_failed' => 1, 'attempt' => 2]);
//                $normal_functionality = true;
//            }
            //Create entry in reservation job table
            $reservation_job = ReservationJob::query()->create($reservation_data);
            StoreReservation::query()->where('id', $storeNewReservation->id)->update(['gift_card_code' => $data['qr_code']]);

            //Get Reservation Status Check
            Log::info("Get reservation status check - start");

            $reservationStatusArrayCheck = [1, 2, 3, 4, 5];
            for ($i = 0; $i < 5; $i++) {
                $reservationJobTotalCount = ReservationJob::query()->where('id', $reservation_job->id)->count();
                Log::info("Reservation Job Total Count number : " . $reservationJobTotalCount);
                if ($reservationJobTotalCount > 0) {
                    sleep($reservationStatusArrayCheck[$i]);
                } else {
                    break;
                }
            }
            $count = 1;
            do {
                Log::info('do while start for : Reservation Status');
                $storeNewReservation = StoreReservation::query()->where('id', $storeNewReservation->id)->first();
                if ($storeNewReservation->res_status != null) {
                    $count = 4;
                } else {
                    sleep(3);
                    $count++;
                }
            } while (($storeNewReservation->res_status == null || $storeNewReservation->res_status == '') && $count <= 3);
            Log::info('do while end for : Reservation Status');

            if ($storeNewReservation->res_status == null || $storeNewReservation->res_status == '') {
                Log::info('storeNewReservation->res_status is null : Reservation ID ' . $storeNewReservation->id);
                StoreReservation::query()->where('id', $storeNewReservation->id)->update(['status' => 'declined', 'is_manually_cancelled' => 2]);
                return [
                    'status' => 'error',
                    'message' => 'Sorry selected slot is not available.Please try another time slot',
                    'error' => 'slot_allocated',
                    'code' => 400
                ];
            }
            if ($storeNewReservation->res_status != '' && $storeNewReservation->res_status != null) {
                if ($storeNewReservation->res_status == 'failed') {
                    Log::info('storeNewReservation->res_status is failed : Reservation ID ' . $storeNewReservation->id);
                    StoreReservation::query()->where('id', $storeNewReservation->id)->update(['status' => 'declined', 'is_manually_cancelled' => 2]);
                    return [
                        'status' => 'error',
                        'message' => 'Sorry selected slot is not available.Please try another time slot',
                        'error' => 'slot_allocated',
                        'code' => 400
                    ];
                }
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Sorry selected slot is not available.Please try another time slot',
                    'error' => 'slot_allocated',
                    'code' => 400
                ];
            }

            Log::info("Socket for New Reservation");
            //Publish socket for new reservation
            if (!$meal->price && $meal->payment_type != 1 && $meal->payment_type != 3) {
                sendResWebNotification($storeNewReservation->id, $storeNewReservation->store_id);
            } else {
                sendResWebNotification($storeNewReservation->id, $storeNewReservation->store_id);
            }
            Log::info("sendResWebNotification END ");

            $thread = Thread::query()->create([
                'subject' => 'reservation'
            ]);
            $storeNewReservation->update(['thread_id' => $thread->id]);
            $ownerId = ($storeNewReservation->store) ? $storeNewReservation->store->created_by : 0;
            $owner = StoreOwner::query()->where('store_id', $storeNewReservation->store_id)->first();
            if ($owner) {
                $ownerId = $owner->user_id;
            }
            Participant::query()->insert([
                [
                    'thread_id' => $thread->id,
                    'user_id' => $storeNewReservation->user_id ? $storeNewReservation->user_id : 0,
                    'last_read' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
                [
                    'thread_id' => $thread->id,
                    'user_id' => $ownerId,
                    'last_read' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            ]);
            $reservation_job->update(['reservation_id' => $data['reservation_id']]);

            if(isset($data['total_price'])){
                if ($storeNewReservation->res_status == 'success' && $data['total_price'] == 0) {
                    Log::info("Reservation status success and total price zero then payment url null in response");
                    $data['payment_method_type'] = '';
                    $data['method'] = '';
                }
            }

            if($storeNewReservation->res_status == 'success' && isset($data['qr_code'])){
                //Fetch the Applied gift card details for calculation
                $giftCardPrice = GiftPurchaseOrder::query()
                    ->where('qr_code', $data['qr_code'])
                    ->whereDate('expire_at', '>', Carbon::now()->toDateString())
                    ->first();
                $totalPrice = 0;
                if (($giftCardPrice->is_multi_usage) == 1) {
                    $totalPrice = (float)$giftCardPrice->remaining_price - (float)$meal->price;
                }
                Log::info("Updated remaining price : " . $totalPrice);
                $giftCardPrice->update(['remaining_price' => $totalPrice]);
            }

            //Payment method type and method defines with cancel,notify and redirect URL
            if ($data['payment_method_type'] && $data['method'] && ($meal->payment_type == 1 || $meal->payment_type == 3) && $meal->price) {
                try {
                    if ($data['payment_method_type'] == 'mollie') {
                        Mollie::api()->setApiKey($store->mollie_api_key);
	                    $webhookUrl = webhookGenerator(env('MOLLIE_WEBHOOK').'/reservation/webhook/', [
		                    'id' => $storeNewReservation->id,
		                    'store_id' => $store->id,
	                    ], ['language' => $data['language']],env('MOLLIE_WEBHOOK'));

	                    $redirectUrl = webhookGenerator(env('MOLLIE_WEBHOOK').'/reservation/success-webhook/', [
		                    'id' => $storeNewReservation->id,
		                    'store_id' => $store->id,
	                    ], ['url' => $data['url'], 'language' => $data['language']],env('MOLLIE_WEBHOOK'));

                        $paymentPayloadData = [
	                        "amount" => [
		                        "currency" => "EUR",
		                        "value" => '' . number_format($storeNewReservation->total_price, 2, '.', '')
	                        ],
	                        'method' => $data['method'],
	                        "description" => "Order #" . $storeNewReservation->reservation_id,
	                        "redirectUrl" => $redirectUrl,
	                        "webhookUrl" => $webhookUrl,
	                        "metadata" => [
		                        "order_id" => $storeNewReservation->reservation_id,
	                        ],
                        ];
	                    Log::info('Mollie payment gateaway payload : '. json_encode($paymentPayloadData).' | Mollie key : ' .$store->mollie_api_key);
	                    $payment = Mollie::api()->payments()->create($paymentPayloadData);
                        $storeNewReservation->update(['mollie_payment_id' => $payment->id,/*, 'payment_status'    => 'pending'*/]);
	                    $paymentUrl = $payment->_links->checkout->href;
                    } else {

	                    $webhookUrl = webhookGenerator(env('MULTISAFE_WEBHOOK').'/reservation/webhook/', [
		                    'id' => $storeNewReservation->id,
		                    'store_id' => $store->id,
	                    ], ['language' => $data['language']],env('MULTISAFE_WEBHOOK'));

	                    $redirectUrl = webhookGenerator(env('MULTISAFE_WEBHOOK').'/reservation/success/', [
		                    'id' => $storeNewReservation->id,
		                    'store_id' => $store->id,
	                    ], ['url' => $data['url'], 'language' => $data['language']],env('MULTISAFE_WEBHOOK'));

	                    $cancelUrl = webhookGenerator(env('MULTISAFE_WEBHOOK').'/reservation/cancel/', [
		                    'id' => $storeNewReservation->id,
		                    'store_id' => $store->id,
	                    ], ['url' => $data['url'], 'language' => $data['language']],env('MULTISAFE_WEBHOOK'));


                        $data = [
                            'type' => $data['method'] == 'IDEAL' ? 'direct' : 'redirect',
                            'currency' => 'EUR',
                            'order_id' => $storeNewReservation->id . '-' . $storeNewReservation->reservation_id,
                            'amount' => (integer)((float)number_format($storeNewReservation->total_price, 2) * 100),
                            'gateway' => $data['method'],
                            'description' => "Order #" . $storeNewReservation->reservation_id,
                            'gateway_info' => [
                                'issuer_id' => isset($data['issuer_id']) && $data['method'] == 'IDEAL' ? $data['issuer_id'] : null,
                            ],
                            'payment_options' => [
                                'notification_url' => $webhookUrl,
                                'redirect_url' => $redirectUrl,
                                'cancel_url' => $cancelUrl,
                                'close_window' => true,
                            ]
                        ];
                        Log::info('Multisafe payload data : '. json_encode($data));
                        $multisafe = new Multisafe();
                        $payment = $multisafe->postOrder($store->multiSafe->api_key, $data);
                    }

                } catch (\Exception $e) {
                    Log::info('Booking reservation mollie error Message: => ' . json_encode($e->getMessage()) . ', Line : =>' . json_encode($e->getLine()));
                    return ['status' => 'error', 'message' => 'something_wrong_payment', 'error' => 'something_wrong_payment'];
                }
            } elseif ($data['payment_method_type'] == '' && $data['method'] == '') {
                $payment['payment_url'] = $data['url']. '?status=paid' . '&store=' . $data['store_slug'] . '&type=reservation' . '&reservationid=' . base64_encode($storeNewReservation->id) . '&language=' . $data['language'];
            }
            $new_reservation_data['id'] = $storeNewReservation->id;
            $new_reservation_data['payment'] = true;
            $new_reservation_data['payment_method_type'] = $data['payment_method_type'] ?? '';
            $new_reservation_data['payment_url'] = isset($paymentUrl) ? $paymentUrl : $payment['payment_url'];
            return $new_reservation_data;
        }
    }
}

if(!function_exists('webhookGenerator')) {
	/**
	 * @param string $path
	 * @param array|null $parameters
	 * @param array|null $queryParam
	 * @param string|null $system
	 *
	 * @return string
	 */
	function webhookGenerator(string $path, ?array $parameters, ?array $queryParam = [], string $system = null): string
	{
		$domain = $path.$parameters['id'].'/'.$parameters['store_id'];
		return $domain.buildQueryParams($queryParam);
	}
}

if (! function_exists('buildQueryParams')) {
	/**
	 * @param $params
	 *
	 * @return string
	 *
	 * @author Darshit Hedpara
	 */
	function buildQueryParams($params): string
	{
		if (! empty($params)) {
			$paramsJoined = [];
			foreach ($params as $param => $value) {
				$paramsJoined[] = "$param=$value";
			}

			return '?'.implode('&', $paramsJoined);
		} else {
			return '';
		}
	}
}

if (!function_exists('currentMonthDisabledDatesList')) {
    /**
     * @param $store
     * @param $selected_month
     * @return array
     * @description Fetch the selected month disable date list from storeSlotModified table
     */
    function currentMonthDisabledDatesList($store, $selected_month)
    {
	    $getDates = StoreSlotModified::query()
            ->where('store_id', $store->id)
            ->where('is_day_meal', 0)
            ->where('is_available', 0)
            ->whereRaw('MONTH(store_date) = ?', $selected_month)
	        ->pluck('store_date')->toArray();

	    $finalDisableDates = [];
	    foreach ($getDates as $disabledDate) {
		    if(!in_array($disabledDate, $finalDisableDates)) {
			    $finalDisableDates[] = $disabledDate;
		    }
	    }

        Log::info("Disable date list for manually disabled admin : ", $finalDisableDates);
        return $finalDisableDates;
    }
}

if (!function_exists('disableDayByAdmin')) {
    /**
     * @param $store
     * @param $selected_month
     * @return array
     * @description Disable day from admin setting
     */
    function disableDayByAdmin($store, $selected_month)
    {
        //Disable Days and today's day disable from admin
        $disableByDayAdmin = [];
        if ($selected_month && $selected_month == Carbon::now()->format('m')) {
            $disableByDayAdmin = $store->getReservationOffDates($store);
        } elseif ($selected_month != Carbon::now()->format('m')) {
        }

        if (isset($store->reservation_off_chkbx) && Carbon::now()->format('m') == $selected_month && $store->reservation_off_chkbx == 1) {
            $disableByDayAdmin[] = Carbon::now()->format('Y-m-d');
        }
        Log::info("Disable Days and today's day disable from admin : ", $disableByDayAdmin);
        return $disableByDayAdmin;
    }
}

if (!function_exists('weekOffDay')) {
    /**
     * @param $store
     * @param $selected_month
     * @param $selected_year
     * @return array
     * @description check the every weekly off day
     */
    function weekOffDay($store, $selected_month, $selected_year)
    {
        // disable days on week off day's
        $weekOffDates = [];
	    $weekOffDay = StoreWeekDay::query()
            ->where('store_id', $store->id)
            ->where('is_week_day_meal', 0)
            ->whereNull('is_active')
            ->pluck('name')
            ->toArray();
        $lastDayMonth = Carbon::now()->endOfMonth()->format('d');

        if (count($weekOffDay)) {
            for ($i = 1; $i <= $lastDayMonth; $i++) {
                $date = $selected_year . '-' . $selected_month . '-' . $i;
                if (in_array(Carbon::parse($date)->format('l'), $weekOffDay)) {
                    if (Carbon::createFromFormat('Y-m-d', $date)->month == $selected_month) {
	                    $weekOffDates[] = $date;
                    }
                }
            }
        }
        Log::info("Disable days on week off date's : ", $weekOffDates);
        Log::info("Disable days on week off day's : ", $weekOffDay);
        return [
        	'weekOffDays' => $weekOffDay,
	        'weekOffDates' => $weekOffDates
        ];
    }
}

if (!function_exists('modifiedSlotsDates')) {
    /**
     * @param $store
     * @param $selected_month
     * @return array
     * @description Fetch only Modified slots date
     */
    function modifiedSlotsDates($store, $selected_month)
    {
        //Slot modified available on date or day
        $modified_slots_date = StoreSlotModified::query()
            ->where('store_id', $store->id)
            ->whereRaw('MONTH(store_date) = ?', $selected_month)
            ->where('is_day_meal', 0)
            ->where('is_available', 0)
            ->orderBy('store_date', 'desc')
            ->pluck('store_date')
            ->toArray();
        Log::info("Modified Slots Date : ", $modified_slots_date);
        return $modified_slots_date;
    }
}

if (!function_exists('getNextEnableDates')) {
    /**
     * @param $availDates
     * @param $date
     * @param $days
     * @return mixed
     * @description Fetch the next first enable date from available dates
     */
    function getNextEnableDates($availDates, $date, $days)
    {
        if (in_array(Carbon::parse($date)->format('l'), $days)) {
            return getNextEnableDates($availDates, Carbon::parse($date)->addDays(1)->format('Y-m-d'), $days);
        }
        if (in_array($date, $availDates)) {
            return getNextEnableDates($availDates, Carbon::parse($date)->addDays(1)->format('Y-m-d'), $days);
        }
        return $date;
    }
}

if (!function_exists('superUnique')) {
    /**
     * @param $array
     * @param $key
     * @return array
     * @description Fetch the Super Unique value from defined specific field
     */
    function superUnique($array, $key)
    {
        $storeArray = [];
        foreach ($array as &$v) {
            if (!isset($storeArray[$v[$key]]))
                $storeArray[$v[$key]] =& $v;
        }
        $array = array_values($storeArray);
        return $array;

    }
}

if (!function_exists('getStoreBySlug')) {
    /**
     * @param $store_slug
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     * @description Fetch the store from store slug
     */
    function getStoreBySlug($store_slug)
    {
        return Store::with('multiSafe', 'storeSetting', 'storeButler')
            ->where('store_slug', $store_slug)
            ->first();
    }
}

if (!function_exists('getActiveMeals')) {
    /**
     * @param $slotAvailableMeals
     * @param $specific_date
     * @param $store_id
     * @param $slot_time
     * @param $person
     * @return array
     * @description Get only active meals based on slot
     */
    function getActiveMeals($specific_date, $store_id, $slot_time, $person,$check_all_reservation)
    {
        Log::info("Reservation : Active Meals Function Start in Helper");
        //Fetch the active status meal
        $active_meals = Meal::query()
            ->where('status', 1)
//            ->whereIn('id', $slotAvailableMeals)
            ->get();
//        if(empty($active_meals->count())){
//            $active_meals = [];
//        }
        $slotAvailableMeals = [];
        foreach ($active_meals as $meal) {
            $getDayFromUser = date('l', strtotime($specific_date));

            //week day check available day or not
            $query = StoreWeekDay::query()
                ->where('store_id', $meal['store_id'])
                ->where('name', $getDayFromUser);

            if (!$meal['is_meal_res'] && $meal['is_week_meal_res']) {
                $query = $query->where('is_week_day_meal', 1);
            } else {
                $query = $query->where('is_week_day_meal', 0);
            }
            $isDaySlotExist = $query->first();

            //Fetch the store slot modified is day meal available or not
            $isSlotModifiedAvailable = StoreSlotModified::query()
                ->where('store_id', $store_id)
                ->where('is_day_meal', 0)
                ->where('store_date', $specific_date);

            if (!empty($slot_time)) {
                $isSlotModifiedAvailable = $isSlotModifiedAvailable->where('from_time', $slot_time)->get()->toArray();
            } else {
                $isSlotModifiedAvailable = $isSlotModifiedAvailable->get()->toArray();
            }

            $assign_person = getTotalPersonFromReservations ($check_all_reservation,$meal,$slot_time);

            Log::info("Reservation : Day slot exist - " . json_encode($isDaySlotExist));
            if (count($isSlotModifiedAvailable) > 1) {
                foreach ($isSlotModifiedAvailable as $slotMeals) {
//                    $reservations = StoreReservation::query()->where('meal_type', $slotMeals['meal_id'])->where('status', 'approved')->where('is_checkout', 0)->where('from_time', $slot_time)->get();
//                    $assign_person = $reservations->sum('person');
                    if ($slotMeals['max_entries'] == 'Unlimited' || (int)$slotMeals['max_entries'] - $assign_person >= $person) {
                        $slotAvailableMeals[] = $slotMeals['meal_id'];
                    }
                }
            } elseif ($isDaySlotExist) {

                //Specific Day wise slots
                $daySlot = StoreSlot::query()
                    ->where('store_id', $store_id)
                    ->where('store_weekdays_id', '!=', null)
                    ->whereHas('store_weekday', function ($q) use ($getDayFromUser, $meal) {
                        $q = $q->where('is_active', 1)->where('name', $getDayFromUser);
                        if (!$meal->is_meal_res && $meal->is_week_meal_res) {
                            $q = $q->where('is_week_day_meal', 1);
                        } else {
                            $q = $q->where('is_week_day_meal', 0);
                        }
                    })
                    ->orderBy('from_time', 'ASC')
                    ->where('from_time', $slot_time)
                    ->get()
                    ->toArray();

                foreach ($daySlot as $slotMeals) {
                    if ($slotMeals['max_entries'] == 'Unlimited' || (int)$slotMeals['max_entries'] - $assign_person >= $person) {
                        $slotAvailableMeals[] = $slotMeals['meal_id'];
                    }
                }
                Log::info("Reservation : Specific Day wise slots based on available meals - " . json_encode($slotAvailableMeals));
            } else {

                //General Slots
                $generalSlot = StoreSlot::query()
                    ->where('store_id', $store_id)
                    ->doesntHave('store_weekday')
                    ->orderBy('from_time', 'ASC')
                    ->where('from_time', $slot_time)
                    ->get();
                foreach ($generalSlot as $slotMeals) {
//                    $reservations = StoreReservation::query()->where('meal_id', $slotMeals['id'])->where('from_time', $slot_time)->count();
                    if ($slotMeals['max_entries'] == 'Unlimited' || (int)$slotMeals['max_entries'] - $assign_person >= $person) {
                        $slotAvailableMeals[] = $slotMeals['meal_id'];
                    }
                }
                Log::info("Reservation : On General slots fetch the available meals - " . json_encode($generalSlot));
            }
        }
        return $slotAvailableMeals;
    }
}

function checkSlotMealAvailable($store_slug, $specific_date, $person, $slot, $slot_time, $slot_model){
    $getDayFromUser = date('l', strtotime($specific_date));
    $onSlotAvailableAllMealID = [];

    if($slot['from_time'] != $slot_time){
        return $onSlotAvailableAllMealID;
    }
    if (($slot['max_entries'] != 'Unlimited' && $slot['max_entries'] < $person)) {
        return $onSlotAvailableAllMealID;
    }
    /***** 8888888888888888888888888888 Data Preparation 88888888888888888888888888888888 *****/
    $store = getStoreBySlug($store_slug);
    $store_id = $store->id;

    //Specific Date Wise Slots
//    $this->date = $specific_date;
    $dayCheck = StoreWeekDay::query()->where('store_id', $store->id)->where('name', $getDayFromUser)->first();
    $meals = Meal::query()->where('store_id', $store->id)->where('status', 1)->get();
    $storeWeekMeal = [];
    $storeIsMeal = [];

    foreach ($meals as $meal) {
        if (!$meal->is_meal_res && $meal->is_week_meal_res) {
            $storeWeekMeal[] = $meal->id;
        }
        elseif ($meal->is_meal_res) {
            $storeIsMeal[] = $meal->id;
        }
    }
    $isSlotModifiedAvailable = StoreSlotModified::query()
        ->where('store_id', $store->id)
        ->where('is_day_meal', 0)
        ->where('store_date', $specific_date)
        ->where('is_available', 1);
    if (!is_null($slot_time)) {
        $isSlotModifiedAvailable = $isSlotModifiedAvailable->where('from_time', $slot_time)->count();
    }
    else {
        $isSlotModifiedAvailable = $isSlotModifiedAvailable->count();
    }
    foreach ($meals as $meal) {
        if ($meal->is_meal_res) {
            $isSlotModifiedAvailable = 1;
        }
    }
    $weekDayCheck = StoreWeekDay::query()
        ->where('store_id', $store->id)
        ->where('is_active', 1)
        ->where('name', $getDayFromUser)
        ->where('is_week_day_meal', 0)
        ->count();
    /***** 88888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888 *****/
    if ($isSlotModifiedAvailable > 0 && ($slot_model == 'StoreSlotModified')) {
        $dateMealSlotsData = StoreSlotModified::query()
            ->where('store_id', $store_id)
            ->where('is_day_meal', 0)
            ->where('store_date', $specific_date)
//            ->when($specific_date == Carbon::now()->format('Y-m-d'),function ($q){
//                $q->where('is_slot_disabled', 0);
//            })
            ->where('is_available', 1)
            ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id');

        if (!is_null($slot_time)) {
            $dateMealSlotsData = $dateMealSlotsData->where('from_time', $slot_time)->get()->toArray();
        } else {
            $dateMealSlotsData = $dateMealSlotsData->orderBy('from_time', 'ASC')->get()->toArray();
        }
        $onSlotAvailableAllMealID = collect($dateMealSlotsData)->pluck('meal_id')->toArray();

        //In Active Meal Specific Date vise slot
        $meals = Meal::query()
            ->where('store_id', $store_id)
            ->where('status', 1)
            ->get();

        $storeWeekMeal = [];
        $storeIsMeal = [];
        $dateDayMealSlots = [];

        foreach ($meals as $meal) {
            if (!$meal->is_meal_res && $meal->is_week_meal_res) {
                $storeWeekMeal[] = $meal->id;
            } elseif ($meal->is_meal_res) {
                $storeIsMeal[] = $meal->id;
            }
        }

        foreach ($storeIsMeal as $dateMeal) {
            $dateDayMealSlots = StoreSlotModified::query()
                ->where('store_id', $store_id)
                ->where('meal_id', $dateMeal)
                ->where('store_date', $specific_date)
//                ->when($specific_date == Carbon::now()->format('Y-m-d'),function ($q){
//                    $q->where('is_slot_disabled', 0);
//                })
                ->where('is_available', 1)
                ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id');

            if (!empty($slot_time)) {
                $dateDayMealSlots = $dateDayMealSlots->where('from_time', $slot_time)->get()
                    ->toArray();
            } else {
                $dateDayMealSlots = $dateDayMealSlots->orderBy('from_time', 'ASC')->get()
                    ->toArray();
            }
            if(empty($dateDayMealSlots)){
                continue;
            }
            if ($dateDayMealSlots == null) {
                $dateDayMealSlots = [];
            } else {
                $onSlotAvailableAllMealID = collect($dateDayMealSlots)->pluck('meal_id')->toArray();
            }
        }
        Log::info("on Specific Date + Meal Slot meal " , [$onSlotAvailableAllMealID]);
    }
    if (empty($onSlotAvailableAllMealID) && $dayCheck) {
        if(!empty($weekDayCheck)) {

        //Meal Slots
        $allActiveMeal = Meal::query()->where('store_id', $store_id)->where('status', 1)->get();

        foreach ($allActiveMeal as $meal) {
            if (!$meal->is_meal_res && $meal->is_week_meal_res) {
                $storeWeekMeal[] = $meal->id;
            } elseif ($meal->is_meal_res) {
                $storeIsMeal[] = $meal->id;
            }
        }
            $dateDayMealSlots = [];
            foreach ($storeWeekMeal as $weekMeal) {
                $getDateDayMealSlots = StoreSlot::query()
                    ->where('store_id', $store_id)
                    ->where('meal_id', $weekMeal)
//                ->when(!empty($specific_date) && $specific_date == Carbon::now()->format('Y-m-d'),function ($q){
//                    $q->where('is_slot_disabled', 0);
//                })
                    ->whereHas('store_weekday', function ($q1) use ($getDayFromUser) {
                        $q1->where('name', $getDayFromUser)
                            ->where('is_week_day_meal', 1)
                            ->where('is_active', 1);
                    })
                    ->where('store_weekdays_id', '!=', null)
                    ->whereHas('store_weekday', function ($q) use ($getDayFromUser) {
                        $q->where('is_active', 1)->where('name', $getDayFromUser);
                    })
                    ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id');

                if (!empty($slot_time)) {
                    $slotData = $getDateDayMealSlots->where('from_time', $slot_time)->get()->toArray();
                } else {
                    $slotData = $getDateDayMealSlots->orderBy('from_time', 'ASC')->get()->toArray();
                }
                if(isset($slotData[0])){
                    $dateDayMealSlots[] = $slotData[0];
                }
            }

            //Specific Day wise slots
            $daySlot = StoreSlot::where('store_id', $store_id)
                ->where('store_weekdays_id', '!=', null)
//            ->when(!empty($specific_date) && $specific_date == Carbon::now()->format('Y-m-d'),function ($q){
//                $q->where('is_slot_disabled', 0);
//            })
                ->whereHas('store_weekday', function ($q) use ($getDayFromUser) {
                    $q->where('is_active', 1)->where('name', $getDayFromUser)->where('is_week_day_meal', 0);
                })
                ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id');

            if (!empty($slot_time)) {
                $daySlot = $daySlot->where('from_time', $slot_time)->get()->toArray();
            } else {
                $daySlot = $daySlot->orderBy('from_time', 'ASC')->get()->toArray();
            }
            $onSlotAvailableAllMealID = collect($daySlot)->pluck('meal_id')->toArray();

            //For Meal + Specific Day Remove Specific Day meal
            $uniqueAvailableMealID = array_values(array_diff($onSlotAvailableAllMealID,$storeWeekMeal));
            $onSlotAvailableAllMealID = $uniqueAvailableMealID;

            if(empty($onSlotAvailableAllMealID)){
                $onSlotAvailableAllMealID = collect($dateDayMealSlots)->pluck('meal_id')->toArray();
            } else {
                $onSlotAvailableAllMealID = array_unique(array_merge($onSlotAvailableAllMealID, collect($dateDayMealSlots)->pluck('meal_id')->toArray()));
            }
            Log::info("on Specific Day + Meal Slot meal " , [$onSlotAvailableAllMealID]);
        }else{
            //Meal Slots
            $allActiveMeal = Meal::query()->where('store_id', $store_id)->where('status', 1)->get();

            foreach ($allActiveMeal as $meal) {
                if (!$meal->is_meal_res && $meal->is_week_meal_res) {
                    $storeWeekMeal[] = $meal->id;
                } elseif ($meal->is_meal_res) {
                    $storeIsMeal[] = $meal->id;
                }
            }
            $dateDayMealSlots = [];
            foreach ($storeWeekMeal as $weekMeal) {
                $getDateDayMealSlots = StoreSlot::query()
                    ->where('store_id', $store_id)
                    ->where('meal_id', $weekMeal)
//                ->when(!empty($specific_date) && $specific_date == Carbon::now()->format('Y-m-d'),function ($q){
//                    $q->where('is_slot_disabled', 0);
//                })
                    ->whereHas('store_weekday', function ($q1) use ($getDayFromUser) {
                        $q1->where('name', $getDayFromUser)
                            ->where('is_week_day_meal', 1)
                            ->where('is_active', 1);
                    })
                    ->where('store_weekdays_id', '!=', null)
                    ->whereHas('store_weekday', function ($q) use ($getDayFromUser) {
                        $q->where('is_active', 1)->where('name', $getDayFromUser);
                    })
                    ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id')
                    ->get();

                if (!empty($slot_time)) {
                    $slotData = collect($getDateDayMealSlots)->where('from_time', $slot_time)->pluck('meal_id')->toArray();
                } else {
                    $slotData = collect($getDateDayMealSlots)->pluck('meal_id')->toArray();
                }

                if(isset($slotData[0])){
                    $dateDayMealSlots[] = $slotData[0];
                }
//                if(empty($slotData[0])){
//                    if (in_array($weekMeal, $storeWeekMeal)){
//                        unset($storeWeekMeal[array_search($weekMeal,$storeWeekMeal)]);
//                    }
//                }
            }

            //General Slots
            $generalSlot = StoreSlot::query()
                ->where('store_id', $store_id)
//            ->when(!empty($specific_date) && $specific_date == Carbon::now()->format('Y-m-d'),function ($q){
//                $q->where('is_slot_disabled', 0);
//            })
                ->doesntHave('store_weekday')
                ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id');

            if (!empty($slot_time)) {
                $generalSlot = $generalSlot->where('from_time', $slot_time)->get()->toArray();
            } else {
                $generalSlot = $generalSlot->orderBy('from_time', 'ASC')->get()->toArray();
            }

            $onSlotAvailableAllMealID = collect($generalSlot)->pluck('meal_id')->toArray();

            //For Meal + General slot Remove General meal
            if(empty($storeWeekMeal)){
                $uniqueAvailableMealID = $storeWeekMeal;
            }else{
                $uniqueAvailableMealID = array_values(array_diff($onSlotAvailableAllMealID,$storeWeekMeal));
                $onSlotAvailableAllMealID = $uniqueAvailableMealID;
            }

            if(empty($onSlotAvailableAllMealID)){
                $onSlotAvailableAllMealID = $dateDayMealSlots;
            } else {
//                $onSlotAvailableAllMealID = array_unique(array_merge($onSlotAvailableAllMealID, collect($dateDayMealSlots)->pluck('meal_id')->toArray()));
                if(!empty($dateDayMealSlots)){
                    $onSlotAvailableAllMealID = array_unique(array_merge($onSlotAvailableAllMealID, $dateDayMealSlots));
                }else{
                    $uniqueAvailableMealID = array_values(array_diff($onSlotAvailableAllMealID,$storeWeekMeal));
                    $onSlotAvailableAllMealID = $uniqueAvailableMealID;
                }
            }
            Log::info("on Meal + General Slot meal " , [$onSlotAvailableAllMealID]);
        }
    }
    if(empty($onSlotAvailableAllMealID)){
        //General Slots
        $generalSlot = StoreSlot::query()
            ->where('store_id', $store_id)
//            ->when(!empty($specific_date) && $specific_date == Carbon::now()->format('Y-m-d'),function ($q){
//                $q->where('is_slot_disabled', 0);
//            })
            ->doesntHave('store_weekday')
            ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id');

        if (!empty($slot_time)) {
            $generalSlot = $generalSlot->where('from_time', $slot_time)->get()->toArray();
        } else {
            $generalSlot = $generalSlot->orderBy('from_time', 'ASC')->get()->toArray();
        }
        $onSlotAvailableAllMealID = collect($generalSlot)->pluck('meal_id')->toArray();
        Log::info("on General Slot meal " , [$onSlotAvailableAllMealID]);
    }

    /***** -------------------------------------------------------------------------- *****/
    //If Store create specific date on meal then that meal not showen in other Slots
    $specificDateOnMealStore = [];
    $dateMealSlotsData = StoreSlotModified::query()
        ->where('store_id', $store_id)
        ->where('is_day_meal', 1)
        ->where('store_date', $specific_date)
        ->where('is_available', 1)
        ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id')
        ->get();

    if (!is_null($slot_time)) {
        $specificDateOnMeal = $dateMealSlotsData->where('from_time', $slot_time)->pluck('meal_id')->toArray();
    } else {
        $specificDateOnMeal = $dateMealSlotsData->pluck('meal_id')->toArray();
    }
    if(isset($specificDateOnMeal)){
        $specificDateOnMealStore = $specificDateOnMeal;
    }

    $generalSlot = StoreSlot::query()
        ->where('store_id', $store_id)
        ->doesntHave('store_weekday')
        ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id');

    if (!empty($slot_time)) {
        $generalSlot = $generalSlot->where('from_time', $slot_time)->get()->toArray();
    } else {
        $generalSlot = $generalSlot->orderBy('from_time', 'ASC')->get()->toArray();
    }

    $onSlotAvailableAllMealID = collect($generalSlot)->pluck('meal_id')->toArray();

    if(empty($onSlotAvailableAllMealID)){
        $onSlotAvailableAllMealID = $specificDateOnMealStore;
    } else {
        if(!empty($specificDateOnMealStore)){
            $onSlotAvailableAllMealID = array_unique(array_merge($onSlotAvailableAllMealID, $specificDateOnMealStore));
        }else{
            $uniqueAvailableMealID = array_values(array_diff($onSlotAvailableAllMealID,$storeIsMeal));
            $onSlotAvailableAllMealID = $uniqueAvailableMealID;
        }
    }
    /***** -------------------------------------------------------------------------- *****/

    return $onSlotAvailableAllMealID;
}

if (!function_exists('getDisable')) {
    /**
     * @param $store_id
     * @param $specific_date
     * @param $person
     * @param $slot_active_meals
     * @param $store
     * @param $slot_time
     * @return string
     * @discription Return disable for meal function show or hide
     */
    function getDisable($store_id, $specific_date, $person, $slot_active_meals, $store, $slot_time, $disable,$check_all_reservation)
    {
        $disable = false;
        $available_table_list = [];
        //Reservation check here for limit of person based on available reservation
//        $res_id = 0;
//        $check_all_reservation = StoreReservation::query()
//            ->with('tables.table.diningArea', 'meal')
//            ->where('store_id', $store_id)
//            ->where('res_date', $specific_date)
//            ->whereNotIn('status', ['declined', 'cancelled'])
//            ->where(function ($q1) {
//                $q1->whereIn('local_payment_status', ['paid', '', 'pending'])->orWhere('total_price', null);
//            })
//            ->where('is_seated', '!=', 2)
//            ->get();

        //check dining area status active or not
        $sections = DiningArea::query()
            ->where('store_id', $store_id)
            ->where('status', 1)
//            ->where('display_booking_frame', 1)
            ->get();

        foreach ($sections as $section) {
            $section_id = $section->id;
            //Table Reservation
            if ($store->is_table_mgt_enabled == 1 && $store->is_smart_res) {
                $check_table = Table::query()
                    ->whereHas('diningArea', function ($q1) use ($store) {
                        $q1->where('store_id', $store->id)
                            ->where('status', 1);
                    })->where('status', 1)->where('online_status', 1);

                if (!empty($section_id)) {
                    $check_table = $check_table->where('dining_area_id', $section_id);
                }

                $tables = $check_table->get();
                $tables_id = $tables->pluck('id')->toArray();

                //Already assign table list
                $table_assign = [];
                $time_limit_of_reservation = [];

                foreach ($check_all_reservation as $reservation) {

                    //If not set time limit of meal then auto set 120 minutes for it.
                    $meal = $slot_active_meals;
                    $time_limit_of_reservation = (isset($meal->time_limit) && $meal->time_limit) ? $meal->time_limit : 120;

                    //Slot End Time of the
                    $end_time = Carbon::parse($slot_time)
                        ->addMinutes($time_limit_of_reservation)
                        ->format('H:i');
                    if ($end_time == '00:00') {
                        $end_time = '24:00';
                    } elseif (strtotime($end_time) < strtotime($slot_time)) {
                        $end_time = '24:00';
                    }
                    $another_meeting =
                        (strtotime($slot_time) > strtotime($reservation->from_time) && strtotime($slot_time) < strtotime($reservation->end_time)) ||
                        (strtotime($reservation->from_time) > strtotime($slot_time) && strtotime($reservation->from_time) < strtotime($end_time)) ||
                        (strtotime($slot_time) == strtotime($reservation->from_time));
                    if ($another_meeting) {
                        foreach ($reservation->tables as $table) {
                            $table_assign[] = $table->table_id;
                        }
                    }
                }

                $available_table_list = array_diff($tables_id, $table_assign);
                if (!$available_table_list) {
                    $disable = true;
                    Log::info("Available table not there");
                    continue;
                }

                $table_availability = false;
                foreach ($available_table_list as $empty_table) {
                    $empty_table_data = $tables->where('id', $empty_table)->first();
                    $person_seat_range = range($empty_table_data->no_of_min_seats, $empty_table_data->no_of_seats);

                    if (in_array($person, $person_seat_range)) {
                        $table_availability = true;
                        $disable = false;
                        Log::info("Table Availability Found there 0 ",[$person,$empty_table_data->no_of_min_seats,$empty_table_data->no_of_seats,$person_seat_range]);
                        break 2;
                    } else if (!$table_availability) {
                        Log::info("Table Availability not there ");
                        $disable = true;
                    }
                }
                $data['store_id'] = $store->id;
                $availableTables = getAvailableTables($data, $table_assign, $section_id);

                /*<--- min-max person capacity check--->*/
                $isExist = $availableTables
                    ->where('no_of_min_seats', '<=', $person)
                    ->Where('no_of_seats', '>=', $person)
                    ->first();

                if (!$isExist) {
                    /*<--- min-max person capacity check--->*/
                    $isExist = $availableTables
                        ->where('no_of_min_seats', '<=', $person)
                        ->Where('no_of_seats', '>=', ($person + 1))
                        ->first();
                }

                /*check if the auto group/merge table is allowed or not and no single table available*/
                if ($store->allow_auto_group && !$isExist) {
                    Log::info('Allow auto group is on');

                    $get_section = DiningArea::query()
                        ->with(['tables' => function ($q1) use ($available_table_list, $person) {
                            $q1->whereIn('id', $available_table_list)
                                ->where('online_status', 1)
                                ->where('status', 1)
                                ->where('no_of_seats', '<=', $person);
                        }
                        ])->where('store_id', $store->id)->where('status', 1);
                    if ($section_id != null) {
                        $get_section = $get_section->where('id', $section_id);
                    }
                    $sections = $get_section->get();
                    if (empty($sections->count())) {
                        Log::info("Section not available : ");
                        $disable = true;
                        continue;
                    }

                    foreach ($sections as $section_str) {
                        $store_table = [];
                        $total = 0;
                        foreach ($section_str->tables as $table) {
                            $store_table[] = [$table->no_of_seats];
                            $total += $table->no_of_seats;
                        }
                        if ($total >= $person) {
                            usort($store_table, function ($a, $b) {
                                return $a <=> $b;
                            });
                            $match = bestsum($store_table, $person);
                            if ($match && (array_sum($match) == $person || array_sum($match) == $person + 1)) {
                                if ((collect($match)->count() == 1) || ($store->allow_auto_group == 1 && collect($match)->count() > 1)) {
                                    $disable = false;
                                    Log::info("Table Availability Found here 1 ", [$match, $store->allow_auto_group]);
                                    break;
                                }
                            } else {
                                $match = bestsum($store_table, $person + 1);
                                if ($match && array_sum($match) == $person + 1) {
                                    if ((collect($match)->count() == 1) || ($store->allow_auto_group == 1 && collect($match)->count() > 1)) {
                                        $disable = false;
                                        Log::info("Table Availability Found here 2(match +1) ", [$match, $store->allow_auto_group]);
                                        break;
                                    }
                                }
                            }
                        }//Compare person availability check
                    }//Second loop for section end
                }
            }//If condition smart reservation end
        }//Loop for section end
        Log::info("Reservation : Available Table List | disable status - ", [$available_table_list, $disable]);
        return $disable;
    }
}

if (!function_exists('tableAssign')) {
    /**
     * @param $dataFromCron
     * @return mixed
     * @description Auto assigned table on cron
     */
    function tableAssign($dataFromCron)
    {
        $newReservationDetail = $dataFromCron['new_reservation_details'];
        $data = $dataFromCron['payload'];
        $store = $dataFromCron['store'];
        $reservation_check_attempt = $dataFromCron['reservation_check_attempt'];
        $newReservationStatus = $dataFromCron['reservation_status'];
        $meal = Meal::query()->findOrFail($data['meal_type']);
        $availableSeats = [];
        $table_ids = [];
        /*if reservation is auto approval then assign table*/
        if ($data['status'] == 'approved' || ($meal->price && ($meal->payment_type == 1 || $meal->payment_type == 3))) {
            $time_limit = ($meal->time_limit) ? $meal->time_limit : 120;
            $start_time = $data['from_time'];
            $end_time = Carbon::parse($data['from_time'])->addMinutes($time_limit)->format('H:i');
            if (strtotime($start_time) > strtotime($end_time)) {
                $end_time = '23:59';
            }
            $data['end_time'] = $end_time;
            $availableTables = collect();

            $reservedTables = getReservationTables($data, $start_time, $end_time);
            Log::info('Get already reserved tables : ' . json_encode($reservedTables));

            $availableTables = getAvailableTables($data, $reservedTables, $data['section_id']);
            Log::info('(With out payment) Get Available tables : ' . json_encode($availableTables));

            if ($availableTables->count() && $store->is_table_mgt_enabled == 1) {
                if ($store->is_smart_fit) {
                    Log::info('Smart fit is on');
                    /*<--- min-max person capacity check--->*/
                    $isExist = $availableTables
                        ->where('no_of_min_seats', '<=', $data['person'])
                        ->Where('no_of_seats', '>=', $data['person'])
                        ->first();

                    if (!$isExist) {
                        /*<--- min-max person capacity check--->*/
                        $isExist = $availableTables
                            ->where('no_of_min_seats', '<=', $data['person'])
                            ->Where('no_of_seats', '>=', ($data['person'] + 1))
                            ->first();
                    }

                    /*check if the auto group/merge table is allowed or not and no single table available*/
                    if ($store->allow_auto_group && !$isExist) {
                        Log::info('Allow auto group is on');

                        $availableTables = $availableTables->pluck('id')->toArray();
                        /*get available tables section wise*/
                        $reservation_person = $data['person'];
                        $sectionsDetails = DiningArea::with([
                            'tables' => function ($q1) use ($availableTables, $reservation_person) {
                                $q1->whereIn('id', $availableTables)
                                    ->where('online_status', 1)
                                    ->where('status', 1)
                                    ->where('no_of_seats', '<=', $reservation_person);
                            }
                        ])->where('status', 1)->where('store_id', $store->id);
                        if ($data['section_id'] != null) {
                            $sectionsDetails = $sectionsDetails->where('id', $data['section_id']);
                        }
                        $sections = $sectionsDetails->get();
                        foreach ($sections as $key => $section) {
                            $storeTablesList = [];
                            $totalValue = 0;
                            foreach ($section->tables as $table) {
                                $storeTablesList[] = [$table->no_of_seats];
                                $totalValue += (int)$table->no_of_seats;
                            }
                            try {
                                Log::info('section total seats : ' . $totalValue);
                                /*check if any tables or merge tables matches the requested person val*/
                                if ($totalValue >= (int)$data['person']) {
                                    /*sort available tables*/
                                    usort($storeTablesList, function ($a, $b) {
                                        return $a <=> $b;
                                    });
                                    $match = bestsum($storeTablesList, (int)$data['person']);
                                    if ($match && (array_sum($match) == (int)$data['person'] || array_sum($match) == (int)$data['person'] + 1)) {
                                        $availableSeats = $match;
                                    } else {
                                        $match = bestsum($storeTablesList, (int)$data['person'] + 1);
                                        if ($match && array_sum($match) == (int)$data['person'] + 1) {
                                            $availableSeats = $match;
                                        }
                                    }
                                    $table_ids[$key] = [];
                                    foreach ($availableSeats as $seat) {
                                        $fetchSeats = $section->tables->where('no_of_seats', $seat)->whereNotIn('id', $table_ids[$key])->first();
                                        if ($fetchSeats) {
                                            $table_ids[$key][] = $fetchSeats->id;
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                Log::error("Auto assigned table cron : " . 'Message | ' . $e->getMessage() . 'File | ' . $e->getFile() . 'Line | ' . $e->getLine());
                            }
                        }
                    } else {
                        if ($isExist) {
                            $table_ids[0][] = $isExist->id;
                        }
                    }
                } else {
                    $isExist = $availableTables->where('no_of_seats', '>=', $data['person'])->first();
                    if ($isExist) {
                        $table_ids[0][] = $isExist->id;
                    }
                }
            }
        }
        $keys = array_map('strlen', array_keys($table_ids));
        array_multisort($keys, SORT_DESC, $table_ids);
        if ($table_ids && collect($table_ids[0])->count() > 1) {
            $data['group_id'] = 1;
            $fetchGroups = StoreReservation::query()
                ->where('res_date', ($data['res_date'] ?? Carbon::now()->format('Y-m-d')))
                ->where('store_id', $data['store_id'])
                ->orderBy('group_id', 'desc')
                ->first();
            if ($fetchGroups) {
                $data['group_id'] = $fetchGroups->group_id + 1;
            }
        }
        if (isset($data['group_id'])) {
            StoreReservation::query()->where('id', $newReservationDetail->reservation_id)->update(['group_id' => $data['group_id']]);
        }
        Log::info('Table ids : ' . json_encode($table_ids));
        $storeReservationId = StoreReservation::query()->where('id', $newReservationDetail->reservation_id)->firstOrFail();
        if ($table_ids) {
            foreach ($table_ids[0] as $table_id) {
                Log::info('Assign table to reservation');
                ReservationTable::create([
                    'reservation_id' => $storeReservationId->id,
                    'table_id' => $table_id,
                ]);
            }
        }
        ReservationJob::query()->where('id', $newReservationDetail->id)->update(['is_completed' => 1, 'is_failed' => 0, 'attempt' => $reservation_check_attempt]);
        StoreReservation::query()->where('id', $newReservationDetail->reservation_id)->update(['res_status' => $newReservationStatus]);
        if ($newReservationStatus == 'failed') {
            Log::info('Seven : Create reservation entry in cancel reservation table.');
            CancelReservation::query()->create([
                'reservation_id' => $newReservationDetail->reservation_id,
                'store_id' => $newReservationDetail->store_id,
                'reservation_front_data' => $newReservationDetail->reservation_front_data,
                'reason' => 'Failed'
            ]);
        }
        Log::info("Update reservation detail done", ['reservation_id' => $newReservationDetail->reservation_id, 'status' => $newReservationStatus]);
        return $newReservationStatus;
    }
}

if (!function_exists('getReservationTables')) {
    /**
     * @param $reservation
     * @param $start_time
     * @param $end_time
     * @return array
     * @description Fetch all the reserved table id list
     */
    function getReservationTables($reservation, $start_time, $end_time)
    {
        $table_ids = [];
        $add_new_start_time = \Carbon\Carbon::parse($start_time)->addMinutes(1)->format('H:i');
        if ($end_time < $add_new_start_time) {
            $end_time = '24:00';
        }
        //Check table availability seated and fetch table id list
        ReservationTable::query()->leftJoin('store_reservations', 'store_reservations.id', '=', 'reservation_tables.reservation_id')
            ->whereNotIn('store_reservations.status', [
                'declined',
                'cancelled'
            ])
            ->where('store_reservations.is_checkout', '<>', 1)
            ->where('store_reservations.is_seated', '!=', 2)
            ->whereIn('store_reservations.payment_status', ['paid', '', 'pending'])
            ->whereDate('store_reservations.res_date', $reservation['res_date'])
            ->where('store_reservations.store_id', $reservation['store_id'])
            ->chunk(200, function ($reseravtions) use (&$table_ids, $start_time, $end_time, $add_new_start_time) {
                foreach ($reseravtions as $reservation) {
                    if ($reservation['end_time'] < $reservation['from_time']) {
                        $reservation['end_time'] = '24:00';
                    }
                    if ((strtotime($reservation['from_time']) <= strtotime($start_time) && strtotime($reservation['end_time']) > strtotime($add_new_start_time)) ||
                        (strtotime($reservation['from_time']) < strtotime($end_time) && strtotime($reservation['end_time']) >= strtotime($end_time)) ||
                        (strtotime($reservation['from_time']) >= strtotime($start_time) && strtotime($reservation['end_time']) <= strtotime($end_time)) ||
                        (!is_null($reservation['checked_in_at']) && $reservation['is_checkout'] != 1 &&
                            $reservation['end_time'] == date('H:i') && strtotime($reservation['end_time']) > strtotime($add_new_start_time))) {
                        $table_ids[] = $reservation['table_id'];
                    }
                }
            });
        $table_ids = array_unique($table_ids);
        return $table_ids;
    }
}

if (!function_exists('getAvailableTables')) {
    /**
     * @param $reservation : Array
     * @param $reservedTables : Array
     * @return mixed
     * @Description get available tables for given stores
     */
    function getAvailableTables($reservation, $reservedTables, $section_id = null)
    {
        Log::info('If selected then Section id : ' . $section_id);
        return Table::select('tables.*')
            ->leftJoin('dining_areas', 'dining_areas.id', '=', 'tables.dining_area_id')
            ->where('tables.online_status', 1)
            ->where('tables.status', 1)
            ->where('dining_areas.status', 1)
            ->where('dining_areas.is_automatic', 1)
            ->where('dining_areas.store_id', $reservation['store_id'])
            ->when(!empty($section_id), function ($q1) use ($section_id) {
                $q1->where('dining_areas.id', $section_id);
            })
            ->whereNotIn('tables.id', $reservedTables)
            ->get();
    }
}

if (!function_exists('reservedTimeSlot')) {
    /**
     * @param $store_id
     * @param $specific_date
     * @param $meal
     * @param $person
     * @param $store
     * @param $disable
     * @param $data
     * @return array
     * @Description Fetch reserved time slot
     */
    function remainingSeatCheckDisable($store_id, $specific_date, $meal, $person, $store, $disable, $data, $slot, $check_all_reservation)
    {
        $disable = false;
        $time_slot = [];
        $sections = DiningArea::query()
            ->where('store_id', $store_id)
            ->where('status', 1);

        if ($data['section_id'] != null) {
            $sections = $sections->where('dining_areas.id', $data['section_id']);
        }
        $sections = $sections->get();

        if (empty($sections->count())) {
            Log::info("Any section not there");
            return ['disable' => true];
        }


        if ($store->is_table_mgt_enabled == 1 && $sections->count() > 0) {
            $total_seat = 0;
            foreach ($sections as $section) {
                foreach ($section->tables as $table) {
                    if ($table->online_status == 1 && $table->status == 1) {
                        $total_seat += $table->no_of_seats;
                    }
                }
            }

            $without_table_person = 0;
            $with_table_person = 0;
            $res_id = 0;
            //Check the all reservation which local payment status paid, pending or null
//            $check_all_reservation = StoreReservation::query()
//                ->with('tables.table.diningArea', 'meal')
//                ->where('store_id', $store_id)
//                ->where('res_date', $specific_date)
//                ->where('id', '!=', $res_id)
//                ->whereNotIn('status', ['declined', 'cancelled'])
//                ->where(function ($q1) {
//                    $q1->whereIn('local_payment_status', ['paid', '', 'pending'])->orWhere('total_price', null);
//                })
//                ->where('is_seated', '!=', 2)
//                ->get();

            foreach ($check_all_reservation as $reservation) {
                $another_meeting = getAnotherMeeting($reservation, $meal, $slot);
                if ($another_meeting) {
                    if ($reservation->tables && count($reservation->tables) > 0) {
                        foreach ($reservation->tables as $table) {
                            if ($table->table && $table->table->diningArea && $table->table->diningArea->status == 1) {
                                $with_table_person = $with_table_person + $table->table->no_of_seats;
                            }
                        }
                    } elseif (count($reservation->tables) == 0) {
                        $without_table_person = $without_table_person + $reservation->person;
                    }
                }
            }
                $remain_seats = $total_seat - $with_table_person - $without_table_person;

            if ($remain_seats < $person) {
                Log::info("Remaining seat less than person capacity " , [$remain_seats,$person]);
                return ["slot_disable" => true];
            }

            if ($remain_seats >= $person) {
                $disable = true;
                foreach ($sections as $section) {

                    $section_wise_seat = 0;
                    $reservation_seat = 0;
                    foreach ($section->tables as $table) {
                        if ($table->online_status == 1 && $table->status == 1) {
                            $section_wise_seat = $section_wise_seat + $table->no_of_seats;
                        }
                    }
                    foreach ($check_all_reservation as $reservation) {
                        $another_meeting = getAnotherMeeting($reservation, $meal, $slot);
                        if ($another_meeting) {
                            if ($reservation->tables && count($reservation->tables) > 0) {
                                foreach ($reservation->tables as $table) {
                                    if ($table->table && $table->table->diningArea && $table->table->diningArea->id == $section->id && $table->table->diningArea->status == 1) {
                                        $reservation_seat = $reservation_seat + $table->table->no_of_seats;
                                    }
                                }
                            }
                        }
                    }
                    $remaining_seat = $section_wise_seat - $reservation_seat;
                    if ($remaining_seat >= $person) {
                        Log::info("Remaining seat greater than person capacity " , [$remain_seats,$person]);
                        $disable = false;
                        break;
                    }
                }
            }
        }
        return ['disable' => $disable];
    }
}

function checkSlotAvailability($start_time, $key, $picktimes) {
    $start_picktime = Carbon::parse($start_time);
    $diff_picktime = 120;
    if(isset($picktimes[$key + 1])) {
        $end_picktime = Carbon::parse($picktimes[$key + 1]->from_time);
        $diff_picktime = ($start_picktime->diffInMinutes($end_picktime) <= 120) ? $start_picktime->diffInMinutes($end_picktime) : 120;
    } else {
        if(!isset($picktimes[$key + 1])) {
            if(isset($picktimes[$key - 1])) {
                $end_picktime = Carbon::parse($picktimes[$key - 1]->from_time);
                $diff_picktime = ($start_picktime->diffInMinutes($end_picktime) <= 120) ? $start_picktime->diffInMinutes($end_picktime) : 120;
            }
        }
    }
    $end_time = Carbon::parse($start_picktime)->addMinutes($diff_picktime)->format('H:i');
    if(strtotime($start_picktime) > strtotime($end_time)) {
        return '24:00';
    } else {
        return $end_time;
    }
}

if (!function_exists('getAnotherMeeting')) {
    /**
     * @param $reservation
     * @param $meal
     * @param $item
     * @return bool
     * @description To check the reserved time for next reservation available or not
     */
    function getAnotherMeeting($reservation, $meal, $item)
    {

        $time_limit = '';
        if (!$reservation->end_time) {
            $time_limit = ($reservation->meal && $reservation->meal->time_limit) ? $reservation->meal->time_limit : 120;
            $reservation->end_time = Carbon::parse($reservation->from_time)
                ->addMinutes($time_limit)
                ->format('H:i');
        }
        if (!empty($time_limit)) {
            $time_limit = ($meal->time_limit) ? $meal->time_limit : 120;
        }
        $end_time = Carbon::parse($item['from_time'])->addMinutes($time_limit)->format('H:i');

        $another_meeting = (strtotime($item['from_time']) > strtotime($reservation['from_time']) && strtotime($item['from_time']) < strtotime($reservation->end_time)) ||
            (strtotime($reservation['from_time']) > strtotime($item['from_time']) && strtotime($reservation['from_time']) < strtotime($end_time)) ||
            (strtotime($item['from_time']) == strtotime($reservation['from_time']));

        return $another_meeting;
    }
}

if (!function_exists('bestsum')) {
    /**
     * @param $data
     * @param $maxsum
     * @return array|mixed
     * @description To calculate the time
     */
    function bestsum($data, $maxsum)
    {
        $res = array_fill(0, $maxsum + 1, '0');
        $res[0] = [];              //base case
        foreach ($data as $group) {
            $new_res = $res;               //copy res
            foreach ($group as $ele) {
                for ($i = 0; $i < ($maxsum - $ele + 1); $i++) {
                    if ($res[$i] != 0) {
                        $ele_index = $i + $ele;
                        $new_res[$ele_index] = $res[$i];
                        $new_res[$ele_index][] = $ele;
                    }
                }
            }
            $res = $new_res;
        }

        for ($i = $maxsum; $i > 0; $i--) {
            if ($res[$i] != 0) {
                return $res[$i];
                break;
            }
        }
        return [];
    }
}

/**
 * @param $id : Integer
 * @param $store_id : Integer
 * @param string $channel : Not in used
 * @param array $oldTables : Not in used
 * @param null $socket_origin_client_id : Not in used
 * @return bool
 * @Description create new Reservation then show in planner
 */
function sendResWebNotification($id, $store_id, $channel = '', $oldTables = [], $socket_origin_client_id = null)
{
    try {
        Log::info('in sendResWebNotification start : Socket');
        /*Web notification*/
        $reservation = ReservationTable::query()->whereHas('reservation', function ($q) use ($store_id) {
            $q/*->whereHas('meal')*/ ->whereIn('status', ['approved', 'pending', 'cancelled', 'declined']);
        })->with([
            'reservation' => function ($q) use ($store_id) {
                $q->with([
                    'reservation_serve_requests',
                    'meal:id,name,time_limit',
                    'tables.table',
                    'user' => function ($q2) use ($store_id) {
                        $q2->select('id', 'profile_img')->with([
                            'card' => function ($q3) use ($store_id) {
                                $q3->select('id', 'customer_id', 'total_points')
                                    ->where('store_id', $store_id)
                                    ->where('status', 'active');
                            }
                        ]);
                    }
                ])->whereIn('status', ['approved', 'pending', 'cancelled', 'declined'])->where(function ($q1) {
                    $q1->whereIn('local_payment_status', ['paid', '', 'pending', 'failed'])->orWhere('total_price', null);
                });
            }
        ])->where('reservation_id', $id)->first();

        if ($reservation && $reservation->reservation) {
            $reservation->reservation->end = 120;
            if ($reservation->reservation->is_dine_in || $reservation->reservation->is_qr_scan) {
                $orders = Order::with('orderItems.product:id,image,sku')
                    ->where('status', 'paid')
                    ->where('parent_id', $reservation->reservation->id)
                    ->orderBy('id', 'desc')
                    ->get()->toArray();
                foreach ($orders as $key => $order) {
                    $orders[$key]['dutch_order_status'] = __('messages.' . $order['order_status']);
                }
                $reservation->reservation->orders = $orders;
            }
            if ($reservation->reservation->end_time) {
                if ($reservation->reservation->end_time == '00:00') {
                    $reservation->reservation->end_time = '24:00';
                }
                $start = Carbon::parse($reservation->reservation->from_time)->format('H:i');
                $reservation->reservation->end = Carbon::parse($reservation->reservation->end_time)
                    ->diffInMinutes($start);
            } elseif (isset($reservation->reservation->meal)) {
                $reservation->reservation->end = $reservation->reservation->meal->time_limit;
            }
            if (isset($reservation->reservation->user) && $reservation->reservation->user != null && isset($reservation->reservation->user->profile_img) && file_exists(public_path($reservation->reservation->user->profile_img))) {
                $reservation->reservation->user_profile_image = isset($reservation->reservation->user->profile_img) ? asset($reservation->reservation->user->profile_img) : asset('asset_new/app/media/img/users/user4.jpg');
            } else {
                //						$reservation->reservation->user_profile_image = asset('asset_new/app/media/img/users/user4.jpg');
            }
            if ($reservation->reservation->voornaam || $reservation->reservation->achternaam) {
                $reservation->reservation->img_name = strtoupper(mb_substr($reservation->reservation->voornaam, 0, 1) . mb_substr($reservation->reservation->achternaam, strrpos($reservation->reservation->achternaam, ' '), 1));
            } else {
                $reservation->reservation->img_name = 'G';
            }
            $reservation->reservation->unread_msg = false;
            $reservation->reservation->messages = [];

            $time = Carbon::now()->format('H:i');
            if ($reservation->reservation->group_id) {
                $all_tables = $reservation->reservation->tables->pluck('table.name')->toArray();
                $reservation->reservation->all_tables = $all_tables;
            }
            if (isset($reservation->reservation->tables) && $reservation->reservation->tables->count() > 0) {
                $tables = [];
                foreach ($reservation->reservation->tables as $table) {
                    if ($table->table) {
                        $tables[] = $table->table->name;
                    }
                }
                $reservation->reservation->table_names = isset($tables) ? implode('  ', $tables) : '';
            }
            unset($reservation->reservation->tables);
            $last_message = null;
            if ($last_message) {
                $reservation->reservation->last_message = $last_message->body;
            } else {
                $reservation->reservation->last_message = null;
            }
            $tempReservation = $reservation->toArray();
            $tempReservation['reservation']['reservation_date'] = $reservation->reservation->getRawOriginal('res_date');

            $dinein_area_id = '';
            $table = ReservationTable::with(['table'])->where('reservation_id', $id)->first();
            if ($table && $table['table']) {
                $dinein_area_id = $table['table']->dining_area_id;
            }

            $additionalData = json_encode([
                'reservation' => $tempReservation,
                'reservation_id' => $reservation->reservation->id,
                'dinein_area_id' => $dinein_area_id,
                'socket_origin_client_id' => $socket_origin_client_id
            ]);
            $channel = $channel ? $channel : 'new_booking';
            $table_ids = ReservationTable::query()->where('reservation_id', $id)->pluck('table_id')->toArray();
            if ($channel && ($channel == 'checkin' || $channel == 'remove_booking' || $channel == 'payment_status_update')) {
                Log::info('double inn' . json_encode($tempReservation));
                if ($channel == 'remove_booking') {
                    $start = Carbon::parse($reservation->from_time)->format('H:i');
                    $end = Carbon::parse($reservation->end_time)->diffInMinutes($start);
                    $additionalData = json_encode([
                        'reservation_id' => $id,
                        'status' => $reservation->reservation->status,
                        'local_payment_status' => $reservation->reservation->local_payment_status,
                        'payment_status' => $reservation->reservation->payment_status,
                        'table_ids' => $table_ids,
                        'reload' => 1, // here reload flag not set that's why set 1 by default
                        'is_reload' => 1, // here reload flag not set that's why set 1 by default
                        'table_id' => isset($reservation->table_id) ? $reservation->table_id : null,
                        'reservation_type' => $reservation->reservation->reservation_type,
                        'dinein_price_id' => $reservation->reservation->dinein_price_id,
                        'end' => $end,
                        'reservation_date' => $reservation->reservation->getRawOriginal('res_date'),
                        'swap_id' => null,
                        'id' => $reservation->reservation->id,
                        'store_id' => $reservation->reservation->store_id,
                        'parked_table_id' => null,
                        'old_tables' => [],
                        'all_tables' => [],
                        'dinein_area_id' => $dinein_area_id,
                        'socket_origin_client_id' => $socket_origin_client_id
                    ]);
                } elseif ($channel == 'payment_status_update') {
                    $additionalData = json_encode([
                        'reservation_id' => $id,
                        'socket_origin_client_id' => $socket_origin_client_id,
                        'reservation_date' => $reservation->reservation->getRawOriginal('res_date'),
                        'dinein_area_id' => $dinein_area_id,
                        'status' => $reservation->reservation->status,
                        'payment_status' => $reservation->reservation->payment_status,
                        'local_payment_status' => $reservation->reservation->local_payment_status,
                        'table_ids' => $table_ids,
                        'table_id' => isset($reservation->table_id) ? $reservation->table_id : null,
                        'all_tables' => isset($reservation->reservation->all_tables) ? $reservation->reservation->all_tables : [],
                        'multisafe_payment_id' => $reservation->reservation->multisafe_payment_id,
                        'mollie_payment_id' => $reservation->reservation->mollie_payment_id,
                    ]);
                } else {
                    $additionalData = json_encode([
                        'reservation_id' => $id,
                        'reservation' => $tempReservation,
                        'dinein_area_id' => $dinein_area_id,
                        'socket_origin_client_id' => $socket_origin_client_id
                    ]);
                }
                $redis = LRedis::connection();
                $redis->publish('reservation_booking', json_encode([
                    'store_id'        => $store_id,
                    'channel'         => $channel,
                    'notification_id' => 0,
                    'additional_data' => $additionalData,
                    'socket_origin_client_id' => $socket_origin_client_id,
                    'system_name' => 'Reservation'
                ]));
            } else if ($channel == 'new_booking') {
                $additionalData = json_encode([
                    'reservation_id' => $id,
                    'socket_origin_client_id' => $socket_origin_client_id,
                    'reservation_date' => $reservation->reservation->getRawOriginal('res_date'),
                    'dinein_area_id' => $dinein_area_id,
                    'dinein_price_id' => $reservation->reservation->dinein_price_id,
                    'table_id' => isset($reservation->table_id) ? $reservation->table_id : null,
                    'table_ids' => $table_ids,
                    'is_seated' => $reservation->reservation->is_seated,
                    'all_tables' => isset($reservation->reservation->all_tables) ? $reservation->reservation->all_tables : [],
                    'reservation_type' => $reservation->reservation->reservation_type,
                    'is_until' => $reservation->reservation->is_until,
                ]);
                $redis = LRedis::connection();
                $redis->publish('reservation_booking', json_encode([
                    'store_id'        => $store_id,
                    'channel' => $channel?$channel:'new_booking',
                    'notification_id' => 0,
                    'additional_data' => $additionalData,
                    'system_name' => 'Reservation'
                ]));
            } else if ($channel && $channel == 'booking_table_change') {
                $additionalData = json_encode([
                    'reservation_id' => $reservation->reservation->id,
                    'socket_origin_client_id' => $socket_origin_client_id,
                    'reservation_date' => $reservation->reservation->getRawOriginal('res_date'),
                    'dinein_area_id' => $dinein_area_id,
                    'table_id' => isset($reservation->table_id) ? $reservation->table_id : null,
                    'is_reload' => 1,
                    'table_ids' => $table_ids,
                    'old_tables' => $oldTables,  // push old table when fire booking table change
                    'swap_id' => null, // push old table when fire booking table change
                    'all_tables' => isset($reservation->reservation->all_tables) ? $reservation->reservation->all_tables : [],
                    'from_time' => $reservation->reservation->from_time,
                    'end_time' => $reservation->reservation->end_time,
                ]);
                $redis = LRedis::connection();
                $redis->publish('reservation_booking', json_encode([
                    'store_id' => $store_id,
                    'channel' => 'booking_table_change',
                    'notification_id' => 0,
                    'additional_data' => $additionalData,
                    'system_name' => 'Reservation'
                ]));
            } else {
                $redis = LRedis::connection();
                $redis->publish('reservation_booking', json_encode([
                    'store_id'        => $store_id,
                    'channel' => $channel?$channel:'new_booking',
                    'notification_id' => 0,
                    'additional_data' => $additionalData,
                    'system_name' => 'Reservation'
                ]));
            }

            Log::info('new booking web notification success:');
        }
        return true;
    } catch (\Exception $e) {
        Log::info('New booking web notification error: ' . $e->getMessage() . $e->getLine() . $e->getFile() . ', IP address : ' . request()->ip() . ', browser : ' . request()->header('User-Agent'));
    }
}

if (!function_exists('getDutchDateTable')) {
	/**
	 * @param $date
	 * @param bool $is_takeaway
	 * @return string
	 */
	function getDutchDateTable($date, $is_takeaway = false)
	{
		$dutchDayNames = [
			'Sunday' => 'zondag',
			'Monday' => 'maandag',
			'Tuesday' => 'dinsdag',
			'Wednesday' => 'woensdag',
			'Thursday' => 'donderdag',
			'Friday' => 'vrijdag',
			'Saturday' => 'zaterdag'
		];
		$monthNames = [
			"January" => "januari",
			"February" => "februari",
			"March" => "maart",
			"April" => "april",
			"May" => "mei",
			"June" => "juni",
			"July" => "juli",
			"August" => "augustus",
			"September" => "september",
			"October" => "oktober",
			"November" => "november",
			"December" => "december",
		];

		if ($is_takeaway) {
			return $dutchDayNames[Carbon::parse($date)->format('l')] . ' (' .
				Carbon::parse($date)->format('d') . ' ' .
				$monthNames[Carbon::parse($date)->format('F')] . ')';
		}
		return $dutchDayNames[Carbon::parse($date)->format('l')] . ' ' .
			Carbon::parse($date)->format('d') . ' ' .
			$monthNames[Carbon::parse($date)->format('F')];
	}
}
if (!function_exists('getDutchDate')) {
	/**
	 * @param $date
	 * @param bool $day2let
	 * @return string
	 */
	function getDutchDate($date, $day2let = false)
	{
		$dutchDayNames = [
			'Sunday' => 'zondag',
			'Monday' => 'maandag',
			'Tuesday' => 'dinsdag',
			'Wednesday' => 'woensdag',
			'Thursday' => 'donderdag',
			'Friday' => 'vrijdag',
			'Saturday' => 'zaterdag'
		];


		$monthNames = [
			"January" => "januari",
			"February" => "februari",
			"March" => "maart",
			"April" => "april",
			"May" => "mei",
			"June" => "juni",
			"July" => "juli",
			"August" => "augustus",
			"September" => "september",
			"October" => "oktober",
			"November" => "november",
			"December" => "december",
		];
		$day = $day2let ? appDutchDay2Letter(Carbon::parse($date)->format('l')) : $dutchDayNames[Carbon::parse($date)->format('l')];
		return $day . ' ' . Carbon::parse($date)->format('d') . ' ' .
			$monthNames[Carbon::parse($date)->format('F')] . ' ' .
			Carbon::parse($date)->format('Y');
	}
}
if (!function_exists('appDutchDay2Letter')) {
	/**
	 * @param $day
	 * @return string
	 */
	function appDutchDay2Letter($day)
	{
		$dutchDayNames = [
			'Sunday' => 'Zo',
			'Monday' => 'Ma',
			'Tuesday' => 'Di',
			'Wednesday' => 'Wo',
			'Thursday' => 'Do',
			'Friday' => 'Vr',
			'Saturday' => 'Za'
		];
		return $dutchDayNames[$day];
	}
}

function getTotalPersonFromReservations ($check_all_reservation,$meal,$slot_time) {
    $time_limit_of_reservation = (isset($meal->time_limit) && $meal->time_limit) ? $meal->time_limit : 120;
    //Slot End Time of the
    $end_time = Carbon::parse($slot_time)
        ->addMinutes($time_limit_of_reservation)
        ->format('H:i');
    if ($end_time == '00:00') {
        $end_time = '24:00';
    } elseif (strtotime($end_time) < strtotime($slot_time)) {
        $end_time = '24:00';
    }

    return collect($check_all_reservation)->filter(function ($reservation) use($slot_time,$end_time) {
        return (strtotime($slot_time) > strtotime($reservation->from_time) && strtotime($slot_time) < strtotime($reservation->end_time)) ||
            (strtotime($reservation->from_time) > strtotime($slot_time) && strtotime($reservation->from_time) < strtotime($end_time))  ||
            (strtotime($slot_time) == strtotime($reservation->from_time));
    })->sum('person');
}
