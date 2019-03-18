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

class OIDplusJava extends OIDplusObject {
	private $java;

	public function __construct($java) {
		// TODO: syntax checks
		$this->java = $java;
	}

	public static function parse($node_id) {
		@list($namespace, $java) = explode(':', $node_id, 2);
		if ($namespace !== 'java') return false;
		return new self($java);
	}

	public static function objectTypeTitle() {
		return "Java Package Names";
	}

	public static function objectTypeTitleShort() {
		return "Package";
	}

	public static function ns() {
		return 'java';
	}

	public static function root() {
		return 'java:';
	}

	public function isRoot() {
		return $this->java == '';
	}

	public function nodeId() {
		return 'java:'.$this->java;
	}

	public function addString($str) {
		if ($this->isRoot()) {
			return 'java:'.$str;
		} else {
			if (strpos($str,'.') !== false) die("Please only submit one arc.");
			return $this->nodeId() . '.' . $str;
		}
	}

	public function crudShowId(OIDplusObject $parent) {
		return $this->java;
	}

	public function crudInsertPrefix() {
		return $this->isRoot() ? '' : substr($this->addString(''), strlen(self::ns())+1);
	}

	public function jsTreeNodeName(OIDplusObject $parent = null) {
		if ($parent == null) return $this->objectTypeTitle();
		return $this->java;
	}

	public function defaultTitle() {
		return $this->java;
	}

	public function isLeafNode() {
		return false;
	}

	public function getContentPage(&$title, &$content) {
		if ($this->isRoot()) {
			$title = OIDplusJava::objectTypeTitle();

			$res = OIDplus::db()->query("select * from ".OIDPLUS_TABLENAME_PREFIX."objects where parent = '".OIDplus::db()->real_escape_string(self::root())."'");
			if (OIDplus::db()->num_rows($res) > 0) {
				$content  = 'Please select a Java Package Name in the tree view at the left to show its contents.';
			} else {
				$content  = 'Currently, no Java Package Name is registered in the system.';
			}

			if (!$this->isLeafNode()) {
				if (OIDplus::authUtils()::isAdminLoggedIn()) {
					$content .= '<h2>Manage root objects</h2>';
				} else {
					$content .= '<h2>Available objects</h2>';
				}
				$content .= '%%CRUD%%';
			}
		} else {
			$content = '<h3>'.explode(':',$this->nodeId())[1].'</h3>';

			$content .= '<h2>Description</h2>%%DESC%%'; // TODO: add more meta information about the object type

			if (!$this->isLeafNode()) {
				if ($this->userHasWriteRights()) {
					$content .= '<h2>Create or change subsequent objects</h2>';
				} else {
					$content .= '<h2>Subsequent objects</h2>';
				}
				$content .= '%%CRUD%%';
			}
		}
	}
}

OIDplusObject::$registeredObjectTypes[] = 'OIDplusJava';