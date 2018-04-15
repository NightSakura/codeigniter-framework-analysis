输出类负责将用户请求转化为浏览器输出的，理解输出类需要对HTTP协议有一定的理解，知道服务器如何将请求转化的文件送到浏览器，知道Http的header和content中与服务器输出有关的标签的含义，才能更好的理解CI中输出类CI_Output需要完成的工作。
这里就缓存的例子进行说明，服务器缓存在CI中是如何实现的呢？首先缓存的形式是什么？简单点的实现用文件缓存，那么就需要考虑写缓存的互斥问题。如何判断缓存是否命中？用基础的方法给缓存标记一个key——uri的md5，比较这个key是否相等即可。那么这里又要考虑是否对原始的uri进行md5操作，需要对uri进行标准化处理吗？需要考虑查询字符串吗？后续的内容还包括如何判断缓存过期，缓存输出的时候如何处理HTTP响应头中与缓存控制相关的标签，如果缓存未命中应该如何通知其他组件进行后续流程等等。
不过值得庆幸的是以上内容在CI框架中都已经实现了，下面我们开始分析CI_Output类是如何做到这些的。
## 属性概览
|属性名称|注释|
|:----------:|:-----:|
|public $final_output;|保存最终的输出结果|
|public $cache_expiration = 0;|缓存生效时间，默认为0|
|public $headers = array();|headers列表|
|public $mimes = array();|mimies类型列表|
|protected $mime_type = 'text/html';|text/html类型|
|public $enable_profiler = FALSE;|保存用户profile标志位|
|protected $_zlib_oc = FALSE;|ini开启zlib标志位|
|protected $_compress_output = FALSE;|是否压缩输出标志位|
|protected $_profiler_sections = array();|保存用户profile的数组|
|protected static $func_overload;|函数重载标志位|
|public $parse_exec_vars = TRUE;||

## 方法概览
|方法名称|注释|
|:-----------:|:----:|
|__construct()|构造函数|
|get_output()|获取并返回$final_output|
|set_output($output)|设置$final_output返回类|
|append_output($output)|以append的方式修改$final_output，返回类|
|set_header($header, $replace = TRUE)|设置header|
|set_content_type($mime_type, $charset = NULL)|设置内容的类型|
|get_content_type()|获取内容的类型|
|get_header($header)|获取header|
|set_status_header($code = 200, $text = '')|设置header的状态|
|enable_profiler($val = TRUE)|是否保存profile|
|set_profiler_sections($sections)|保存profile|
|cache($time)|设置缓存有效时间|
|_display($output = '')|将输出展示到浏览器|
|_write_cache($output)|写文件缓存的放发|
|_display_cache(&$CFG, &$URI)|读取缓存输出到浏览器|
|delete_cache($uri = '')|删除缓存|
|set_cache_header($last_modified, $expiration)|设置缓存相关的header|

