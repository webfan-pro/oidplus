<?php

/*
 * OIDplus 2.0
 * Copyright 2019 Daniel Marschall, ViaThinkSoft
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(ticks=1);

set_time_limit(0);

require_once __DIR__ . '/../includes/oidplus.inc.php';

// Note: we don't want to use OIDplus::init() in this updater (it should be independent as much as possible)
OIDplus::baseConfig(); // This call will redirect to setup if userdata/baseconfig/config.inc.php is missing

define('OIDPLUS_REPO', 'https://svn.viathinksoft.com/svn/oidplus');

?><!DOCTYPE html>
<html lang="en">

<head>
	<title>OIDplus File Completeness Check</title>
	<meta name="robots" content="noindex">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="../setup/setup.css">
	<?php
	if (OIDplus::baseConfig()->getValue('RECAPTCHA_ENABLED', false)) {
	?>
	<script src="https://www.google.com/recaptcha/api.js"></script>
	<?php
	}
	?>
</head>

<body>

<h1>OIDplus File Completeness Check</h1>

<?php

echo '<p><input type="button" onclick="document.location=\'index.php\'" value="Go back to updater"></p>';

if (OIDplus::baseConfig()->getValue('RECAPTCHA_ENABLED', false)) {
	$secret = OIDplus::baseConfig()->getValue('RECAPTCHA_PRIVATE', '');
	$response = $_POST["g-recaptcha-response"];
	$verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret}&response={$response}");
	$captcha_success = json_decode($verify);
}

if (OIDplus::baseConfig()->getValue('RECAPTCHA_ENABLED', false) && ($captcha_success->success==false)) {
	echo '<p><font color="red"><b>CAPTCHA not sucessfully verified</b></font></p>';
	//echo '<p><a href="index.php">Try again</a></p>';
} else {
	if (!OIDplusAuthUtils::adminCheckPassword($_REQUEST['admin_password'])) {
		echo '<p><font color="red"><b>Wrong password</b></font></p>';
		//echo '<p><a href="index.php">Try again</a></p>';
	} else {
		$svn = new phpsvnclient(OIDPLUS_REPO);

		$svn_rev = isset($_REQUEST['svn_version']) && is_numeric($_REQUEST['svn_version']) ? (int)$_REQUEST['svn_version'] : -1;

		list($svn_cont, $local_cont) = $svn->compareToDirectory('../', '/trunk/', $svn_rev);
		foreach ($local_cont as $key => &$c) {
			if ((strpos($c,'userdata/') === 0) && ($c !== 'userdata/info.txt') && ($c !== 'userdata/.htaccess') && ($c !== 'userdata/index.html') && (substr($c,-1) !== '/')) unset($local_cont[$key]);
		}
		foreach ($svn_cont as $key => &$c) {
			if ((strpos($c,'userdata/') === 0) && ($c !== 'userdata/info.txt') && ($c !== 'userdata/.htaccess') && ($c !== 'userdata/index.html') && (substr($c,-1) !== '/')) unset($svn_cont[$key]);
		}
		echo '<pre>';
		echo "Compare local <--> svn-$svn_rev\n\n";
		echo "=== FILES MISSING ===\n";
		$diff = array_diff($svn_cont, $local_cont);
		if (count($diff) === 0) echo "Everything OK\n";
		foreach ($diff as $c) echo "$c\n";
		echo "\n";

		echo "=== ADDITIONAL FILES ===\n";
		$diff = array_diff($local_cont, $svn_cont);
		if (count($diff) === 0) echo "Everything OK\n";
		foreach ($diff as $c) echo "$c\n";
		echo "\n";
		echo '</pre>';
	}
}

?>

</body>
</html>