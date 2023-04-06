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

use ViaThinkSoft\OIDplus\OIDplus;

// Note: You can override these values in your userdata/baseconfig/config.inc.php file
//       Do NOT edit this file, because your changes would get overwritten
//       by program updates!

// -----------------------------------------------------------------------------

/**
  * LIMITS_MAX_ID_LENGTH
  *
  * Example:
  * 	OID 2.999.123.456 has a length of 13 characters in dot notation.
  *		OIDplus adds the prefix "oid:" in front of every OID,
  *		so the overal length of the ID would be 17.
  *
  * Default value:
  * 	255 digits (OIDs 251 digits)
  *
  * Which value is realistic?
  * 	In the oid-info.com database (April 2020), the OID with the greatest size is 65 characters (dot notation)
  *
  * Maximal value:
  * 	OIDs may only have a size of max 251 characters in dot notation.
  * 	Reason: The field defintion of *_objects.oid is defined as varchar(255),
  * 	        and the OID will have the prefix 'oid:' (4 bytes).
  * 	You can increase the limit by changing the field definition in the database.
  **/
OIDplus::baseConfig()->setValue('LIMITS_MAX_ID_LENGTH', 255);

// -----------------------------------------------------------------------------

/**
  * LIMITS_MAX_OID_ASN1_ID_LEN
  *
  * Default value:
  *	255 characters
  *
  * Maximal value:
  *	255, as defined in the database fields *_asn1id.name
  *	You can change the database field definition if you really need more.
  **/
OIDplus::baseConfig()->setValue('LIMITS_MAX_OID_ASN1_ID_LEN', 255);

// -----------------------------------------------------------------------------

/**
  * LIMITS_MAX_OID_UNICODE_LABEL_LEN
  *
  * Default value:
  *	255 bytes (UTF-8 encoded!)
  *
  * Maximal value:
  *	255, as defined in the database fields *_iri.name
  *	You can change the database field definition if you really need more.
  **/
OIDplus::baseConfig()->setValue('LIMITS_MAX_OID_UNICODE_LABEL_LEN', 255);

// -----------------------------------------------------------------------------
