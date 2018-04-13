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

namespace App\Mail;

use App\Larapen\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FormSent extends Mailable
{
    use Queueable, SerializesModels;

    public $msg;

    /**
     * Create a new message instance.
     *
     * @param $request
     */
    public function __construct($request)
    {
        $this->msg = $request;

		// Get admin email address
		$adminUser = User::where('is_admin', 1)->orderBy('id')->first();
		if (empty($adminUser)) {
			dd("No administrator found.");
		}

		$this->to($adminUser->email, $adminUser->name);
        $this->replyTo($request->email, $request->first_name . ' ' . $request->last_name);
        $this->subject(trans('mail.:app_name - New message', [
            'country'   => $request->country,
            'app_name'  => config('settings.app_name')
        ]));
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.form');
    }
}
