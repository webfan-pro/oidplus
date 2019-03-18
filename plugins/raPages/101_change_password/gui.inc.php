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

if (explode('$',$id)[0] == 'oidplus:changepwd_ra') {
	$handled = true;
	$out['title'] = 'Change RA password';

	$ra_email = explode('$',$id)[1];

	if (!OIDplus::authUtils()::isRaLoggedIn($ra_email) && !OIDplus::authUtils()::isAdminLoggedIn()) {
		$out['text'] .= '<p>You need to <a href="?goto=oidplus:login">log in</a> as the requested RA <b>'.htmlentities($ra_email).'</b>.</p>';
	} else {
		$out['text'] .= '<form id="raChangePasswordForm" onsubmit="return raChangePasswordFormOnSubmit();">';
		$out['text'] .= '<input type="hidden" id="email" value="'.htmlentities($ra_email).'"/><br>';
		$out['text'] .= 'Old password: <input type="password" id="old_password" value=""/><br>';
		$out['text'] .= 'New password: <input type="password" id="new_password1" value=""/><br>';
		$out['text'] .= 'Again: <input type="password" id="new_password2" value=""/><br>';
		$out['text'] .= '<input type="submit" value="Change password"></form>';
	}
}