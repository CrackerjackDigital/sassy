<?php
class ScussiControllerTest extends FunctionalTest {
	public function testCSS() {
		$config = SassyController::config();

		$savedInputPath = $config->get('input_path');
		$savedConfig = $config->get('formatter_config');

		$config->input_path = "scussi/tests/scss";
		$config->formatter_config = 'live';

		$response = $this->get('scussi/css/test');

		$config->input_path = $savedInputPath;
		$config->formatter_config = $savedConfig;

		$this->assertEquals(
			200,
			$response->getStatusCode()
		);
		$this->assertContains(
			"text/css",
			$response->getHeader("Content-Type")
		);

		$this->assertContains("* {
  -webkit-box-sizing: border-box;
  -moz-box-sizing: border-box;
  box-sizing: border-box; }",
			$response->getBody()
		);
	}
}