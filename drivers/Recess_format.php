<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Recess_format extends CI_Driver{

	protected static $CI;

	function __construct() {

		self::$CI = &get_instance();
		self::$CI->load->helper('inflector');
	}

	// function toarray($array = NULL) {

	// 	$output = array();

	// 	if( NULL === $array ) {
	// 		return $output;
	// 	}

	// 	is_array( $array ) === FALSE && $array = (array)$array;

	// 	foreach( $array as $var => $value ) {

	// 		if( is_object( $value ) === TRUE || is_array( $value ) === TRUE ) {
	// 			$output[$var] = $this->array( $value );
	// 		}
	// 		else{
	// 			$output[$var] = $value;
	// 		}
	// 	}

	// 	return $output;
	// }

	function XML( $array = NULL, $structure = NULL, $basenode = 'xml' ) {

		// turn off compatibility mode as simple xml throws a wobbly if you don't.
		if (ini_get('zend.ze1_compatibility_mode') == 1)
		{
			ini_set('zend.ze1_compatibility_mode', 0);
		}

		if ($structure === NULL)
		{
			$structure = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><$basenode />");
		}

		// Force it to be something useful
		if (is_array($array) === FALSE && is_object($array) === FALSE)
		{
			$array = (array) $array;
		}

		foreach ($array as $var => $value)
		{

			//change false/true to 0/1
			if (is_bool($value))
			{
				$value = (int) $value;
			}

			// no numeric keys in our xml please!
			if (is_numeric($var))
			{
				// make string key...
				$var = (singular($basenode) != $basenode) ? singular($basenode) : 'item';
			}

			// replace anything not alpha numeric
			$var = preg_replace('/[^a-z_\-0-9]/i', '', $var);

			if ($var === '_attributes' && (is_array($value) || is_object($value)))
			{
				$attributes = $value;
				if (is_object($attributes))
				{
					$attributes = get_object_vars($attributes);
				}

				foreach ($attributes as $attribute_name => $attribute_value)
				{
					$structure->addAttribute($attribute_name, $attribute_value);
				}
			}
			// if there is another array found recursively call this function
			elseif (is_array($value) || is_object($value))
			{
				$node = $structure->addChild($var);

				// recursive call.
				$this->XML($value, $node, $var);
			}
			else
			{
				// add single node.
				$value = htmlspecialchars(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');

				$structure->addChild($var, $value);
			}
		}

		return $structure->asXML();
	}

	// function CSV($array = NULL, $delimiter = ',', $enclosure = '"') {

	// 	// Use a threshold of 1 MB (1024 * 1024)
 //        $handle = fopen('php://temp/maxmemory:1048576', 'w')
 //        if ($handle === FALSE)
 //        {
 //            return NULL;
 //        }

 //        // If NULL, then set as the default delimiter
 //        if ($delimiter === NULL)
 //        {
 //            $delimiter = ',';
 //        }

 //        // If NULL, then set as the default enclosure
 //        if ($enclosure === NULL)
 //        {
 //            $enclosure = '"';
 //        }

 //        // Cast as an array if not already
 //        if (is_array($array) === FALSE)
 //        {
 //            $array = (array) $array;
 //        }

 //        // Check if it's a multi-dimensional array
 //        if (isset($array[0]) && count($array) !== count($array, COUNT_RECURSIVE))
 //        {
 //            // Multi-dimensional array
 //            $headings = array_keys($array[0]);
 //        }
 //        else
 //        {
 //            // Single array
 //            $headings = array_keys($array);
 //            $array = [$array];
 //        }

 //        // Apply the headings
 //        fputcsv($handle, $headings, $delimiter, $enclosure);

 //        foreach ($array as $record)
 //        {
 //            // If the record is not an array, then break. This is because the 2nd param of
 //            // fputcsv() should be an array
 //            if (is_array($record) === FALSE)
 //            {
 //                break;
 //            }

 //            // Suppressing the "array to string conversion" notice.
 //            // Keep the "evil" @ here.
 //            $record = @ array_map('strval', $record);

 //            // Returns the length of the string written or FALSE
 //            fputcsv($handle, $record, $delimiter, $enclosure);
 //        }

 //        // Reset the file pointer
 //        rewind($handle);

 //        // Retrieve the csv contents
 //        $csv = stream_get_contents($handle);

 //        // Close the handle
 //        fclose($handle);

 //        return $csv;
	// }

	function JSON($array) {

		// Get the callback parameter (if set)
		$callback = self::$CI->input->get('callback');

		if (empty($callback) === TRUE)
		{
			return json_encode($array);
		}

		// We only honour a jsonp callback which are valid javascript identifiers
		elseif (preg_match('/^[a-z_\$][a-z0-9\$_]*(\.[a-z_\$][a-z0-9\$_]*)*$/i', $callback))
		{
			// Return the data as encoded json with a callback
			return $callback . '(' . json_encode($array) . ');';
		}

		// An invalid jsonp callback function provided.
		// Though I don't believe this should be hardcoded here
		$array['warning'] = 'INVALID JSONP CALLBACK: ' . $callback;

		return json_encode($array);
	}

	function JSONP($array) {
		return $this->json($array);
	}

	function PHP($array) {
		return var_export($array, TRUE);
	}
}
