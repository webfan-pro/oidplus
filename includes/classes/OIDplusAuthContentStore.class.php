<?php

/*
 * OIDplus 2.0
 * Copyright 2019 - 2021 Daniel Marschall, ViaThinkSoft
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

if (!defined('INSIDE_OIDPLUS')) die();

abstract class OIDplusAuthContentStore {

	protected $content = array();

	// Getter / Setter

	public abstract function getValue($name);

	public abstract function setValue($name, $value);

	protected abstract function destroySession();

	// RA authentication functions

	public function raLogin($email) {
		if (strpos($email, '|') !== false) return;

		$list = $this->getValue('oidplus_logged_in');
		if (is_null($list)) $list = '';

		$ary = ($list == '') ? array() : explode('|', $list);
		if (!in_array($email, $ary)) $ary[] = $email;
		$list = implode('|', $ary);

		$this->setValue('oidplus_logged_in', $list);
	}

	public function raLogout($email) {
		$list = $this->getValue('oidplus_logged_in');
		if (is_null($list)) $list = '';

		$ary = ($list == '') ? array() : explode('|', $list);
		$key = array_search($email, $ary);
		if ($key !== false) unset($ary[$key]);
		$list = implode('|', $ary);

		$this->setValue('oidplus_logged_in', $list);

		if (($list == '') && (!self::isAdminLoggedIn())) {
			// Nobody logged in anymore. Destroy session cookie to make GDPR people happy
			$this->destroySession();
		}
	}

	public function raNumLoggedIn() {
		return count(self::loggedInRaList());
	}

	public function raLogoutAll() {
		$this->setValue('oidplus_logged_in', '');
	}

	public function loggedInRaList() {
		if (OIDplus::authUtils()->forceAllLoggedOut()) {
			return array();
		}

		$list = $this->getValue('oidplus_logged_in');
		if (is_null($list)) $list = '';

		$res = array();
		foreach (array_unique(explode('|',$list)) as $ra_email) {
			if ($ra_email == '') continue;
			$res[] = new OIDplusRA($ra_email);
		}
		return $res;
	}

	public function isRaLoggedIn($email) {
		foreach (self::loggedInRaList() as $ra) {
			if ($email == $ra->raEmail()) return true;
		}
		return false;
	}

	// Admin authentication functions

	public function adminLogin() {
		$this->setValue('oidplus_admin_logged_in', '1');
	}

	public function adminLogout() {
		$this->setValue('oidplus_admin_logged_in', '0');

		if (self::raNumLoggedIn() == 0) {
			// Nobody logged in anymore. Destroy session cookie to make GDPR people happy
			$this->destroySession();
		}
	}

	public function isAdminLoggedIn() {
		if (OIDplus::authUtils()->forceAllLoggedOut()) {
			return false;
		}
		return $this->getValue('oidplus_admin_logged_in') == '1';
	}

}
