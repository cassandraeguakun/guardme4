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
use App\Larapen\Models\Payment;
use App\Larapen\Models\PaymentMethod;
use App\Larapen\Models\City;
use App\Larapen\Models\Picture;
use App\Larapen\Models\SalaryType;
use App\Larapen\Models\User;
use App\Http\Controllers\FrontController;
use App\Larapen\Scopes\ActiveScope;
use App\Larapen\Scopes\ReviewedScope;
use App\Mail\AdPosted;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;
use Torann\LaravelMetaTags\Facades\MetaTag;
use App\Larapen\Helpers\Localization\Helpers\Country as CountryLocalizationHelper;
use App\Larapen\Helpers\Localization\Country as CountryLocalization;
use App\Larapen\Helpers\Payment as PaymentHelper;

class PostController extends FrontController
{
    public $data;
    public $msg = [];
    public $uri = [];
    public $packs;
    public $payment_methods;

    /**
     * PostController constructor.
     */
    public function __construct()
    {
        parent::__construct();

		// Check if guests can post Ads
		if (config('settings.activation_guests_can_post') != '1') {
			$this->middleware('auth')->only(['getForm', 'postForm']);
		}

        // From Laravel 5.3.4 or above
        $this->middleware(function ($request, $next) {
            $this->commonQueries();
            return $next($request);
        });
    }

    /**
     * Common Queries
     */
    public function commonQueries()
    {
        /*
         * Messages
         */
        $this->msg['post']['success'] = t("Your Ad has been created.");
        $this->msg['checkout']['success'] = t("We have received your payment. Please check your inbox to activate your ad.");
        $this->msg['checkout']['cancel'] = t("We have not received your payment. Payment cancelled.");
        $this->msg['checkout']['error'] = t("We have not received your payment. An error occurred.");
        $this->msg['activation']['success'] = "Congratulation ! Your ad \":title\" has been activated.";
        $this->msg['activation']['multiple'] = "Your ad is already activated.";
        $this->msg['activation']['error'] = "Your ad's activation has failed.";

        /*
         * URL Paths
         */
        $this->uri['form'] =  $this->lang->get('abbr') . '/' . trans('routes.create');
        $this->uri['success'] = $this->lang->get('abbr') . '/create/success';

        /*
         * Payment Helper vars
         */
        PaymentHelper::$lang = $this->lang;
        PaymentHelper::$msg = $this->msg;
        PaymentHelper::$uri = $this->uri;

        /*
         * References
         */
        $data = array();
        $data['countries'] = CountryLocalizationHelper::transAll(CountryLocalization::getCountries(), $this->lang->get('abbr'));
        $data['categories'] = Category::where('parent_id', 0)->where('translation_lang', $this->lang->get('abbr'))->with([
            'children' => function ($query) {
                $query->where('translation_lang', $this->lang->get('abbr'));
            }
        ])->orderBy('lft')->get();
        $data['ad_types'] = AdType::where('translation_lang', $this->lang->get('abbr'))->get();
        $data['salary_type'] = SalaryType::where('translation_lang', $this->lang->get('abbr'))->get();
        $data['packs'] = Pack::where('translation_lang', $this->lang->get('abbr'))->with('currency')->get();
        $data['payment_methods'] = PaymentMethod::orderBy('lft')->get();

        $this->packs = $data['packs'];
        $this->payment_methods = $data['payment_methods'];
        Rules::$packs = $this->packs;
        Rules::$payment_methods = $this->payment_methods;

        view()->share('countries', $data['countries']);
        view()->share('categories', $data['categories']);
        view()->share('ad_types', $data['ad_types']);
        view()->share('salary_type', $data['salary_type']);
        view()->share('packs', $data['packs']);
        view()->share('payment_methods', $data['payment_methods']);

        // Meta Tags
        MetaTag::set('title', t('Post a Job'));
        MetaTag::set('description', t('Post a Job') . ' - ' . $this->country->get('name') . '.');
    }

