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

var OIDplusPagePublicFreeOID = {

	oid: "1.3.6.1.4.1.37476.2.5.2.4.1.200",

	freeOIDFormOnSubmit: function() {
		$.ajax({
			url: "ajax.php",
			type: "POST",
			beforeSend: function(jqXHR, settings) {
				$.xhrPool.abortAll();
				$.xhrPool.add(jqXHR);
			},
			complete: function(jqXHR, text) {
				$.xhrPool.remove(jqXHR);
			},
			data: {
				csrf_token:csrf_token,
				plugin:OIDplusPagePublicFreeOID.oid,
				action: "request_freeoid",
				email: $("#email").val(),
				captcha: oidplus_captcha_response()
			},
			error: function (jqXHR, textStatus, errorThrown) {
				oidplus_ajax_error(jqXHR, textStatus, errorThrown);
				oidplus_captcha_reset();
			},
			success: function (data) {
				var ok = false;
				oidplus_ajax_success(data, function (data) {
					alertSuccess(_L("Instructions have been sent via email."));
					window.location.href = '?goto=oidplus%3Asystem';
					//reloadContent();
					ok = true;
				});
				if (!ok) oidplus_captcha_reset();
			}
		});
		return false;
	},

	activateFreeOIDFormOnSubmit: function() {
		$.ajax({
			url: "ajax.php",
			type: "POST",
			beforeSend: function(jqXHR, settings) {
				$.xhrPool.abortAll();
				$.xhrPool.add(jqXHR);
			},
			complete: function(jqXHR, text) {
				$.xhrPool.remove(jqXHR);
			},
			data: {
				csrf_token:csrf_token,
				plugin:OIDplusPagePublicFreeOID.oid,
				action: "activate_freeoid",
				email: $("#email").val(),
				ra_name: $("#ra_name").val(),
				title: $("#title").val(),
				url: $("#url").val(),
				auth: $("#auth").val(),
				password1: $("#password1").val(),
				password2: $("#password2").val(),
				timestamp: $("#timestamp").val()
			},
			error: oidplus_ajax_error,
			success: function (data) {
				oidplus_ajax_success(data, function (data) {
					alertSuccess(_L("Registration successful! You received OID %1 and can now start using it.", data.new_oid));
					window.location.href = '?goto=oidplus%3Alogin';
					//reloadContent();
				});
			}
		});
		return false;
	}

};
