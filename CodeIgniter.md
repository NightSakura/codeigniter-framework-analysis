承接上文的分析可知，在CodeIgniter框架中`CodeIgniter.php`才是整个框架核心内容的启动器，贯穿了整个框架的加载和运行流程。下面我们就进行启动器的代码分析。
## 加载和运行流程
我理解的CI框架的流程图如下图所示，淡蓝色部分表示的是框架的核心库的内容，淡橙色部分是框架为用户定义的扩展框架的核心的挂钩点，在Hook.php的分析中我们会讲到钩子机制。比较特殊的钩子类是$hook['cache_override']和$hook['display_override']，这两个钩子的含义是实用用户自己定义的方式来替代输出类中的_display_cache()和_display()方法，实现自定义的缓存和显示机制。相对其他钩子而言，这两个钩子完成的工作不是“扩展”，而是“替换”。
![](https://upload-images.jianshu.io/upload_images/8371576-fd8a82076a201563.png?imageMogr2/auto-orient/strip%7CimageView2/2/w/1240)

## 代码分析
CodeIgniter的设计为了兼容之前的版本和旧PHP的语法，面向对象的特性使用不多，主流程中的部分内容以类的形式进行了封装，依然有部分内容直接在`CodeIgniter.php`中进行完成的，下面我们从代码的角度以更细粒度的方式来分析`CodeIgniter.php`的执行过程，其中涉及到核心类库的部分将切分到接下来的文章中单独开篇进行分析。
### 设置版本号
`const CI_VERSION = '3.1.8';`
### 加载系统常量和公共函数
在框架流程的开始先加载了系统常量和公共函数，系统常量包括文件读写、退出状态位等的设置，当然用户也可以扩充此文件；公共函数是后续流程中会使用到的函数在这里就进行了加载，比如使用非常多的load()函数。
```php
if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/constants.php')){		 
    require_once(APPPATH.'config/'.ENVIRONMENT.'/constants.php');
}
if (file_exists(APPPATH.'config/constants.php')){
    require_once(APPPATH.'config/constants.php');
}
require_once(BASEPATH.'core/Common.php');
```
### 低于PHP5.4版本的安全处理
这部分内容是为了兼容PHP5.4之前的版本，PHP 5.4废除了register_globals，magic_quotes以及安全模式，之后的版本就不需要考虑这些问题。
```php
if ( ! is_php('5.4'))
{
	ini_set('magic_quotes_runtime', 0);

	if ((bool) ini_get('register_globals'))
	{
		$_protected = array(
			'_SERVER',
			'_GET',
			'_POST',
			'_FILES',
			'_REQUEST',
			'_SESSION',
			'_ENV',
			'_COOKIE',
			'GLOBALS',
			'HTTP_RAW_POST_DATA',
			'system_path',
			'application_folder',
			'view_folder',
			'_protected',
			'_registered'
		);

		$_registered = ini_get('variables_order');
		foreach (array('E' => '_ENV', 'G' => '_GET', 'P' => '_POST', 'C' => '_COOKIE', 'S' => '_SERVER') as $key => $superglobal)
		{
			if (strpos($_registered, $key) === FALSE)
			{
				continue;
			}

			foreach (array_keys($$superglobal) as $var)
			{
				if (isset($GLOBALS[$var]) && ! in_array($var, $_protected, TRUE))
				{
					$GLOBALS[$var] = NULL;
				}
			}
		}
	}
}
```
### 设置系统错误处理Handler
设置错误处理，异常处理，shutdown的梳理函数，这几个函数其实是定义在`Common.php`中的。
```php
set_error_handler('_error_handler');
set_exception_handler('_exception_handler');
register_shutdown_function('_shutdown_handler');
```
### 获取Composer支持
CodeIgniter的Composer支持可以通过用户配置来完成，但是CodeIgniter本身使用的是自己定义的类加载方法，还是为了兼容老版本。个人认为历史包袱太重，适应现在PHP的标准应该引入Composer支持，通过PSR4的标准完成类的加载以利于框架之间的互相调用，不过这些都是题外话。
```php
if ($composer_autoload = config_item('composer_autoload'))
{
   if ($composer_autoload === TRUE)
   {
      file_exists(APPPATH.'vendor/autoload.php')
         ? require_once(APPPATH.'vendor/autoload.php')
         : log_message('error', '$config[\'composer_autoload\'] is set to TRUE but '.APPPATH.'vendor/autoload.php was not found.');
   }
   elseif (file_exists($composer_autoload))
   {
      require_once($composer_autoload);
   }
   else
   {
      log_message('error', 'Could not find the specified $config[\'composer_autoload\'] path: '.$composer_autoload);
   }
}
```
### 加载框架的核心类库
在上文的流程图中框架核心类库的加载也被切分成了几部分，比如先加载`Output.php`来确认是否存在缓存，如果可以从缓存读取并输出就不需要加载后续的文件。不过从介绍上我们将所有的核心库和文件名称统一归纳如下表所示，具体内容将在后续文章中展开分析，其中日志类`Log.php`和配置类`Config.php`分别用于日志和读取配置，比较简单不再进行分析。

|功能|文件名称|注释|
|:----:|:-----------:|:----:|
|公共函数|Common.php||
|基准类|Benchmark.php||
|钩子|Hook.php||
|UTF8编码转换类|UTF8.php||
|地址解析类|URI.php||
|路由类|Router.php|
|输入类|Input.php||
|输出类|Output.php||
|控制器类|Controller.php||
|加载类|Loader.php||
|安全类|Security.php||
|模型类|Model.php||

### 多字节支持

### 部分系统库重写

### 缓存调用
注意框架的组件加载过程，在判断缓存是否存在时很多组件还没有加载，CodeIgniter也是以此为标准来判断是否是缓存读取的。
```php
if ($EXT->call_hook('cache_override') === FALSE 
    && $OUT->_display_cache($CFG, $URI) === TRUE){
	exit;
}
```
### 404错误处理
代码运行到这一部分URI和Router组件都已经加载完毕，并且路由解析的结果保存在$RTR的$class和$method属性中，为了提高框架的健壮性，需要进行404错误的判断。
并且CodeIgniter实现了“_remap”方法，意思是不论路由结果如何，如果路由到的类中有名为“_remap”的方法，都将执行该方法。
```php
$e404 = FALSE;
$class = ucfirst($RTR->class);
$method = $RTR->method;
//没有对应的类
if (empty($class) OR ! file_exists(APPPATH.'controllers/'.$RTR->directory.$class.'.php')){
	$e404 = TRUE;
} else {
    require_once(APPPATH.'controllers/'.$RTR->directory.$class.'.php');
    if ( ! class_exists($class, FALSE) OR $method[0] === '_' OR method_exists('CI_Controller', $method)){
		$e404 = TRUE;
    } elseif (method_exists($class, '_remap')){
		$params = array($method, array_slice($URI->rsegments, 2));
		$method = '_remap';
    } elseif ( ! method_exists($class, $method)){
		$e404 = TRUE;
    } elseif ( ! is_callable(array($class, $method))){
		$reflection = new ReflectionMethod($class, $method);
		if ( ! $reflection->isPublic() OR $reflection->isConstructor()){
			$e404 = TRUE;
		}
	}
}
//类不存在，方法不存在都被标记为404，使用错误处理类进行处理
if ($e404){
	if ( ! empty($RTR->routes['404_override'])){
		if (sscanf($RTR->routes['404_override'], '%[^/]/%s', $error_class, $error_method) !== 2){
			$error_method = 'index';
		}
		$error_class = ucfirst($error_class);
		if ( ! class_exists($error_class, FALSE) {
			if (file_exists(APPPATH.'controllers/'.$RTR->directory.$error_class.'.php')){
				require_once(APPPATH.'controllers/'.$RTR->directory.$error_class.'.php');
				$e404 = ! class_exists($error_class, FALSE);
			} elseif ( ! empty($RTR->directory) && file_exists(APPPATH.'controllers/'.$error_class.'.php')){			                                require_once(APPPATH.'controllers/'.$error_class.'.php');
					if (($e404 = ! class_exists($error_class, FALSE)) === FALSE){
						$RTR->directory = '';
					}
				}
			}
			else{
				$e404 = FALSE;
			}
		}

		if ( ! $e404)
		{
			$class = $error_class;
			$method = $error_method;

			$URI->rsegments = array(
				1 => $class,
				2 => $method
			);
		}
		else
		{
			show_404($RTR->directory.$class.'/'.$method);
		}
	}
        
	if ($method !== '_remap')
	{
		$params = array_slice($URI->rsegments, 2);
	}
```
### 调用请求方法 输出结果
调用请求的方法和输出是通过如下代码实现的，其中display_override钩子可以替换默认的_display()方法
```php
$CI = new $class();  
call_user_func_array(array(&$CI, $method), $params);  

if ($EXT->call_hook('display_override') === FALSE)
{
    $OUT->_display();
}
```
