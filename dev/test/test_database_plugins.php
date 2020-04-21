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

// This script is used to detect problems with your database plugin

require_once __DIR__ . '/../../includes/oidplus.inc.php';

echo '<h1>OIDplus Database plugin testcases</h1>';

# Test MySQL
include __DIR__ . '/../../plugins/database/mysqli/plugin.inc.php';
$db = new OIDplusDatabasePluginMySQLi();
if (function_exists('mysqli_fetch_all')) {
	OIDplus::baseConfig()->setValue('MYSQL_FORCE_MYSQLND_SUPPLEMENT', false);
	echo "[With MySQLnd support] ";
	dotest($db);
	OIDplus::baseConfig()->setValue('MYSQL_FORCE_MYSQLND_SUPPLEMENT', true);
}
echo "[Without MySQLnd support] ";
dotest($db);

# Test PDO
include __DIR__ . '/../../plugins/database/pdo/plugin.inc.php';
$db = new OIDplusDatabasePluginPDO();
dotest($db);

# Test ODBC
include __DIR__ . '/../../plugins/database/odbc/plugin.inc.php';
$db = new OIDplusDatabasePluginODBC();
dotest($db);

# Test PgSQL
include __DIR__ . '/../../plugins/database/pgsql/plugin.inc.php';
$db = new OIDplusDatabasePluginPgSQL();
dotest($db);

# Test SQLite3
include __DIR__ . '/../../plugins/database/sqlite3/plugin.inc.php';
$db = new OIDplusDatabasePluginSQLite3();
dotest($db);

# ---

