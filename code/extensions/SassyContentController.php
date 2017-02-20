<?php
class SassyContentControllerExtension extends Extension {
	public function onBeforeInit() {
		parent::onBeforeInit();
	}
	/**
	 * If we are not live then return a cache-busting string to use in template calling SassyController endpoints.
	 *
	 * e.g. in template: <style>/sassy/css/main?cb={$SassyCacheBuster}</style>
	 *
	 * @return void|float
	 */
	public function SassyCacheBuster() {
		if (!Director::isLive()) {
			return microtime(true);
		}
	}
}