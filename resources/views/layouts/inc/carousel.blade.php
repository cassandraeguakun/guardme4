@if (isset($carousel) and !empty($carousel) and !empty($carousel->ads))
<div class="col-lg-12 content-box ">
	<div class="row row-featured">
		<div class="col-lg-12  box-title ">
			<div class="inner">
				<h2>
					<span class="title-3">{!! $carousel->title !!}</span>
					<a href="{{ $carousel->link }}" class="sell-your-item">
						{{ t('View more') }} <i class="  icon-th-list"></i>
					</a>
				</h2>
			</div>
		</div>

		<div style="clear: both"></div>

		<div class=" relative content featured-list-row clearfix">

			<nav class="slider-nav has-white-bg nav-narrow-svg">
				<a class="prev">
					<span class="nav-icon-wrap"></span>
				</a>
				<a class="next">
					<span class="nav-icon-wrap"></span>
				</a>
			</nav>

			<div class="no-margin featured-list-slider ">
				<?php
				foreach($carousel->ads as $key => $ad):
					if (!$countries->has($ad->country_code)) continue;

					// Ads URL setting
					$adUrl = lurl(slugify($ad->title) . '/' . $ad->id . '.html');

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
					<div class="item">
						<a href="{{ $adUrl }}">
							<span class="item-carousel-thumb">
								<img class="img-responsive" src="{{ $adLogo }}" alt="{{ mb_ucfirst($ad->title) }}" data-no-retina style="border: 1px solid #e7e7e7; margin-top: 2px;">
							</span>
							<span class="item-name">{{ mb_ucfirst($ad->title) }}</span>
							<span class="price">
								{{ \App\Larapen\Models\AdType::find($ad->ad_type_id)->name }}
							</span>
						</a>
					</div>
				<?php endforeach; ?>

			</div>

		</div>
	</div>
</div>
@endif