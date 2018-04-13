<?php
/**
 * JobClass - Geolocalized Job Board Script
 * Copyright (c) BedigitCom. All Rights Reserved
 *
 * Website: http://www.bedigit.com
 *
 * LICENSE
 * -------
 * This software is furnished under a license and may be used and copied
 * only in accordance with the terms of such license and with the inclusion
 * of the above copyright notice. If you Purchased from Codecanyon,
 * Please read the full License from here - http://codecanyon.net/licenses/standard
 */

namespace App\Larapen\Models;

use App\Larapen\Scopes\ActiveScope;
use App\Larapen\Scopes\ReviewedScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

class Ad extends BaseModel
{
    //use SoftDeletes;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ads';
    
    /**
     * The primary key for the model.
     *
     * @var string
     */
    // protected $primaryKey = 'id';
    protected $appends = ['created_at_ta'];
    
    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = true;
    
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country_code',
        'user_id',
		'company_name',
		'company_description',
		'company_website',
        'category_id',
        'ad_type_id',
        'title',
        'description',
        'salary_min',
		'salary_max',
		'salary_type_id',
        'negotiable',
		'start_date',
        'contact_name',
        'contact_email',
        'contact_phone',
        'contact_phone_hidden',
        'city_id',
        'lat',
        'lon',
		'address',
        'pack_id',
        'ip_addr',
        'visits',
        'activation_token',
        'active',
        'reviewed',
        'archived',
        'partner'
    ];
    
    /**
     * The attributes that should be hidden for arrays
     *
     * @var array
     */
    // protected $hidden = [];
    
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }
    
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    protected static function boot()
    {
        parent::boot();
        
        static::addGlobalScope(new ActiveScope());
        static::addGlobalScope(new ReviewedScope());
        
        // DELETING - before delete() method call this
        static::deleting(function ($ad) {
            // Delete all messages
            $ad->messages()->delete();
            
            // Delete all entries by users in database
            $ad->saveByUsers()->delete();
            
            // Remove associated files
            if (is_numeric($ad->id)) {
                // Delete logo file (if exists)
                if (!is_null($ad->logo)) {
                    $logo_path = public_path() . '/uploads/pictures/';
                    if (File::exists($logo_path . strtolower($ad->country_code) . '/' . $ad->id)) {
                        File::deleteDirectory($logo_path . strtolower($ad->country_code) . '/' . $ad->id);
                    }
                }
            }
            
            // Delete all pictures entries in database
            $ad->pictures()->delete();
            
            // Delete the paymentof this Ad
            $ad->onePayment()->delete();
        });


        // UPDATING - before update() method call this
        static::updating(function ($ad) {
            // Get category
            $cat = Category::find(Input::get('parent'));
            if (!is_null($cat))
            {
                // Resumes files cleanup by category type
				if (!empty($ad->logo)) {
					$logo_path = public_path() . '/uploads/pictures/';
					if (File::exists($logo_path . $ad->logo)) {
						File::delete($logo_path . $ad->logo);
					} else {
						$logo_path = public_path() . '/';
						if (File::exists($logo_path . $ad->logo)) {
							File::delete($logo_path . $ad->logo);
						}
					}
				}
            }
        });
    }
    
    public function getTitleHtml()
    {
        return '<a href="/' . config('app.locale') . '/' . slugify($this->title) . '/' . $this->id . '.html" target="_blank">' . $this->title . '</a>';
    }
    
    public function getCityHtml()
    {
        if (isset($this->city) and !is_null($this->city)) {
            $lang = config('app.locale');
            $country_code = strtolower($this->city->country_code);
            $routes_text = trans('routes.t-search-location');
            $city_name = $this->city->name;
            $city_slug = slugify($city_name);
            $city_id = $this->city->id;
            
            return '<a href="/' . $lang . '/' . $country_code . '/' . $routes_text . '/' . $city_slug . '/' . $city_id . '" target="_blank">' . $city_name . '</a>';
        } else {
            return $this->city_id;
        }
    }

    public function getReviewedHtml()
    {
		$id = $this->{$this->primaryKey};
		$lineId = 'reviewed' . $id;
		$data = 'data-table="' . $this->getTable() . '" 
			data-field="reviewed" 
			data-line-id="' . $lineId . '" 
			data-id="' . $id . '" 
			data-value="' . $this->reviewed . '"';

		// Decoration
		if ($this->reviewed == 1) {
			$html = '<i id="' . $lineId . '" class="fa fa-check-square-o" aria-hidden="true"></i>';
		} else {
			$html = '<i id="' . $lineId . '" class="fa fa-square-o" aria-hidden="true"></i>';
		}
		$html = '<a href="" class="ajax-request" ' . $data . '>' . $html . '</a>';

		return $html;
    }
    
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function adType()
    {
        return $this->belongsTo('App\Larapen\Models\AdType', 'ad_type_id');
    }
    
    public function category()
    {
        return $this->belongsTo('App\Larapen\Models\Category', 'category_id');
    }
    
    public function city()
    {
        return $this->belongsTo('App\Larapen\Models\City', 'city_id');
    }
    
    public function country()
    {
        return $this->belongsTo('App\Larapen\Models\Country', 'country_code');
    }
    
    public function messages()
    {
        return $this->hasMany('App\Larapen\Models\Message', 'ad_id');
    }
    
    /*
    public function payment()
    {
        // @todo: Delete this method. Check if it's unused before.
        //return $this->belongsToMany('App\Larapen\Models\PaymentMethod', 'payments', 'ad_id', 'payment_method_id');
    }
    */
    public function onePayment()
    {
        return $this->hasOne('App\Larapen\Models\Payment', 'ad_id');
    }
    
    public function pictures()
    {
        return $this->hasMany('App\Larapen\Models\Picture');
    }
    
    public function saveByUsers()
    {
        return $this->belongsToMany('App\Larapen\Models\User', 'saved_ads', 'ad_id', 'user_id');
    }
    
    public function user()
    {
        return $this->belongsTo('App\Larapen\Models\User', 'user_id');
    }
    
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeActive($builder)
    {
        if (Request::segment(1) == config('backpack.base.route_prefix', 'admin')) {
            return $builder;
        }
    
        if (config('settings.ads_review_activation')) {
            return $builder->where('active', 1)->where('reviewed', 1)->where('archived', 0);
        } else {
            return $builder->where('active', 1)->where('archived', 0);
        }
    }
    
    public function scopeArchived($builder)
    {
        if (Request::segment(1) == config('backpack.base.route_prefix', 'admin')) {
            return $builder;
        }
        
        return $builder->where('archived', 1);
    }
    
    public function scopePending($builder)
    {
        if (Request::segment(1) == config('backpack.base.route_prefix', 'admin')) {
            return $builder;
        }

        if (config('settings.ads_review_activation')) {
            return $builder->where('active', 0)->orWhere('reviewed', 0);
        } else {
            return $builder->where('active', 0);
        }
    }
    
    /*
    |--------------------------------------------------------------------------
    | ACCESORS
    |--------------------------------------------------------------------------
    */
    public function getCreatedAtAttribute($value)
    {
        $value = \Carbon\Carbon::parse($value);
        if (session('time_zone')) {
            $value->timezone(session('time_zone'));
        }
        //echo $value->format('l d F Y H:i:s').'<hr>'; exit();
        //echo $value->formatLocalized('%A %d %B %Y %H:%M').'<hr>'; exit(); // Multi-language

        return $value;
    }
    
    public function getUpdatedAtAttribute($value)
    {
        $value = \Carbon\Carbon::parse($value);
        if (session('time_zone')) {
            $value->timezone(session('time_zone'));
        }

        return $value;
    }
    
    public function getDeletedAtAttribute($value)
    {
        $value = \Carbon\Carbon::parse($value);
        if (session('time_zone')) {
            $value->timezone(session('time_zone'));
        }

        return $value;
    }
    
    public function getCreatedAtTaAttribute($value)
    {
        $value = \Carbon\Carbon::parse($this->attributes['created_at']);
        if (session('time_zone')) {
            $value->timezone(session('time_zone'));
        }
        $value = time_ago($value, session('time_zone'), session('language_code'));

        return $value;
    }
    
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
