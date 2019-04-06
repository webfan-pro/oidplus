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

class OIDplusConfig {

	protected $values = array();
	protected $dirty = 1;

	public function prepareConfigKey($name, $description, $init_value, $protected, $visible) {
		OIDplus::db()->query("insert into ".OIDPLUS_TABLENAME_PREFIX."config (name, description, value, protected, visible) values ('".OIDplus::db()->real_escape_string($name)."', '".OIDplus::db()->real_escape_string($description)."', '".OIDplus::db()->real_escape_string($init_value)."', '".OIDplus::db()->real_escape_string($protected)."', '".OIDplus::db()->real_escape_string($visible)."')");
		$this->dirty = 1;
	}

	public function __construct() {
		$this->prepareConfigKey('system_title', 'What is the name of your RA?', 'OIDplus 2.0', 0, 1);
		$this->prepareConfigKey('global_cc', 'Global CC for all outgoing emails?', '', 0, 1);
		$this->prepareConfigKey('ra_min_password_length', 'Minimum length for RA passwords', '6', 0, 1);
		$this->prepareConfigKey('max_ra_invite_time', 'Max RA invite time in seconds (0 = infinite)', '0', 0, 1);
		$this->prepareConfigKey('max_ra_pwd_reset_time', 'Max RA password reset time in seconds (0 = infinite)', '0', 0, 1);
		$this->prepareConfigKey('whois_auth_token', 'OID-over-WHOIS authentication token to display confidential data', '', 0, 1);
	}

	public function systemTitle() {
		return trim($this->getValue('system_title'));
	}

	public function globalCC() {
		return trim(getValue('global_cc'));
	}

	public function minRaPasswordLength() {
		return $this->getValue('ra_min_password_length');
	}

	/* hardcoded in setup/, because during installation, we don't have a settings database
	public function minAdminPasswordLength() {
		return 6;
	}
	*/

	public function maxInviteTime() {
		return getValue('max_ra_invite_time');
	}

	public function maxPasswordResetTime() {
		return getValue('max_ra_pwd_reset_time');
	}

	public function authToken() {
		$val = trim($this->getValue('whois_auth_token'));
		return empty($val) ? false : $val;
	}

	public function getValue($name) {
		if ($this->dirty) {
			$this->values = array();
			$res = OIDplus::db()->query("select * from ".OIDPLUS_TABLENAME_PREFIX."config");
			while ($row = OIDplus::db()->fetch_object($res)) {
				$this->values[$row->name] = $row->value;
			}
			$this->dirty = 0;
		}

		if (isset($this->values[$name])) {
			return $this->values[$name];
		} else {
			return null;
		}
	}

	public function exists($name) {
		return !is_null($this->getValue($name));
	}

	public function setValue($name, $value) {
		// Check for valid values

		if ($name == 'system_title') {
			if (empty($value)) {
				throw new Exception("Please enter a value for the system title.");

			}
		}
		if ($name == 'global_cc') {
			if (!empty($value) && !oiddb_valid_email($value)) {
				throw new Exception("This is not a correct email address");
			}
		}
		if ($name == 'ra_min_password_length') {
			if (!is_numeric($value) || ($value < 1)) {
				throw new Exception("Please enter a valid password length.");
			}
		}
		if (($name == 'max_ra_invite_time') || ($name == 'max_ra_pwd_reset_time')) {
			if (!is_numeric($value) || ($value < 0)) {
				throw new Exception("Please enter a valid value.");
			}
		}
		if ($name == 'whois_auth_token') {
			$test_value = preg_replace('@[0-9a-zA-Z]*@', '', $value);
			if ($test_value != '') {
				throw new Exception("Only characters and numbers are allowed as authentication token.");
			}
		}

		if ($name == 'objecttypes_enabled') {
			# TODO: when objecttypes_enabled is changed at the admin control panel, we need to do a reload of the page, so that jsTree will be updated. Is there anything we can do?

			$ary = explode(';',$value);
			$uniq_ary = array_unique($ary);

			if (count($ary) != count($uniq_ary)) {
				throw new Exception("Please check your input. Some object types are double.");
			}

			foreach ($ary as $ot_check) {
				$ns_found = false;
				foreach (OIDplus::getRegisteredObjectTypes() as $ot) {
					if ($ot::ns() == $ot_check) {
						$ns_found = true;
						break;
					}
				}
				foreach (OIDplus::getDisabledObjectTypes() as $ot) {
					if ($ot::ns() == $ot_check) {
						$ns_found = true;
						break;
					}
				}
				if (!$ns_found) {
					throw new Exception("Please check your input. Namespace \"$ot_check\" is not found");
				}
			}
		}

		// Give plugins the possibility to stop the process (e.g. if the value is invalid)

		foreach (OIDplus::getPagePlugins('*') as $plugin) {
			$plugin->cfgSetValue($name, $value);
		}

		// Now change the value in the database

		if (!OIDplus::db()->query("update ".OIDPLUS_TABLENAME_PREFIX."config set value = '".OIDplus::db()->real_escape_string($value)."' where name = '".OIDplus::db()->real_escape_string($name)."'")) {
			throw new Exception(OIDplus::db()->error());
		}
		$this->values[$name] = $value;
	}

}
