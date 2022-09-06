<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;
    use HasRoles;
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

    public function getProfileImageUrlAttribute()
    {
        if ($this->profile_img) {
            return asset('imagecache/UserProfileDropDownImage'.getCachedImagePath($this->profile_img));
        } else {
            return asset('images/no_image.png');
        }
    }

    public function store_owner()
    {
        return $this->hasOne('App\Models\StoreOwner','user_id','id');
    }

    public function store_manager()
    {
        return $this->hasOne('App\Models\StoreManager','user_id','id');
    }

    public function store_pos_employee()
    {
        return $this->hasOne('App\Models\StorePosEmployee','user_id','id');
    }

    public function store_owners()
    {
        return $this->hasMany('App\Models\StoreOwner','user_id','id');
    }

    public function store_managers()
    {
        return $this->hasMany('App\Models\StoreManager','user_id','id');
    }

    public function card()
    {
        return $this->hasOne('App\Models\Card' , 'customer_id', 'id');
    }

    public function card_history()
    {
            return $this->hasMany('\App\Models\CardHistory' , 'user_id' , 'id');
    }

    public function hasStoreHeader($user_id = null )
    {
        if(!$user_id)
        {
            $user_id = auth()->id();
        }

        $cache_key = "header_store_".$user_id;

       return Cache::has($cache_key);
    }

    public function setStoreHeader($user_id = null, $store_id )
    {
        if(!$user_id)
        {
            $user_id = auth()->id();
        }

        $cache_key = "header_store_".$user_id;

		auth()->user()->update(['selected_store_id' => $store_id]);

       return Cache::put($cache_key, $store_id , 2880);
    }

    public function getStoreHeader($user_id = null, $default = null)
    {
        if(!$user_id)
        {
            $user_id = auth()->id();
        }

        $cache_key = "header_store_".$user_id;

        return Cache::get($cache_key, $default);
    }

    public function removeStoreHeader($user_id = null)
    {
        if(!$user_id)
        {
            $user_id = auth()->id();
        }
        $cache_key = "header_store_".$user_id;
        Cache::forget($cache_key);
    }

}
