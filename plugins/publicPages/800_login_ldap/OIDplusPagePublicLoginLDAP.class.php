<?php

/*
 * OIDplus 2.0
 * Copyright 2020 Daniel Marschall, ViaThinkSoft
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

class OIDplusPagePublicLoginLdap extends OIDplusPagePluginPublic {

	protected function ldapAuthByEMail($email, $password) {
		$cfg_ldap_server   = OIDplus::baseConfig()->getValue('LDAP_SERVER');
		$cfg_ldap_port     = OIDplus::baseConfig()->getValue('LDAP_PORT', 389);
		$cfg_ldap_base_dn  = OIDplus::baseConfig()->getValue('LDAP_BASE_DN');
		$cfg_ldap_rdn      = OIDplus::baseConfig()->getValue('LDAP_CONTROLUSER_RDN');
		$cfg_ldap_password = OIDplus::baseConfig()->getValue('LDAP_CONTROLUSER_PASSWORD');

		// Connect to the server
		if (!empty($cfg_ldap_port)) {
			if (!($ldapconn = @ldap_connect($cfg_ldap_server, $cfg_ldap_port))) throw new OIDplusException(_L('Cannot connect to LDAP server'));
		} else {
			if (!($ldapconn = @ldap_connect($cfg_ldap_server))) throw new OIDplusException(_L('Cannot connect to LDAP server'));
		}
		ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);

		// Login in order to search for the user
		if (!empty($cfg_ldap_rdn)) {
			if (!empty($cfg_ldap_password)) {
				if (!($ldapbind = @ldap_bind($ldapconn, $cfg_ldap_rdn, $cfg_ldap_password))) throw new OIDplusException(_L('System cannot login to LDAP in order to search the user'));
			} else {
				if (!($ldapbind = @ldap_bind($ldapconn, $cfg_ldap_rdn))) throw new OIDplusException(_L('System cannot login to LDAP in order to search the user'));
			}
		} else {
			if (!($ldapbind = @ldap_bind($ldapconn))) throw new OIDplusException(_L('System cannot login to LDAP in order to search the user'));
		}

		// Search the user using the email address
		if (!($result = @ldap_search($ldapconn,$cfg_ldap_base_dn, '(&(objectClass=user)(cn=*))'))) throw new OIDplusException(_L('Error in search query: %1', ldap_error($ldapconn)));
		$data = ldap_get_entries($ldapconn, $result);
		$found_username = null;
		for ($i=0; $i<$data['count']; $i++) {
			if ((isset($data[$i]['mail'][0])) && ($data[$i]['mail'][0] == $email)) {
				$found_username = $data[$i]['userprincipalname'][0];
				$ldap_userinfo = array();
				foreach ($data[$i] as $x => $y) {
					if (is_int($x)) continue;
					if (!is_array($y)) continue;
					$ldap_userinfo[$x] = $y[0];
				}
			}
		}
		if (is_null($found_username)) return false;

		// Login as the new user in order to check the credentials
		//ldap_unbind($ldapconn); // commented out because ldap_unbind() kills the link descriptor
		if ($ldapbind = @ldap_bind($ldapconn, $found_username, $password)) {
			//ldap_unbind($ldapconn);
			ldap_close($ldapconn);
			return $ldap_userinfo;
		} else {
			return false;
		}
	}

	private function registerRA($ra, $ldap_userinfo) {
		$email = $ra->raEmail();

		$ra->register_ra(null); // create a user account without password

		/*
		OID+ DB Field             ActiveDirectory field
		------------------------------------------------
		ra_name                   cn
		personal_name             displayname (or: givenname + " " + sn)
		organization              company
		office                    physicaldeliveryofficename or department
		street                    streetaddress
		zip_town                  postalcode + " " + l
		country                   co (human-readable) or c (ISO country code)
		phone                     telephonenumber or homephone
		mobile                    mobile
		fax                       facsimiletelephonenumber
		(none)                    wwwhomepage
		*/

		if (!isset($ldap_userinfo['cn']))                         $ldap_userinfo['cn'] = '';
		if (!isset($ldap_userinfo['displayname']))                $ldap_userinfo['displayname'] = '';
		if (!isset($ldap_userinfo['givenname']))                  $ldap_userinfo['givenname'] = '';
		if (!isset($ldap_userinfo['sn']))                         $ldap_userinfo['sn'] = '';
		if (!isset($ldap_userinfo['company']))                    $ldap_userinfo['company'] = '';
		if (!isset($ldap_userinfo['physicaldeliveryofficename'])) $ldap_userinfo['physicaldeliveryofficename'] = '';
		if (!isset($ldap_userinfo['department']))                 $ldap_userinfo['department'] = '';
		if (!isset($ldap_userinfo['streetaddress']))              $ldap_userinfo['streetaddress'] = '';
		if (!isset($ldap_userinfo['postalcode']))                 $ldap_userinfo['postalcode'] = '';
		if (!isset($ldap_userinfo['l']))                          $ldap_userinfo['l'] = '';
		if (!isset($ldap_userinfo['co']))                         $ldap_userinfo['co'] = '';
		if (!isset($ldap_userinfo['c']))                          $ldap_userinfo['c'] = '';
		if (!isset($ldap_userinfo['telephonenumber']))            $ldap_userinfo['telephonenumber'] = '';
		if (!isset($ldap_userinfo['homephone']))                  $ldap_userinfo['homephone'] = '';
		if (!isset($ldap_userinfo['mobile']))                     $ldap_userinfo['mobile'] = '';
		if (!isset($ldap_userinfo['facsimiletelephonenumber']))   $ldap_userinfo['facsimiletelephonenumber'] = '';
		if (!isset($ldap_userinfo['wwwhomepage']))                $ldap_userinfo['wwwhomepage'] = '';

		$opuserdata = array();
		$opuserdata['ra_name'] = $ldap_userinfo['cn'];
		if (!empty($ldap_userinfo['displayname'])) {
			$opuserdata['personal_name'] = $ldap_userinfo['displayname'];
		} else {
			$opuserdata['personal_name'] = trim($ldap_userinfo['givenname'].' '.$ldap_userinfo['sn']);
		}
		$opuserdata['organization'] = $ldap_userinfo['company'];
		if (!empty($ldap_userinfo['physicaldeliveryofficename'])) {
			$opuserdata['office'] = $ldap_userinfo['physicaldeliveryofficename'];
		} else {
			$opuserdata['office'] = $ldap_userinfo['department'];
		}
		$opuserdata['street'] = $ldap_userinfo['streetaddress'];
		$opuserdata['zip_town'] = trim($ldap_userinfo['postalcode'].' '.$ldap_userinfo['l']);
		$opuserdata['country'] = $ldap_userinfo['co']; // ISO country code: $ldap_userinfo['c']
		$opuserdata['phone'] = $ldap_userinfo['telephonenumber']; // homephone for private phone number
		$opuserdata['mobile'] = $ldap_userinfo['mobile'];
		$opuserdata['fax'] = $ldap_userinfo['facsimiletelephonenumber'];

		foreach ($opuserdata as $dbfield => $val) {
			if (!empty($val)) {
				OIDplus::db()->query("update ###ra set ".$dbfield." = ? where email = ?", array($val, $email));
			}
		}
	}

	public function action($actionID, $params) {
		if ($actionID == 'ra_login_ldap') {
			if (!OIDplus::baseConfig()->getValue('LDAP_ENABLED', false)) {
				throw new OIDplusException(_L('LDAP authentication is disabled on this system.'));
			}

			if (OIDplus::baseConfig()->getValue('RECAPTCHA_ENABLED', false)) {
				$secret=OIDplus::baseConfig()->getValue('RECAPTCHA_PRIVATE', '');
				$response=$params["captcha"];
				$verify=file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret}&response={$response}");
				$captcha_success=json_decode($verify);
				if ($captcha_success->success==false) {
					throw new OIDplusException(_L('CAPTCHA not successfully verified'));
				}
			}

			$email = $params['email'];
			$password = $params['password'];

			if (empty($email)) {
				throw new OIDplusException(_L('Please enter a valid email address'));
			}

			if (!($ldap_userinfo = $this->ldapAuthByEMail($email, $password))) {
				if (OIDplus::config()->getValue('log_failed_ra_logins', false)) {
					OIDplus::logger()->log("[WARN]A!", "Failed login to RA account '$email' using LDAP");
				}
				throw new OIDplusException(_L('Wrong password or user not registered'));
			}

			$ra = new OIDplusRA($email);
			if (!$ra->existing()) {
				$this->registerRA($ra, $ldap_userinfo);
				OIDplus::logger()->log("[INFO]RA($email)!", "RA '$email' was created because of successful LDAP login");
			}

			OIDplus::logger()->log("[OK]RA($email)!", "RA '$email' logged in via LDAP");
			OIDplus::authUtils()::raLogin($email);

			OIDplus::db()->query("UPDATE ###ra set last_login = ".OIDplus::db()->sqlDate()." where email = ?", array($email));

			return array("status" => 0);
		} else {
			throw new OIDplusException(_L('Unknown action ID'));
		}
	}

	public function init($html=true) {
		// Nothing
	}

	public function gui($id, &$out, &$handled) {
		if ($id === 'oidplus:login_ldap') {
			$handled = true;
			$out['title'] = _L('Login using LDAP / ActiveDirectory');
			$out['icon']  = OIDplus::webpath(__DIR__).'icon_big.png';

			if (!OIDplus::baseConfig()->getValue('LDAP_ENABLED', false)) {
				$out['icon'] = 'img/error_big.png';
				$out['text'] = _L('LDAP authentication is disabled on this system.');
				return;
			}

			$out['text'] = '';

			$out['text'] .= '<noscript>';
			$out['text'] .= '<p>'._L('You need to enable JavaScript to use the login area.').'</p>';
			$out['text'] .= '</noscript>';

			$out['text'] .= '<div id="loginLdapArea" style="visibility: hidden">';
			$out['text'] .= (OIDplus::baseConfig()->getValue('RECAPTCHA_ENABLED', false) ?
			                '<script> grecaptcha.render(document.getElementById("g-recaptcha"), { "sitekey" : "'.OIDplus::baseConfig()->getValue('RECAPTCHA_PUBLIC', '').'" }); </script>'.
			                '<p>'._L('Before logging in, please solve the following CAPTCHA').'</p>'.
			                '<div id="g-recaptcha" class="g-recaptcha" data-sitekey="'.OIDplus::baseConfig()->getValue('RECAPTCHA_PUBLIC', '').'"></div>' : '');
			$out['text'] .= '<br>';

			$out['text'] .= '<p><a '.OIDplus::gui()->link('oidplus:login').'><img src="img/arrow_back.png" width="16" alt="'._L('Go back').'"> '._L('Regular login method').'</a></p>';

			$out['text'] .= '<h2>'._L('Login as RA').'</h2>';

			$login_list = OIDplus::authUtils()->loggedInRaList();
			if (count($login_list) > 0) {
				foreach ($login_list as $x) {
					$out['text'] .= '<p>'._L('You are logged in as %1','<b>'.$x->raEmail().'</b>').' (<a href="#" onclick="return raLogout('.js_escape($x->raEmail()).');">'._L('Logout').'</a>)</p>';
				}
				$out['text'] .= '<p>'._L('If you have more accounts, you can log in with another account here.').'</p>';
			} else {
				$out['text'] .= '<p>'._L('Enter your email address and your password to log in as Registration Authority.').'</p>';
			}
			$out['text'] .= '<form onsubmit="return raLoginLdapOnSubmit(this);">';
			$out['text'] .= '<div><label class="padding_label">'._L('E-Mail').':</label><input type="text" name="email" value="" id="raLoginLdapEMail"></div>';
			$out['text'] .= '<div><label class="padding_label">'._L('Password').':</label><input type="password" name="password" value="" id="raLoginLdapPassword"></div>';
			$out['text'] .= '<br><input type="submit" value="'._L('Login').'"><br><br>';
			$out['text'] .= '</form>';

			$invitePlugin = OIDplus::getPluginByOid('1.3.6.1.4.1.37476.2.5.2.4.2.92'); // OIDplusPageRaInvite
			$out['text'] .= '<p><abbr title="'._L('You don\'t need to register. Just enter your Windows/Company credentials.').'">'._L('How to register?').'</abbr></p>';

			$mins = ceil(OIDplus::baseConfig()->getValue('SESSION_LIFETIME', 30*60)/60);
			$out['text'] .= '<p><font size="-1">'._L('<i>Privacy information</i>: By using the login functionality, you are accepting that a "session cookie" is temporarily stored in your browser. The session cookie is a small text file that is sent to this website every time you visit it, to identify you as an already logged in user. It does not track any of your online activities outside OIDplus. The cookie will be destroyed when you log out or after an inactivity of %1 minutes.', $mins);
			$privacy_document_file = 'OIDplus/privacy_documentation.html';
			$resourcePlugin = OIDplus::getPluginByOid('1.3.6.1.4.1.37476.2.5.2.4.1.500'); // OIDplusPagePublicResources
			if (!is_null($resourcePlugin) && file_exists(OIDplus::basePath().'/res/'.$privacy_document_file)) {
				$out['text'] .= ' <a '.OIDplus::gui()->link('oidplus:resources$'.$privacy_document_file.'$'.OIDplus::authUtils()::makeAuthKey("resources;".$privacy_document_file).'#cookies').'>'._L('More information about the cookies used').'</a>';
			}
			$out['text'] .= '</font></p></div>';

			$out['text'] .= '<script>document.getElementById("loginLdapArea").style.visibility = "visible";</script>';
		}
	}

	public function publicSitemap(&$out) {
		$out[] = 'oidplus:login_ldap';
	}

	public function tree(&$json, $ra_email=null, $nonjs=false, $req_goto='') {
		return true;
	}

	public function tree_search($request) {
		return false;
	}

	public function implementsFeature($id) {
		if (strtolower($id) == '1.3.6.1.4.1.37476.2.5.2.3.5') return true; // alternativeLoginMethods
		return false;
	}

	public function alternativeLoginMethods() {
		$logins = array();
		if (OIDplus::baseConfig()->getValue('LDAP_ENABLED', false)) {
			$logins[] = array(
				'oidplus:login_ldap',
				_L('Login using LDAP / ActiveDirectory'),
				OIDplus::webpath(__DIR__).'treeicon.png'
			);
		}
		return $logins;
	}
}