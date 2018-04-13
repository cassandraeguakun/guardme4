{{--
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
--}}
@extends('layouts.master')

<?php
// Phone
$ad->contact_phone = TextToImage::make($ad->contact_phone, IMAGETYPE_PNG, ['backgroundColor' => '#2ECC71', 'color' => '#FFFFFF']);
?>

@section('javascript-top')
	@parent
	@if (config('services.googlemaps.key'))
	<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.googlemaps.key') }}" type="text/javascript"></script>
	@endif
@endsection

@section('content')
	{!! csrf_field() !!}
	<input type="hidden" id="ad_id" value="{{ $ad->id }}">
	<div class="main-container">

		@if (Session::has('flash_notification.message'))
			<div class="container" style="margin-bottom: -10px; margin-top: -10px;">
				<div class="row">
					<div class="col-lg-12">
						@include('flash::message')
					</div>
				</div>
			</div>
			<?php Session::forget('flash_notification.message'); ?>
		@endif

		@include('layouts.inc.advertising.top')

		<div class="container">
			<ol class="breadcrumb pull-left">
				<li><a href="{{ lurl('/') }}"><i class="icon-home fa"></i></a></li>
				<li><a href="{{ lurl('/') }}">{{ $country->get('name') }}</a></li>
				<li>
					<a href="{{ lurl(trans('routes.v-search-cat', ['countryCode' => $country->get('icode'), 'catSlug' => $parent_cat->slug])) }}">
						{{ $parent_cat->name }}
					</a>
				</li>
				@if ($parent_cat->id != $cat->id)
				<li>
					<a href="{{ lurl(trans('routes.v-search-subCat',
					[
					'countryCode' => $country->get('icode'),
					'catSlug' => $parent_cat->slug,
					'subCatSlug' => $cat->slug
					])) }}">
						{{ $cat->name }}
					</a>
				</li>
				@endif
				<li class="active">{{ $ad->title }}</li>
			</ol>
			<div class="pull-right backtolist">
				<a href="{{ URL::previous() }}">
					<i class="fa fa-angle-double-left"></i> {{ t('Back to Results') }}
				</a>
			</div>
		</div>
		<div class="container">
			<div class="row">
				<div class="col-sm-9 page-content col-thin-right">
					<div class="inner inner-box ads-details-wrapper">
						<h2>
							<strong> <a href="{{ lurl(slugify($ad->title).'/'.$ad->id.'.html') }}"
										title="{{ mb_ucfirst($ad->title) }}">{{ mb_ucfirst($ad->title) }}</a> </strong>
							<small class="label label-default adlistingtype">{{ t(':type Job', ['type' => t($ad->adType->name)]) }}</small>
						</h2>
						<span class="info-row">
							<span class="date"><i class=" icon-clock"> </i> {{ $ad->created_at_ta }} </span> -&nbsp;
							<span class="category">{{ $parent_cat->name }}</span> -&nbsp;
							<span class="item-location"><i class="fa fa-map-marker"></i> {{ $ad->city->name }} </span> -&nbsp;
							<span class="category"><i class="icon-eye-3"></i> {{ $ad->visits }} {{ (\Illuminate\Support\Facades\Lang::has('global.views')) ? trans_choice('global.views', $ad->visits) : '' }}</span>
						</span>

						<div class="Ads-Details">
							<div class="row" style="padding-bottom: 20px;">
								<div class="ads-details-info jobs-details-info col-md-8">
									<h5 class="list-title">{{ t('Job Details') }}</h5>
									{!! nl2br(auto_link(str_clean($ad->description))) !!}
									@if (!empty($ad->company_description))
										<div style="margin-bottom: 50px;"></div>
										<h5 class="list-title">{{ t('Company Description') }}</h5>
										{!! nl2br(auto_link(str_clean($ad->company_description))) !!}
									@endif
								</div>
								<div class="col-md-4">
									<aside class="panel panel-body panel-details job-summery">
										<ul>
											@if (!empty($ad->start_date))
											<li>
												<p class="no-margin">
													<strong>{{ t('Start Date') }}:</strong>&nbsp;
													{{ $ad->start_date }}
												</p>
											</li>
											@endif
											<li>
												<p class="no-margin">
													<strong>{{ t('Company') }}:</strong>&nbsp;
													<a href="{!! lurl(trans('routes.v-search-company', ['companyName' => $ad->company_name])) !!}">
														{{ $ad->company_name }}
													</a>
												</p>
											</li>
											<li>
												<p class=" no-margin">
													<strong>{{ t('Salary') }}:</strong>&nbsp;
													@if ($ad->salary_max > 0)
														@if ($country->get('currency')->in_left == 1){{ $country->get('currency')->symbol }}@endif
														{{ \App\Larapen\Helpers\Number::short($ad->salary_max) }}
														@if ($country->get('currency')->in_left == 0){{ $country->get('currency')->symbol }}@endif
													@else
														@if ($country->get('currency')->in_left == 1){{ $country->get('currency')->symbol }}@endif
														{{ '--' }}
														@if ($country->get('currency')->in_left == 0){{ $country->get('currency')->symbol }}@endif
													@endif
												</p>
											</li>
											<li>
												<p class="no-margin">
													<strong>{{ t('Job Type') }}:</strong>&nbsp;
													<a href="{{ lurl(trans('routes.t-search')) . '?type[]=' . $ad->ad_type_id }}">
														{{ \App\Larapen\Models\AdType::find($ad->ad_type_id)->name }}
													</a>
												</p>
											</li>
											<li>
												<p class="no-margin">
													<strong>{{ t('Location') }}:</strong>&nbsp;
													<a href="{!! lurl(trans('routes.v-search-location', ['countryCode' => $country->get('icode'), 'city' => slugify($ad->city->name), 'id' => $ad->city->id])) !!}">
														{{ $ad->city->name }}
													</a>
												</p>
											</li>
										</ul>
									</aside>
									<div class="ads-action">
										<ul class="list-border">
											@if (isset($ad->user) and $ad->user->id != 1)
												<li>
													<a href="{{ lurl(trans('routes.v-search-user', ['countryCode' => $country->get('icode'), 'id' => $ad->user->id])) }}">
														<i class="fa fa-user"></i> {{ t('More jobs by Company') }}
													</a>
												</li>
											@endif
											<li><a class="make-favorite" id="{{ $ad->id }}">
												@if (Auth::check())
													@if (\App\Larapen\Models\SavedAd::where('user_id', $user->id)->where('ad_id', $ad->id)->count() > 0)
														<i class="fa fa-heart"></i> {{ t('Remove favorite') }} </a>
													@else
														<i class="fa fa-heart"></i> {{ t('Save ad') }} </a>
													@endif
												@else
													<i class="fa fa-heart"></i> {{ t('Save ad') }} </a>
												@endif
											</li>
											<!--<li><a href="#"> <i class="fa fa-share-alt"></i> {{ t('Share ad') }} </a> </li>-->
											<li>
												<a href="#report_abuse" data-toggle="modal">
													<i class="fa icon-info-circled-alt"></i> {{ t('Report abuse') }}
												</a>
											</li>
										</ul>
									</div>
								</div>

								<br>&nbsp;<br>
							</div>
							<div class="content-footer text-left">
								@if (Auth::check())
									@if ($user->id == $ad->user_id)
										<a class="btn btn-default" href="{{ lurl('update/'.$ad->id) }}">
											<i class="icon-pencil-2"></i> {{ t('Update') }}
										</a>
									@else
										@if ($ad->contact_email != '')
											<a class="btn btn-default" data-toggle="modal" href="#applyJob">
												<i class=" icon-mail-2"></i> {{ t('Apply Online') }}
											</a>
										@endif
									@endif
								@else
									@if ($ad->contact_email != '')
										<a class="btn btn-default" data-toggle="modal" href="#applyJob">
											<i class=" icon-mail-2"></i> {{ t('Apply Online') }}
										</a>
									@endif
								@endif
								@if ($ad->contact_phone_hidden != 1 and !empty($ad->contact_phone))
									<a class="btn btn-success showphone">
										<i class="icon-phone-1"></i> {!! $ad->contact_phone !!}{{-- t('View phone') --}}
									</a>
								@endif
							</div>
						</div>

						@if(config('settings.show_ad_on_googlemap'))
							<div class="Ads-OnGoogleMaps" style="margin-bottom: 10px;">
								<h5 class="list-title"><strong>{{ t('Location') }}</strong></h5>
								<iframe id="googleMaps" width="100%" height="170" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src=""></iframe>
							</div>
						@endif
					</div>
					<!--/.ads-details-wrapper-->
				</div>
				<!--/.page-content-->

				<div class="col-sm-3 page-sidebar-right">
					<aside>
						<div class="panel sidebar-panel panel-contact-seller">
							<div class="panel-heading">{{ t('Company Information') }}</div>
							<div class="panel-content user-info">
								<div class="panel-body text-center">
									<?php
									// Logo setting
									$adLogo = '';
									if (!empty($ad->logo)) {
										if (is_file(public_path() . '/uploads/pictures/'. $ad->logo)) {
											$adLogo = url('pic/x/cache/medium/' . $ad->logo);
										}
										if ($adLogo=='') {
											if (is_file(public_path() . '/'. $ad->logo)) {
												$adLogo = url('pic/x/cache/medium/' . $ad->logo);
											}
										}
									}
									// Default picture
									if ($adLogo=='') {
										$adLogo = url('pic/x/cache/medium/' . config('larapen.core.picture'));
									}
									?>
									<div class="seller-info">
										<div class="company-logo-thumb">
											<a>
												<img alt="Logo {{ $ad->company_name }}" class="img-responsive img-circle" src="{{ $adLogo }}" data-no-retina>
											</a>
										</div>

										@if (isset($ad->company_name) and $ad->company_name != '')
											@if (isset($ad->user) and $ad->user->id != 1)
												<h3 class="no-margin">
													<a href="{{ lurl(trans('routes.v-search-user', ['countryCode' => $country->get('icode'), 'id' => $ad->user->id])) }}">
														{{ $ad->company_name }}
													</a>
												</h3>
											@else
												<h3 class="no-margin">{{ $ad->company_name }}</h3>
											@endif
										@endif
										<p>
											{{ t('Location') }}:&nbsp;
											<strong>
												<a href="{!! lurl(trans('routes.v-search-location', ['countryCode' => $country->get('icode'), 'city' => slugify($ad->city->name), 'id' => $ad->city->id])) !!}">
													{{ $ad->city->name }}
												</a>
											</strong>
										</p>
										@if($ad->user and !empty($ad->user->created_at_ta))
											<p> {{ t('Joined') }}: <strong>{{ $ad->user->created_at_ta }}</strong></p>
										@endif
										@if(!empty($ad->company_website))
											<p>
												{{ t('Web') }}:
												<strong>
													<a href="{{ $ad->company_website }}" target="_blank" rel="nofollow">
														{{ getHostByUrl($ad->company_website) }}
													</a>
												</strong>
											</p>
										@endif
									</div>
									<div class="user-ads-action">
										@if (Auth::check())
											@if ($user->id == $ad->user_id)
												<a href="{{ lurl('update/'.$ad->id) }}" data-toggle="modal" class="btn btn-default btn-block">
													<i class=" icon-pencil-2"></i> {{ t('Update') }}
												</a>
											@else
												@if ($ad->contact_email != '')
													<a href="#applyJob" data-toggle="modal" class="btn btn-default btn-block">
														<i class=" icon-mail-2"></i> {{ t('Apply Online') }}
													</a>
												@endif
											@endif
										@else
											@if ($ad->contact_email != '')
												<a href="#applyJob" data-toggle="modal" class="btn btn-default btn-block">
													<i class=" icon-mail-2"></i> {{ t('Apply Online') }}
												</a>
											@endif
										@endif
										@if ($ad->contact_phone_hidden != 1 and !empty($ad->contact_phone))
											<a class="btn btn-success btn-block showphone">
												<i class=" icon-phone-1"></i> {!! $ad->contact_phone !!}{{-- t('View phone') --}}
											</a>
										@endif
									</div>
								</div>
							</div>
						</div>

						@include('layouts.inc.social.horizontal')

						<div class="panel sidebar-panel">
							<div class="panel-heading">{{ t('Safety Tips for Buyers') }}</div>
							<div class="panel-content">
								<div class="panel-body text-left">
									<ul class="list-check">
										<li> {{ t('Meet seller at a public place') }} </li>
										<li> {{ t('Check the item before you buy') }} </li>
										<li> {{ t('Pay only after collecting the item') }} </li>
									</ul>
									<p>
										<a class="pull-right" href="{{ lurl(trans('routes.anti-scam')) }}">
											{{ t('Know more') }}
											<i class="fa fa-angle-double-right"></i>
										</a>
									</p>
								</div>
							</div>
						</div>
					</aside>
				</div>
			</div>

			<div style="margin-top: 30px;"></div>
			@include('layouts.inc.carousel')
			@include('layouts.inc.advertising.bottom')
			@include('layouts.inc.tools.facebook-comments')

		</div>
	</div>