function dotest($db) {
	echo "Database: " . $db->name()."<br>";
	try {
		$db->connect();
	} catch (Exception $e) {
		echo "Connection <font color=\"red\">FAILED</font> (check config.inc.php): ".$e->getMessage()."<br><br>";
		return;
	}
	echo "Detected slang: " . $db->slang()."<br>";
	$db->query("delete from ###objects where parent = 'test:1'");
	$db->query("insert into ###objects (id, parent, title, description, confidential) values ('test:1.1', 'test:1', '', '', '0')");
	$db->query("insert into ###objects (id, parent, title, description, confidential) values ('test:1.2', 'test:1', '', '', '0')");
	try {
		// --- "SQL Date" handling

		try {
			$res = $db->query("update ###objects set created = ".$db->sqlDate()." where id = 'test:1.1'");
			echo "SQLDate (".$db->sqlDate().") PASSED<br>";
		} catch (Exception $e) {
			echo "SQLDate (".$db->sqlDate().") <font color=\"red\">FAILED</font><br>";
		}

		// --- "Num rows" handling

		$res = $db->query("select id from ###objects where parent = ? order by id", array('test:1'));

		$num_rows = $res->num_rows();
		echo "Num rows: " . ($num_rows===2 ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		$res->fetch_array();
		$num_rows = $res->num_rows();
		echo "Num rows after something fetched: " . ($num_rows===2 ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		$nextid = $res->fetch_array()['id'];
		echo "Num rows does not change cursor: " . ($nextid == 'test:1.2' ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		$next = $res->fetch_array();
		echo "Fetch after EOF gives null: " . (is_null($next) ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		// --- Simultanous prepared statements

		$res = $db->query("select id from ###objects where parent = ? order by id", array('test:1'));

		$passed = false;
		while ($row = $res->fetch_array()) {
			$res2 = $db->query("select id from ###objects where parent = ? order by id", array($row['id']));
			while ($row2 = $res2->fetch_array()) {
			}
			if ($row['id'] == 'test:1.2') {
				$passed = true;
			}
		}
		echo "Simultanous prepared statements: ".($passed ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		// --- Exception handling

		try {
			$db->query("ABCDEF");
			echo "Exception for DirectQuery: <font color=\"red\">FAILED</font>, no Exception thrown<br>";
		} catch (Exception $e) {
			if (strpos($e->getMessage(), 'ABCDEF') !== false) {
				echo "Exception for DirectQuery: PASSED<br>";
			} else {
				echo "Exception for DirectQuery: <font color=\"red\">FAILED</font>, does probably not contain DBMS error string<br>";
			}
		}

		$msg = $db->error();
		if (strpos($msg, 'ABCDEF') !== false) {
			echo "Error-Function after failed direct query: PASSED<br>";
		} else {
			echo "Error-Function after failed direct query: <font color=\"red\">FAILED</font>, does probably not contain DBMS error string ($msg)<br>";
		}

		try {
			$db->query("FEDCBA", array(''));
			echo "Exception for PreparedQuery: <font color=\"red\">FAILED</font>, no Exception thrown<br>";
		} catch (Exception $e) {
			if (strpos($e->getMessage(), 'FEDCBA') !== false) {
				echo "Exception for PreparedQuery: PASSED<br>";
			} else {
				echo "Exception for PreparedQuery: <font color=\"red\">FAILED</font>, does probably not contain DBMS error string<br>";
			}
		}

		$msg = $db->error();
		if (strpos($msg, 'FEDCBA') !== false) {
			echo "Error-Function after failed prepared query: PASSED<br>";
		} else {
			echo "Error-Function after failed prepared query: <font color=\"red\">FAILED</font>, does probably not contain DBMS error string ($msg)<br>";
		}

		$db->query("select 1");
		$msg = $db->error();
		if (!$msg) {
			echo "Error-Function gets cleared after non-failed query: PASSED<br>";
		} else {
			echo "Error-Function gets cleared after non-failed query: <font color=\"red\">FAILED</font>, does probably not contain DBMS error string<br>";
		}

		// --- Boolean handling

		$db->query("update ###objects set confidential = ? where id = 'test:1.1'", array(true));
		$res = $db->query("select confidential from ###objects where id = 'test:1.1'");
		$val = $res->fetch_object()->confidential;
		echo "Boolean handling TRUE with prepared statement: " . ($val ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		$db->query("update ###objects set confidential = ? where id = 'test:1.1'", array(false));
		$res = $db->query("select confidential from ###objects where id = 'test:1.1'");
		$val = $res->fetch_object()->confidential;
		echo "Boolean handling FALSE with prepared statement: " . (!$val ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		$db->query("update ###objects set confidential = '1' where id = 'test:1.1'");
		$res = $db->query("select confidential from ###objects where id = 'test:1.1'");
		$val = $res->fetch_object()->confidential;
		echo "Boolean handling TRUE with normal statement: " . ($val ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		$db->query("update ###objects set confidential = '0' where id = 'test:1.1'");
		$res = $db->query("select confidential from ###objects where id = 'test:1.1'");
		$val = $res->fetch_object()->confidential;
		echo "Boolean handling FALSE with normal statement: " . (!$val ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		// --- Check if transactions work

		$db->query("update ###objects set title = 'A' where id = 'test:1.1'");
		$db->transaction_begin();
		$db->query("update ###objects set title = 'B' where id = 'test:1.1'");
		$db->transaction_rollback();
		$res = $db->query("select title from ###objects where id = 'test:1.1'");
		$val = $res->fetch_object()->title;
		echo "Transaction rollback: " . ($val == 'A' ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		$db->query("update ###objects set title = 'A' where id = 'test:1.1'");
		$db->transaction_begin();
		$db->query("update ###objects set title = 'B' where id = 'test:1.1'");
		$db->transaction_commit();
		$res = $db->query("select title from ###objects where id = 'test:1.1'");
		$val = $res->fetch_object()->title;
		echo "Transaction commit: " . ($val == 'B' ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		// --- Check natOrder feature

		$db->query("delete from ###objects where parent = 'test:1'");
		$db->query("insert into ###objects (id, parent, title, description, confidential) values ('oid:3.1.10', 'test:1', '', '', '0')");
		$db->query("insert into ###objects (id, parent, title, description, confidential) values ('oid:3.1.2', 'test:1', '', '', '0')");
		$res = $db->query("select id from ###objects where parent = ? order by ".$db->natOrder('id'), array('test:1'));
		$val = $res->fetch_object()->id;
		echo "Natural OID Sorting (< 16 Bit): " . ($val == 'oid:3.1.2' ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		$db->query("delete from ###objects where parent = 'test:1'");
		$db->query("insert into ###objects (id, parent, title, description, confidential) values ('oid:2.25.317919736312109525688528068157180855579', 'test:1', '', '', '0')");
		$db->query("insert into ###objects (id, parent, title, description, confidential) values ('oid:2.25.67919736312109525688528068157180855579', 'test:1', '', '', '0')");
		$res = $db->query("select id from ###objects where parent = ? order by ".$db->natOrder('id'), array('test:1'));
		$val = $res->fetch_object()->id;
		echo "Natural OID Sorting (128 Bit): " . ($val == 'oid:2.25.67919736312109525688528068157180855579' ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		$db->query("delete from ###objects where parent = 'test:1'");
		$db->query("insert into ###objects (id, parent, title, description, confidential) values ('abc:3.1.10', 'test:1', '', '', '0')");
		$db->query("insert into ###objects (id, parent, title, description, confidential) values ('abc:3.1.2', 'test:1', '', '', '0')");
		$res = $db->query("select id from ###objects where parent = ? order by ".$db->natOrder('id'), array('test:1'));
		$val = $res->fetch_object()->id;
		echo "Non-Natural Sorting for Non-OIDs: " . ($val == 'abc:3.1.10' ? 'PASSED' : '<font color="red">FAILED</font>')."<br>";

		// --- Test insert_id()

		$db->query("delete from ###log_object where object = 'test:1'");
		$cur = $db->insert_id();
		echo "Insert ID on non-insert: " . ($cur == 0 ? 'PASSED' : '<font color="red">FAILED</font>')." ($cur)<br>";
		$db->query("insert into ###log_object (log_id, object) values (1000, 'test:1')");
		$prev = $db->insert_id();
		$db->query("insert into ###log_object (log_id, object) values (2000, 'test:1')");
		$cur = $db->insert_id();
		echo "Insert ID on actual inserts: " . ($cur == $prev+1 ? 'PASSED' : '<font color="red">FAILED</font>')." ($prev => $cur)<br>";
		if ($cur != $prev+1);
		$db->query("delete from ###log_object where object = 'test:1'");
		$cur = $db->insert_id();
		echo "Non-Insert query will reset insert ID: " . ($cur == 0 ? 'PASSED' : '<font color="red">FAILED</font>')." ($cur)<br>";

	} finally {
		$db->query("delete from ###objects where parent = 'test:1'");
	}
	$db->disconnect();
	echo "<br>";
}