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

class OIDplusPageAdminCreateRa extends OIDplusPagePluginAdmin {

	public function action(&$handled) {
		if (isset($_POST["action"]) && ($_POST["action"] == "create_ra")) {
			$handled = true;

			if (!OIDplus::authUtils()::isAdminLoggedIn()) {
				$out['icon'] = 'img/error_big.png';
				$out['text'] = '<p>You need to <a '.OIDplus::gui()->link('oidplus:login').'>log in</a> as administrator.</p>';
				return $out;
			}

			$email = $_POST['email'];
			$password1 = $_POST['password1'];
			$password2 = $_POST['password2'];

			if (!OIDplus::mailUtils()->validMailAddress($email)) {
				throw new OIDplusException('eMail address is invalid.');
			}

			$res = OIDplus::db()->query("select * from ###ra where email = ?", array($email)); // TODO: this should be a static function in the RA class
			if ($res->num_rows() > 0) {
				throw new OIDplusException('RA does already exist');
			}

			if ($password1 !== $password2) {
				throw new OIDplusException('Passwords are not equal');
			}

			if (strlen($password1) < OIDplus::config()->getValue('ra_min_password_length')) {
				throw new OIDplusException('Password is too short. Minimum password length: '.OIDplus::config()->getValue('ra_min_password_length'));
			}

			OIDplus::logger()->log("RA($email)!/A?", "RA '$email' was created by the admin, without email address verification or invitation");

			$ra = new OIDplusRA($email);
			$ra->register_ra($password1);

			echo json_encode(array("status" => 0));
		}
	}

	public function init($html=true) {
		// Nothing
	}

	public function gui($id, &$out, &$handled) {
		if ($id == 'oidplus:create_ra') {
			$handled = true;
			$out['title'] = 'Manual creation of a RA';
			$out['icon'] = file_exists(__DIR__.'/icon_big.png') ? OIDplus::webpath(__DIR__).'icon_big.png' : '';

			if (!OIDplus::authUtils()::isAdminLoggedIn()) {
				$out['icon'] = 'img/error_big.png';
				$out['text'] = '<p>You need to <a '.OIDplus::gui()->link('oidplus:login').'>log in</a> as administrator.</p>';
				return $out;
			}

			$out['text'] .= '<form id="adminCreateRaFrom" onsubmit="return adminCreateRaFormOnSubmit();">';
			$out['text'] .= '<div><label class="padding_label">E-Mail:</label><input type="text" id="email" value=""></div>';
			$out['text'] .= '<div><label class="padding_label">Password:</label><input type="password" id="password1" value=""/></div>';
			$out['text'] .= '<div><label class="padding_label">Repeat:</label><input type="password" id="password2" value=""/></div>';
			$out['text'] .= '<br><input type="submit" value="Create"></form>';
		}
	}

	public function tree(&$json, $ra_email=null, $nonjs=false, $req_goto='') {
		if (file_exists(__DIR__.'/treeicon.png')) {
			$tree_icon = OIDplus::webpath(__DIR__).'treeicon.png';
		} else {
			$tree_icon = null; // default icon (folder)
		}

		$json[] = array(
			'id' => 'oidplus:create_ra',
			'icon' => $tree_icon,
			'text' => 'Create RA manually'
		);

		return true;
	}

	public function tree_search($request) {
		return false;
	}
}