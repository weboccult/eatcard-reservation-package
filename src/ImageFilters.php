<?php

namespace Weboccult\EatcardReservation;

/**
 * Class ImageFilters
 * Created by : dev005
 */
class ImageFilters
{
    /**
     * Usage
     * \App\ImageFilters::applyFilter('FILTER_NAME','AWS-S3-FULL-URL-IMAGE');
     * AVAILABLE FILTERS : ['productName', 'productMedium', 'userProfileDropDown', 'storeBanner']
     */
    public static $filters = [
        'ProductNormalImage'       => ['size' => '200x200'],
        'UserProfileDropDownImage' => ['size' => '70x70'],
        'StoreBannerImage'         => ['size' => '940x220'],
        'StoreLogoImage'         => ['size' => '100x80'],
        'StorePrintLogoImage'         => ['size' => '200xauto'],
        'EmailStoreLogo'         => ['size' => '200xauto'],
    ];


    /**
     * @param $filter : String
     * @param $s3BucketFullURL : String
     * @return string
     * @throws \Exception
     */
    public static function applyFilter($filter, $s3BucketFullURL)
    {
        if (!isset(self::$filters[$filter])) {
            throw new \Exception('Filter not supported', 422);
        }
        $dirname = pathinfo($s3BucketFullURL, PATHINFO_DIRNAME);
        return $dirname . '/' . self::$filters[$filter]['size'] . '/' . basename($s3BucketFullURL);
    }
}
