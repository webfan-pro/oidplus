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

// ATTENTION: If you change something, please make sure that the changes
//            are synchronous with OIDplusPageRaAutomatedAJAXCalls

if (!defined('INSIDE_OIDPLUS')) die();

class OIDplusPageAdminAutomatedAJAXCalls extends OIDplusPagePluginAdmin {

	private static function getUnlockKey($user) {
		// This key prevents that the system gets hacked with brute
		// force of the user passwords.
		return sha3_512('ANTI-BRUTEFORCE-AJAX/'.$user.'/'.OIDplus::baseConfig()->getValue('SERVER_SECRET',''));
	}

	private $autoLoggedIn = false;

	// Attention: Needs to be public, because otherwise register_shutdown_function() won't work
	public function shutdownLogout() {
		if ($this->autoLoggedIn) {
			OIDplus::authUtils()->adminLogout();
		}
	}

	public function init($html=true) {
		if (isset($_SERVER['SCRIPT_FILENAME']) && (basename($_SERVER['SCRIPT_FILENAME']) == 'ajax.php')) {
			$input = array_merge($_POST,$_GET);

			if (isset($input['batch_ajax_unlock_key']) && isset($input['batch_login_username']) && isset($input['batch_login_password'])) {
				originHeaders(); // Allows queries from other domains
				OIDplus::authUtils()->disableCSRF(); // allow access to ajax.php without valid CSRF token

				if ($input['batch_login_username'] == 'admin') {
					if ($input['batch_ajax_unlock_key'] != self::getUnlockKey($input['batch_login_username'])) {
						throw new OIDplusException(_L('Invalid AJAX unlock key'));
					}

					if (OIDplus::authUtils()->adminCheckPassword($input['batch_login_password'])) {
						OIDplus::sesHandler()->simulate = true; // do not change the user session
						OIDplus::authUtils()->adminLogin();
						$this->autoLoggedIn = true;
						register_shutdown_function(array($this,'shutdownLogout'));
					} else {
						throw new OIDplusException(_L('Wrong admin password'));
					}
				}
			}
		}
	}

	public function gui($id, &$out, &$handled) {
		if ($id === 'oidplus:automated_ajax_information_admin') {
			$handled = true;
			$out['title'] = _L('Automated AJAX calls');
			$out['icon'] = file_exists(__DIR__.'/icon_big.png') ? OIDplus::webpath(__DIR__).'icon_big.png' : '';

			if (!OIDplus::authUtils()->isAdminLoggedIn()) {
				$out['icon'] = 'img/error_big.png';
				$out['text'] = '<p>'._L('You need to <a %1>log in</a> as administrator.',OIDplus::gui()->link('oidplus:login$admin')).'</p>';
				return;
			}

			$out['text'] .= '<p>'._L('You can make automated calls to your OIDplus account by calling the AJAX API.').'</p>';
			$out['text'] .= '<p>'._L('The URL for the AJAX script is:').':</p>';
			$out['text'] .= '<p><b>'.OIDplus::webpath(null,false).'ajax.php</b></p>';
			$out['text'] .= '<p>'._L('You must at least provide following fields').':</p>';
			$out['text'] .= '<p><pre>';
			$out['text'] .= 'batch_login_username  = "admin"'."\n";
			$out['text'] .= 'batch_login_password  = "........."'."\n";
			$out['text'] .= 'batch_ajax_unlock_key = "'.$this->getUnlockKey('admin').'"'."\n";
			$out['text'] .= '</pre></p>';
			$out['text'] .= '<p>'._L('Please keep this information confidential!').'</p>';
			$out['text'] .= '<p>'._L('The batch-fields will automatically perform a one-time-login to fulfill the request. The other fields are the normal fields which are called during the usual operation of OIDplus.').'</p>';
			$out['text'] .= '<p>'._L('Currently, there is no documentation for the AJAX calls. However, you can look at the <b>script.js</b> files of the plugins to see the field names being used. You can also enable network analysis in your web browser debugger (F12) to see the request headers sent to the server during the operation of OIDplus.').'</p>';

			$out['text'] .= '<h2>'._L('Example for adding OID 2.999.123 using JavaScript').'</h2>';
			$cont = file_get_contents(__DIR__.'/examples/example_js.html');
			$cont = str_replace('<url>', OIDplus::webpath(null,false).'ajax.php', $cont);
			$cont = str_replace('<username>', 'admin', $cont);
			$cont = str_replace('<password>', '.........', $cont);
			$cont = str_replace('<unlock key>', $this->getUnlockKey('admin'), $cont);
			$out['text'] .= '<pre>'.htmlentities($cont).'</pre>';

			$out['text'] .= '<h2>'._L('Example for adding OID 2.999.123 using PHP (located at a foreign server)').'</h2>';
			$cont = file_get_contents(__DIR__.'/examples/example_php.phps');
			$cont = str_replace('<url>', OIDplus::webpath(null,false).'ajax.php', $cont);
			$cont = str_replace('<username>', 'admin', $cont);
			$cont = str_replace('<password>', '.........', $cont);
			$cont = str_replace('<unlock key>', $this->getUnlockKey('admin'), $cont);
			$out['text'] .= '<pre>'.preg_replace("@<br.*>@ismU","",highlight_string($cont,true)).'</pre>';

			$out['text'] .= '<h2>'._L('Example for adding OID 2.999.123 using VBScript').'</h2>';
			$cont = file_get_contents(__DIR__.'/examples/example_vbs.vbs');
			$cont = str_replace('<url>', OIDplus::webpath(null,false).'ajax.php', $cont);
			$cont = str_replace('<username>', 'admin', $cont);
			$cont = str_replace('<password>', '.........', $cont);
			$cont = str_replace('<unlock key>', $this->getUnlockKey('admin'), $cont);
			$out['text'] .= '<pre>'.htmlentities($cont).'</pre>';
		}
	}

	public function tree(&$json, $ra_email=null, $nonjs=false, $req_goto='') {
		if (!OIDplus::authUtils()->isAdminLoggedIn()) return false;

		if (file_exists(__DIR__.'/treeicon.png')) {
			$tree_icon = OIDplus::webpath(__DIR__).'treeicon.png';
		} else {
			$tree_icon = null; // default icon (folder)
		}

		$json[] = array(
			'id' => 'oidplus:automated_ajax_information_admin',
			'icon' => $tree_icon,
			'text' => _L('Automated AJAX calls')
		);

		return true;
	}

	public function tree_search($request) {
		return false;
	}
}
