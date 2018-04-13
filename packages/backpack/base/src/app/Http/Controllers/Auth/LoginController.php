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

namespace Larapen\Base\app\Http\Controllers\Auth;

use Illuminate\Http\Request;

class LoginController extends \Backpack\Base\app\Http\Controllers\Auth\LoginController
{
	/**
	 * AuthController constructor.
	 */
    public function __construct()
    {
		parent::__construct();

		$this->loginPath = config('backpack.base.route_prefix', 'admin') . '/login';
		$this->redirectTo = config('backpack.base.route_prefix', 'admin');
		$this->redirectAfterLogout = config('backpack.base.route_prefix', 'admin') . '/login';
    }

	/**
	 * @param Request $request
	 * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 */
	public function logout(Request $request)
	{
		$this->guard()->logout();
		$request->session()->flush();
		$request->session()->regenerate();

		return redirect(property_exists($this, 'redirectAfterLogout') ? $this->redirectAfterLogout : '/');
	}
}
