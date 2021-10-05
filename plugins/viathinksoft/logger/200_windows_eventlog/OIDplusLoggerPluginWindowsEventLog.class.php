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

class OIDplusLoggerPluginWindowsEventLog extends OIDplusLoggerPlugin {

	const CLASS_ViaThinkSoftSimpleEventLog = '{E4270053-A217-498C-B395-9EF33187E8C2}';

	const LOGEVENT_MSG_SUCCESS       = 0;
	const LOGEVENT_MSG_INFORMATIONAL = 1;
	const LOGEVENT_MSG_WARNING       = 2;
	const LOGEVENT_MSG_ERROR         = 3;

	const LOGPROVIDER = 'OIDplus'; // "Source name" (should be registered in the registry = mapped to a message file DLL)

	public static function available(&$reason)/*: bool*/ {
		if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
			$reason = _L('Functionality only available on Windows servers');
			return false;
		}

		if (!class_exists('COM')) {
			$reason = _L('To use %1, please enable the lines "extension=%2" and "extension_dir=ext" in your PHP.ini file.','ViaThinkSoftSimpleEventLog','com_dotnet');
			return false;
		}

		try {
			$x = new COM(self::CLASS_ViaThinkSoftSimpleEventLog);
			$reason = '?'; // LogSimulate() must actively clear it if everything is OK
			$x->LogSimulate(self::LOGPROVIDER, self::LOGEVENT_MSG_SUCCESS, 'TEST', $reason);/** @phpstan-ignore-line */
			return $reason != '';
		} catch (Exception $e) {
			$reason = $e->getMessage();
			return false;
		}
	}

	private static function convertOIDplusToWindowsSeverity($sev) {
		switch ($sev) {
			case 0:
				return self::LOGEVENT_MSG_INFORMATIONAL; // undefined
			case 1:
				return self::LOGEVENT_MSG_SUCCESS;
			case 2:
				return self::LOGEVENT_MSG_INFORMATIONAL;
			case 3:
				return self::LOGEVENT_MSG_WARNING;
			case 4:
				return self::LOGEVENT_MSG_ERROR;
			case 5:
				return self::LOGEVENT_MSG_WARNING;
			default:
				return self::LOGEVENT_MSG_INFORMATIONAL; // actually an internal error
		}
	}

	public static function log($event, $users, $objects)/*: bool*/ {
		if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
			return false;
		}

		if (!class_exists('COM')) {
			return false;
		}

		try {
			$x = new COM(self::CLASS_ViaThinkSoftSimpleEventLog);

			$admin_severity = 0;
			foreach ($users as list($severity, $username)) {
				// Since the Windows Event Log is mostly for admins, we use the severity an admin would expect
				if ($username == 'admin') $admin_severity = $severity;
			}

			$x->LogEvent(self::LOGPROVIDER, self::convertOIDplusToWindowsSeverity($admin_severity), $event);/** @phpstan-ignore-line */

			return true;
		} catch (Exception $e) {
			return false;
		}

	}

}