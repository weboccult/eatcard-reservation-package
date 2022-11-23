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
use Weboccult\EatcardReservation\Models\StoreSlot;
use Weboccult\EatcardReservation\Models\StoreSlotModified;
use Weboccult\EatcardReservation\Models\StoreWeekDay;
use Weboccult\EatcardReservation\Models\Table;
use function Weboccult\EatcardReservation\Helper\checkSlotMealAvailable;
use function Weboccult\EatcardReservation\Helper\createNewReservation;
use function Weboccult\EatcardReservation\Helper\currentMonthDisabledDatesList;
use function Weboccult\EatcardReservation\Helper\dataModelSlots;
use function Weboccult\EatcardReservation\Helper\disableDayByAdmin;
use function Weboccult\EatcardReservation\Helper\generateRandomNumberV2;
use function Weboccult\EatcardReservation\Helper\generateReservationId;
use function Weboccult\EatcardReservation\Helper\getActiveMeals;
use function Weboccult\EatcardReservation\Helper\getAnotherMeetingUsingIgnoringArrangementTime;
use function Weboccult\EatcardReservation\Helper\getTotalPersonFromReservations;
use function Weboccult\EatcardReservation\Helper\isValidReservation;
use function Weboccult\EatcardReservation\Helper\remainingSeatCheckDisable;
use function Weboccult\EatcardReservation\Helper\tableAssign;
use function Weboccult\EatcardReservation\Helper\getDisable;
use function Weboccult\EatcardReservation\Helper\getNextEnableDates;
use function Weboccult\EatcardReservation\Helper\getStoreBySlug;
use function Weboccult\EatcardReservation\Helper\modifiedSlotsDates;
use function Weboccult\EatcardReservation\Helper\SpecificDateSlots;
use function Weboccult\EatcardReservation\Helper\specificDaySlots;
use function Weboccult\EatcardReservation\Helper\generalSlots;
use function Weboccult\EatcardReservation\Helper\mealSlots;
use function Weboccult\EatcardReservation\Helper\superUnique;
use function Weboccult\EatcardReservation\Helper\weekOffDay;

