<?php

/**
 * WEID<=>OID Converter
 * (c) Webfan.de, ViaThinkSoft
 * Revision 2021-12-08
 **/

// What is a WEID?
//     A WEID (WEhowski IDentifier) is an alternative representation of an
//     OID (Object IDentifier) defined by Till Wehowski.
//     In OIDs, arcs are in decimal base 10. In WEIDs, the arcs are in base 36.
//     Also, each WEID has a check digit at the end (called WeLohn Check Digit).
//
// Changes in the December 2021 definition by Daniel Marschall:
//     - There are several classes of WEIDs which have different OID bases:
//           "Class C" WEID:  weid:EXAMPLE-3      (base .1.3.6.1.4.1.37553.8.)
//                            oid:1.3.6.1.4.1.37553.8.32488192274
//           "Class B" WEID:  weid:pen:SX0-7PR-6  (base .1.3.6.1.4.1.)
//                            oid:1.3.6.1.4.1.37476.9999
//           "Class A" WEID:  weid:root:2-RR-2    (base .)
//                            oid:2.999
//     - The namespace (weid:, weid:pen:, weid:root:) is now case insensitive.
//     - Padding with '0' characters is valid (e.g. weid:000EXAMPLE-3)
//       The paddings do not count into the WeLuhn check-digit.

class WeidOidConverter {

	protected static function weLuhnGetCheckDigit($str) {

		// Padding zeros don't count to the check digit (December 2021)
		$ary = explode('-', $str);
		foreach ($ary as &$a) $a = ltrim($a, '0');
		$str = implode('-', $ary);

		$wrkstr = str_replace('-', '', $str); // remove separators
		for ($i=0; $i<36; $i++) {
			$wrkstr = str_ireplace(chr(ord('a')+$i), (string)($i+10), $wrkstr);
		}
		$nbdigits = strlen($wrkstr);
		$parity = $nbdigits & 1;
		$sum = 0;
		for ($n=$nbdigits-1; $n>=0; $n--) {
			$digit = $wrkstr[$n];
			if (($n & 1) != $parity) $digit *= 2;
			if ($digit > 9) $digit -= 9;
			$sum += $digit;
		}
		return ($sum%10) == 0 ? 0 : 10-($sum%10);
	}

	// Translates a weid to an oid
	// "weid:EXAMPLE-3" becomes "1.3.6.1.4.1.37553.8.32488192274"
	// If it failed (e.g. wrong namespace, wrong checksum, etc.) then false is returned.
	// If the weid ends with '?', then it will be replaced with the checksum,
	// e.g. weid:EXAMPLE-? becomes weid:EXAMPLE-3
	public static function weid2oid(&$weid) {

		$p = strrpos($weid,':');
		$namespace = substr($weid, 0, $p+1);
		$rest = substr($weid, $p+1);

		$namespace = strtolower($namespace); // namespace is case insensitive
		if ($namespace == 'weid:') {
			// Class C
			$base = '1-3-6-1-4-1-SZ5-8';
		} else if ($namespace == 'weid:pen:') {
			// Class B
			$base = '1-3-6-1-4-1';
		} else if ($namespace == 'weid:root:') {
			// Class A
			$base = '';
		} else {
			// Wrong namespace
			return false;
		}

		$weid = $rest;

		$elements = array_merge(($base != '') ? explode('-', $base) : array(), explode('-', $weid));
		$actual_checksum = array_pop($elements);
		$expected_checksum = self::weLuhnGetCheckDigit(implode('-',$elements));
		if ($actual_checksum != '?') {
			if ($actual_checksum != $expected_checksum) return false; // wrong checksum
		} else {
			// If checksum is '?', it will be replaced by the actual checksum,
			// e.g. weid:EXAMPLE-? becomes weid:EXAMPLE-3
			$weid = str_replace('?', $expected_checksum, $weid);
		}
		foreach ($elements as &$arc) {
			//$arc = strtoupper(base_convert($arc, 36, 10));
			$arc = strtoupper(self::base_convert_bigint($arc, 36, 10));
		}
		$oidstr = implode('.', $elements);

		$weid = $namespace . $weid; // add namespace again

		return $oidstr;
	}

	// Converts an OID to WEID
	// "1.3.6.1.4.1.37553.8.32488192274" becomes "weid:EXAMPLE-3"
	public static function oid2weid($oid) {
		if (substr($oid,0,1) === '.') $oid = substr($oid,1); // remove leading dot

		if ($oid !== '') {
			$elements = explode('.', $oid);
			foreach ($elements as &$arc) {
				//$arc = strtoupper(base_convert($arc, 10, 36));
				$arc = strtoupper(self::base_convert_bigint($arc, 10, 36));
			}
			$weidstr = implode('-', $elements);
		} else {
			$weidstr = '';
		}

		$is_class_c = (strpos($weidstr, '1-3-6-1-4-1-SZ5-8-') === 0) ||
		              ($weidstr === '1-3-6-1-4-1-SZ5-8');
		$is_class_b = ((strpos($weidstr, '1-3-6-1-4-1-') === 0) ||
		              ($weidstr === '1-3-6-1-4-1'))
		              && !$is_class_c;
		$is_class_a = !$is_class_b && !$is_class_c;

		$checksum = self::weLuhnGetCheckDigit($weidstr);

		if ($is_class_c) {
			$weidstr = substr($weidstr, strlen('1-3-6-1-4-1-SZ5-8-'));
			$namespace = 'weid:';
		} else if ($is_class_b) {
			$weidstr = substr($weidstr, strlen('1-3-6-1-4-1-'));
			$namespace = 'weid:pen:';
		} else if ($is_class_a) {
			// $weidstr stays
			$namespace = 'weid:root:';
		}

		return $namespace . ($weidstr == '' ? $checksum : $weidstr . '-' . $checksum);
	}

