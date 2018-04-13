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

namespace App\Http\Controllers\Ad;

use App\Larapen\Events\AdWasVisited;
use App\Larapen\Helpers\Arr;
use App\Larapen\Helpers\Rules;
use App\Larapen\Models\Ad;
use App\Larapen\Models\Category;
use App\Larapen\Models\City;
use App\Larapen\Models\Message;
use App\Larapen\Models\Picture;
use App\Larapen\Models\ReportType;
use App\Http\Controllers\FrontController;
use App\Larapen\Models\Resume;
use App\Larapen\Scopes\ActiveScope;
use App\Larapen\Scopes\ReviewedScope;
use App\Mail\EmployerContacted;
use App\Mail\ReportSent;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Larapen\TextToImage\Facades\TextToImage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request as Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Torann\LaravelMetaTags\Facades\MetaTag;
use Illuminate\Support\Facades\Auth;
use App\Larapen\Helpers\Localization\Helpers\Country as CountryLocalizationHelper;
use App\Larapen\Helpers\Localization\Country as CountryLocalization;

class DetailsController extends FrontController
{
    public $msg = [];
    
    /**
     * Ad expire time (in months)
     * @var int
     */
    public $expire_time = 24;

    /**
     * DetailsController constructor.
     */
    public function __construct()
    {
        parent::__construct();

		// Check Country URL for SEO
        $countries = CountryLocalizationHelper::transAll(CountryLocalization::getCountries(), $this->lang->get('abbr'));
        view()->share('countries', $countries);
        
        // Messages
        $this->msg['message']['success'] = "Your message has sent successfully to :contact_name.";
        $this->msg['report']['success'] = "Your report has sent successfully to us. Thank you!";
        $this->msg['mail']['error'] = "The sending messages is not enabled. Please check the SMTP settings in the admin.";
        $this->msg['notification']['expiration'] = "Warning! This ad has expired. The product or service is not more available (may be)";
    }
    
    /**
     * Show ad's details.
     *
	 * @param string $title
	 * @param $id
	 * @return View
	 */
    public function index($title, $adId)
    {
        $data = array();

        if (!is_numeric($adId)) {
            abort(404);
        }
        
        // GET ADS INFO
        if (Auth::check())
        {
            $ad = Ad::withoutGlobalScopes([ActiveScope::class, ReviewedScope::class])->where('id', $adId)->with(['user', 'adType', 'city', 'pictures'])->first();
            // Unselect non-self ads
            if (Auth::user()->id != $ad->user_id) {
                $ad = Ad::where('id', $adId)->with(['user', 'adType', 'city', 'pictures'])->first();
            }

            // User Resume
            $resume = Resume::where('user_id', Auth::user()->id)->first();
            view()->share('resume', $resume);
        }
        else
        {
            $ad = Ad::where('id', $adId)->with(['user', 'adType', 'city', 'pictures'])->first();
        }

        // Preview Ad after activation
        if (Input::has('preview') and Input::get('preview')==1) {
            $ad = Ad::withoutGlobalScopes([ActiveScope::class, ReviewedScope::class])->where('id', $adId)->with(['user', 'adType', 'city', 'pictures'])->first();
        }
        
        // Ad not found
        if (is_null($ad)) {
            abort(404);
        }

        // Share Ad info
        view()->share('ad', $ad);
        
        // GET AD'S CATEGORY
        $cat = Category::transById($ad->category_id, $this->lang->get('abbr'));
        view()->share('cat', $cat);
        
        // Ad's Category not found
        if (is_null($cat)) {
            abort(404);
        }
        
        // GET PARENT CATEGORY
        if ($cat->parent_id == 0) {
            $parent_cat = $cat;
        } else {
            $parent_cat = Category::transById($cat->parent_id, $this->lang->get('abbr'));
        }
        view()->share('parent_cat', $parent_cat);
        
        // REPORT ABUSE TYPE COLLECTION
        $report_types = ReportType::where('translation_lang', $this->lang->get('abbr'))->get();
        view()->share('report_types', $report_types);
        
        // Increment Ad visits counter
        Event::fire(new AdWasVisited($ad));

		// GET SIMILAR ADS
		$carousel = $this->getSimilarAds($ad);
		$data['carousel'] = $carousel;
        
        // SEO
        $title = $ad->title . ', ' . $ad->city->name;
        $description = str_limit(str_strip($ad->description), 200);
        
        // Meta Tags
        MetaTag::set('title', $title);
        MetaTag::set('description', $description);
        
        // Open Graph
        $this->og->title($title)->description($description)->type('article')->article(['author' => config('settings.facebook_page_url')])->article(['publisher' => config('settings.facebook_page_url')]);
        if (!$ad->pictures->isEmpty()) {
            if ($this->og->has('image')) {
                $this->og->forget('image')->forget('image:width')->forget('image:height');
            }
            foreach ($ad->pictures as $picture) {
                $this->og->image(url('pic/x/cache/large/' . $picture->filename), [
                    'width' => 600,
                    'height' => 600
                ]);
            }
        }
        view()->share('og', $this->og);
        
        // Expiration Info
        $today_dt = Carbon::now($this->country->get('timezone')->time_zone_id);
        if ($today_dt->gt($ad->created_at->addMonths($this->expire_time))) {
            flash()->error(t($this->msg['notification']['expiration']));
        }
        
        // Maintenance - Clean the Ad's storage folders (pictures & resumes) /=======================================
        if (is_numeric($ad->id)) {
            $picture_path = public_path() . '/uploads/';
            // for Logo
            if (empty($ad->logo)) {
                if (File::exists($picture_path . 'pictures/' . strtolower($ad->country_code) . '/' . $ad->id)) {
                    File::deleteDirectory($picture_path . 'pictures/' . strtolower($ad->country_code) . '/' . $ad->id);
                }
            }
        }
        //===========================================================================================================

        // View
        return view('ad.details.index', $data);
    }

