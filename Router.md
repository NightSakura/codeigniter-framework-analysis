# 路由类Router.php
路由是MVC框架的核心内容，路由的作用是寻找和用户的请求URL相匹配的类和方法[处理器]，也可以说是请求分发器，将用户的请求分发到对应的文件进行处理。CI框架在路由上比较灵活，支持路由不同风格的URI，当然针对不同风格的URI需要进行不同的预处理方法，这一步主要在[地址解析](https://www.jianshu.com/p/ed60ea3b8b6c)中完成，总体而言CI的路由解析是需要URI类和Router类配合完成的，针对不通的URL风格进行不通的地址解析方法，当然对应的路由规则也不尽相同，CI也支持自定义路由规则。

## 用户配置
CI的路由和地址解析类时相互配合完成工作的，由于CI支持多种风格的URI解析，对不通模式下的路由规则也是略有不同的，同样依赖于`config.php`中的配置。
* 当`$config['enable_query_string']=True`时,路由解析是工作在QUERY_STRING的模式下，这种情况下**用户定义的路由规则是不生效的，这时候路由解析都是基于查询字符串完成的**。
`$config['controller_trigger'] = 'c';`
`$config['function_trigger'] = 'm';`
`$config['directory_trigger'] = 'd';`
以上3个配置项也是定义在`config.php`中的，他们分别的定义了URI中查询字符串中定义的控制器，方法和文件目录的缩写。
* 当`$config['enable_query_string']=False`时，路由解析工作在REQUEST_URI的模式下，这时候就需要进行路由规则匹配了。
用户自定义的路由规则在 application/config/routes.php 文件中，这个文件定义了一个$route数组。
CI中的自定义路由规则比较强大，能够支持通配符的形式、支持正则表达式的形式，支持设置自定义的回调函数来处理逆向引用，支持使用HTTP动词来进行更精准的路由匹配，具体的定义格式等内容可以参见CI用户手册的[URI路由](http://codeigniter.org.cn/user_guide/general/routing.html)章节。CI是如何对以上路由规则进行适配的呢？我们接下来就慢慢讲解。
在次之前需要指出的是，CI中保留了以下几条默认路由规则。
`$route['default_controller'] = 'welcome';`
`$route['404_override'] = '';`
`$route['translate_uri_dashes'] = FALSE;`

## 属性概览

|属性名称|注释|
|:----------:|:-----:|
|public $config;|配置类CI_CONFIG的对象|
|public $routes = array();|获取并保存从配置文件中得到的路由规则|
|public $class = '';|当前的类名称|
|public $method = 'index';|当前的方法名称，默认为index|
|public $directory;|包含请求的类和方法的次级目录|
|public $default_controller;|默认的控制器类名称|
|public $translate_uri_dashes = FALSE;|确定URI中的“-”是否需要转化成“_”的标志位|
|public $enable_query_strings = FALSE;|是否开启query_string的标志位|

## 函数概览

|方法名称|注释|
|:----------:|:-----:|
|__construct()|构造函数|
|_set_routing()|进行路由解析并保存结果|
|_set_request($segments = array())|根据默认规则匹配路由|
|_set_default_controller()|设置默认的controller|
|_validate_request($segments)|验证uri并识别目录|
|_parse_routes()|request_uri模式下路由解析|
|set_class($class)|设置用户请求对应的class|
|fetch_class()|获取和返回类的$class属性|
|set_method($method)|设置用户请求对应的method|
|fetch_method()|获取和返回类的$method属性|
|set_directory($dir, $append = FALSE)|设置用户请求对应的directory|
|fetch_directory()|获取和返回类的$directory属性|

**构造函数__construct()**

构造函数调用了_set_routing()函数完成了路由解析的过程，并且将结果保存在类的属性$class和$method中，同时考虑到主文件中覆盖解析结果的情况
```
public function __construct($routing = NULL)
{
   $this->config =& load_class('Config', 'core');
   //魔术方法，拦截器把URI对象作为路由类的属性
   $this->uri =& load_class('URI', 'core');
   //设置属性enable_query_strings的值
   $this->enable_query_strings = ( ! is_cli() && $this->config->item('enable_query_strings') === TRUE);

   //如果设置了$routing且包含目录，需要在动态路由之前设置该目录
   is_array($routing) && isset($routing['directory']) && $this->set_directory($routing['directory']);
   //加载config/route.php文件，完成query_string或request_uri模式下的路由解析
   //将路由解析的结果保存在Router类的属性class和method
   $this->_set_routing();

   //如果$routing中定义了controller和function表示覆盖，则直接覆写class和method
   if (is_array($routing))
   {
      empty($routing['controller']) OR $this->set_class($routing['controller']);
      empty($routing['function'])   OR $this->set_method($routing['function']);
   }

   log_message('info', 'Router Class Initialized');
}
```
**进行路由解析_set_routing()**

_set_routing()是进行路由解析的核心方法，从加载用户自定义的路由规则开始，到针对query_string和request_uri两种设置进行路由解析并保存最终的结果，query_string设置下直接根据查询字符串解析进行，解析失败会调用设置默认控制器函数_set_default_controller()，request_uri设置下的解析则调用函数_parse_routes()进行处理。
```
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
```
**REQUEST_URI模式下的URI解析_parse_routes()**

_parse_routes()是request_uri设置下的路由解析方法，分别针对用户自定义的路由规则进行处理，优先处理HTTP动词，然后将通配符替换为正则进行匹配，处理回调函数和逆向引用的情况，如果都没有匹配成功，则根据默认的路由规则调用_set_request()函数进行处理。
```
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
```
**解析uri中文件目录_validate_request($segments)**

处理URI类处理之后的$segments，按顺序匹配直到找到控制器文件为止，并把之前的数组元素设置在目录中。
```
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
```
**默认规则解析控制器和方法_set_request($segments = array())**

_set_request()函数处理的是_validate_request()处理之后剩余的$segments，如果为空表示没有对应的控制器，设置默认控制器，否则表示找到了控制器文件，保存结果即可。
```
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
```
**设置默认的控制器和方法_set_default_controller()**

顾名思义，也很容易理解，设置默认的控制器和方法。
```
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
```
