# 地址解析类URI.php
在CodeIgniter框架中完成地址解析的是`URI.php`文件，地址解析是CI框架为了识别不同风格的URL进行的配置和预处理类，针对配置信息对URL进行预处理然后进行路由。
## 用户配置
配置类中影响地址解析的配置项如下所示。

`$config['permitted_uri_chars'] = 'a-z 0-9~%.:_\-‘`，取值用正则表达式表示URL字符串中允许出现的字符，不允许出现的字符会被过滤；

`$config['url_surfix']`表示URL后缀，定义次配置，CI框架会在展示给用户的URL上添加后缀，当然在地址解析时就需要去掉后缀；

`$config['enable_query_strings’]`，表示是否允许查询字符串形式的URL，其取值为True或False。
## 解析方式
CodeIgniter默认用户使用搜索引擎友好的URL格式，比如example.com/who/what/where，但是也允许用户选择查询query_string形式的URL，比如example.com?who=me&what=something&where=here。用户的配置会影响框架解析URI的方式，`$config['enable_query_strings’]`是其中重要的配置项

当`$config['enable_query_strings’]`的值设置为True时，框架默认以查询字符串的形式去匹配盖茨请求的控制器，方法和目录。URI类将不做任何处理，ROUTER类也只会根据查询字符串来匹配对应的目录、控制器和方法。

下面分析`$config['enable_query_strings’]`取值为False的情况，此时`$config['uri_protocol’]`配置生效，并且会影响框架对URI的解析方式。

