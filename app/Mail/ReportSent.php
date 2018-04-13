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
use App\Larapen\Helpers\Arr;
use App\Larapen\Models\Ad;

class ReportSent extends Mailable
{
    use Queueable, SerializesModels;

    public $ad;
    public $report;

    /**
     * Create a new message instance.
     *
     * @param Ad $ad
     * @param $report
     */
    public function __construct(Ad $ad, $report)
    {
        $this->ad = $ad;
        $this->report = (is_array($report)) ? Arr::toObject($report) : $report;

		// Get admin email address
		$adminUser = User::where('is_admin', 1)->orderBy('id')->first();
		if (empty($adminUser)) {
			dd("No administrator found.");
		}

		$this->to($adminUser->email, $adminUser->name);
		$this->replyTo($this->report->email, $this->report->email);
        $this->subject(trans('mail.New abuse report', [
            'app_name'      => config('settings.app_name'),
            'country_code'  => $ad->country_code
        ]));
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.ad.report-sent');
    }
}