	/**
	 * @param $adId
	 * @param HttpRequest $request
	 * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 */
    public function sendMessage($adId, HttpRequest $request)
    {
        $this->middleware('auth', ['only' => ['sendMessage']]);

        // Form validation
        $validator = Validator::make($request->all(), Rules::Message($request));
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        // Get Ad
        $ad = Ad::find($adId);
        if (is_null($ad)) {
            abort(404);
        }
        
        // Store Message
        $message = new Message(array(
            'ad_id' => $adId,
            'name' => $request->input('sender_name'),
            'email' => $request->input('sender_email'),
            'phone' => $request->input('sender_phone'),
            'message' => $request->input('message'),
        ));
        $message->save();

        // Get user info
        if (Auth::check()) {
            $userFilePath = Auth::user()->id;
        } else {
            $userFilePath = 'guests';
        }


        // UPLOAD FILE : RESUME
        $country_code = strtolower($this->country->get('code'));
        $pathToFile = '';
        if ($request->hasFile('resume')) {
            $destination_path = 'uploads/resumes/';
            $prefix_filename = $country_code . '/' . $userFilePath . '/';
            $full_destination_path = public_path() . '/' . $destination_path . $prefix_filename;

            // Process file request
            $file = $request->file('resume');
            if ($file->isValid()) {
                // Create destination path if not exists
                if (!File::exists($full_destination_path)) {
                    File::makeDirectory($full_destination_path, 0755, true);
                }

                // Get file extension
                $extension = $file->getClientOriginalExtension();

                // Build the new filename
                $filename_gen = uniqid('resume_');
                $new_filename = strtolower($prefix_filename . $filename_gen . '.' . $extension);

                // Save Resume on the server
                $file->move($full_destination_path, $new_filename);

                // Ad Resume in database
                $message->filename = $new_filename;
                $message->save();

                // Get path of uploaded file
                $pathToFile = public_path() . '/' . $destination_path . $new_filename;
            }
        } else {
            if (Auth::check()) {
                $resume = Resume::where('user_id', Auth::user()->id)->first();
                if (!empty($resume)) {
                    $pathToFile = public_path() . '/uploads/resumes/' . $resume->filename;
                }
            }
        }

        
        // Send a message to publisher
        try {
            Mail::send(new EmployerContacted($ad, $message, $pathToFile));
        } catch (\Exception $e) {
            flash()->error($e->getMessage());
        }
        
        // Success message
        if (!session('flash_notification')) {
            flash()->success(t($this->msg['message']['success'], ['contact_name' => $ad->contact_name]));
        }
        
        return redirect($this->lang->get('abbr') . '/' . slugify($ad->title) . '/' . $ad->id . '.html');
    }

	/**
	 * @param $adId
	 * @param HttpRequest $request
	 * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 */
    public function sendReport($adId, HttpRequest $request)
    {
        // Form validation
        $validator = Validator::make($request->all(), Rules::Report($request));
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        // Get Ad ID (input hidden from modal form)
        /*$adId = $request->input('ad');
        if (!is_numeric($adId)) {
            abort(404);
        }*/
        
        // Get Ad
        $ad = Ad::find($adId);
        if (is_null($ad)) {
            abort(404);
        }
        
        // Store Report
        $report = [
            'ad_id' => $adId,
            'report_type_id' => $request->input('report_type'),
            'email' => $request->input('report_sender_email'),
            'message' => $request->input('report_message'),
        ];
        
        // Send Abus Report to admin
        try {
            Mail::send(new ReportSent($ad, $report));
        } catch (\Exception $e) {
            flash()->error($e->getMessage());
        }
        
        // Success message
        if (!session('flash_notification')) {
            flash()->success(t($this->msg['report']['success']));
        }
        
        return redirect($this->lang->get('abbr') . '/' . slugify($ad->title) . '/' . $ad->id . '.html');
    }

