在CI框架中`CodeIgniter.php`才是整个框架核心内容的启动器，代表证整个框架的加载和运行流程。我理解的CI框架的流程图如下图所示。
## 加载和运行流程
淡蓝色部分表示的是框架的核心库，淡橙色部分是用户定义的钩子类扩展框架的核心，在之后的文章对Hook.php文件进行分析时会讲到。比较特殊的钩子类是$hook['cache_override']，这个钩子的含义是实用用户自己定义的方式来替代输出类中的_display_cache()方法，实现自定义的缓存显示机制。相对其他钩子而言，这个钩子完成的工作不是“扩展”，而是“替换”。
下面我们以一种更细粒度的方式来认识`CodeIgniter.php`的执行过程，其中具体的核心库将在接下来的文章中单独开篇进行详细分析。
![](https://upload-images.jianshu.io/upload_images/8371576-fd8a82076a201563.png?imageMogr2/auto-orient/strip%7CimageView2/2/w/1240)

### 设置版本号
`const CI_VERSION = '3.1.8';`
### 加载系统常量和公共函数
```
if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/constants.php')){		 
    require_once(APPPATH.'config/'.ENVIRONMENT.'/constants.php');
}
if (file_exists(APPPATH.'config/constants.php')){
    require_once(APPPATH.'config/constants.php');
}
require_once(BASEPATH.'core/Common.php');
```
### 低于PHP5.4版本的安全处理
```
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
```
set_error_handler('_error_handler');
set_exception_handler('_exception_handler');
register_shutdown_function('_shutdown_handler');
```
### 加载框架的核心类库
框架核心类库和文件名称对应如下表所示，具体内容将在后续文章中展开分析，其中日志类`Log.php`和配置类`Config.php`不再分析。

|功能|文件名称|注释|
|:----:|:-----------:|:----:|
|公共函数|Common.php||
|基准类|Benchmark.php||
|钩子|Hook.php||
|UTF8编码转换类|UTF8.php||
|地址解析类|URI.php||
|输入类|Input.php||
|输出类|Output.php||
|控制器类|Controller.php||
|加载类|Loader.php||
|安全类|Security.php||

### 多字节支持

### 部分系统库重写

### 缓存调用

```
if ($EXT->call_hook('cache_override') === FALSE 
    && $OUT->_display_cache($CFG, $URI) === TRUE){
	exit;
}
```
### 404错误处理
```
$e404 = FALSE;
$class = ucfirst($RTR->class);
$method = $RTR->method;
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
			} elseif ( ! empty($RTR->directory) && file_exists(APPPATH.'controllers/'.$error_class.'.php')){			
                    require_once(APPPATH.'controllers/'.$error_class.'.php');
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