@endsection

@section('modal-abuse')
	@include('ad.details.inc.modal-abuse')
@endsection

@section('modal-message')
	@include('ad.details.inc.modal-message')
@endsection

@section('javascript')
	@parent
	<script src="{{ url('assets/plugins/bxslider/jquery.bxslider.min.js') }}"></script>
	<script>
		var stateId = '<?php echo (isset($city)) ? $country->get('code') . '.' . $city->subadmin1_code : '0' ?>';

		/* JS translation */
		var lang = {
			loginToSaveAd: "@lang('global.Please log in to save the Ads.')",
			loginToSaveSearch: "@lang('global.Please log in to save your search.')",
			confirmationSaveSearch: "@lang('global.Search saved successfully !')",
			confirmationRemoveSaveSearch: "@lang('global.Search deleted successfully !')"
		};

		$('.bxslider').bxSlider({
			pagerCustom: '#bx-pager',
			controls: true,
			nextText: " @lang('global.Next') &raquo;",
			prevText: "&laquo; @lang('global.Previous') "
		});

		$(document).ready(function () {
			@if(count($errors) > 0)
				@if(count($errors) > 0 and old('msg_form')=='1')
					$('#applyJob').modal();
				@endif
				@if(count($errors) > 0 and old('abuse_form')=='1')
					$('#report_abuse').modal();
				@endif
			@endif
			@if(config('settings.show_ad_on_googlemap'))
				genGoogleMaps(
				'<?php echo config('services.googlemaps.key'); ?>',
				'<?php echo (isset($ad->city) and !is_null($ad->city)) ? addslashes($ad->city->name) . ',' . $country->get('name') : $country->get('name') ?>',
				'<?php echo $lang->get('abbr'); ?>'
				);
			@endif
		})
	</script>
	<script src="{{ url('assets/js/form-validation.js') }}"></script>
	<script src="{{ url('assets/js/app/show.phone.js') }}"></script>
	<script type="text/javascript" src="{{ url('assets/js/app/make.favorite.js') }}"></script>
@endsection