	protected static function base_convert_bigint($numstring, $frombase, $tobase) {
		$frombase_str = '';
		for ($i=0; $i<$frombase; $i++) {
			$frombase_str .= strtoupper(base_convert((string)$i, 10, 36));
		}

		$tobase_str = '';
		for ($i=0; $i<$tobase; $i++) {
			$tobase_str .= strtoupper(base_convert((string)$i, 10, 36));
		}

		$length = strlen($numstring);
		$result = '';
		$number = array();
		for ($i = 0; $i < $length; $i++) {
			$number[$i] = stripos($frombase_str, $numstring[$i]);
		}
		do { // Loop until whole number is converted
			$divide = 0;
			$newlen = 0;
			for ($i = 0; $i < $length; $i++) { // Perform division manually (which is why this works with big numbers)
				$divide = $divide * $frombase + $number[$i];
				if ($divide >= $tobase) {
					$number[$newlen++] = (int)($divide / $tobase);
					$divide = $divide % $tobase;
				} else if ($newlen > 0) {
					$number[$newlen++] = 0;
				}
			}
			$length = $newlen;
			$result = $tobase_str[$divide] . $result; // Divide is basically $numstring % $tobase (i.e. the new character)
		}
		while ($newlen != 0);

		return $result;
	}
}


# --- Usage Example ---

/*
echo "Class C tests:\n\n";

var_dump($oid = '1.3.6.1.4.1.37553.8')."\n";
var_dump(WeidOidConverter::oid2weid($oid))."\n";
$weid = 'weid:?';
var_dump(WeidOidConverter::weid2oid($weid))."\n";
var_dump($weid)."\n";
echo "\n";

var_dump($oid = '1.3.6.1.4.1.37553.8.32488192274')."\n";
var_dump(WeidOidConverter::oid2weid($oid))."\n";
$weid = 'weid:EXAMPLE-?';
var_dump(WeidOidConverter::weid2oid($weid))."\n";
var_dump($weid)."\n";
$weid = 'weid:00000example-?';
var_dump(WeidOidConverter::weid2oid($weid))."\n";
var_dump($weid)."\n";
echo "\n";

echo "Class B tests:\n\n";

var_dump($oid = '1.3.6.1.4.1')."\n";
var_dump(WeidOidConverter::oid2weid($oid))."\n";
$weid = 'weid:pen:?';
var_dump(WeidOidConverter::weid2oid($weid))."\n";
var_dump($weid)."\n";
echo "\n";

var_dump($oid = '1.3.6.1.4.1.37553.7.99.99.99')."\n";
var_dump(WeidOidConverter::oid2weid($oid))."\n";
$weid = 'weid:pen:SZ5-7-2R-2R-2R-?';
var_dump(WeidOidConverter::weid2oid($weid))."\n";
var_dump($weid)."\n";
$weid = 'weid:pen:000SZ5-7-02R-00002R-002r-?';
var_dump(WeidOidConverter::weid2oid($weid))."\n";
var_dump($weid)."\n";
echo "\n";

var_dump($oid = '1.3.6.1.4.1.37476.9999')."\n";
var_dump(WeidOidConverter::oid2weid($oid))."\n";
$weid = 'weid:pen:SX0-7PR-?';
var_dump(WeidOidConverter::weid2oid($weid))."\n";
var_dump($weid)."\n";
echo "\n";

echo "Class A tests:\n\n";

var_dump($oid = '')."\n";
var_dump(WeidOidConverter::oid2weid($oid))."\n";
$weid = 'weid:root:?';
var_dump(WeidOidConverter::weid2oid($weid))."\n";
var_dump($weid)."\n";
echo "\n";

var_dump($oid = '.2.999')."\n";
var_dump(WeidOidConverter::oid2weid($oid))."\n";
$weid = 'weid:root:2-RR-?';
var_dump(WeidOidConverter::weid2oid($weid))."\n";
var_dump($weid)."\n";
echo "\n";

var_dump($oid = '2.999')."\n";
var_dump(WeidOidConverter::oid2weid($oid))."\n";
$weid = 'weid:root:2-RR-?';
var_dump(WeidOidConverter::weid2oid($weid))."\n";
var_dump($weid)."\n";
echo "\n";
*/
