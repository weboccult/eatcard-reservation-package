<?php

namespace Weboccult\EatcardReservation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'store_name',
        'store_slug',
        'first_name',
        'last_name',
        'phone_no',
        'address1',
        'address2',
        'profile_img',
        'bod',
        'gender_prefix',
        'post_code',
        'city',
        'my_company',
        'features_popup_show',
        'selected_store_id',
        'unique_code',
        'image'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

	protected $appends = ['user_role','profile_image_url'];

	public function getUserRoleAttribute(){
		return $this->getRoleNames()->first();
	}

    public function store_owner()
    {
        return $this->hasOne(StoreOwner::class,'user_id','id');
    }

}