class EatcardReservation
{
	/**TODO Remove this functions
	 * @param $name
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
	 * @return array
	 * @Description get given store slug slots based on given data
	 */
	public function getSlotsMonthly()
	{
		$this->store = getStoreBySlug($this->slug);
        if(empty($this->store)) {
            Log::warning("getSlotsMonthly : Store not be empty !!!");
            return [
                'code' => 400,
                'status' => 'error',
                'error' => 'store_not_found',
            ];
        }
		//Find current month & year
		$current_month = Carbon::now()->format('m');
		$current_year = Carbon::now()->format('Y');
		//Find Get month by User
		$selected_month = $this->data['month'];
		$selected_year = $this->data['year'];
		$get_data = [];
		//User select month
		if ($selected_month == null && $selected_year == null) {
			$selected_month = $current_month;
			$selected_year = $current_year;
		}
		//Given dates using months and years
		$yearAndDate = Carbon::create($selected_year)->month($selected_month);
		$monthStart = $yearAndDate->startOfMonth()->format('Y-m-d');
		$monthEnd = $yearAndDate->endOfMonth()->format('Y-m-d');
		$ranges = CarbonPeriod::create($monthStart, $monthEnd);
		$dates = [];
		foreach ($ranges as $date) {
			$dates[] = $date->format('Y-m-d');
		}
		//Fetch enable date list
        $fetchEnableDates = StoreSlotModified::where('store_id', $this->store->id)
            ->whereYear('store_date', $selected_year)
            ->whereMonth('store_date', $selected_month)
            ->where('is_available', 1)
            ->pluck('store_date')
            ->toArray();
        $enableDates = array_unique($fetchEnableDates,SORT_STRING);

		/*Find selected month disable dates*/
		$currentMonthDisabledDates = currentMonthDisabledDatesList($this->store, $selected_month);
		/*Find admin disable day*/
		$disableDayByAdmin = disableDayByAdmin($this->store, $selected_month);
		/*Find given month off days and dates*/
		$weekOff = weekOffDay($this->store, $selected_month, $selected_year);
		$weekOffDates = $weekOff['weekOffDates'];
		$weekOffDays = $weekOff['weekOffDays'];
		/*Get modified slots date*/
		$modifiedSlotsDates = modifiedSlotsDates($this->store, $selected_month);
		$firstDay = '';
		if ($selected_month == Carbon::now()->format('m')) {
			$firstDay = getNextEnableDates($weekOffDates, Carbon::now()->format('Y-m-d'), $weekOffDays);
			$bookingOffData = $this->checkBookingOffFirstDay($firstDay, $weekOffDates, $this->store);
			$firstDay = $bookingOffData['firstDayOfMonth'];
		}

        //When Disable days by admin and enable dates by specific date then remove enable dates
        if(!empty($disableDayByAdmin)){
            $allowReservationOverRideDates = array_diff($enableDates, $disableDayByAdmin);
            $enableDates = $allowReservationOverRideDates;
        }

		$two_arr_day_week = array_merge($disableDayByAdmin, $weekOffDates);
		$unique = array_merge($two_arr_day_week, $modifiedSlotsDates);
		$result = array_merge($currentMonthDisabledDates, array_unique($unique));
		$result = array_unique($result);
		$get_data['month'] = $selected_month;
		$get_data['First_day_of_month'] = $firstDay;
		$get_data['all_disable_dates'] = array_values($result);
        $get_data['all_enable_dates'] = array_values($enableDates);
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
	public function slots($store_slug = null, $slot_time = null, $slot_model = null)
	{
		$this->store = getStoreBySlug($this->data['store_slug'] ?? $store_slug);
		$specific_date = $this->data['res_date'] ?? $this->data['date'];
		$section_id = $this->data['section_id'];
        $person = (int)($this->data['person'] ?? 0);
        if(empty($person)){
            return [
                    'code' => '400',
                    'status' => 'error',
                    'error' => 'error_person_select'
            ];
        }
        if(empty($specific_date)){
            return [
                'code' => '400',
                'status' => 'error',
                'error' => 'error_date_select'
            ];
        }
		$disableByDay = [];
		if (Carbon::parse($specific_date)->format('m') == Carbon::now()->format('m')) {
			$disableByDay = $this->store->getReservationOffDates($this->store);
		}
        // If current day is off then return error message
        if (isset($this->store) && $specific_date == Carbon::now()->format('Y-m-d') && $this->store->reservation_off_chkbx == 1) {
            Log::info("Today close from Admin");
            return [
                "active_slots"     => [],
                "booking_off_time" => $this->store->booking_off_time,
                "disable" => false
            ];
        }
		//Specific Date Wise Slots
		$this->date = $specific_date;
		$getDayFromUser = date('l', strtotime($specific_date));
		$dayCheck = StoreWeekDay::query()->where('store_id', $this->store->id)->where('name', $getDayFromUser)->first();
		$meals = Meal::query()->where('store_id', $this->store->id)->where('status', 1)->get();
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
		$disableDate = currentMonthDisabledDatesList($this->store, Carbon::now()->format('m'));
		$fetchDate = in_array($specific_date, $disableDate);
		if(!empty($fetchDate)){
            return [
                "active_slots"     => [],
                "booking_off_time" => $this->store->booking_off_time,
                "disable" => false
            ];
        }
		$isSlotModifiedAvailable = StoreSlotModified::query()
			->where('store_id', $this->store->id)
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
		$this->activeSlots = [];
		if ($isSlotModifiedAvailable > 0 && ($slot_model == 'StoreSlotModified' || empty($slot_model))) {
			$this->activeSlots = specificDateSlots($this->store, $specific_date, $slot_time, $slot_model);
		}

		if (empty($this->activeSlots) && $dayCheck) {
            $this->activeSlots = specificDaySlots($this->store, $getDayFromUser, $slot_time, $specific_date);
            $this->activeSlots += mealSlots($this->store, $slot_time, $specific_date);
            $this->activeSlots = superUnique(collect($this->activeSlots), 'from_time');
		}
		if(empty($this->activeSlots)) {
			$this->activeSlots = generalSlots($this->store, $slot_time, $specific_date);
		}
		//Current Time
        $pastTimeSlots = [];
		$featureTimeSlots = [];
        $futureSlot = [];
		$currentTime = Carbon::now()->format('G:i');
		if ($specific_date == Carbon::now()->format('Y-m-d')) {
			foreach ($this->activeSlots as $activeSloteKey => $activeCurrentSlot) {
				if (strtotime($activeCurrentSlot['from_time']) < strtotime($currentTime) /*&& $this->activeSlots[$activeSloteKey]['is_slot_disabled'] == 0*/) {
//					$this->activeSlots[$activeSloteKey]['is_slot_disabled'] = 1;
                    $pastTimeSlots[] = $activeCurrentSlot;
				}
				else{
                    $featureTimeSlots[] = $activeCurrentSlot;
                }
			}
		}else{
            $futureSlot = $this->activeSlots;
        }
		if(!empty($featureTimeSlots)){
            $this->activeSlots = $featureTimeSlots;
        }else if(!empty($futureSlot)){
            $this->activeSlots = $futureSlot;
        }
		foreach ($disableByDay as $each) {
			if ($each == $specific_date) {
				$this->activeSlots = [];
			}
		}
		$table = Table::leftjoin('dining_areas', function ($sq) {
			$sq->on('dining_areas.id', '=', 'tables.dining_area_id');
		})->where('tables.status', 1)->where('tables.online_status', 1)->where('dining_areas.status', 1);
		if (isset($section_id)) {
			$table = $table->where('dining_areas.id', $section_id);
		}
		$table = $table->get();
		if ($table->count() == 0) {
			$this->activeSlots = [];
		}

		$disable = false;
		$current24Time = Carbon::now()->format('G:i');
		// Booking time off for current day checking
		if ($this->store->is_booking_enable == 1 && $specific_date === Carbon::now()->format('Y-m-d')) {
			if ($this->store->booking_off_time == "00:00") {
				$this->store->booking_off_time = "24:00";
			}
			Log::info(" current booking time " . $current24Time . "Booking off time : " . $this->store->booking_off_time);
			// check the booking off time with slot time
			if (strtotime($current24Time) >= strtotime($this->store->booking_off_time)) {
				$disable = true;
			}else{
				$disable = false;
			}
		}

		Log::info("Slots fetched Successfully!!!" . " | Store Id " . $this->store->id . " | Store Slug " . $this->store->store_slug);

		//Check Slot available for meal or not
        $availableSlots = [];
        $notShowSlots = [];

        $check_all_reservation = StoreReservation::query()
            ->with('tables.table.diningArea', 'meal')
            ->where('store_id', $this->store->id)
            ->where('res_date', $specific_date)
            ->whereNotIn('status', ['declined', 'cancelled'])
            ->where(function ($q1) {
                $q1->whereIn('local_payment_status', ['paid', '', 'pending'])->orWhere('total_price', null);
            })
            ->where('is_seated', '!=', 2)
            ->get();

        foreach($this->activeSlots as $slotKey => $eachSlot){
            if ($eachSlot['max_entries'] == 'Unlimited' || $eachSlot['max_entries'] >= $person ) {

                $onSlotAvailableAllMealID =  checkSlotMealAvailable($this->store->store_slug, $specific_date, $person, $eachSlot, $eachSlot['from_time'], $eachSlot['data_model']);

                $slot_active_meals = Meal::query()->where('status', 1)->whereIn('id', $onSlotAvailableAllMealID)->get();

                if (empty($slot_active_meals->count())) {
                    $disable = true;
                    continue;
                }

                $checkSlotTableAvailable = getDisable($this->store->id, $specific_date, $person, $slot_active_meals, $this->store, $eachSlot['from_time'], $disable,$check_all_reservation);

                if (!$checkSlotTableAvailable) {
                    $disableStatus = remainingSeatCheckDisable($this->store->id, $specific_date, $slot_active_meals, $person, $this->store, $disable, $this->data,$eachSlot,$check_all_reservation);
                } else {
                    Log::info("Slot disable true from remaining seat check disable : " , [$eachSlot]);
                    $disableStatus['slot_disable'] = true;
                }

                if(isset($disableStatus['slot_disable'])){
                 $this->activeSlots[$slotKey]['is_slot_disabled'] = 1;
                    $eachSlot['is_slot_disabled'] = 1;
                }else{
                    $disable = $disableStatus['disable'];
                }

                if($disable == true){
                    $notShowSlots[] = $eachSlot;
                }else{
                    $availableSlots[] = $eachSlot;
                }

            }else{
                $notShowSlots[] = $eachSlot;
                Log::info("Not Available slots : " , [$notShowSlots]);
            }
        }
        Log::info("Available slots : " , [$availableSlots]);
        return [
            "active_slots"     => $availableSlots,
            "booking_off_time" => $this->store->booking_off_time,
            "disable" => $disable
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
		if (isset($this->data['res_date'])) {
			$specific_date = $this->data['res_date'];
		}
		else {
			$specific_date = $this->data['date'];
		}
		$person = (int)($this->data['person'] ?? 0);
		if (isset($this->data['from_time'])) {
			$slot_time = $this->data['from_time'];
		}
		else {
			$slot_time = $this->data['slot_time'];
		}
		$slot_model = $this->data['slot_model'];
		$slotAvailableMealsIds = [];
		$section_id = $this->data['section_id'];
		$store_slug = Store::query()->where('id', $store_id)->pluck('store_slug')->first();
		//        $this->data[1]['store_slug'] = $store_slug;
		$availableSlots = $this->slots($store_slug, $slot_time, $slot_model)['active_slots'];

        $getDayFromUser = date('l', strtotime($specific_date));

        $check_all_reservation = StoreReservation::query()
            ->with('tables.table.diningArea', 'meal')
            ->where('store_id', $this->store->id)
            ->where('res_date', $specific_date)
            ->whereNotIn('status', ['declined', 'cancelled'])
            ->where(function ($q1) {
                $q1->whereIn('local_payment_status', ['paid', '', 'pending'])->orWhere('total_price', null);
            })
            ->where('is_seated', '!=', 2)
            ->get();

		foreach ($availableSlots as $slot) {
            $slotAvailableMealsIds = checkSlotMealAvailable($store_slug, $specific_date, $person, $slot, $slot_time, $slot_model);
        }
		$activeMeals = [];
//		if($slot_model == 'StoreSlotModified'){
//		}else {
//			$activeMeals = getActiveMeals($specific_date, $store_id, $slot_time, $person,$check_all_reservation);
//		}
//		$slotAvailableMealsIds += $activeMeals;
		//Fetch the details of meal based on meals id
		$slot_active_meals = Meal::query()->where('status', 1)->whereIn('id', $slotAvailableMealsIds)->get();
		$current24Time = Carbon::now()->format('G:i');
		$disable = false;
        if (empty($slot_active_meals->count())) {
            $disable = true;
        }
		$store = getStoreBySlug($store_slug);
		// Booking time off for current day checking
		if ($store->is_booking_enable == 1 && $specific_date === Carbon::now()->format('Y-m-d')) {

			if ($store->booking_off_time == "00:00") {
				$store->booking_off_time = "24:00";
			}
			// Checking if time has been past for today
			if (strtotime($slot_time) <= strtotime($current24Time)) {
				return [
					'code' => '400',
					'status' => 'error',
					'error' => 'error_time_has_been_past',
					'disable' => true
				];
			}
			// check the booking off time with slot time
			if (strtotime($current24Time) >= strtotime($store->booking_off_time)) {
				if (strtotime($slot_time) >= strtotime($current24Time)) {
					return [
						'code' => '400',
						'status' => 'error',
						'error' => 'error_booking_off_time',
						'disable' => true
					];
				}
			}
		}
		foreach ($slot_active_meals as $meal) {

		    //find this meal slot in modified date
		    $getAllSlotOfCurrentMeal = StoreSlotModified::query()
                    ->where('store_id',$store_id)
                    ->where('meal_id',$meal['id'])
                    ->where('from_time',$slot_time)
                    ->first();

		    $type = 'date';

		    if (empty($getAllSlotOfCurrentMeal)) {

                //find this meal slot in specific day meal
                    $getAllSlotOfCurrentMeal = StoreSlot::query()
                        ->where('store_id', $store_id)
                        ->where('meal_id', $meal['id'])
                        ->where('store_weekdays_id', '!=', null)
                        ->when(!empty($specific_date) && $specific_date == Carbon::now()->format('Y-m-d'), function ($q) {
                            $q->where('is_slot_disabled', 0);
                        })
                        ->whereHas('store_weekday', function ($q) use ($getDayFromUser) {
                            $q->where('is_active', 1)
                                ->where('is_week_day_meal', 1)
                                ->where('name', $getDayFromUser);
                        })
                        ->where('from_time', $slot_time)
                        ->first();

                    $type = 'meal day';
            }

            if (empty($getAllSlotOfCurrentMeal)) {

                //find this meal slot in specific day
                $getAllSlotOfCurrentMeal = StoreSlot::query()
                    ->where('store_id',$store_id)
                    ->where('store_weekdays_id', '!=', null)
                    ->whereHas('store_weekday', function ($q) use($getDayFromUser) {
                        $q->where('is_active', 1)
                            ->where('is_week_day_meal', 0)
                            ->where('name', $getDayFromUser);
                    })
                    ->where('meal_id',$meal['id'])
                    ->where('from_time',$slot_time)
                    ->first();
                $type = 'day';
            }

            if (empty($getAllSlotOfCurrentMeal)) {

                //find this meal slot in general slot

                $getAllSlotOfCurrentMeal = StoreSlot::query()
                    ->where('store_id', $store->id)
                    ->when(!empty($specific_date) && $specific_date == Carbon::now()->format('Y-m-d'),function ($q){
                        $q->where('is_slot_disabled', 0);
                    })
                    ->doesntHave('store_weekday')
                    ->select('id', 'is_slot_disabled', 'from_time', 'max_entries', 'meal_id')
                    ->where('meal_id',$meal['id'])
                    ->where('from_time',$slot_time)
                    ->first();
                $type = 'general';
            }

            $assign_person = getTotalPersonFromReservations ($check_all_reservation,$meal,$slot_time);
            if ($getAllSlotOfCurrentMeal['max_entries'] != 'Unlimited' && (int)$getAllSlotOfCurrentMeal['max_entries'] - $assign_person < $person) {
                Log::info("check max entry getTotalPersonFromReservations" , [$getAllSlotOfCurrentMeal['max_entries']]);
                $meal['is_disable_meal'] = true;
                continue;
            }

			$checkDisable = getDisable($store_id, $specific_date, $person, $meal, $store, $slot_time, $disable,$check_all_reservation);
			if ($checkDisable == false) {
                $currentSlot = collect($availableSlots)->first();
                $disableStatus = remainingSeatCheckDisable($store_id, $specific_date, $meal, $person, $store, $disable, $this->data,$currentSlot,$check_all_reservation);
                if(isset($disableStatus['slot_disable'])){
                    Log::info("Slot disable set from remainingSeatCheckDisable " , [$disableStatus['slot_disable']]);
                    $meal['is_disable_meal'] = true;

                }else{
                    Log::info("Else set from remainingSeatCheckDisable " , [$disableStatus['disable']]);
                    $meal['is_disable_meal'] = $disableStatus['disable'];
                }
			} else {
			    Log::info( "main else ford disable : " , [$meal['is_disable_meal']]);
                $meal['is_disable_meal'] = true;
            }
			$disable = $checkDisable;
		}
		$reservationDetails['meals'] = $slot_active_meals;
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
        if (empty($this->data['country_code'])) {
            return [
                'code'   => '400',
                'status' => 'error',
                'error'  => 'error_country_code'
            ];
        }
		/*If ayce reservation was available then create ayce reservation other wise create cart reservation*/
		$this->data['dinein_price_id'] = 0;
		$this->data['reservation_type'] = 'cart';
		if (isset($store) && isset($store->storeButler) && isset($store->storeButler->is_buffet) && $store->storeButler->is_buffet == 1) {
			$meal_id = $this->data['meal_type'];
			$dinein_categories = DineinPriceCategory::with([
				'prices' => function ($q1) use ($meal_id) {
					$q1->where('meal_type', $meal_id);
				}
			])->where('store_id', $store->id)->get();
			if(empty($dinein_categories->count())){
                $dinein_categories = [];
            }
			$week_array = [
				'Monday'    => 1,
				'Tuesday'   => 2,
				'Wednesday' => 3,
				'Thursday'  => 4,
				'Friday'    => 5,
				'Saturday'  => 6,
				'Sunday'    => 7
			];
			$current_day = Carbon::parse(request()->date)->format('l');
			$dine_price_selected = true;
			foreach ($dinein_categories as $dinein_category) {
				if ($dinein_category->to_day && $dinein_category->from_day) {
					if ($dine_price_selected && isset($dinein_category->prices) && count($dinein_category->prices) > 0) {
						if ($week_array[$dinein_category->from_day] == $week_array[$current_day] || $week_array[$dinein_category->to_day] == $week_array[$current_day]) {
							$dine_price_selected = false;
						}
						elseif ($week_array[$dinein_category->from_day] <= $week_array[$dinein_category->to_day]) {
							$range_array = range($week_array[$dinein_category->from_day], $week_array[$dinein_category->to_day]);
							if (in_array($week_array[$current_day], $range_array)) {
								$dine_price_selected = false;
							}
						}
						elseif ($week_array[$dinein_category->from_day] > $week_array[$dinein_category->to_day]) {
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
								$all_you_eat_data['dinein_price'] = DineinPrices::with([
									'dineInCategory',
									'meal',
									'dynamicPrices'
									/* => function($q1) { }*/
								])->where('id', $this->data['dinein_price_id'])->first();
								$this->data['all_you_eat_data'] = json_encode($all_you_eat_data, true);
							}
						}
					}
				}
			}
		}
		//fetch the slot details
		$slot = dataModelSlots($this->data['data_model'], $this->data['from_time'],$this->data['res_date'],$this->data['meal_type']);
		if (isset($slot['error'])) {
			Log::info("dataModelSlots function get error message");
			return $slot;
		}
		//Check in slot verify the from time
		if (!isset($slot['from_time'])) {
			Log::warning('slot not founded : ' . json_encode($slot));
			return [
				'code'   => '400',
				'status' => 'error',
				'error'  => 'error_slot_frame'
			];
		}
        //check the company selected or not
        if($this->data['is_company_selected'] == 0){
            $this->data['company'] = null;
        }
        //Check the is_reservation yes or not
        if ($store->is_reservation != 1) {
            Log::warning('Reservation system off due to  : ' . $store->is_reservation);
            return [
                'code'   => '400',
                'status' => 'error',
                'error'  => 'error_reservation_off'
            ];
        }
        // Make past date and time always disabled then return error message
		$current24Time = Carbon::now()->format('H:i');
		if (isset($store) && ($this->data['res_date'] < Carbon::now()
					->format('Y-m-d') || ($this->data['res_date'] == Carbon::now()
						->format('Y-m-d') && strtotime($slot['from_time']) <= strtotime($current24Time)))) {
			Log::info("Make past date and time always disabled");
			return [
				'code'   => '400',
				'status' => 'error',
				'error'  => 'error_date_time_frame'
			];
		}
		//Fetch the already created reservations
		$allReservations = StoreReservation::query()
			->where('res_date', $this->data['res_date'])
			->where('store_id', $store->id)
			->whereNotIn('status', [
				'declined',
				'cancelled'
			])
			->where('meal_type', $this->data['meal_type'])
			->where('is_seated', '!=', 2)
			->where(function ($q1) {
				$q1->whereIn('local_payment_status', [
					'paid',
					'',
					'pending'
				])
					->orWhere('original_total_price', null); // local_payment_status maintain 4 status (paid, ''/null, failed, pending)
			})
			->get();
		Log::info("All reservation <----->" . $allReservations);
		//Owner request true or false
		$is_owner = false;
		Log::info("Is Owner" . $is_owner);
		$is_valid_reservation = isValidReservation($this->data, $store, $slot);
		// If current day is off then return error message
		if (isset($store) && $this->data['res_date'] == Carbon::now()
				->format('Y-m-d') && $store->reservation_off_chkbx == 1) {
			return [
				'code'   => '400',
				'status' => 'error',
				'error'  => 'error_current_day_frame'
			];
		}
		//Count the reservations based on from time and meal type
		$count = $allReservations->where('from_time', $slot['from_time'])
			->where('meal_type', $this->data['meal_type'])
			->count();
		if ($slot['max_entries'] != 'Unlimited' && $count >= $slot['max_entries'] && !$is_owner) {
			$is_valid_reservation = false;
		}
		if ($slot['is_slot_disabled'] && $this->data['res_date'] == Carbon::now()->format('Y-m-d')) {
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
		$remainPersons = (int)$slot['max_entries'] - (int)$assignPersons;
		if ($slot['max_entries'] != 'Unlimited' && $this->data['person'] > $remainPersons && !$is_owner) {
			$is_valid_reservation = false;
		}
		if ($slot['max_entries'] != 'Unlimited' && $this->data['person'] > $slot['max_entries'] && !$is_owner) {
			$is_valid_reservation = false;
		}
		if ($is_valid_reservation == false) {
			return [
				'code'   => '400',
				'status' => 'error',
				'error'  => 'error_person_frame'
			];
		}
		//Fetch the data from user's details and other parameters
		$this->data['store_id'] = $store->id;
		$this->data['slot_id'] = $slot['id'];
		$this->data['from_time'] = Carbon::parse($slot['from_time'])->format('H:i');
		$this->data['res_time'] = Carbon::parse($slot['from_time'])->format('H:i');
		$this->data['user_id'] = isset($this->data['user_id']) ? $this->data['user_id'] : null;
		$this->data['status'] = 'pending';
		$this->data['reservation_id'] = generateReservationId();
		$this->data['reservation_sent'] = 0;
		$this->data['slot_model'] = (isset($this->data['data_model']) && $this->data['data_model'] == 'StoreSlot') ? 'StoreSlot' : 'StoreSlotModified';
		$time_limit = ($meal->time_limit) ? (int)$meal->time_limit : 120;
		$this->data['end_time'] = Carbon::parse($slot['from_time'])->addMinutes($time_limit)->format('H:i');
		if (strtotime($slot['from_time']) > strtotime($this->data['end_time'])) {
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
		}
		else {
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
				}
				elseif ($this->data['total_price'] > $giftCardPrice->total_price) {
					Log::info("Meal * person > gift price");
					$this->data['total_price'] -= $giftCardPrice->total_price;
					$this->data['coupon_price'] = $giftCardPrice->total_price;
					Log::info("total_price : " . $this->data['total_price'] . " > " . " coupon_price : " . $this->data['coupon_price'] . " Gift coupon purchase id : " . $giftCardPrice->id);
				}
			}
		}
		if ($meal->price == null || $meal->price == 0 || $meal->payment_type == 2) {
			$this->data['payment_method_type'] = '';
			$this->data['method'] = '';
		}
		Log::info("Meal price null or 0 | Meal Id : ". $meal->id . $this->data['payment_method_type'] . $this->data['method']);
		if (!($meal->payment_type == 1 || $meal->payment_type == 3) && !$meal->price) {
			$this->data['payment_method_type'] = '';
			$this->data['method'] = '';
		}
		$this->data['created_from'] = 'reservation'; // Change Reservation type after change all project
		if (!$meal->price && $meal->payment_type != 1 && $meal->payment_type != 3) {

		}
		else {
			$this->data['payment_status'] = 'pending';
			$this->data['local_payment_status'] = 'pending';
		}
		if (isset($this->data['gsm_no']) && strlen($this->data['gsm_no']) >= 4) {
			$this->data['gastpin'] = substr($this->data['gsm_no'], -4);
		}
		else {
			$this->data['gastpin'] = generateRandomNumberV2();
		}
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
			}
			elseif ($remainingPrice < 0) {
				Log::info("Total price in minus then add left out price in total price");
				$this->data['total_price'] = $this->data['original_total_price'] - (float)$giftCardPrice->total_price;
				$this->data['coupon_price'] = $giftCardPrice->total_price;
				$this->data['gift_purchase_id'] = $giftCardPrice->id;
			}
		}

		$current24Time = Carbon::now()->format('G:i');
		// Booking time off for current day checking
		if ($store->is_booking_enable == 1 && $this->data['res_date'] === Carbon::now()->format('Y-m-d')) {
			if ($store->booking_off_time == "00:00") {
				$store->booking_off_time = "24:00";
			}

			// Checking if time has been past for today
			if (strtotime($this->data['from_time']) <= strtotime($current24Time)) {
				return [
					'code' => '400',
					'status' => 'error',
					'error' => 'error_time_has_been_past',
					'disable' => true
				];
			}

			// check the booking off time with slot time
			if (strtotime($current24Time) >= strtotime($store->booking_off_time)) {
				if (strtotime($this->data['from_time']) >= strtotime($current24Time)) {
					return [
						'code' => '400',
						'status' => 'error',
						'error' => 'error_booking_off_time',
						'disable' => true
					];
				}
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
		if (isset($store->reservation_off_chkbx) && Carbon::now()
				->format('m') == request()->month && $store->reservation_off_chkbx == 1) {
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
			'availableDates'  => $availDates
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
			'Date can not be empty.!'      => empty($this->date),
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
		}
		catch (\Exception $e) {
			Log::error('Reservation : Create new slots' . 'Message | ' . $e->getMessage() . 'File | ' . $e->getFile() . 'Line | ' . $e->getLine());
		}
	}
}
