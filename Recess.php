<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Recess extends CI_Driver_Library
{
	protected $valid_drivers = ['assign', 'format'];
	/**
	 * @var object
	 */
	private static $_instance;
	/**
	 * @var object
	 */
	private static $_ci_instance;
	/**
	 * @var object
	 */
	private static $_ci_hooks_instance;
	/**
	 * @var object
	 */
	private static $_ci_output_instance;
	/**
	 * @var boolean
	 */
	protected $_enable_xss = FALSE;
	/**
	 * @var string
	 */
	protected $_content_type = NULL;
	/**
	 * @var string
	 */
	protected $_directory = NULL;
	/**
	 * @var string
	 */
	protected $_class = NULL;
	/**
	 * @var string
	 */
	protected $_method = NULL;
	/**
	 * @var array
	 */
	protected $_arguments = array();

	function __construct()
	{
		log_message('debug', "Recess driver initialized");
		/*
		 * ------------------------------------------------------
		 *  Recess Class
		 * ------------------------------------------------------
		 * remap()
		 * input()
		 * input_stream()
		 * input_method()
		 * response()
		 */
		self::$_instance = &$this;
		/*
		 * ------------------------------------------------------
		 *  Codeigniter Controller Class
		 * ------------------------------------------------------
		 */
		self::$_ci_instance = &get_instance();
		self::$_ci_instance->benchmark->mark('recess_construct');
		self::$_ci_hooks_instance = &load_class('Hooks', 'core');
		self::$_ci_output_instance = &load_class('Output', 'core');

		$this->_directory = self::$_ci_instance->router->fetch_directory();
		$this->_class = self::$_ci_instance->router->fetch_class();

		$this->_enable_xss = (self::$_ci_instance->config->item('global_xss_filtering') === TRUE);

		$this->assign->put('recess_input_methods', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
		$this->assign->put('recess_authorized', TRUE);

		$this->_content_type = $this->_detected_content_type();
		$this->_detected_hook();
	}

	//------------------------------------------------------

	public static function &get_instance()
	{
		return self::$_instance;
	}

	//------------------------------------------------------

	/**
	 *
	 * @param  [type] $method    [description]
	 * @param  [type] $arguments [description]
	 * @return [type]            [description]
	 */
	public function remap( $method, $arguments = NULL )
	{
		self::$_ci_hooks_instance->call_hook('recess_construct');

		$orig_method = $method;
		$method === 'index' OR $method = [$orig_method, 'index'];

		if( $this->_parse_route($method, $arguments) === FALSE )
		{
			log_message('error', 'Not found');
			show_404();
		}

		if( $orig_method !== 'index' && preg_match('/\_index/i', $this->_method) )
		{
			array_unshift($this->_arguments, $orig_method);
		}

		$this->_is_authorized() && self::$_ci_hooks_instance->call_hook('recess_authorized');

		try
		{
			call_user_func_array([self::$_ci_instance, $this->_method], $this->_arguments );
		}
		catch (Exception $e)
		{
			log_message('error', $e->getMessage());
		}
	}

	//------------------------------------------------------

	/**
	 *
	 * @param  [type]  $output           [description]
	 * @param  [type]  $http_status_code [description]
	 * @param  boolean $continue         [description]
	 * @return [type]                    [description]
	 */
	public function response($output, $http_status_code = NULL, $continue = FALSE)
	{
		$this->_ci_append_output($output);
		if( $continue )
		{
			return;
		}

		if( is_callable( array( $this->format, $this->_content_type) ) === FALSE ) {
			show_error('format error');
		}

		$response = array();

		(int)$http_status_code > 0 OR $http_status_code = 200;
		self::$_ci_output_instance->set_status_header( $http_status_code );

		$response['request_body'] = [
			'method' => $this->input_method(),
			'uri' => self::$_ci_instance->uri->ruri_string(),
			'segments' => $this->_arguments,
			'params' => $this->input(),
			'headers' => $this->header()
		];

		$response['response_body'] = $this->_ci_get_output();

		self::$_ci_instance->benchmark->mark('recess_destruct');
		$duration = (float)self::$_ci_instance->benchmark->elapsed_time('recess_construct', 'recess_destruct');
		$response['duration'] = $duration;

		$this->_ci_set_output($response);

		self::$_ci_hooks_instance->call_hook('recess_override_display');

		try
		{
			$final_output = $this
								->format
							->{$this->_content_type}($this->_ci_get_output());

			self::$_ci_output_instance->_display( $final_output );
			self::$_ci_hooks_instance->call_hook('recess_destruct');

		} catch (Exception $e) {

		}

		exit;
	}

	//------------------------------------------------------

	/**
	 *
	 * @param  [type] $index     [description]
	 * @param  [type] $xss_clean [description]
	 * @return [type]            [description]
	 */
	public function header($index = NULL, $xss_clean = NULL)
	{
		if( isset( $index ) )
		{
			return self::$_ci_instance->input->get_request_header( $index, $xss_clean );
		}

		return self::$_ci_instance->input->request_headers( $xss_clean );
	}

	//------------------------------------------------------

	/**
	 *
	 * @param  [type] $index     [description]
	 * @param  [type] $xss_clean [description]
	 * @return [type]            [description]
	 */
	public function input( $index = NULL, $xss_clean = NULL )
	{
		static $_input_stream;

		if( NULL === $_input_stream )
		{
			$raw_input_stream = self::$_ci_instance->input->raw_input_stream;

			if (is_string($raw_input_stream))
			{
				@json_decode($raw_input_stream);

				if(json_last_error() === JSON_ERROR_NONE)
				{
					$body = json_decode($raw_input_stream, TRUE);
				}
			}

			isset($body) OR $body = self::$_ci_instance->input->post();

			$_input_stream = array_merge(
				$body,
				self::$_ci_instance->input->get()
			);
		}

		$input_stream_defined = $this->_ci_property('recess_override_input');

		foreach ( (array)$input_stream_defined as $key => $value)
		{
			isset( $_input_stream[$key] ) OR $_input_stream[$key] = $value;
		}

		return $this->array_search( $_input_stream, $index, $xss_clean );
	}

	//------------------------------------------------------

	/**
	 *
	 * @return string
	 */
	public function input_method()
	{
		static $_input_method;

		if( NULL === $_input_method )
		{
			$_input_method = strtoupper(self::$_ci_instance->input->method());
		}

		$recess_input_methods = $this->_ci_property('recess_input_methods');

		if( NULL === $recess_input_methods )
		{
			$recess_input_methods = $this->assign->get('recess_input_methods');
		}

		if( in_array( $_input_method, $recess_input_methods ) )
		{
			log_message('debug', "detected input method: ". $_input_method);
			return $_input_method;
		}

		log_message('error', 'Requested an unacceptable input_method: '. $_input_method);
	}

	//------------------------------------------------------

	/**
	 *
	 * @param  [type] &$array    [description]
	 * @param  [type] $index     [description]
	 * @param  [type] $xss_clean [description]
	 * @return [type]            [description]
	 */
	public function array_search(&$array, $index = NULL, $xss_clean = NULL)
	{
		// If $index is NULL, it means that the whole $array is requested
		isset($index) OR $index = array_keys($array);

		// allow fetching multiple keys at once
		if( is_array($index) )
		{
			$output = array();
			foreach ($index as $key)
			{
				$output[$key] = $this->array_search($array, $key, $xss_clean);
			}

			return $output;
		}

		if( isset($array[$index]) === FALSE )
		{
			return NULL;
		}

		return $this->_xss_clean( $array[$index], $xss_clean );
	}

	public function authorized_keys()
	{
		$authorized_keys = self::$_ci_instance->input->server('HTTP_AUTHORIZATION');
		empty($authorized_keys) && $authorized_keys = self::$_ci_instance->input->get_request_header('Authorization');

		if( $authorized_keys )
		{
			$ret = preg_split('/\s+/', $authorized_keys);
			$authorized_keys = isset($ret[1]) ? $ret[1] : $ret[0];

			return $authorized_keys;
		}

		return NULL;
	}

	//------------------------------------------------------

	/**
	 * [_parse_route description]
	 * @param  [type] $method    [description]
	 * @param  [type] $arguments [description]
	 * @return [type]            [description]
	 */
	protected function _parse_route( $method, $arguments = NULL )
	{

		static $_methods = NULL;

		if( NULL === $_methods )
		{
			$_methods = get_class_methods(self::$_ci_instance);
		}

		if( is_array($method) )
		{
			foreach( $method as $m )
			{
				if( $this->_parse_route( $m, $arguments ) )
				{
					return TRUE;
				}
			}

			return FALSE;
		}

		$input_method = $this->input_method();

		$regexp = "/^{$input_method}\_{$method}$/i";
		$matched = preg_grep( $regexp, $_methods );

		if( count( $matched ) > 0 )
		{
			$this->_method = array_shift($matched);
			is_array($arguments) OR $arguments = [];
			$this->_arguments = $arguments;

			return TRUE;
		}

		return FALSE;
	}

	//------------------------------------------------------

	/**
	 * recess_input_methods
	 * recess_override_input
	 * recess_authorized
	 * recess_authorized_override_methods
	 * @param  [string] $property
	 * @return [mixed]
	 */
	protected function _ci_property($property)
	{
		if( ! is_string($property) )
		{
			return NULL;
		}

		isset(self::$_ci_instance->$property)
			&& $output = self::$_ci_instance->$property;

		return isset( $output ) ? $this->_xss_clean($output) : NULL;
	}

	//------------------------------------------------------

	/**
	 *
	 * @param  [type] $output [description]
	 * @return [type]         [description]
	 */
	public function _ci_set_output($output)
	{
		return self::$_ci_output_instance->set_output($output);
	}

	//------------------------------------------------------

	/**
	 *
	 * @return [type] [description]
	 */
	public function _ci_get_output()
	{
		return self::$_ci_output_instance->get_output();
	}

	//------------------------------------------------------

	/**
	 *
	 * @param  [type] $output [description]
	 * @return [type]         [description]
	 */
	public function _ci_append_output($output)
	{
		$final_output = $this->_ci_get_output();
		is_array($output) OR $output = [$output];
		return $this->_ci_set_output( array_merge( (array)$final_output, $output ) );
	}

	//------------------------------------------------------

	/**
	 *
	 * @return boolean [description]
	 */
	protected function _is_authorized() {

		$check_authorized = ($this->assign->get('recess_authorized') === TRUE);

		is_bool( $this->_ci_property('recess_authorized') ) && $check_authorized = $this->_ci_property('recess_authorized');

		if( $check_authorized === FALSE )
		{
			return FALSE;
		}

		$ignores = $this->_ci_property('recess_authorized_override_methods');
		return (in_array( $this->_method, (array)$ignores ) === FALSE);
	}

	//------------------------------------------------------

	/**
	 *
	 * @param  [string] $value     [description]
	 * @param  [boolean] $xss_clean [description]
	 * @return [type]            [description]
	 */
	protected function _xss_clean($value, $xss_clean = NULL)
	{
		is_bool($xss_clean) OR $xss_clean = $this->_enable_xss;
		return $xss_clean === TRUE ? self::$_ci_instance->security->xss_clean($value) : $value;
	}

	//------------------------------------------------------

	/**
	 *
	 * @return [type] [description]
	 */
	protected function _detected_content_type()
	{
		$_supported = [
			'JSON' => 'application/json',
			// 'html' => 'text/html',
			'JSONP' => 'application/javascript',
			'XML' => 'application/xml'
		];

		$format_defined = 'JSON';

		$CONTENT_TYPE = self::$_ci_instance->input->server('CONTENT_TYPE');

		if( isset( $CONTENT_TYPE ) )
		{
			foreach ($_supported as $key => $mine)
			{
				$CONTENT_TYPE = ( strpos($CONTENT_TYPE, ';') !== FALSE
						? current(explode(';', $CONTENT_TYPE))
						: $CONTENT_TYPE );

				if ($CONTENT_TYPE === $mine)
				{
					return $key;
				}
			}
		}

		return $format_defined;
	}

	/**
	 *
	 * @return [type] [description]
	 */
	protected function _detected_hook()
	{
		if ( ! self::$_ci_hooks_instance->enabled )
		{
			return;
		}

		$recess_hooks = ['recess_authorized', 'recess_override_display', 'recess_destruct'];

		foreach( $recess_hooks as $which )
		{
			if( isset(self::$_ci_hooks_instance->hooks[$which]) )
			{
				$data = self::$_ci_hooks_instance->hooks[$which];
				$hook_instance = $this->_hook_instance($data);

				$function = isset($data['function']) ? $data['function'] : FALSE;

				if( $function === FALSE || $hook_instance === FALSE )
				{
					continue;
				}

				unset( self::$_ci_hooks_instance->hooks[$which] );
				if( method_exists($hook_instance, $function) )
				{
					self::$_ci_hooks_instance->hooks[$which][] = array($hook_instance, $function);
					continue;
				}
			}
		}

		return;
	}

	/**
	 *
	 * @param  [type] $data [description]
	 * @return [type]       [description]
	 */
	protected static function _hook_instance( $data )
	{
		static $_classes = array();

		if ( ! isset($data['filepath'], $data['filename'], $data['class']))
		{
			return FALSE;
		}

		$class = $data['class'];

		if( isset( $_classes[ $class ] ) )
		{
			return $_classes[ $class ];
		}

		$filepath = $data['filepath'];
		$filename = $data['filename'];

		$filepath = APPPATH.$filepath.'/'.$filename;

		if ( ! file_exists($filepath))
		{
			return FALSE;
		}

		class_exists($class, FALSE) OR require_once($filepath);

		if ( ! class_exists($class, FALSE) )
		{
			return FALSE;
		}

		$_classes[$class] = new $class();
		return $_classes[$class];
	}
}
