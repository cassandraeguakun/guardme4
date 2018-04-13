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

namespace App\Http\Controllers\Admin;

use App\Larapen\Models\AdType;
use App\Larapen\Models\Category;
use Illuminate\Support\Facades\Input;
use App\Larapen\Models\SalaryType;
use Larapen\CRUD\app\Http\Controllers\CrudController;
// VALIDATION: change the requests to match your own file names if you need form validation
use App\Http\Requests\Admin\AdRequest as StoreRequest;
use App\Http\Requests\Admin\AdRequest as UpdateRequest;

class AdController extends CrudController
{
    public function __construct()
    {
		parent::__construct();

		/*
		|--------------------------------------------------------------------------
		| BASIC CRUD INFORMATION
		|--------------------------------------------------------------------------
		*/
		$this->crud->setModel('App\Larapen\Models\Ad');
		$this->crud->setRoute(config('backpack.base.route_prefix', 'admin') . '/ad');
		$this->crud->setEntityNameStrings('ad', 'ads');
		$this->crud->enableAjaxTable();
		$this->crud->denyAccess(['create']);

		// Filters
		if (Input::has('active')) {
			if (Input::get('active') == 0) {
				$this->crud->addClause('where', 'active', '=', 0);
				if (config('settings.ads_review_activation')) {
					$this->crud->addClause('orWhere', 'reviewed', '=', 0);
				}
			}
			if (Input::get('active') == 1) {
				$this->crud->addClause('where', 'active', '=', 1);
				if (config('settings.ads_review_activation')) {
					$this->crud->addClause('where', 'reviewed', '=', 1);
				}
			}
		}

		/*
		|--------------------------------------------------------------------------
		| COLUMNS AND FIELDS
		|--------------------------------------------------------------------------
		*/
		// COLUMNS
		$this->crud->addColumn([
			'name' => 'created_at',
			'label' => "Date",
			'type' => 'date',
		]);
		$this->crud->addColumn([
			'name' => 'title',
			'label' => "Title",
			'type' => "model_function",
			'function_name' => 'getTitleHtml',
		]);
		$this->crud->addColumn([
			'name' => 'salary_max',
			'label' => "Salary",
		]);
		$this->crud->addColumn([
			'name' => 'contact_name',
			'label' => "Saller Name",
		]);
		$this->crud->addColumn([
			'name' => 'country_code',
			'label' => "Country",
		]);
		$this->crud->addColumn([
			'name' => 'city_id',
			'label' => "City",
			'type' => "model_function",
			'function_name' => 'getCityHtml',
		]);
		$this->crud->addColumn([
			'name' => 'active',
			'label' => "Active",
			'type' => "model_function",
			'function_name' => 'getActiveHtml',
		]);
		$this->crud->addColumn([
			'name' => 'reviewed',
			'label' => "Reviewed",
			'type' => "model_function",
			'function_name' => 'getReviewedHtml',
		]);

		// FIELDS
		$this->crud->addField([
			'name' => 'company_name',
			'label' => 'Company Name',
			'type' => 'text',
			'attributes' => [
				'placeholder' => 'Enter the Company Name',
			],
		]);
		$this->crud->addField([
			'name' => 'logo',
			'label' => 'Company logo (Supported file extensions: jpg, jpeg, png, gif)',
			'type' => 'browse',
		]);
		$this->crud->addField([
			'name' => 'company_description',
			'label' => "Company Description",
			'type' => 'textarea',
			'attributes' => [
				'placeholder' => 'Enter a Company Description',
			],
		]);
		$this->crud->addField([
			'label' => "Ad Type",
			'name' => 'ad_type_id',
			'type' => 'select_from_array',
			'options' => $this->adType(),
			'allows_null' => false,
		]);
		$this->crud->addField([
			'label' => "Category",
			'name' => 'category_id',
			'type' => 'select_from_array',
			'options' => $this->categories(),
			'allows_null' => false,
		]);
		$this->crud->addField([
			'name' => 'title',
			'label' => 'Title',
			'type' => 'text',
			'attributes' => [
				'placeholder' => 'Enter a Title',
			],
		]);
		$this->crud->addField([
			'name' => 'description',
			'label' => "Description",
			'type' => 'textarea',
			'attributes' => [
				'placeholder' => 'Enter a Description',
			],
		]);
		$this->crud->addField([
			'name' => 'salary_min',
			'label' => "Salary (min)",
			'type' => 'text',
			'attributes' => [
				'placeholder' => 'Enter a Salary (min)',
			],
		]);
		$this->crud->addField([
			'name' => 'salary_max',
			'label' => "Salary (max)",
			'type' => 'text',
			'attributes' => [
				'placeholder' => 'Enter a Salary (max)',
			],
		]);
		$this->crud->addField([
			'label' => "Type Salary",
			'name' => 'salary_type_id',
			'type' => 'select_from_array',
			'options' => $this->salaryType(),
			'allows_null' => false,
		]);
		$this->crud->addField([
			'name' => 'negotiable',
			'label' => "Negotiable Price",
			'type' => 'checkbox',
		]);

		$this->crud->addField([
			'name' => 'contact_name',
			'label' => 'User Name',
			'type' => 'text',
			'attributes' => [
				'placeholder' => 'User Name',
			],
		]);
		$this->crud->addField([
			'name' => 'contact_email',
			'label' => 'User Email',
			'type' => 'text',
			'attributes' => [
				'placeholder' => 'User Email',
			],
		]);
		$this->crud->addField([
			'name' => 'contact_phone',
			'label' => 'User Phone',
			'type' => 'text',
			'attributes' => [
				'placeholder' => 'User Phone',
			],
		]);
		$this->crud->addField([
			'name' => 'contact_phone_hidden',
			'label' => "Hide contact phone",
			'type' => 'checkbox',
		]);
		$this->crud->addField([
			'name' => 'company_website',
			'label' => 'Company Website',
			'type' => 'text',
			'attributes' => [
				'placeholder' => 'Enter the Company Website',
			],
		]);
		/*$this->crud->addField([
			'name' => 'address',
			'label' => 'Address',
			'type' => 'text',
			'attributes' => [
				'placeholder' => 'Enter an Address',
			],
		]);*/
		$this->crud->addField([
			'name' => 'archived',
			'label' => "Archived",
			'type' => 'checkbox'
		]);
		$this->crud->addField([
			'name' => 'active',
			'label' => "Active",
			'type' => 'checkbox'
		]);
		$this->crud->addField([
			'name' => 'reviewed',
			'label' => "Reviewed",
			'type' => 'checkbox'
		]);
    }

    public function store(StoreRequest $request)
    {
        return parent::storeCrud();
    }

    public function update(UpdateRequest $request)
    {
        return parent::updateCrud();
    }

    public function adType()
    {
        $entries = AdType::where('translation_lang', config('app.locale'))->get();
        if (is_null($entries)) {
            return [];
        }

        $tab = [];
        foreach ($entries as $entry) {
            $tab[$entry->translation_of] = $entry->name;
        }

        return $tab;
    }

    public function categories()
    {
        $entries = Category::where('translation_lang', config('app.locale'))->orderBy('lft')->get();
        if (is_null($entries)) {
            return [];
        }

        $tab = [];
        foreach ($entries as $entry) {
			if ($entry->parent_id == 0) {
				$tab[$entry->translation_of] = $entry->name;
			} else {
				$tab[$entry->translation_of] = "---| " . $entry->name;
			}
        }

        return $tab;
    }

	public function salaryType()
	{
		$entries = SalaryType::where('translation_lang', config('app.locale'))->get();
		if (is_null($entries)) {
			return [];
		}

		$tab = [];
		foreach ($entries as $entry) {
			$tab[$entry->translation_of] = $entry->name;
		}

		return $tab;
	}
}
