<?php

namespace Weboccult\EatcardReservation;


use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;
use Weboccult\EatcardReservation\Models\DineinPriceCategory;
use Weboccult\EatcardReservation\Models\DineinPrices;
use Weboccult\EatcardReservation\Models\GiftPurchaseOrder;
use Weboccult\EatcardReservation\Models\StoreReservation;
use Weboccult\EatcardReservation\Models\Meal;
use Weboccult\EatcardReservation\Models\Store;
use Carbon\Carbon;
use Weboccult\EatcardReservation\Models\StoreSlotModified;
use Weboccult\EatcardReservation\Models\StoreWeekDay;
use Weboccult\EatcardReservation\Models\Table;
use function Weboccult\EatcardReservation\Helper\createNewReservation;
use function Weboccult\EatcardReservation\Helper\currentMonthDisabledDatesList;
use function Weboccult\EatcardReservation\Helper\dataModelSlots;
use function Weboccult\EatcardReservation\Helper\disableDayByAdmin;
use function Weboccult\EatcardReservation\Helper\generateRandomNumberV2;
use function Weboccult\EatcardReservation\Helper\generateReservationId;
use function Weboccult\EatcardReservation\Helper\getActiveMeals;
use function Weboccult\EatcardReservation\Helper\getAnotherMeetingUsingIgnoringArrangementTime;
use function Weboccult\EatcardReservation\Helper\isValidReservation;
use function Weboccult\EatcardReservation\Helper\remainingSeatCheckDisable;
use function Weboccult\EatcardReservation\Helper\tableAssign;
use function Weboccult\EatcardReservation\Helper\getDisable;
use function Weboccult\EatcardReservation\Helper\getNextEnableDates;
use function Weboccult\EatcardReservation\Helper\getStoreBySlug;
use function Weboccult\EatcardReservation\Helper\modifiedSlots;
use function Weboccult\EatcardReservation\Helper\SpecificDateSlots;
use function Weboccult\EatcardReservation\Helper\specificDaySlots;
use function Weboccult\EatcardReservation\Helper\generalSlots;
use function Weboccult\EatcardReservation\Helper\mealSlots;
use function Weboccult\EatcardReservation\Helper\superUnique;
use function Weboccult\EatcardReservation\Helper\weekOffDay;

class EatcardReservation
{
    /**
     * @param $name
     *
     * @return mixed
     **/
    // Build your next great package.
    public function hello($name)
    {
        return $name;
    }

    public $store_slug;

    private $date;

    public $slug;

    public $data;

    public $specific_date;

    public $activeSlots = [];

    private $store = '';


    /**
     * @param $date
     * @return  EatcardReservation
     * @description Test demo
     */
    public function dateFetch($date): self
    {
        $this->date = $date;
        return $this;
    }

