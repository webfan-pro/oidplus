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

if (!defined('IN_OIDPLUS')) die();

define('OIDPLUS_MYSQL_QUERYLOG', false);

if (OIDPLUS_MYSQL_QUERYLOG) {
	function CallingFunctionName() {
		$ex = new Exception();
		$trace = $ex->getTrace();
		if (!isset($trace[2])) return '(main)';
		$final_call = $trace[2];
		return $final_call['file'].':'.$final_call['line'].'/'.$final_call['function'].'()';
	}
}

class OIDplusDataBaseMySQLi extends OIDplusDataBase {
	private $mysqli;
	private $last_query;
	private $prepare_cache = array();

	public static function name() {
		return "MySQL";
	}

	public function query($sql, $prepared_args=null) {
		$this->last_query = $sql;
		if (OIDPLUS_MYSQL_QUERYLOG) file_put_contents("query.log", "$sql <== ".CallingFunctionName()."\n", FILE_APPEND);
		if (is_null($prepared_args)) {
			return $this->mysqli->query($sql, MYSQLI_STORE_RESULT);
		} else {
			if (!is_array($prepared_args)) {
				throw new Exception("'prepared_args' must be either NULL or an ARRAY.");
			}
			if (isset($this->prepare_cache[$sql])) {
				$ps = $this->prepare_cache[$sql];
			} else {
				$ps = $this->mysqli->prepare($sql);
				if (!$ps) {
					throw new Exception("Cannot prepare statement '$sql'");
				}
				$this->prepare_cache[$sql] = $ps;
			}

			bind_placeholder_vars($ps,$prepared_args);
			if (!$ps->execute()) return false;
			$res = $ps->get_result();
			if ($res === false) return true; // A non-SELECT statement does not give a result-set, but it is still successful
			return $res;
		}
	}
	public function num_rows($res) {
		if (!is_object($res)) {
			throw new Exception("num_rows called on non object. Last query: ".$this->last_query);
		} else {
			return $res->num_rows;
		}
	}
	public function fetch_array($res) {
		if (!is_object($res)) {
			throw new Exception("fetch_array called on non object. Last query: ".$this->last_query);
		} else {
			return $res->fetch_array(MYSQLI_BOTH);
		}
	}
	public function fetch_object($res) {
		if (!is_object($res)) {
			throw new Exception("fetch_object called on non object. Last query: ".$this->last_query);
		} else {
			return $res->fetch_object("stdClass");
		}
	}
	public function insert_id() {
		return $this->mysqli->insert_id;
	}
	public function error() {
		return !empty($this->mysqli->connect_error) ? $this->mysqli->connect_error : $this->mysqli->error;
	}

	public function connect() {
		if (OIDPLUS_MYSQL_QUERYLOG) file_put_contents("query.log", '');

		$html = OIDPLUS_HTML_OUTPUT;

		// Try connecting to the database
		list($hostname,$port) = explode(':', OIDPLUS_MYSQL_HOST.':'.ini_get("mysqli.default_port"));
		$this->mysqli = @new mysqli($hostname, OIDPLUS_MYSQL_USERNAME, base64_decode(OIDPLUS_MYSQL_PASSWORD), OIDPLUS_MYSQL_DATABASE, $port);
		if (!empty($this->mysqli->connect_error) || ($this->mysqli->connect_errno != 0)) {
			if ($html) {
				echo "<h1>Error</h1><p>Database connection failed! (".$e->getMessage().")</p>";
				if (is_dir(__DIR__.'/../../setup')) {
					echo '<p>If you believe that the login credentials are wrong, please run <a href="setup/">setup</a> again.</p>';
				}
			} else {
				echo "Error: Database connection failed! (".$e->getMessage().")";
				if (is_dir(__DIR__.'/../../setup')) {
					echo ' If you believe that the login credentials are wrong, please run setup again.';
				}
			}
			die();
		}

		$this->query("SET NAMES 'utf8'");
		$this->afterConnect($html);
		$this->connected = true;
	}

	private $intransaction = false;

	public function transaction_begin() {
		if ($this->intransaction) throw new Exception("Nested transactions are not supported by this database plugin.");
		$this->mysqli->autocommit(true);
		$this->intransaction = true;
	}

	public function transaction_commit() {
		$this->mysqli->commit();
		$this->mysqli->autocommit(false);
		$this->intransaction = false;
	}

	public function transaction_rollback() {
		$this->mysqli->rollback();
		$this->mysqli->autocommit(false);
		$this->intransaction = false;
	}

}

function bind_placeholder_vars(&$stmt,$params,$debug=0) {
    // Credit to: Dave Morgan
    // Code ripped from: http://www.devmorgan.com/blog/2009/03/27/dydl-part-3-dynamic-binding-with-mysqli-php/
    if ($params != null) {
        $types = '';                        //initial sting with types
        foreach ($params as $param) {        //for each element, determine type and add
            if (is_int($param)) {
                $types .= 'i';              //integer
            } elseif (is_float($param)) {
                $types .= 'd';              //double
            } elseif (is_string($param)) {
                $types .= 's';              //string
            } else {
                $types .= 'b';              //blob and unknown
            }
        }

        $bind_names = array();
        $bind_names[] = $types;             //first param needed is the type string
                                // eg:  'issss'

        for ($i=0; $i<count($params);$i++) {    //go through incoming params and added em to array
            $bind_name = 'bind' . $i;       //give them an arbitrary name
            $$bind_name = $params[$i];      //add the parameter to the variable variable
            $bind_names[] = &$$bind_name;   //now associate the variable as an element in an array
        }

        if ($debug) {
            echo "\$bind_names:<br />\n";
            var_dump($bind_names);
            echo "<br />\n";
        }
        //error_log("better_mysqli has params ".print_r($bind_names, 1));
        //call the function bind_param with dynamic params
        call_user_func_array(array($stmt,'bind_param'),$bind_names);
        return true;
    }else{
        return false;
    }
}

function bind_result_array($stmt, &$row) {
    // Credit to: Dave Morgan
    // Code ripped from: http://www.devmorgan.com/blog/2009/03/27/dydl-part-3-dynamic-binding-with-mysqli-php/
    $meta = $stmt->result_metadata();
    while ($field = $meta->fetch_field()) {
        $params[] = &$row[$field->name];
    }
    call_user_func_array(array($stmt, 'bind_result'), $params);
    return true;
}

// https://stackoverflow.com/questions/17219214/how-to-bind-in-mysqli-dynamically

OIDplus::registerDatabasePlugin(new OIDplusDataBaseMySQLi());