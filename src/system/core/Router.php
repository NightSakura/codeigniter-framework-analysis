<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014 - 2018, British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	CodeIgniter
 * @author	EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc. (https://ellislab.com/)
 * @copyright	Copyright (c) 2014 - 2018, British Columbia Institute of Technology (http://bcit.ca/)
 * @license	http://opensource.org/licenses/MIT	MIT License
 * @link	https://codeigniter.com
 * @since	Version 1.0.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Router Class
 *
 * Parses URIs and determines routing
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/user_guide/general/routing.html
 */
class CI_Router {

	/**
	 * CI_Config class object
	 *
	 * @var	object
	 */
	public $config;

	/**
	 * List of routes
	 *
	 * @var	array
	 */
	public $routes =	array();

	/**
	 * Current class name
	 *
	 * @var	string
	 */
	public $class =		'';

	/**
	 * Current method name
	 *
	 * @var	string
	 */
	public $method =	'index';

	/**
	 * Sub-directory that contains the requested controller class
	 *
	 * @var	string
	 */
	public $directory;

	/**
	 * Default controller (and method if specific)
	 *
	 * @var	string
	 */
	public $default_controller;

	/**
	 * Translate URI dashes
	 *
	 * Determines whether dashes in controller & method segments
	 * should be automatically replaced by underscores.
	 *
	 * @var	bool
	 */
	public $translate_uri_dashes = FALSE;

	/**
	 * Enable query strings flag
	 *
	 * Determines whether to use GET parameters or segment URIs
	 *
	 * @var	bool
	 */
	public $enable_query_strings = FALSE;

	// --------------------------------------------------------------------

	/**
	 * Class constructor
	 *
	 * Runs the route mapping function.
	 *
	 * @param	array	$routing
	 * @return	void
	 */
	public function __construct($routing = NULL)
	{
		$this->config =& load_class('Config', 'core');
		$this->uri =& load_class('URI', 'core');
        //设置属性enable_query_strings的值
		$this->enable_query_strings = ( ! is_cli() && $this->config->item('enable_query_strings') === TRUE);

		//如果设置了$routing且包含目录，需要在动态路由之前设置该目录
        is_array($routing) && isset($routing['directory']) && $this->set_directory($routing['directory']);
		//读取config/route.php文件并初始化类的对应属性
        //完成query_string或request_uri模式下的路由解析
		$this->_set_routing();

		//如果$routing中定义了controller和function表示覆盖，则直接覆写class和method
		if (is_array($routing))
		{
			empty($routing['controller']) OR $this->set_class($routing['controller']);
			empty($routing['function'])   OR $this->set_method($routing['function']);
		}

		log_message('info', 'Router Class Initialized');
	}

	// --------------------------------------------------------------------

	/**
	 * Set route mapping
	 *
	 * Determines what should be served based on the URI request,
	 * as well as any "routes" that have been set in the routing config file.
	 *
	 * @return	void
	 */
	protected function _set_routing()
	{
		//加载routes.php文件
		if (file_exists(APPPATH.'config/routes.php'))
		{
			include(APPPATH.'config/routes.php');
		}

		if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/routes.php'))
		{
			include(APPPATH.'config/'.ENVIRONMENT.'/routes.php');
		}

		//验证和设置默认controller,破折号下划线的值，并将剩下的路由规则保存在$routes中
		if (isset($route) && is_array($route))
		{
			isset($route['default_controller']) && $this->default_controller = $route['default_controller'];
			isset($route['translate_uri_dashes']) && $this->translate_uri_dashes = $route['translate_uri_dashes'];
			unset($route['default_controller'], $route['translate_uri_dashes']);
			$this->routes = $route;
		}

		//如果启用query_strings模式，寻找class/function的规则处理如下
		if ($this->enable_query_strings)
		{
			//如果已经设置了directory表示出现覆盖跳过directory的获取，否则获取方法如下
			if ( ! isset($this->directory))
			{
			    //获取配置项中directory的缩写，并从$_GET中取得，过滤，赋值给directory
				$_d = $this->config->item('directory_trigger');
				$_d = isset($_GET[$_d]) ? trim($_GET[$_d], " \t\n\r\0\x0B/") : '';

				if ($_d !== '')
				{
					$this->uri->filter_uri($_d);
					$this->set_directory($_d);
				}
			}

			$_c = trim($this->config->item('controller_trigger'));
			if ( ! empty($_GET[$_c]))
			{
			    //分别根据请求字符串设置class和function，并将其保存在URI对象的rsegments数组中
				$this->uri->filter_uri($_GET[$_c]);
				$this->set_class($_GET[$_c]);

				$_f = trim($this->config->item('function_trigger'));
				if ( ! empty($_GET[$_f]))
				{
					$this->uri->filter_uri($_GET[$_f]);
					$this->set_method($_GET[$_f]);
				}

				$this->uri->rsegments = array(
					1 => $this->class,
					2 => $this->method
				);
			}
			else
			{
			    //设置默认的controller
				$this->_set_default_controller();
			}

			//路由规则在query_string模式下不适用，且不用定位目录，所以到这里就完成了路由
			return;
		}

		//如果经过URI对象处理后的uri_string不为空，就需要路由处理；否则设置默认的controller
		if ($this->uri->uri_string !== '')
		{
		    //querst_uri模式下的路由解析方法
			$this->_parse_routes();
		}
		else
		{
			$this->_set_default_controller();
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Set request route
	 *
	 * Takes an array of URI segments as input and sets the class/method
	 * to be called.
	 *
	 * @used-by	CI_Router::_parse_routes()
	 * @param	array	$segments	URI segments
	 * @return	void
	 */
	protected function _set_request($segments = array())
	{
	    //验证uri并将其中的dir元素移除并拼接到类的$directory属性中
		$segments = $this->_validate_request($segments);

		//如果已经没有元素就只能设置默认的controller了
		if (empty($segments))
		{
			$this->_set_default_controller();
			return;
		}

		//把uri中的"-"转化成"_"
		if ($this->translate_uri_dashes === TRUE)
		{
			$segments[0] = str_replace('-', '_', $segments[0]);
			if (isset($segments[1]))
			{
				$segments[1] = str_replace('-', '_', $segments[1]);
			}
		}

		//如果$segments[1]存在那么设置其为方法名，否则默认设置index
		$this->set_class($segments[0]);
		if (isset($segments[1]))
		{
			$this->set_method($segments[1]);
		}
		else
		{
			$segments[1] = 'index';
		}
		//保证$segments数组有用下标是从1开始的
		array_unshift($segments, NULL);
		unset($segments[0]);
		$this->uri->rsegments = $segments;
	}

	// --------------------------------------------------------------------

	/**
	 * Set default controller
	 * 设置默认的Controller
	 * @return	void
	 */
	protected function _set_default_controller()
	{
	    //default_controller是保留路由，不设置这里会报错
		if (empty($this->default_controller))
		{
			show_error('Unable to determine what should be displayed. A default route has not been specified in the routing file.');
		}

		//如果没有设置方法，将其设置为index
		if (sscanf($this->default_controller, '%[^/]/%s', $class, $method) !== 2)
		{
			$method = 'index';
		}
        //寻找$class首字母大写的php文件失败
		if ( ! file_exists(APPPATH.'controllers/'.$this->directory.ucfirst($class).'.php'))
		{
			//这里之后会触发404错误
			return;
		}

		//设置这种情况下路由到的的class和method
		$this->set_class($class);
		$this->set_method($method);

		//把路由结果保存到uri对象的rsegments数组中，下标从1开始
		$this->uri->rsegments = array(
			1 => $class,
			2 => $method
		);

		log_message('debug', 'No URI present. Default controller set.');
	}

	// --------------------------------------------------------------------

	/**
	 * Validate request
	 *
	 * Attempts validate the URI request and determine the controller path.
	 *
	 * @used-by	CI_Router::_set_request()
	 * @param	array	$segments	URI segments
	 * @return	mixed	URI segments
	 */
	protected function _validate_request($segments)
	{
		$c = count($segments);
		$directory_override = isset($this->directory);

		//$segments中保存的应该是dir名称+class名称+method名称
        //从数组首位开始循环知道找到第一个不是directory的元素或者找到controller文件终止
		while ($c-- > 0)
		{
			$test = $this->directory
				.ucfirst($this->translate_uri_dashes === TRUE ? str_replace('-', '_', $segments[0]) : $segments[0]);
            //当前元素是dir名称且没有对应的controller文件
			if ( ! file_exists(APPPATH.'controllers/'.$test.'.php')
				&& $directory_override === FALSE
				&& is_dir(APPPATH.'controllers/'.$this->directory.$segments[0])
			)
			{
			    //把当前的$segments的首元素拼接到directory中
				$this->set_directory(array_shift($segments), TRUE);
				continue;
			}
            //到这一步表示返回的$segments的首元素不是dir目录或者是controller文件
			return $segments;
		}

		//到这一步返回的已经是空数组了，所有元素表示的都是目录
		return $segments;
	}

	// --------------------------------------------------------------------

	/**
	 * Parse Routes
	 *
	 * Matches any routes that may exist in the config/routes.php file
	 * against the URI to determine if the class/method need to be remapped.
	 *
	 * @return	void
	 */
	protected function _parse_routes()
	{
		//将URI对象的segments数组转化成uri字符
		$uri = implode('/', $this->uri->segments);

		//获取HTTP动词
		$http_verb = isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : 'cli';

		//从路由规则中寻找匹配的规则
		foreach ($this->routes as $key => $val)
		{
			//如果定义了HTTP动词，那一定是一个二维数组
            //这种情况严格匹配HTTP动词，匹配失败直接跳过当前$ket匹配下个$key
			if (is_array($val))
			{
				$val = array_change_key_case($val, CASE_LOWER);
				if (isset($val[$http_verb]))
				{
				    //从多个HTTP动词中选择出匹配的哪个作为$val，还要继续匹配$key
					$val = $val[$http_verb];
				}
				else
				{
					continue;
				}
			}

			//将通配符转化为正则的形式
			$key = str_replace(array(':any', ':num'), array('[^/]+', '[0-9]+'), $key);

			//如果正则匹配成功
			if (preg_match('#^'.$key.'$#', $uri, $matches))
			{
				//用于处理用户设置的回调函数
				if ( ! is_string($val) && is_callable($val))
				{
					//首元素保存的是controller名称
					array_shift($matches);

					//用匹配上的数据作为回调函数的参数并执行
					$val = call_user_func_array($val, $matches);
				}
				//用于处理参数的逆向引用
				elseif (strpos($val, '$') !== FALSE && strpos($key, '(') !== FALSE)
				{
					$val = preg_replace('#^'.$key.'$#', $val, $uri);
				}

				//用于处理常规的uri路由规则
				$this->_set_request(explode('/', $val));
				return;
			}
		}

		//如果走到这一步，表示用户路由规则中没有匹配成功，根据默认规则匹配路由
		$this->_set_request(array_values($this->uri->segments));
	}

	// --------------------------------------------------------------------

	/**
	 * Set class name
	 *
	 * @param	string	$class	Class name
	 * @return	void
	 */
	public function set_class($class)
	{
		$this->class = str_replace(array('/', '.'), '', $class);
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch the current class
	 *
	 * @deprecated	3.0.0	Read the 'class' property instead
	 * @return	string
	 */
	public function fetch_class()
	{
		return $this->class;
	}

	// --------------------------------------------------------------------

	/**
	 * Set method name
	 *
	 * @param	string	$method	Method name
	 * @return	void
	 */
	public function set_method($method)
	{
		$this->method = $method;
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch the current method
	 *
	 * @deprecated	3.0.0	Read the 'method' property instead
	 * @return	string
	 */
	public function fetch_method()
	{
		return $this->method;
	}

	// --------------------------------------------------------------------

	/**
	 * Set directory name
	 *
	 * @param	string	$dir	Directory name
	 * @param	bool	$append	Whether we're appending rather than setting the full value
	 * @return	void
	 */
	public function set_directory($dir, $append = FALSE)
	{
		if ($append !== TRUE OR empty($this->directory))
		{
			$this->directory = str_replace('.', '', trim($dir, '/')).'/';
		}
		else
		{
			$this->directory .= str_replace('.', '', trim($dir, '/')).'/';
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch directory
	 *
	 * Feches the sub-directory (if any) that contains the requested
	 * controller class.
	 *
	 * @deprecated	3.0.0	Read the 'directory' property instead
	 * @return	string
	 */
	public function fetch_directory()
	{
		return $this->directory;
	}

}
