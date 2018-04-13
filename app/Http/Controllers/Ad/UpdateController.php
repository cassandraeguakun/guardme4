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

use App\Larapen\Helpers\Ip;
use App\Larapen\Helpers\Rules;
use App\Larapen\Models\Ad;
use App\Larapen\Models\AdType;
use App\Larapen\Models\Category;
use App\Larapen\Models\Pack;
use App\Larapen\Models\PaymentMethod;
use App\Larapen\Models\City;
use App\Larapen\Models\Picture;
use App\Larapen\Models\SalaryType;
use App\Larapen\Models\User;
use App\Larapen\Models\Language;
use App\Larapen\Scopes\ActiveScope;
use App\Larapen\Scopes\ReviewedScope;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use App\Http\Controllers\FrontController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;
use Torann\LaravelMetaTags\Facades\MetaTag;
use App\Larapen\Helpers\Localization\Helpers\Country as CountryLocalizationHelper;
use App\Larapen\Helpers\Localization\Country as CountryLocalization;

class UpdateController extends FrontController
{
    /**
     * UpdateController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        
        /*
         * References
         */
        $this->countries = CountryLocalizationHelper::transAll(CountryLocalization::getCountries(), $this->lang->get('abbr'));
        view()->share('countries', $this->countries);
    }
    
    /**
     * Show the form the create a new ad post.
     *
     * @param $adId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getForm($adId)
    {
        $data = array();
        
        // Get Ad
        // GET ADS INFO
        $ad = Ad::withoutGlobalScopes([ActiveScope::class, ReviewedScope::class])->where('user_id', $this->user->id)->where('id', $adId)->with([
            'user',
            'country',
            'category',
            'adType',
            'city',
            'pictures'
        ])->first();
        
        if (is_null($ad)) {
            abort(404);
        }
        view()->share('ad', $ad);
        
        
        /*
         * References
         */
        $data['categories'] = Category::where('parent_id', 0)->where('translation_lang', $this->lang->get('abbr'))->with([
            'children' => function ($query) {
                $query->where('translation_lang', $this->lang->get('abbr'));
            }
        ])->orderBy('lft')->get();
        $data['ad_types'] = AdType::where('translation_lang', $this->lang->get('abbr'))->get();
		$data['salary_type'] = SalaryType::where('translation_lang', $this->lang->get('abbr'))->get();
        $data['states'] = City::where('country_code', $this->country->get('code'))->where('feature_code', 'ADM1')->get()->all();
        $data['packs'] = Pack::where('translation_lang', $this->lang->get('abbr'))->with('currency')->get();
        $data['payment_methods'] = PaymentMethod::orderBy('lft')->get();
        
        // Debug
        //echo '<pre>'; print_r($data['categories']->toArray()); echo '</pre><hr>'; exit();
        
        // Meta Tags
        MetaTag::set('title', t('Update My Ad'));
        MetaTag::set('description', t('Update My Ad'));
        
        return view('ad.update.index', $data);
    }
    
    /**
     * Store a new ad post.
     *
     * @param $adId
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postForm($adId, Request $request)
    {
        // Form validation
        $validator = Validator::make($request->all(), Rules::Ad($request, 'PUT'));
        if ($validator->fails()) {
            // BugFix with : $request->except('logo')
            return back()->withErrors($validator)->withInput($request->except('logo'));
        }
        
        // Get Ad
        $ad = Ad::withoutGlobalScopes([ActiveScope::class, ReviewedScope::class])->where('user_id', $this->user->id)->where('id', $adId)->first();
        if (empty($ad)) {
            abort(404);
        }
        
        
        // Update Ad
        // @todo: In this version user can't change the country her Ad! Please add a SELECT BOX in the post view to activate this functionality.
        // $ad->country_code = $request->input('country_code');
        $ad->category_id = $request->input('category');
        $ad->ad_type_id = $request->input('ad_type');
        $ad->company_name = $request->input('company_name');
        $ad->company_description = $request->input('company_description');
		$ad->company_website = $request->input('company_website');
		$ad->title = $request->input('title');
		$ad->description = $request->input('description');
        $ad->salary_min = $request->input('salary_min');
		$ad->salary_max = $request->input('salary_max');
		$ad->salary_type_id = $request->input('salary_type');
        $ad->negotiable = $request->input('negotiable');
		$ad->start_date = $request->input('start_date');
        $ad->contact_name = $request->input('contact_name');
        $ad->contact_email = $request->input('contact_email');
        $ad->contact_phone = $request->input('contact_phone');
        $ad->contact_phone_hidden = $request->input('contact_phone_hidden');
        $ad->ip_addr = Ip::get();
        $ad->save();
        
        
        // Get Country Code
        $country_code = ($ad->country_code != '') ? strtolower($ad->country_code) : strtolower($this->country->get('code'));

		// Upload Logo
		if ($request->hasFile('logo')) {
			$destination_path = 'uploads/pictures/';
			$prefix_filename = $country_code . '/' . $ad->id . '/';
			$full_destination_path = public_path() . '/' . $destination_path . $prefix_filename;

			// Process file request
			$file = $request->file('logo');
			if ($file->isValid()) {
				try {
					// Create destination path if not exists
					if (!File::exists($full_destination_path)) {
						File::makeDirectory($full_destination_path, 0755, true);
					}

					// Get file extension
					$extension = $file->getClientOriginalExtension();

					// Build the new filename
					$filename_gen = uniqid('logo_' . slugify($ad->company_name) . '_');
					$new_filename = strtolower($prefix_filename . $filename_gen . '.' . $extension);

					// Delete old file if new file has uploaded
					if (!empty($ad->logo)) {
						if (is_file(public_path() . '/' . $destination_path . $ad->logo)) {
							@unlink(public_path() . '/' . $destination_path . $ad->logo);
						}
					}

					// Save Resume on the server
					$file->move($full_destination_path, $new_filename);

					// Ad Logo in database
					$ad->logo = $new_filename;
					$ad->save();
				} catch (\Exception $e) {
					flash()->error($e->getMessage());
				}
			} else {
				flash()->error("Invalid file !");
			}
		}
        
        
        $country_code = strtoupper($this->country->get('code'));
        if ($this->countries->has($country_code)) {
            $urlPath =  $this->lang->get('abbr') . '/' . slugify($ad->title) . '/' . $ad->id . '.html';
        } else {
            $urlPath = '/';
        }
        
        $message = t("Your Ad has been updated.");
        flash()->success($message);
        
        return redirect($urlPath);
    }

    /**
     * @param $adId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function success($adId)
    {
        if (!session('success')) {
            return redirect($this->lang->get('abbr') . '/account/myads');
        }
        
        return view('ad.update.success');
    }
}