    /**
     * Show the form the create a new ad post.
     *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
    public function getForm()
    {
        return view('ad.post.index');
    }

    /**
     * Store a new ad post.
     *
	 * @param Request $request
	 * @return $this|\Illuminate\Http\RedirectResponse
	 */
    public function postForm(Request $request)
    {
        // Form validation
        $validator = Validator::make($request->all(), Rules::Ad($request, 'POST'));
        if ($validator->fails()) {
            // BugFix with : $request->except('pictures')
            return back()->withErrors($validator)->withInput($request->except('pictures'));
        }


        // Get User if exists
        if (Auth::check()) {
            $user = $this->user;
        } else {
            if ($request->has('contact_email')) {
                $user = User::where('email', $request->input('contact_email'))->first();
            }
        }

        // Get city infos
        if ($request->has('city')) {
            $city = City::find($request->input('city'));
            if (is_null($city)) {
                flash()->error(t("Post Ads was disabled for this time. Please try later. Thank you."));

                return back();
            }
        }


        // Ad data
        $ad_info = [
            'country_code' => $this->country->get('code'),
            'user_id' => (isset($user) and !is_null($user)) ? $user->id : 0,
            'category_id' => $request->input('category'),
            'ad_type_id' => $request->input('ad_type'),
			'company_name' => $request->input('company_name'),
			'company_description' => $request->input('company_description'),
			'company_website' => $request->input('company_website'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'salary_min' => $request->input('salary_min'),
			'salary_max' => $request->input('salary_max'),
			'salary_type_id' => $request->input('salary_type'),
            'negotiable' => $request->input('negotiable'),
			'start_date' => $request->input('start_date'),
            'contact_name' => $request->input('contact_name'),
            'contact_email' => $request->input('contact_email'),
            'contact_phone' => $request->input('contact_phone'),
            'contact_phone_hidden' => $request->input('contact_phone_hidden'),
			//'address' => $request->input('address'),
            'city_id' => $request->input('city'),
            'lat' => $city->latitude,
            'lon' => $city->longitude,
            'pack_id' => $request->input('pack'),
            'ip_addr' => Ip::get(),
            'activation_token' => md5(uniqid()),
            'active' => (config('settings.require_ads_activation') == 1) ? 0 : 1,
        ];

        // Save Ad to database
        $ad = new Ad($ad_info);
        $ad->save();


        // Get Pack infos
        $pack = Pack::find($request->input('pack'));
        $need_payment = false;
        if (!is_null($pack) and $pack->price > 0 and $request->has('payment_method')) {
            $need_payment = true;
        }

        // Add the Payment Method
        if ($need_payment) {
            $payment_info = array(
                'ad_id' => $ad->id,
                'pack_id' => $pack->id,
                'payment_method_id' => $request->input('payment_method'),
            );
            $payment = new Payment($payment_info);
            $payment->save();
        }

        // User country unknown (Update It!)
        if (isset($user) and isset($user->country_code) and $user->country_code == '') {
            if (is_numeric($user->id)) {
                $user = User::find($user->id);
                if (!is_null($user)) {
                    $user->country_code = $this->country->get('code');
                    $user->save();
                }
            }
        }


        $country_code = strtolower($this->country->get('code'));
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

					// Save Resume on the server
					$file->move($full_destination_path, $new_filename);

					// Ad Logo in database
					$ad->logo = $new_filename;
					$ad->save();
				} catch (\Exception $e) {
					flash()->error($e->getMessage());
				}
            }
        }


        // Init. result
        $result = true;

        // CheckOut
        if ($need_payment) {
            $result = $this->postPayment($request, $ad);
        }

        if ($result) {
            // Send Confirmation Email
            if (config('settings.require_ads_activation') == 1) {
                try {
                    Mail::send(new AdPosted($ad));
                } catch (\Exception $e) {
                    flash()->error($e->getMessage());
                }
            }

            return redirect($this->uri['success'])->with(['success' => 1, 'message' => $this->msg['post']['success']]);
        } else {
            return redirect($this->uri['form'] . '?error=payment')->withInput();
        }
    }

    /**
     * Success post
     *
     * @return mixed
     */
    public function success()
    {
        if (!session('success')) {
            return redirect($this->lang->get('abbr') . '/');
        }

        // Meta Tags
        MetaTag::set('title', session('message'));
        MetaTag::set('description', session('message'));

        return view('ad.post.success');
    }

    /**
     * Activation
     *
     * @param $token
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function activation($token)
    {
        $ad = Ad::withoutGlobalScopes([ActiveScope::class, ReviewedScope::class])->where('activation_token', $token)->first();

        if ($ad) {
            if ($ad->active != 1) {
                // Activate
                $ad->active = 1;
                $ad->save();
                flash()->success(t($this->msg['activation']['success'], ['title' => $ad->title]));
            } else {
                flash()->error(t($this->msg['activation']['multiple']));
            }

            return redirect($this->lang->get('abbr') . '/' . slugify($ad->title) . '/' . $ad->id . '.html?preview=1');
        } else {
            $data = ['error' => 1, 'message' => t($this->msg['activation']['error'])];
        }

        // Meta Tags
        MetaTag::set('title', $data['message']);
        MetaTag::set('description', $data['message']);

        return view('ad.post.activation', $data);
    }

    /**
     * Send Payment
     *
     * @param Request $request
     * @param Ad $ad
     * @return bool
     */
    public function postPayment(Request $request, Ad $ad)
    {
        // Payment by Paypal (1 in 'payment_methods' table)
        if ($request->input('payment_method') == 1) {
            return PaymentHelper\Paypal::postPayment($request, $ad);
        }

        // No Payment
        return true;
    }

    /**
     * Success Payment
     *
     * @return mixed
     */
    public function getSuccessPayment()
    {
        // Get session parameters
        $params = Session::get('params');

        if ($params) {
            // Get Ad
            $ad = Ad::withoutGlobalScopes([ActiveScope::class, ReviewedScope::class])->find($params['ad_id']);

            // Payment by Paypal
            if (isset($params['payment_method']) and $params['payment_method'] == 1) {
                return PaymentHelper\Paypal::getSuccessPayment($params, $ad);
            }
        }

        // Problem with session
        flash()->error($this->msg['checkout']['error']);

        // Go to Post form
        return redirect($this->uri['form'] . '?error=paymentSessionNotFound');
    }

    /**
     * Cancel Payment
     *
     * @return mixed
     */
    public function cancelPayment()
    {
        // Get session parameters
        $params = Session::get('params');

        if ($params) {
            // Get Ad
            $ad = Ad::withoutGlobalScopes([ActiveScope::class, ReviewedScope::class])->find($params['ad_id']);
            $ad->delete();
        }

        flash()->error($this->msg['checkout']['cancel']);

        // Redirect to Ad form
        return redirect($this->uri['form'] . '?error=paymentCancelled')->withInput();
    }
}
