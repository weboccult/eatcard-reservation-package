<?php

namespace Weboccult\EatcardReservation;


use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;
use Weboccult\EatcardReservation\Models\DineinPriceCategory;
use Weboccult\EatcardReservation\Models\DineinPrices;
use Weboccult\EatcardReservation\Models\StoreReservation;
use Weboccult\EatcardReservation\Models\Meal;
use Weboccult\EatcardReservation\Models\Store;
use Carbon\Carbon;
use Weboccult\EatcardReservation\Models\StoreSlotModified;
use Weboccult\EatcardReservation\Models\StoreWeekDay;
use function Weboccult\EatcardReservation\Helper\createNewReservation;
use function Weboccult\EatcardReservation\Helper\currentMonthDisabledDatesList;
use function Weboccult\EatcardReservation\Helper\dataModelSlots;
use function Weboccult\EatcardReservation\Helper\disableDayByAdmin;
use function Weboccult\EatcardReservation\Helper\generateRandomNumberV2;
use function Weboccult\EatcardReservation\Helper\generateReservationId;
use function Weboccult\EatcardReservation\Helper\getActiveMeals;
use function Weboccult\EatcardReservation\Helper\getAnotherMeetingUsingIgnoringArrangmentTime;
use function Weboccult\EatcardReservation\Helper\isValidReservation;
use function Weboccult\EatcardReservation\Helper\tableAssign;
use function Weboccult\EatcardReservation\Helper\getDisable;
use function Weboccult\EatcardReservation\Helper\getNextEnableDates;
use function Weboccult\EatcardReservation\Helper\getStoreBySlug;
use function Weboccult\EatcardReservation\Helper\modifiedSlots;
use function Weboccult\EatcardReservation\Helper\reservedTimeSlot;
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
    /**
     * @param $store_slug
     *
     * @return EatcardReservation
     */
    public $store_slug;

    private $date;

    public $specific_date;

    public $activeSlots = [];

    private $store = '';

    /**
     * @param $date
     *
     * @return EatcardReservation
     */
    public function datefetch($date)
    {
        $this->date = $date;
        return $this;
    }

	/**
	 * @param $slug : String
	 * @param $data : Array
	 * @return array
	 * @Description get given store slug slots based on given data
	 */
	public function getSlotsMonthly($slug,$data){

        $this->store = getStoreBySlug($slug);

        //Find current month
        $current_month = Carbon::now()->format('m');
        $current_year = Carbon::now()->format('Y');
        //Find Get month by User
        $get_month = $data['month'];
        $get_year = $data['year'];
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

        $currentMonthDisabledDates = currentMonthDisabledDatesList($this->store,$current_month_str);

        $disableDayByAdmin = disableDayByAdmin($this->store,$current_month_str);

        $weekOffDay = weekOffDay($this->store,$current_month_str,$current_year_str);

        $modifiedSlots = modifiedSlots($this->store,$current_month_str);

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
     * @param null $slot_time
     * @param null $slot_model
     * @return array
     */
    public function slots($store_slug, $specific_date, $slot_time = null, $slot_model = null)
    {

        $this->store = getStoreBySlug($store_slug);

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

        if(!is_null($slot_time)) {
            $isSlotModifiedAvailable = $isSlotModifiedAvailable->where('from_time', $slot_time)->count();
        } else {
            $isSlotModifiedAvailable = $isSlotModifiedAvailable->count();
        }

        if ($meal->is_meal_res) {
            $isSlotModifiedAvailable = 1;
        }
        $this->activeSlots = [];
        if ($isSlotModifiedAvailable > 0 && ($slot_model == 'StoreSlotModified' || is_null($slot_model))) {
            $this->activeSlots =  specificDateSlots($this->store,$specific_date, $slot_time, $slot_model);
        }else if ($dayCheck) {
            $this->activeSlots = specificDaySlots($this->store, $slot_time);
            $general_slots = generalSlots($this->store, $slot_time);
            $this->activeSlots = array_merge($this->activeSlots, $general_slots);
            $this->activeSlots = superUnique(collect($this->activeSlots), 'from_time');
        }else{
            $this->activeSlots = generalSlots($this->store, $slot_time);
        }
        $this->activeSlots += mealSlots($this->store, $slot_time);

        //Curent Time
        $currentTime = Carbon::now()->format('G:i');

        if ($specific_date == Carbon::now()->format('Y-m-d')) {
            foreach ($this->activeSlots as $activeSloteKey => $activeCurrentSlot) {
                if (strtotime($activeCurrentSlot['from_time']) < strtotime($currentTime) && $this->activeSlots[$activeSloteKey]['is_slot_disabled'] == 0) {
                    $this->activeSlots[$activeSloteKey]['is_slot_disabled'] = 1;
                }
            }
        }

        foreach ($disableByDay as $each){
            if($each == $specific_date){
                $this->activeSlots = [];
            }
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
     * @param $slot_time
     * @return EatcardReservation
     */
    public function getMeals($store_id, $specific_date,$person,$slot_time, $slot_model){
        $slotAvailableMeals = [];

        $store_slug = Store::query()
                            ->where('id',$store_id)
                            ->get('store_slug')
                            ->first();
            $availableSlots =  $this->slots($store_slug->store_slug, $specific_date,$slot_time, $slot_model)['active_slots'];

//            $availableSlots = $availableSlots->where('from_time',$slot_time)
            foreach ($availableSlots as $slotMeals) {
                if($slotMeals['from_time'] == $slot_time) {
                    if ($slotMeals['max_entries'] == 'unlimited' || $slotMeals['max_entries'] >= $person && $slotMeals['from_time'] == $slot_time) {
                        $slotAvailableMeals[] = $slotMeals['meal_id'];
                    }
                }elseif ($slotMeals['from_time'] != $slot_time){
                        $slotNotMatched[] = $slotMeals['meal_id'];
                }
            }

        $activeMeals = getActiveMeals($slotAvailableMeals,$specific_date,$store_id,$slot_time,$person);

            $slotAvailableMeals += $activeMeals;

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
        $checkDisable = getDisable($store_id, $specific_date,$person,$slot_active_meals,$store);

        $disable = $checkDisable;

        $rservedTime = reservedTimeSlot($store_id,$specific_date,$slot_active_meals,$person,$store,$disable);

        $disable = $rservedTime['disable'];

        $reservationDetails['meals'] = $slot_active_meals;
        $reservationDetails['disable'] = $disable;
        $reservationDetails['message'] = $class;
        $reservationDetails['reserv_time'] = $rservedTime['time_slot'];

        return $reservationDetails;
    }

    public function autoTableAssign($newReservationDetail,$data,$store,$reservation_check_attempt,$newReservationStatus){
        $autoAssignedTable = tableAssign($newReservationDetail,$data,$store,$reservation_check_attempt,$newReservationStatus);
        return $autoAssignedTable;
    }

    public function reservationData($data){
        $store = getStoreBySlug($data['store_slug']);
        $meal = Meal::query()->findOrFail($data['meal_type']);

        /*If ayce reservation was available then create ayce reservation other wise create cart reservation*/
        $data['dinein_price_id'] = 0;
        $data['reservation_type'] = 'cart';
        if(isset($store) && isset($store->storeButler) && isset($store->storeButler->is_buffet) && $store->storeButler->is_buffet == 1) {
            $meal_id = $data['meal_type'];
            $dinein_categories = DineinPriceCategory::with(['prices' => function($q1) use ($meal_id) {
                $q1->where('meal_type', $meal_id);
            }])->where('store_id', $store->id)->get();
            $week_array = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];
            $current_day = Carbon::parse(request()->date)->format('l');
            $dine_price_selected = true;
            foreach ($dinein_categories as $dinein_category) {
                if($dinein_category->to_day && $dinein_category->from_day) {
                    if($dine_price_selected && isset($dinein_category->prices) && count($dinein_category->prices) > 0) {
                        if($week_array[$dinein_category->from_day] == $week_array[$current_day] || $week_array[$dinein_category->to_day] == $week_array[$current_day]) {
                            $dine_price_selected = false;
                        } elseif ($week_array[$dinein_category->from_day] <= $week_array[$dinein_category->to_day]) {
                            $range_array = range($week_array[$dinein_category->from_day], $week_array[$dinein_category->to_day]);
                            if(in_array($week_array[$current_day], $range_array)) {
                                $dine_price_selected = false;
                            }
                        } elseif ($week_array[$dinein_category->from_day] > $week_array[$dinein_category->to_day]) {
                            $range_array = range($week_array[$dinein_category->from_day] + 1, $week_array[$dinein_category->to_day] - 1);
                            if(!in_array($week_array[$current_day], $range_array)) {
                                $dine_price_selected = false;
                            }
                        }
                        if(!$dine_price_selected) {
                            $data['dinein_price_id'] = $dinein_category->prices[0]->id;
                            $data['reservation_type'] = 'all_you_eat';
                            if (isset($data['reservation_type']) && $data['reservation_type'] == 'all_you_eat') {
                                $all_you_eat_data['no_of_adults'] = $data['person'];
                                $all_you_eat_data['no_of_kids2'] = 0;
                                $all_you_eat_data['no_of_kids'] = 0;
                                $all_you_eat_data['kids_age'] = [];
                                $all_you_eat_data['dinein_price'] = DineinPrices::with(['dineInCategory', 'meal', 'dynamicPrices'/* => function($q1) { }*/])->where('id', $data['dinein_price_id'])->first();
                                $data['all_you_eat_data'] = json_encode($all_you_eat_data, true);
                            }
                        }
                    }
                }
            }
        }
        $slot = dataModelSlots($data['data_model'],$data['slot_id']);
        if(isset($slot['error'])){
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
        if (isset($store) && ($data['res_date'] < Carbon::now()->format('Y-m-d') || ($data['res_date'] == Carbon::now()->format('Y-m-d') && strtotime($slot->from_time) <= strtotime($current24Time)))) {
            Log::info("Make past date and time always disabled");
            return [
                'code' => '400',
                'status' => 'error',
                'error' => 'error_date_time_frame'
            ];
        }

        //Fetch the already created reservations
        $allReservations = StoreReservation::query()
            ->where('res_date', $data['res_date'])
            ->where('store_id', $store->id)
            ->whereNotIn('status', ['declined', 'cancelled'])
            ->where('meal_type', $data['meal_type'])
            ->where('is_seated', '!=', 2)
            ->where(function ($q1) {
                $q1->whereIn('local_payment_status', ['paid', '', 'pending'])->orWhere('original_total_price', null); // local_payment_status maintain 4 status (paid, ''/null, failed, pending)
            })
            ->get();
        Log::info("All reservation" . $allReservations);

        //Owner request true or false
        $is_owner = false;
        Log::info("Is Owner" . $is_owner);

        $is_valid_reservation = isValidReservation($data, $store, $slot);

        // If current day is off then return error message
        if (isset($store) && $data['res_date'] == Carbon::now()->format('Y-m-d') && $store->reservation_off_chkbx == 1) {
            return [
                'code' => '400',
                'status' => 'error',
                'error' => 'error_current_day_frame'
            ];
        }

        //Count the reservations based on from time and meal type
        $count = $allReservations
            ->where('from_time', $slot->from_time)
            ->where('meal_type', $data['meal_type'])
            ->count();

        if ($slot->max_entries != 'Unlimited' && $count >= $slot->max_entries && !$is_owner) {
            $is_valid_reservation = false;
        }
        if ($slot->is_slot_disabled && $data['res_date'] == Carbon::now()->format('Y-m-d')) {
            $is_valid_reservation = false;
        }

        $assignPersons = 0;

        //Check all reservation with each reservation
        foreach ($allReservations as $reservation_each) {
            $another_meet = getAnotherMeetingUsingIgnoringArrangmentTime($reservation_each, $slot);
            if ($another_meet) {
                $assignPersons += $reservation_each->person;
            }
        }

        //Remain person number
        $remainPersons = (int)$slot->max_entries - (int)$assignPersons;
        if ($slot->max_entries != 'Unlimited' && $data['person'] > $remainPersons && !$is_owner) {
            $is_valid_reservation = false;
        }
        if ($slot->max_entries != 'Unlimited' && $data['person'] > $slot->max_entries && !$is_owner) {
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
        $data['store_id'] = $store->id;
        $data['slot_id'] = $slot->id;
        $data['from_time'] = Carbon::parse($slot->from_time)->format('H:i');
        $data['res_time'] = Carbon::parse($slot->from_time)->format('H:i');
        $data['to_time'] = $slot->to_time;
        $data['user_id'] = isset($data['user_id']) ? $data['user_id'] : null;
        $data['status'] = 'pending';
        $data['reservation_id'] = generateReservationId();
        $data['reservation_sent'] = 0;
        $data['slot_model'] = (isset($data['data_model']) && $data['data_model'] == 'StoreSlot') ? 'StoreSlot' : 'StoreSlotModified';
        $time_limit = ($meal->time_limit) ? (int)$meal->time_limit : 120;
        $data['end_time'] = Carbon::parse($slot->from_time)->addMinutes($time_limit)->format('H:i');

        if (strtotime($slot->from_time) > strtotime($data['end_time'])) {
            $data['end_time'] = '23:59';
        }

        if ($store->auto_approval) {
            if ($store->auto_approve_condition == 'lt') {
                if ($data['person'] <= $store->auto_approve_members) {
                    $data['status'] = 'approved';
                }
            }
            if ($store->auto_approve_condition == 'gt') {
                if ($data['person'] >= $store->auto_approve_members) {
                    $data['status'] = 'approved';
                }
            }
        }

        if ($store->auto_approve_booking_with_comment && $data['comments']) {
            Log::info('if comment available then status was pending');
            $data['status'] = 'pending';
        }

        if(($meal->payment_type == 1 || $meal->payment_type == 3) && $meal->price){
            $data['total_price'] = $meal->price * $data['person'];
            $data['original_total_price'] = $meal->price * $data['person'];
            $data['payment_type'] = ($meal->payment_type == 1) ? 'full_payment' : (($meal->payment_type == 3) ? 'partial_payment' : "");
            $data['is_manually_cancelled'] = 0;
            $data['payment_status'] = 'pending';
            $data['local_payment_status'] = 'pending';
        }else{
            $data['payment_status'] = '';
            $data['local_payment_status'] = '';
        }
        if(!($meal->payment_type == 1 || $meal->payment_type == 3) && !$meal->price){
            $data['payment_method_type'] = '';
            $data['method'] = '';
        }
        $data['created_from'] = 'reservation';
        if(!$meal->price && $meal->payment_type != 1 && $meal->payment_type != 3) {

        } else {
            $data['payment_status'] = 'pending';
            $data['local_payment_status'] = 'pending';
        }
        $data['gastpin'] = generateRandomNumberV2();

        $data_model = $data['data_model'];
        unset($data['data_model']);

        $new_reservation = createNewReservation($meal,$data,$data_model,$store);

        return $new_reservation;

    }

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
            'Date can not be empty.!'      => empty($this->date),
        ];
        foreach ($conditions as $ex => $condition) {
            if ($condition) {
//                throw new Exception($ex);
            }
        }
        try{
            $slotData['slots'] = $this->slots();
            $slotData['current_time'] = Carbon::now()->format('G:i');
            $slotData['today_booking_endtime'] = $this->bookingOffTime();
            return $slotData;
        }catch (\Exception $e){
            Log::error('Reservation : Create new slots'. 'Message | ' . $e->getMessage() . 'File | ' . $e->getFile(). 'Line | ' . $e->getLine());
        }

    }
}