`$config['uri_protocol’]`的取值范为REQUEST_URI，QUERT_STRING，PATH_INFO
这项配置是为了应对用户的不同的URL风格，对服务器的影响是选择**取回URL字符串的方式**不同，具体信息如下所示：
* REQUEST_URI 使用的是$_SERVER['REQUEST_URI’] ，返回的是用户访问地址，即被访问的文件之后的所有URI段 [包括path_info和query_string]。
* QUERY_STRING  使用的是$_SERVER['QUERY_STRING’]，返回查询字符串即符号?之后的URL部分。
* PATH_INFO     使用的是$_SERVER['PATH_INFO’]，返回真实脚本文件之后和查询字符串之前的URL部分。

至此，我们已经清楚了钩子的开启和配置方法，下面我们开始分析CI_URI类的代码。
## 属性概览

|属性名称|注释|
|:----------:|:-----:|
|public $keyval = array()|用缓存保存的URL字符列表|
|public $uri_string = ''|当前的URL字符|
|public $segments = array()|保存URL字符列表，数组下标从1开始|
|public $rsegments = array()|保存路由的URL字符列表|
|protected $_permitted_uri_chars|保存允许的URL字符集合|

## 方法概览
URI类的方法比较多，不过部分函数的功能类似，都是提供uri到键值对数组的转换，或者返回相对uri等方法，根据名称即可理解实现也比较简单，我们这里只分析相对复杂的方法。

|方法名称|注释|
|:----------:|:-----:|
|__construct()|构造函数|
|_set_uri_string($str)|保存解析后的uri并设置segements数组|
|_parse_request_uri()|request_uri配置下的解析方法|
|_parse_query_string()|query_string配置下的解析方法|
|_parse_argv()|cli模式下参数处理方法|
|_remove_relative_directory($uri)|去除相对路径和多个斜线|
|filter_uri(&$str)|安全处理过滤不允许的字符|
|segment($n, $no_result = NULL)||
|rsegment($n, $no_result = NULL)||
|uri_to_assoc($n = 3, $default = array())||
|ruri_to_assoc($n = 3, $default = array())||
|_uri_to_assoc($n = 3, $default = array(), $which = 'segment')||
|assoc_to_uri($array)||
|slash_segment($n, $where = 'trailing')||
|slash_rsegment($n, $where = 'trailing')||
|_slash_segment($n, $where = 'trailing', $which = 'segment')||
|segment_array()||
|rsegment_array()||
|total_segments()||
|total_rsegments()||
|uri_string()||
|ruri_string()||

**构造函数__construct**

```php
public function __construct()
{
   $this->config =& load_class('Config', 'core');
   //如果配置项中config['enable_query_string']取值为True，我们不需要处理当前的URL
   //但是这项配置在CLI模式下不生效，因此要处理CLI模式和不允许query_string的情况
   if (is_cli() OR $this->config->item('enable_query_strings') !== TRUE)
   {
       //从配置项中获取允许字符集合的正则
      $this->_permitted_uri_chars = $this->config->item('permitted_uri_chars');
      //如果是CLI模式忽略配置，将参数拼接城argv1/argv2的形式
      if (is_cli())
      {
         $uri = $this->_parse_argv();
      }
      else
      {
          //不允许query_string时，根据配置的uri_protocol选择取回的字符串，默认为REQUEST_URI
         $protocol = $this->config->item('uri_protocol');
         empty($protocol) && $protocol = 'REQUEST_URI';
         //根据配置项的不同，选择不同的函数处理URL
         switch ($protocol)
         {
            case 'AUTO': // For BC purposes only
            case 'REQUEST_URI':
               $uri = $this->_parse_request_uri();
               break;
            case 'QUERY_STRING':
               $uri = $this->_parse_query_string();
               break;
            case 'PATH_INFO':
            default:
               $uri = isset($_SERVER[$protocol])
                  ? $_SERVER[$protocol]
                  : $this->_parse_request_uri();
               break;
         }
      }
      //保存解析后的uri，并设置segements数组
      $this->_set_uri_string($uri);
   }

   log_message('info', 'URI Class Initialized');
}
```

**REQUEST_URI配置的解析_prase_request_uri**

```php
 protected function _parse_request_uri()
{
   if ( ! isset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME']))

   {
      return '';
   }
   //如果不提供host，函数parse_url()会返回false
   //从凭借的uri中获取get请求参数$query和请求的路径$url
   $uri = parse_url('[http://dummy](http://dummy/)'.$_SERVER['REQUEST_URI']);
   $query = isset($uri['query']) ? $uri['query'] : '';
   $uri = isset($uri['path']) ? $uri['path'] : '';
   //去掉$uri中包含的$_SERVER['SCRIPT_NAME']
   //Q:当前的uri已经是取path得到的不应该再包含脚本名称，应该是为了防止恶意的URL引起的解析错误
   if (isset($_SERVER['SCRIPT_NAME'][0]))
   {
      if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0)
      {
         $uri = (string) substr($uri, strlen($_SERVER['SCRIPT_NAME']));
      }
      elseif (strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0)
      {
         $uri = (string) substr($uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
      }
   }
   //对于服务器请求的uri的处理，保证再服务器下也能正确解析uri
   // 并且修复了QUERY_STRING和$_GET的值.
   if (trim($uri, '/') === '' && strncmp($query, '/', 1) === 0)
   {
      $query = explode('?', $query, 2);
      $uri = $query[0];
      $_SERVER['QUERY_STRING'] = isset($query[1]) ? $query[1] : '';
   }
   else
   {
      $_SERVER['QUERY_STRING'] = $query;
   }
   //将字符串解析为多个变量存入$_GET中
   parse_str($_SERVER['QUERY_STRING'], $_GET);
   if ($uri === '/' OR $uri === '')
   {
      return '/';
   }
   //去除相对路径"../"和多余的斜线"///"并返回
   return $this->_remove_relative_directory($uri);
}

```

**QUERY_STRING配置的解析**

```php
protected function _parse_query_string()
{
   $uri = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : @getenv('QUERY_STRING');
   //如果去掉斜线之后$uri为空返回空串
   if (trim($uri, '/') === '')
   {
      return '';
   }
   elseif (strncmp($uri, '/', 1) === 0)
   {
       //根据?分割$uri并修复$_SERVER['QUERY_STRING']的值
      $uri = explode('?', $uri, 2);
      $_SERVER['QUERY_STRING'] = isset($uri[1]) ? $uri[1] : '';
      $uri = $uri[0];
   }
   parse_str($_SERVER['QUERY_STRING'], $_GET);
   //去除相对路径"../"和多个斜线"///"，然后返回
   return $this->_remove_relative_directory($uri);
}
```

**设置uri_string的值_set_uri_string($str)**

```php
protected function _set_uri_string($str)
{
   //将处理过的$str经过去除不可见字符和trim之后保存在属性$uri_string中
   $this->uri_string = trim(remove_invisible_characters($str, FALSE), '/');

   if ($this->uri_string !== '')
   {
      //如果定义了url后缀且当前存在就移除后缀
      if (($suffix = (string) $this->config->item('url_suffix')) !== '')
      {
         $slen = strlen($suffix);

         if (substr($this->uri_string, -$slen) === $suffix)
         {
            $this->uri_string = substr($this->uri_string, 0, -$slen);
         }
      }

      $this->segments[0] = NULL;
      //以"/"分割url_string用来填充segments数组
      foreach (explode('/', trim($this->uri_string, '/')) as $val)
      {
         $val = trim($val);
         //安全处理，过滤$val
         $this->filter_uri($val);

         if ($val !== '')
         {
            $this->segments[] = $val;
         }
      }
      //由于这一步，实际有用的数据从segments的下标1开始
      unset($this->segments[0]);
   }
}
```