	/**
	 * Get similar ads
	 *
	 * @param $ad
	 * @param string $type
	 * @return array|null|\stdClass
	 */
    private function getSimilarAds($ad, $type='location')
	{
		switch ($type) {
			case 'category':
				return $this->getCategorySimilarAds($ad->category_id, $ad->id);
				break;
			case 'location':
				return $this->getLocationSimilarAds($ad->city_id, $ad->id);
				break;
			default:
				return $this->getLocationSimilarAds($ad->city_id, $ad->id);
		}
	}

	/**
	 * Get similar ads (Ads in the same Category)
	 *
	 * @param $categoryId
	 * @param int $currentAdId
	 * @return array|null|\stdClass
	 */
	private function getCategorySimilarAds($categoryId, $currentAdId = 0)
	{
		$limit = 20;
		$carousel = null;

		// Get ads from same category
		$reviewedAdSql = '';
		if (config('settings.ads_review_activation')) {
			$reviewedAdSql = ' AND a.reviewed = 1';
		}
		$sql = 'SELECT DISTINCT a.* ' . '
				FROM ' . DB::getTablePrefix() . 'ads as a
				INNER JOIN ' . DB::getTablePrefix() . 'categories as c ON c.id=a.category_id AND c.active=1
				INNER JOIN ' . DB::getTablePrefix() . 'categories as cp ON cp.id=c.parent_id AND cp.active=1
				WHERE a.country_code = :country_code 
					AND :category_id  IN (c.id, cp.id) 
					AND a.active=1 
					AND a.archived!=1 
					AND a.deleted_at IS NULL ' . $reviewedAdSql . '
					AND a.id != :current_ad_id
				ORDER BY a.created_at DESC
				LIMIT 0,' . (int)$limit;
		$bindings = [
			'country_code' 	=> $this->country->get('code'),
			'category_id' 	=> $categoryId,
			'current_ad_id' => $currentAdId
		];
		$ads = DB::select(DB::raw($sql), $bindings);

		if (!empty($ads)) {
			shuffle($ads);
			$carousel = [
				'title' => t('Similar Ads'),
                'link' 	=> qsurl($this->lang->get('abbr').'/'.trans('routes.v-search', ['countryCode' => $this->country->get('icode')]), array_merge(Request::except('c'), ['c'=>$categoryId])),
				'ads' 	=> $ads,
			];
			$carousel = Arr::toObject($carousel);
		}

		return $carousel;
	}

	/**
	 * Get ads in the same Location
	 *
	 * @param $cityId
	 * @param int $currentAdId
	 * @return array|null|\stdClass
	 */
	private function getLocationSimilarAds($cityId, $currentAdId = 0)
	{
		$distance = 500; // km
		$limit = 20;
		$carousel = null;

		$city = City::find($cityId);

		if (!empty($city)) {
			// Get ads from same location (with radius)
			$reviewedAdSql = '';
			if (config('settings.ads_review_activation')) {
				$reviewedAdSql = ' AND a.reviewed = 1';
			}
			$sql = 'SELECT a.*, 3959 * acos(cos(radians(' . $city->latitude . ')) * cos(radians(a.lat))'
				. '* cos(radians(a.lon) - radians(' . $city->longitude . '))'
				. '+ sin(radians(' . $city->latitude . ')) * sin(radians(a.lat))) as distance
				FROM ' . DB::getTablePrefix() . 'ads as a
				WHERE a.country_code = :country_code 
					AND a.active=1 
					AND a.archived!=1 
					AND a.deleted_at IS NULL ' . $reviewedAdSql . '
					AND a.id != :current_ad_id
				HAVING distance <= ' . $distance . ' 
				ORDER BY distance ASC, a.created_at DESC 
				LIMIT 0,' . (int)$limit;
			$bindings = [
				'country_code' 	=> $this->country->get('code'),
				'current_ad_id' => $currentAdId
			];
			$ads = DB::select(DB::raw($sql), $bindings);

			if (!empty($ads)) {
				shuffle($ads);
				$carousel = [
					'title' => t('More jobs at :distance km around :city', ['distance' => $distance, 'city' => $city->name]),
                    'link' 	=> qsurl($this->lang->get('abbr').'/'.trans('routes.v-search', ['countryCode' => $this->country->get('icode')]), array_merge(Request::except(['l', 'location']), ['l'=>$city->id])),
					'ads' 	=> $ads,
				];
				$carousel = Arr::toObject($carousel);
			}
		}

		return $carousel;
	}
}
