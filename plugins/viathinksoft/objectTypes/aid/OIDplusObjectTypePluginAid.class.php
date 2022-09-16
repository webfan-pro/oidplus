<?php

/*
 * OIDplus 2.0
 * Copyright 2019 - 2022 Daniel Marschall, ViaThinkSoft
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

class OIDplusObjectTypePluginAid extends OIDplusObjectTypePlugin {

	public static function getObjectTypeClassName() {
		return OIDplusAid::class;
	}

	public static function prefilterQuery($static_node_id, $throw_exception) {
		if (str_starts_with($static_node_id,'aid:')) {
			$static_node_id = str_replace(' ', '', $static_node_id);

			$tmp = explode(':',$static_node_id,2);
			if (isset($tmp[1])) $tmp[1] = strtoupper($tmp[1]);
			$static_node_id = implode(':',$tmp);
		}
		return $static_node_id;
	}

}
