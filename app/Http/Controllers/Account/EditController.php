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

namespace App\Http\Controllers\Account;

use App\Larapen\Models\Resume;
use App\Larapen\Models\User;
use App\Http\Controllers\FrontController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class EditController extends AccountBaseController
{
	/**
	 * @param Request $request
	 * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 */
    public function details(Request $request)
    {
        // Validation
		// Check if email has changed
		$email_changed = ($request->input('email') != $this->user->email);
		$rules = [
			'gender' => 'required|not_in:0',
			'name' => 'required|max:100',
			'phone' => 'required|max:60',
			'email' => ($email_changed) ? 'required|email|unique:users,email' : 'required|email',
		];
        // Check 'resume' is required
        $resume = null;
        if ($request->hasFile('resume')) {
            $resume = Resume::where('user_id', $this->user->id)->first();
            if (empty($resume) or trim($resume->filename) == '' or !file_exists(public_path() . '/uploads/resumes/' . $resume->filename)) {
                $rules['resume'] = 'required|mimes:pdf,doc,docx,word,rtf,rtx,ppt,pptx,odt,odp,wps,jpeg,jpg,bmp,png';
            }
        }
        $this->validate($request, $rules);

        
        // UPDATE
        $user = User::find($this->user->id);
        $user->user_type_id = $request->input('user_type');
        $user->gender_id = $request->input('gender');
        $user->name = $request->input('name');
        $user->about = $request->input('about');
        $user->country_code = $request->input('country');
        $user->phone = $request->input('phone');
        $user->phone_hidden = $request->input('phone_hidden');
        if ($email_changed) {
            $user->email = $request->input('email');
        }
        $user->receive_newsletter = $request->input('receive_newsletter');
        $user->receive_advice = $request->input('receive_advice');
        $user->save();


        // UPLOAD FILE : RESUME
        $country_code = strtolower($this->country->get('code'));
        if ($request->hasFile('resume'))
        {
            // Create resume if doesn't exists
            if (empty($resume)) {
                $resume = [
                    'country_code' => $this->country->get('code'),
                    'user_id' => $this->user->id,
                    'active' => 1,
                ];
                $resume = new Resume($resume);
                $resume->save();
                $resume = Resume::where('user_id', $this->user->id)->first();
            }

            // Upload...
            $destination_path = 'uploads/resumes/';
            $prefix_filename = $country_code . '/' . $this->user->id . '/';
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

                // Delete old file if new file has uploaded
                if (!empty($resume->filename)) {
                    if (is_file(public_path() . '/' . $destination_path . $resume->filename)) {
                        @unlink(public_path() . '/' . $destination_path . $resume->filename);
                    }
                }

                // Save Resume on the server
                $file->move($full_destination_path, $new_filename);

                // Ad Resume in database
                $resume->filename = $new_filename;
                $resume->save();
            }
        }

        
        flash()->success(t("Your details account has update successfully."));
        
        return redirect($this->lang->get('abbr') . '/account');
    }

	/**
	 * @param Request $request
	 * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 */
    public function settings(Request $request)
    {
        // Validation
        $this->validate($request, [
            'password' => 'between:5,15|confirmed',
        ]);
        
        // Update
        $user = User::find($this->user->id);
        $user->comments_enabled = (int)$request->input('comments_enabled');
        if ($request->has('password')) {
            $user->password = $request->input('password');
        }
        $user->save();
        
        flash()->success(t("Your settings account has update successfully."));
        
        return redirect($this->lang->get('abbr') . '/account');
    }

	/**
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
    public function preferences()
    {
        $data = [];
        
        return view('account.home', $data);
    }
}
