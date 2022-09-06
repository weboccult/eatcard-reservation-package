<?php

namespace Weboccult\EatcardReservation\Facade;

use Illuminate\Support\Facades\Facade;
use phpDocumentor\Reflection\Types\Static_;
use Weboccult\EatcardReservation\EatcardReservation as EatcardReservationCore;

/**
 * @method static string hello($name)
 *
 * @mixin EatcardReservationCore
 *
 * @return EatcardReservationCore
 *
 * @see EatcardReservationCore
 */
class EatcardReservation extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
//        return EatcardReservationCore::class;   //First way to import this and if use this return then change the service provider similar class
        return 'eatcard-reservation';             //Second way to default create when package is ready
    }
}