**构造函数__construct()**
```
public function __construct()
{
    //获取php的ini对压缩输出的配置
   $this->_zlib_oc = (bool) ini_get('zlib.output_compression');
   //当php环境没有开启gzip压缩，且配置文件设置compress_output为Ture，安装了zlib扩展
       //将属性_compress_output设置为True，通常情况下是启用服务器压缩而关闭程序本身的压缩功能
   $this->_compress_output = (
      $this->_zlib_oc === FALSE
      && config_item('compress_output') === TRUE
      && extension_loaded('zlib')
   );

   isset(self::$func_overload) OR self::$func_overload = (extension_loaded('mbstring') && ini_get('mbstring.func_overload'));

   //获取mimes的类型
   $this->mimes =& get_mimes();

   log_message('info', 'Output Class Initialized');
}
```
**把输出显示在浏览器_display($output = '')**
```
public function _display($output = '')
{
   // Note:我们实用load_class()而不直接用$CI =& get_instance()
   // 因为有时候本方法是被缓存机制调用的，这时候$CI超级对象还无法使用
   $BM =& load_class('Benchmark', 'core');
   $CFG =& load_class('Config', 'core');

   //如果可能的话，获取超级对象$CI
   if (class_exists('CI_Controller', FALSE))
   {
      $CI =& get_instance();
   }

   //为属性$output赋值
   if ($output === '')
   {
      $output =& $this->final_output;
   }

   //当$CI对象存在时证明我们不是在从缓存输出数据，这时如果Controller没有自定义_output方法就需要写缓存
   if ($this->cache_expiration > 0 && isset($CI) && ! method_exists($CI, '_output'))
   {
      $this->_write_cache($output);
   }

   //解析出请求耗时和内存实用并替换输出中的伪变量数组
   $elapsed = $BM->elapsed_time('total_execution_time_start', 'total_execution_time_end');
   if ($this->parse_exec_vars === TRUE)
   {
      $memory    = round(memory_get_usage() / 1024 / 1024, 2).'MB';
      $output = str_replace(array('{elapsed_time}', '{memory_usage}'), array($elapsed, $memory), $output);
   }

   //根据需要压缩文件
   if (isset($CI) //通过$CI判断我们不是在读缓存文件，如果是文件可能已经压缩过了
      && $this->_compress_output === TRUE
      && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== FALSE)
   {
      ob_start('ob_gzhandler');
   }

   //如果headers不为空，则设置为返回到browser的header
   if (count($this->headers) > 0)
   {
      foreach ($this->headers as $header)
      {
         @header($header[0], $header[1]);
      }
   }

   //如果$CI变量不存在，我们是在从缓存读取数据，直接读取并退出即可
   if ( ! isset($CI))
   {
      if ($this->_compress_output === TRUE)
      {
         if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== FALSE)
         {
            header('Content-Encoding: gzip');
            header('Content-Length: '.self::strlen($output));
         }
         else
         {
            //User agent不支持gzip格式的数据，我们需要将缓存解压缩
            $output = gzinflate(self::substr($output, 10, -8));
         }
      }
           //直接将$output输出
      echo $output;
      log_message('info', 'Final output sent to browser');
      log_message('debug', 'Total execution time: '.$elapsed);
      return;
   }

   //如果需要收集用户profile则加载类库profile处理
   if ($this->enable_profiler === TRUE)
   {
      $CI->load->library('profiler');
      if ( ! empty($this->_profiler_sections))
      {
         $CI->profiler->set_sections($this->_profiler_sections);
      }

      //如果输出数据中包含关闭的</body>和</html>
      //我们会先移除，等到收集用户profile之后再加上
      $output = preg_replace('|</body>.*?</html>|is', '', $output, -1, $count).$CI->profiler->run();
      if ($count > 0)
      {
         $output .= '</body></html>';
      }
   }

   //如果controller中包含有_output方法，我们将$output交给它处理否则直接输出
   if (method_exists($CI, '_output'))
   {
      $CI->_output($output);
   }
   else
   {
      echo $output; //将其输出到浏览器!
   }

   log_message('info', 'Final output sent to browser');
   log_message('debug', 'Total execution time: '.$elapsed);
}
```
**写入缓存文件_write_cache($output)**
```
public function _write_cache($output)
{
   $CI =& get_instance();
   $path = $CI->config->item('cache_path');
   $cache_path = ($path === '') ? APPPATH.'cache/' : $path;
       //如果目录不存在活着不可写，记录日志直接返回
   if ( ! is_dir($cache_path) OR ! is_really_writable($cache_path))
   {
      log_message('error', 'Unable to write cache file: '.$cache_path);
      return;
   }

   $uri = $CI->config->item('base_url')
      .$CI->config->item('index_page')
      .$CI->uri->uri_string();

   //$cache_query_string为True表示缓存页考虑查询字符串的key
   if (($cache_query_string = $CI->config->item('cache_query_string')) && ! empty($_SERVER['QUERY_STRING']))
   {
      if (is_array($cache_query_string))
      {
         $uri .= '?'.http_build_query(array_intersect_key($_GET, array_flip($cache_query_string)));
      }
      else
      {
         $uri .= '?'.$_SERVER['QUERY_STRING'];
      }
   }
       //缓存路径其实就是$uri的md5，如果考虑了查询字符串的key单个页面可能页会出现多个缓存
   $cache_path .= md5($uri);
   //文件无法打开，记录错误日志，返回
   if ( ! $fp = @fopen($cache_path, 'w+b'))
   {
      log_message('error', 'Unable to write cache file: '.$cache_path);
      return;
   }
       //对文件加互斥锁失败，记录错误日志，关闭文件，返回
   if ( ! flock($fp, LOCK_EX))
   {
      log_message('error', 'Unable to secure a file lock for file at: '.$cache_path);
      fclose($fp);
      return;
   }

   //如果启用了输出压缩，则压缩缓存
   if ($this->_compress_output === TRUE)
   {
      $output = gzencode($output);

      if ($this->get_header('content-type') === NULL)
      {
         $this->set_content_type($this->mime_type);
      }
   }

   $expire = time() + ($this->cache_expiration * 60);

   //缓存包括$cache_info和$output两部分，$cache_info中保存了过期时间和headers
   $cache_info = serialize(array(
      'expire'   => $expire,
      'headers'  => $this->headers
   ));

   //缓存文件的组成$cache_info + ENDCI---> + $output
   $output = $cache_info.'ENDCI--->'.$output;

   for ($written = 0, $length = self::strlen($output); $written < $length; $written += $result)
   {
       //缓存内容写入文件
      if (($result = fwrite($fp, self::substr($output, $written))) === FALSE)
      {
         break;
      }
   }

   //释放文件锁关闭文件
   flock($fp, LOCK_UN);
   fclose($fp);

   if ( ! is_int($result))
   {
      @unlink($cache_path);
      log_message('error', 'Unable to write the complete cache content at: '.$cache_path);
      return;
   }

   chmod($cache_path, 0640);
   log_message('debug', 'Cache file written: '.$cache_path);

   //设置HTTP缓存控制相关的headers，并发送到浏览器便于检测cache是否过期
   $this->set_cache_header($_SERVER['REQUEST_TIME'], $expire);
}
```
**从缓存读取内容显示在浏览器_display_cache(&$CFG, &$URI)**
```
public function _display_cache(&$CFG, &$URI)
{
   $cache_path = ($CFG->item('cache_path') === '') ? APPPATH.'cache/' : $CFG->item('cache_path');

   //缓存文件是全URI的MD5哈希，这一步构建出全URI
   $uri = $CFG->item('base_url').$CFG->item('index_page').$URI->uri_string;
       //如果缓存文件考虑了查询字符串的key的影响，需要将其拼接在当前uri中
   if (($cache_query_string = $CFG->item('cache_query_string')) && ! empty($_SERVER['QUERY_STRING']))
   {
      if (is_array($cache_query_string))
      {
         $uri .= '?'.http_build_query(array_intersect_key($_GET, array_flip($cache_query_string)));
      }
      else
      {
         $uri .= '?'.$_SERVER['QUERY_STRING'];
      }
   }

   $filepath = $cache_path.md5($uri);
       //缓存文件不存在，直接返回false
   if ( ! file_exists($filepath) OR ! $fp = @fopen($filepath, 'rb'))
   {
      return FALSE;
   }
       //对文件加共享锁【读锁】
   flock($fp, LOCK_SH);
       //读取文件到$cache并解锁关闭文件
   $cache = (filesize($filepath) > 0) ? fread($fp, filesize($filepath)) : '';

   flock($fp, LOCK_UN);
   fclose($fp);

   //获取到缓存中真正的输出数据的那部分，正则匹配失败直接返回false
   if ( ! preg_match('/^(.*)ENDCI--->/', $cache, $match))
   {
      return FALSE;
   }

   $cache_info = unserialize($match[1]);
   $expire = $cache_info['expire'];

   $last_modified = filemtime($filepath);

   //如果缓存已经过期删除缓存文件，返回false表示无缓存
   if ($_SERVER['REQUEST_TIME'] >= $expire && is_really_writable($cache_path))
   {
      @unlink($filepath);
      log_message('debug', 'Cache file has expired. File deleted.');
      return FALSE;
   }

   //设置HTTP的header中与cache控制相关的属性值
   $this->set_cache_header($last_modified, $expire);

   //从缓存文件中写到headers中
   foreach ($cache_info['headers'] as $header)
   {
      $this->set_header($header[0], $header[1]);
   }

   //用_display方法将缓存数据展示到页面
   $this->_display(self::substr($cache, self::strlen($match[0])));
   log_message('debug', 'Cache file is current. Sending it to browser.');
   return TRUE;
}
```
**删除缓存文件delete_cache($uri = '')**
```
public function delete_cache($uri = '')
{
    //获取缓存保存路径
   $CI =& get_instance();
   $cache_path = $CI->config->item('cache_path');
   if ($cache_path === '')
   {
      $cache_path = APPPATH.'cache/';
   }

   if ( ! is_dir($cache_path))
   {
      log_message('error', 'Unable to find cache path: '.$cache_path);
      return FALSE;
   }

   if (empty($uri))
   {
      $uri = $CI->uri->uri_string();

      //缓存考虑查询字符串情况下，拼接$uri
      if (($cache_query_string = $CI->config->item('cache_query_string')) && ! empty($_SERVER['QUERY_STRING']))
      {
         if (is_array($cache_query_string))
         {
            $uri .= '?'.http_build_query(array_intersect_key($_GET, array_flip($cache_query_string)));
         }
         else
         {
            $uri .= '?'.$_SERVER['QUERY_STRING'];
         }
      }
   }
   //计算缓存路径的md5
   $cache_path .= md5($CI->config->item('base_url').$CI->config->item('index_page').ltrim($uri, '/'));
   //删除缓存文件
   if ( ! @unlink($cache_path))
   {
      log_message('error', 'Unable to delete cache file for '.$uri);
      return FALSE;
   }

   return TRUE;
}
```