    /**
     * @param $slug
     * @return EatcardReservation
     */
    public function slug($slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    /**
     * @param $data
     * @return EatcardReservation
     */
    public function data($data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @param $slug : String
     * @param $data : Array
     * @return array
     * @Description get given store slug slots based on given data
     */
    public function getSlotsMonthly()
    {

        $this->store = getStoreBySlug($this->slug);

        //Find current month
        $current_month = Carbon::now()->format('m');
        $current_year = Carbon::now()->format('Y');
        //Find Get month by User
        $get_month = $this->data['month'];
        $get_year = $this->data['year'];
        $get_data = [];

        //User select month
        if ($get_month == null && $get_year == null) {
            $current_month_str = $current_month;
            $current_year_str = $current_year;
        } else {
            $current_month_str = $get_month;
            $current_year_str = $get_year;
        }

        //Given dates using months and years
        $yearAndDate = Carbon::create($current_year_str)->month($current_month_str);

        $monthStart = $yearAndDate->startOfMonth()->format('Y-m-d');
        $monthEnd = $yearAndDate->endOfMonth()->format('Y-m-d');
        $ranges = CarbonPeriod::create($monthStart, $monthEnd);

        $dates = [];
        foreach ($ranges as $date) {
            $dates[] = $date->format('Y-m-d');
        }

        $currentMonthDisabledDates = currentMonthDisabledDatesList($this->store, $current_month_str);

        $disableDayByAdmin = disableDayByAdmin($this->store, $current_month_str);

        $weekOffDay = weekOffDay($this->store, $current_month_str, $current_year_str);

        $modifiedSlots = modifiedSlots($this->store, $current_month_str);

        //First Day Of month Activate
        $modified_days = StoreWeekDay::query()
            ->where('store_id', $this->store->id)
            ->where('is_week_day_meal', 0)
            ->whereNull('is_active')
            ->get()
            ->pluck('name')
            ->toArray();

        $firstDay = '';
        if ($current_month_str == Carbon::now()->format('m')) {
            $firstDay = getNextEnableDates($weekOffDay, Carbon::now()->format('Y-m-d'), $modified_days);
            $bookingOffData = $this->checkBookingOffFirstDay($firstDay, $weekOffDay, $this->store);
            $firstDay = $bookingOffData['firstDayOfMonth'];
        }

        $two_arr_day_week = array_merge($disableDayByAdmin, $weekOffDay);
        $unique = array_merge($two_arr_day_week, $modifiedSlots);

        $result = array_merge($currentMonthDisabledDates, array_unique($unique));
        $result = array_unique($result);

        $get_data['month'] = $current_month_str;
        $get_data['First_day_of_month'] = $firstDay;
        $get_data['all_disable_dates'] = array_values($result);
        return $get_data;
    }

    /**
     * @param $store_slug
     * @param $specific_date
     * @param $section_id
     * @param null $slot_time
     * @param null $slot_model
     * @return array
     * @description Return the available slots array
     */
    public function slots($store_slug = null,$slot_time = null, $slot_model = null)
    {
        if(isset($store_slug['store_slug'])){
            $this->store = getStoreBySlug($store_slug['store_slug']);
        }else{
            $this->store = getStoreBySlug($this->data['store_slug']);
        }

        if(isset($this->data['res_date'])){
            $specific_date = $this->data['res_date'];
        }else{
            $specific_date = $this->data['date'];
        }
        $section_id = $this->data['section_id'];

        $disableByDay = [];

        if (Carbon::parse($specific_date)->format('m') == Carbon::now()->format('m')) {
            $disableByDay = $this->store->getReservationOffDates($this->store);
        } else {
            $disableByDay = [];
        }

        //Specific Date Wise Slots
        $this->date = $specific_date;
        $getDayFromUser = date('l', strtotime($specific_date));

        $dayCheck = StoreWeekDay::query()
            ->where('store_id', $this->store->id)
            ->where('name', $getDayFromUser)
            ->first();

        $meals = Meal::query()
            ->where('store_id', $this->store->id)
            ->where('status', 1)
            ->get();

        $storeWeekMeal = [];
        $storeIsMeal = [];

        foreach ($meals as $meal) {
            if (!$meal->is_meal_res && $meal->is_week_meal_res) {
                $storeWeekMeal[] = $meal->id;
            } elseif ($meal->is_meal_res) {
                $storeIsMeal[] = $meal->id;
            }
        }

        $isSlotModifiedAvailable = StoreSlotModified::query()
            ->where('store_id', $this->store->id)
            ->where('is_day_meal', 0)
            ->where('store_date', $specific_date)
            ->where('is_available', 1);

        if (!is_null($slot_time)) {
            $isSlotModifiedAvailable = $isSlotModifiedAvailable->where('from_time', $slot_time)->count();
        } else {
            $isSlotModifiedAvailable = $isSlotModifiedAvailable->count();
        }

        foreach ($meals as $meal){
            if ($meal->is_meal_res) {
                $isSlotModifiedAvailable = 1;
            }
        }
        $this->activeSlots = [];
        if ($isSlotModifiedAvailable > 0 && ($slot_model == 'StoreSlotModified' || is_null($slot_model))) {
            $this->activeSlots = specificDateSlots($this->store, $specific_date, $slot_time, $slot_model);
        } else if ($dayCheck) {
            $this->activeSlots = specificDaySlots($this->store, $slot_time);
            $general_slots = generalSlots($this->store, $slot_time);
            $this->activeSlots = array_merge($this->activeSlots, $general_slots);
            $this->activeSlots = superUnique(collect($this->activeSlots), 'from_time');
        } else {
            $this->activeSlots = generalSlots($this->store, $slot_time);
        }
        $this->activeSlots += mealSlots($this->store, $slot_time);

        //Current Time
        $currentTime = Carbon::now()->format('G:i');

        if ($specific_date == Carbon::now()->format('Y-m-d')) {
            foreach ($this->activeSlots as $activeSloteKey => $activeCurrentSlot) {
                if (strtotime($activeCurrentSlot['from_time']) < strtotime($currentTime) && $this->activeSlots[$activeSloteKey]['is_slot_disabled'] == 0) {
                    $this->activeSlots[$activeSloteKey]['is_slot_disabled'] = 1;
                }
            }
        }

        foreach ($disableByDay as $each) {
            if ($each == $specific_date) {
                $this->activeSlots = [];
            }
        }

        $table = Table::leftjoin('dining_areas', function ($sq) {
            $sq->on('dining_areas.id', '=', 'tables.dining_area_id');
        })->where('tables.status', 1)
            ->where('tables.online_status', 1)
            ->where('dining_areas.status', 1);

        if (isset($section_id)) {
            $table = $table->where('dining_areas.id', $section_id);
        }
        $table = $table->get();

        if ($table->count() == 0) {
            $this->activeSlots = [];
        }
        Log::info("Slots fetched Successfully!!!");
        return [
            "active_slots" => $this->activeSlots,
            "booking_off_time" => $this->store->booking_off_time
        ];
    }

    /**
     * @param $store_id
     * @param $specific_date
     * @param $person
     * @param $slot_time
     * @param $slot_model
     * @param $data
     * @return EatcardReservation
     * @description Get Meal Data
     */
    public function getMeals()
    {
        $store_id = $this->data['store_id'];
        if(isset($this->data['res_date'])){
            $specific_date = $this->data['res_date'];
        }else{
            $specific_date = $this->data['date'];
        }
        $person = $this->data['person'];
        if(isset($this->data['from_time'])){
            $slot_time = $this->data['from_time'];
        }else{
            $slot_time = $this->data['slot_time'];
        }
        $slot_model = $this->data['slot_model'];
        $slotAvailableMeals = [];
        $section_id = $this->data['section_id'];

        $store_slug = Store::query()
            ->where('id', $store_id)
            ->get('store_slug')
            ->first();
//        $this->data[1]['store_slug'] = $store_slug;

        $availableSlots = $this->slots($store_slug,$slot_time, $slot_model)['active_slots'];

        foreach ($availableSlots as $slotMeals) {
            if ($slotMeals['from_time'] == $slot_time) {
                if ($slotMeals['max_entries'] == 'unlimited' || $slotMeals['max_entries'] >= $person && $slotMeals['from_time'] == $slot_time) {
                    $slotAvailableMeals[] = $slotMeals['meal_id'];
                }
            } elseif ($slotMeals['from_time'] != $slot_time) {
                $slotNotMatched[] = $slotMeals['meal_id'];
            }
        }

        $activeMeals = getActiveMeals($slotAvailableMeals, $specific_date, $store_id, $slot_time, $person);

        $slotAvailableMeals += $activeMeals;

        //Fetch the details of meal based on meals id
        $slot_active_meals = Meal::query()
            ->where('status', 1)
            ->whereIn('id', $slotAvailableMeals)
            ->get();

        $current24Time = Carbon::now()->format('G:i');
        $disable = '';
        $class = '';

        $store = getStoreBySlug($store_slug->store_slug);

        // Booking time off for current day checking
        if ($store->is_booking_enable == 1 && $specific_date === Carbon::now()->format('Y-m-d')) {

            if ($store->booking_off_time == "00:00") {
                $store->booking_off_time = "24:00";
            }

            // Checking if time has been past for today
            if (strtotime($slot_time) <= strtotime($current24Time)) {
                $disable = 'true';
                $class = 'before_msg';
            }

            // check the booking off time with slot time
            if (strtotime($current24Time) >= strtotime($store->booking_off_time)) {
                if (strtotime($slot_time) >= strtotime($current24Time)) {
                    $disable = 'true';
                    $class = 'after_msg';
                }
            }
        }
        $final_available_meals = [];
        foreach ($slot_active_meals as $meal) {
            $checkDisable = getDisable($store_id, $specific_date, $person, $meal, $store, $slot_time);
            if ($checkDisable == 'false') {
                $final_available_meals[] = $meal;
            }
            $disable = $checkDisable;
        }

        foreach ($final_available_meals as $meal) {
            $disableStatus = remainingSeatCheckDisable($store_id, $specific_date, $meal, $person, $store, $disable, $this->data);
            $disable = $disableStatus['disable'];
        }


        $reservationDetails['meals'] = $final_available_meals;
        $reservationDetails['disable'] = $disable;
        $reservationDetails['message'] = $class;

        return $reservationDetails;
    }

    /**
     * @param $newReservationDetail
     * @param $data
     * @param $store
     * @param $reservation_check_attempt
     * @param $newReservationStatus
     * @return mixed
     * @description Use in CRON and return reservation details
     */
    public function autoTableAssign()
    {
        return tableAssign($this->data);
    }

    /**
     * @param $data
     * @return array|string[]|Facade\EatcardReservation
     * @description Create New Reservation
     */
    public function reservationData()
    {
        $store = getStoreBySlug($this->data['store_slug']);
        $meal = Meal::query()->findOrFail($this->data['meal_type']);

        /*If ayce reservation was available then create ayce reservation other wise create cart reservation*/
        $this->data['dinein_price_id'] = 0;
        $this->data['reservation_type'] = 'cart';
        if (isset($store) && isset($store->storeButler) && isset($store->storeButler->is_buffet) && $store->storeButler->is_buffet == 1) {
            $meal_id = $this->data['meal_type'];
            $dinein_categories = DineinPriceCategory::with(['prices' => function ($q1) use ($meal_id) {
                $q1->where('meal_type', $meal_id);
            }])->where('store_id', $store->id)->get();
            $week_array = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];
            $current_day = Carbon::parse(request()->date)->format('l');
            $dine_price_selected = true;
            foreach ($dinein_categories as $dinein_category) {
                if ($dinein_category->to_day && $dinein_category->from_day) {
                    if ($dine_price_selected && isset($dinein_category->prices) && count($dinein_category->prices) > 0) {
                        if ($week_array[$dinein_category->from_day] == $week_array[$current_day] || $week_array[$dinein_category->to_day] == $week_array[$current_day]) {
                            $dine_price_selected = false;
                        } elseif ($week_array[$dinein_category->from_day] <= $week_array[$dinein_category->to_day]) {
                            $range_array = range($week_array[$dinein_category->from_day], $week_array[$dinein_category->to_day]);
                            if (in_array($week_array[$current_day], $range_array)) {
                                $dine_price_selected = false;
                            }
                        } elseif ($week_array[$dinein_category->from_day] > $week_array[$dinein_category->to_day]) {
                            $range_array = range($week_array[$dinein_category->from_day] + 1, $week_array[$dinein_category->to_day] - 1);
                            if (!in_array($week_array[$current_day], $range_array)) {
                                $dine_price_selected = false;
                            }
                        }
                        if (!$dine_price_selected) {
                            $this->data['dinein_price_id'] = $dinein_category->prices[0]->id;
                            $this->data['reservation_type'] = 'all_you_eat';
                            if (isset($this->data['reservation_type']) && $this->data['reservation_type'] == 'all_you_eat') {
                                $all_you_eat_data['no_of_adults'] = $this->data['person'];
                                $all_you_eat_data['no_of_kids2'] = 0;
                                $all_you_eat_data['no_of_kids'] = 0;
                                $all_you_eat_data['kids_age'] = [];
                                $all_you_eat_data['dinein_price'] = DineinPrices::with(['dineInCategory', 'meal', 'dynamicPrices'/* => function($q1) { }*/])->where('id', $this->data['dinein_price_id'])->first();
                                $this->data['all_you_eat_data'] = json_encode($all_you_eat_data, true);
                            }
                        }
                    }
                }
            }
        }

        //fetch the slot details
        $slot = dataModelSlots($this->data['data_model'], $this->data['from_time'], $this->data['meal_type']);
        if (isset($slot['error'])) {
            Log::info("dataModelSlots function get error message");
            return $slot;
        }

        //Check in slot verify the from time
        if (!isset($slot->from_time)) {
            Log::info('slot not founded : ' . json_encode($slot, JSON_PRETTY_PRINT));
            return [
                'code' => '400',
                'status' => 'error',
                'error' => 'error_slot_frame'
            ];
        }

        // Make past date and time always disabled then return error message
        $current24Time = Carbon::now()->format('H:i');
        if (isset($store) && ($this->data['res_date'] < Carbon::now()->format('Y-m-d') || ($this->data['res_date'] == Carbon::now()->format('Y-m-d') && strtotime($slot->from_time) <= strtotime($current24Time)))) {
            Log::info("Make past date and time always disabled");
            return [
                'code' => '400',
                'status' => 'error',
                'error' => 'error_date_time_frame'
            ];
        }

        //Fetch the already created reservations
        $allReservations = StoreReservation::query()
            ->where('res_date', $this->data['res_date'])
            ->where('store_id', $store->id)
            ->whereNotIn('status', ['declined', 'cancelled'])
            ->where('meal_type', $this->data['meal_type'])
            ->where('is_seated', '!=', 2)
            ->where(function ($q1) {
                $q1->whereIn('local_payment_status', ['paid', '', 'pending'])->orWhere('original_total_price', null); // local_payment_status maintain 4 status (paid, ''/null, failed, pending)
            })
            ->get();
        Log::info("All reservation <----->" . $allReservations);

        //Owner request true or false
        $is_owner = false;
        Log::info("Is Owner" . $is_owner);

        $is_valid_reservation = isValidReservation($this->data, $store, $slot);

        // If current day is off then return error message
        if (isset($store) && $this->data['res_date'] == Carbon::now()->format('Y-m-d') && $store->reservation_off_chkbx == 1) {
            return [
                'code' => '400',
                'status' => 'error',
                'error' => 'error_current_day_frame'
            ];
        }

        //Count the reservations based on from time and meal type
        $count = $allReservations
            ->where('from_time', $slot->from_time)
            ->where('meal_type', $this->data['meal_type'])
            ->count();

        if ($slot->max_entries != 'Unlimited' && $count >= $slot->max_entries && !$is_owner) {
            $is_valid_reservation = false;
        }
        if ($slot->is_slot_disabled && $this->data['res_date'] == Carbon::now()->format('Y-m-d')) {
            $is_valid_reservation = false;
        }

        $assignPersons = 0;

        //Check all reservation with each reservation
        foreach ($allReservations as $reservation_each) {
            $another_meet = getAnotherMeetingUsingIgnoringArrangementTime($reservation_each, $slot);
            if ($another_meet) {
                $assignPersons += $reservation_each->person;
            }
        }

        //Remain person number
        $remainPersons = (int)$slot->max_entries - (int)$assignPersons;
        if ($slot->max_entries != 'Unlimited' && $this->data['person'] > $remainPersons && !$is_owner) {
            $is_valid_reservation = false;
        }
        if ($slot->max_entries != 'Unlimited' && $this->data['person'] > $slot->max_entries && !$is_owner) {
            $is_valid_reservation = false;
        }
        if ($is_valid_reservation == false) {
            return [
                'code' => '400',
                'status' => 'error',
                'error' => 'error_person_frame'
            ];
        }


        //Fetch the data from user's details and other parameters
        $this->data['store_id'] = $store->id;
        $this->data['slot_id'] = $slot->id;
        $this->data['from_time'] = Carbon::parse($slot->from_time)->format('H:i');
        $this->data['res_time'] = Carbon::parse($slot->from_time)->format('H:i');
        $this->data['to_time'] = $slot->to_time;
        $this->data['user_id'] = isset($this->data['user_id']) ? $this->data['user_id'] : null;
        $this->data['status'] = 'pending';
        $this->data['reservation_id'] = generateReservationId();
        $this->data['reservation_sent'] = 0;
        $this->data['slot_model'] = (isset($this->data['data_model']) && $this->data['data_model'] == 'StoreSlot') ? 'StoreSlot' : 'StoreSlotModified';
        $time_limit = ($meal->time_limit) ? (int)$meal->time_limit : 120;
        $this->data['end_time'] = Carbon::parse($slot->from_time)->addMinutes($time_limit)->format('H:i');

        if (strtotime($slot->from_time) > strtotime($this->data['end_time'])) {
            $this->data['end_time'] = '23:59';
        }

        if ($store->auto_approval) {
            if ($store->auto_approve_condition == 'lt') {
                if ($this->data['person'] <= $store->auto_approve_members) {
                    $this->data['status'] = 'approved';
                }
            }
            if ($store->auto_approve_condition == 'gt') {
                if ($this->data['person'] >= $store->auto_approve_members) {
                    $this->data['status'] = 'approved';
                }
            }
            if ($store->auto_approve_booking_with_comment && $this->data['comments']) {
                Log::info('if comment available then status was pending');
                $this->data['status'] = 'pending';
            }
        }


        $this->data['coupon_price'] = 0;
        if (($meal->payment_type == 1 || $meal->payment_type == 3) && $meal->price) {
            $this->data['total_price'] = $meal->price * $this->data['person'];
            $this->data['original_total_price'] = $meal->price * $this->data['person'];
            $this->data['payment_type'] = ($meal->payment_type == 1) ? 'full_payment' : (($meal->payment_type == 3) ? 'partial_payment' : "");
            $this->data['is_manually_cancelled'] = 0;
            $this->data['payment_status'] = 'pending';
            $this->data['local_payment_status'] = 'pending';
        } else {
            $this->data['payment_status'] = '';
            $this->data['local_payment_status'] = '';
        }

        /*verify qr_code*/
        if (isset($this->data['qr_code'])) {

            //Fetch the Applied gift card price for calculation
            $giftCardPrice = GiftPurchaseOrder::query()
                ->where('qr_code', $this->data['qr_code'])
                ->whereDate('expire_at', '>', Carbon::now()->toDateString())
                ->first();

            Log::info("Gift card price : " . $giftCardPrice->total_price);

            if (($giftCardPrice->total_price > 0)) {
                if ($this->data['total_price'] <= $giftCardPrice->total_price) {
                    Log::info("Meal * person < gift price");

                    $this->data['total_price'] = 0;
                    $this->data['coupon_price'] = $this->data['total_price'];
                    Log::info("total_price : " . $this->data['total_price'] . " < " . " coupon_price : " . $this->data['coupon_price'] . " Gift coupon purchase id : " . $giftCardPrice->id);

                } elseif ($this->data['total_price'] > $giftCardPrice->total_price) {
                    Log::info("Meal * person > gift price");

                    $this->data['total_price'] -= $giftCardPrice->total_price;
                    $this->data['coupon_price'] = $giftCardPrice->total_price;
                    Log::info("total_price : " . $this->data['total_price'] . " > " . " coupon_price : " . $this->data['coupon_price'] . " Gift coupon purchase id : " . $giftCardPrice->id);

                }
            }
        }

        if (!($meal->payment_type == 1 || $meal->payment_type == 3) && !$meal->price) {
            $this->data['payment_method_type'] = '';
            $this->data['method'] = '';
        }
        $this->data['created_from'] = 'reservation';
        if (!$meal->price && $meal->payment_type != 1 && $meal->payment_type != 3) {

        } else {
            $this->data['payment_status'] = 'pending';
            $this->data['local_payment_status'] = 'pending';
        }
        $this->data['gastpin'] = generateRandomNumberV2();

        $data_model = $this->data['data_model'];
        unset($this->data['data_model']);

        if (isset($this->data['qr_code'])) {

            //Fetch the Applied gift card details for calculation
            $giftCardPrice = GiftPurchaseOrder::query()
                ->where('qr_code', $this->data['qr_code'])
                ->whereDate('expire_at', '>', Carbon::now()->toDateString())
                ->first();

            $remainingPrice = (float)$giftCardPrice->total_price - (float)$this->data['original_total_price'];
            Log::info("Applied Gift Card to update reservation total price : " . $remainingPrice);

            if ($remainingPrice >= 0) {
                Log::info("Total price zero for payment and remaining Gift card price");
                $this->data['total_price'] = 0;
                $this->data['coupon_price'] = $this->data['original_total_price'];
                $this->data['gift_purchase_id'] = $giftCardPrice->id;
            } elseif ($remainingPrice < 0) {
                Log::info("Total price in minus then add left out price in total price");
                $this->data['total_price'] = $this->data['original_total_price'] - (float)$giftCardPrice->total_price;
                $this->data['coupon_price'] = $giftCardPrice->total_price;
                $this->data['gift_purchase_id'] = $giftCardPrice->id;
            }
        }

        return createNewReservation($meal, $this->data, $data_model, $store);

    }

    /**
     * @param $firstDay
     * @param $availDates
     * @param $store
     * @return array
     * @description Custom function for check first day booking date of month
     */
    public function checkBookingOffFirstDay($firstDay, $availDates, $store)
    {
        $bookingOffDates = $this->getBookingOffDates($store);

        if (isset($store->reservation_off_chkbx) && Carbon::now()->format('m') == request()->month && $store->reservation_off_chkbx == 1) {
            $bookingOffDates = [Carbon::now()->format('Y-m-d')];
        }

        foreach ($bookingOffDates as $disable) {
            $availDates[] = Carbon::parse($disable)->format('Y-m-d');
        }
        $availDates = array_unique($availDates);

        while (in_array(Carbon::parse($firstDay)->format('Y-m-d'), $availDates)) {
            $firstDay = Carbon::parse($firstDay)->addDay()->format('Y-m-d');
        }

        return [
            'firstDayOfMonth' => $firstDay,
            'availableDates' => $availDates
        ];
    }

    /**
     * @param $store
     * @return array
     * @description Custom function for given booking off dates
     */
    public function getBookingOffDates($store)
    {
        if ($store->reservation_off != 0) {

            $today = Carbon::now();
            $from = Carbon::createFromDate($today->format('Y'), $today->format('m'), $today->format('d'));

            $tillDay = Carbon::now()->addDay($store->reservation_off - 1);
            $to = Carbon::createFromDate($tillDay->format('Y'), $tillDay->format('m'), $tillDay->format('d'));

            return $this->generateBookingDateRange($from, $to);
        }
        return [];
    }

    /**
     * @param Carbon $start_date
     * @param Carbon $end_date
     * @return array
     * @description Generate booking date range
     */
    public function generateBookingDateRange(Carbon $start_date, Carbon $end_date)
    {
        $dates = [];
        for ($date = $start_date; $date->lte($end_date); $date->addDay()) {
            $dates[] = $date->format('m/d/Y');
        }
        return $dates;
    }

    /**
     * @param $store_slug
     * @param $date
     * @return mixed
     */
    public function dispatch()
    {
        $conditions = [
            'StoreSlug can not be empty.!' => empty($this->store_slug),
            'Date can not be empty.!' => empty($this->date),
        ];
        foreach ($conditions as $ex => $condition) {
            if ($condition) {
//                throw new Exception($ex);
            }
        }
        try {
            $slotData['slots'] = $this->slots();
            $slotData['current_time'] = Carbon::now()->format('G:i');
            $slotData['today_booking_endtime'] = $this->bookingOffTime();
            return $slotData;
        } catch (\Exception $e) {
            Log::error('Reservation : Create new slots' . 'Message | ' . $e->getMessage() . 'File | ' . $e->getFile() . 'Line | ' . $e->getLine());
        }

    }
}
