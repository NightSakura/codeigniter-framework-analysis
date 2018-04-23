# 输入类input.php
输入类主要有以下两个用途。
* 为了安全性，对输入数据进行预处理，预处理逻辑是根据用户的配置选择对应的处理方法。
* 提供了一些辅助方法来获取输入数据并处理，CodeIgniter 提供了几个辅助方法来从 POST、GET、COOKIE 和 SERVER 数组中获取数据。 使用这些方法来获取数据而不是直接访问数组`$_POST['key']`的最大好 处是这些方法会检查获取的数据是否存在，如果不存在则返回 NULL 。这使用起来将很方便， 你不再需要去检查数据是否存在 ，此外也能提高程序的可读性。
## 用户配置
和输入类相关的用户配置包括以下几项。

`$config['allow_get_array']`表示是否允许用户使用`$_GET`全局变量，如果设置为不允许，会在输入类构造函数处理中将`$_GET`清空。

`$config['global_xss_filtering']`表示是否开启XSS全局防御的标志位，如果设置为允许，则会对用户输入和Cookie的内容中进行XSS过滤。

`$config['csrf_protection']`表示是否开启CSRF防御，如果设置为允许，则会在对表单数据进行处理时进行CSRF方法的检查。

`$config['standardize_newlines']`表示是否标准化换行符，如果设置为允许，则会在对表单数据进行处理时用PHP_EOL代替数据中的换行符。

## 属性概览

## 方法概览

**构造函数__construct**
```php
public function __construct()
{
    //根据config的配置确定对应的属性值
   $this->_allow_get_array       = (config_item('allow_get_array') !== FALSE);
   $this->_enable_xss    = (config_item('global_xss_filtering') === TRUE);
   $this->_enable_csrf       = (config_item('csrf_protection') === TRUE);
   $this->_standardize_newlines   = (bool) config_item('standardize_newlines');

   $this->security =& load_class('Security', 'core');

   //如果需要Utf8则加载类
   if (UTF8_ENABLED === TRUE)
   {
      $this->uni =& load_class('Utf8', 'core');
   }

   //处理表单数据,$_GET,$_POST,$_COOKIE去掉不合要求的字符
   $this->_sanitize_globals();

   //如果开启了csrf检查且不是cli模式就进行csrf安全检查
   if ($this->_enable_csrf === TRUE && ! is_cli())
   {
      $this->security->csrf_verify();
   }

   log_message('info', 'Input Class Initialized');
}
```
**表单处理函数_sanitize_globals()**
```php
protected function _sanitize_globals()
{
   //如果$_GET不允许实用，将$_GET置为空串
   if ($this->_allow_get_array === FALSE)
   {
      $_GET = array();
   }
   elseif (is_array($_GET))
   {   //过滤$_GET的字符
      foreach ($_GET as $key => $val)
      {
         $_GET[$this->_clean_input_keys($key)] = $this->_clean_input_data($val);
      }
   }
   //对$_POST数据进行检查处理
   if (is_array($_POST))
   {
      foreach ($_POST as $key => $val)
      {
         $_POST[$this->_clean_input_keys($key)] = $this->_clean_input_data($val);
      }
   }
   //对$_COOKIE数据进行检查处理
   if (is_array($_COOKIE))
   {
      //取消特殊字符作为key的值，因为这些值一般是由服务端设置的
      //当出现这种情况的时候还应该警报'Disallowed Key Characters'
      unset(
         $_COOKIE['$Version'],
         $_COOKIE['$Path'],
         $_COOKIE['$Domain']
      );

      foreach ($_COOKIE as $key => $val)
      {
         if (($cookie_key = $this->_clean_input_keys($key)) !== FALSE)
         {
            $_COOKIE[$cookie_key] = $this->_clean_input_data($val);
         }
         else
         {
            unset($_COOKIE[$key]);
         }
      }
   }
   $_SERVER['PHP_SELF'] = strip_tags($_SERVER['PHP_SELF']);

   log_message('debug', 'Global POST, GET and COOKIE data sanitized');
}
```

**表单数据key值处理_clean_input_keys()**
```php
protected function _clean_input_keys($str, $fatal = TRUE)
{
    //如果$str中有不允许的字符串则根据$fatal取值返回false活着直接报503，exit
   if ( ! preg_match('/^[a-z0-9:_\/|-]+$/i', $str))
   {
      if ($fatal === TRUE)
      {
         return FALSE;
      }
      else
      {
         set_status_header(503);
         echo 'Disallowed Key Characters.';
         exit(7); // EXIT_USER_INPUT
      }
   }

   //如果需要utf8支持则调用UTF8的clean_string()方法处理并返回$str
   if (UTF8_ENABLED === TRUE)
   {
      return $this->uni->clean_string($str);
   }
  
   return $str;
}
```
**从表单取值的函数_fetch_from_array()**
```php
protected function _fetch_from_array(&$array, $index = NULL, $xss_clean = NULL)
{
   is_bool($xss_clean) OR $xss_clean = $this->_enable_xss;

   //如果$index是空，那么获取并输出$array中所有的键值对
   isset($index) OR $index = array_keys($array);

   //$index是数组是循环自身调用输出，允许一次获取多个值
   if (is_array($index))
   {
      $output = array();
      foreach ($index as $key)
      {
         $output[$key] = $this->_fetch_from_array($array, $key, $xss_clean);
      }

      return $output;
   }

   //如果能直接取到直接输出，能正则匹配
   if (isset($array[$index]))
   {
      $value = $array[$index];
   }
   elseif (($count = preg_match_all('/(?:^[^\[]+)|\[[^]]*\]/', $index, $matches)) > 1) //如果有数组注解符号
   {
      //从数组中匹配$key作为最终取到的值
      $value = $array;
      for ($i = 0; $i < $count; $i++)
      {
         $key = trim($matches[0][$i], '[]');
         if ($key === '') // Empty notation will return the value as array
         {
            break;
         }

         if (isset($value[$key]))
         {
            $value = $value[$key];
         }
         else
         {
            return NULL;
         }
      }
   }
   else
   {
      return NULL;
   }

    //如果开启了xss检查，输出xss_clean方法处理后的$value
   return ($xss_clean === TRUE)
      ? $this->security->xss_clean($value)
      : $value;
}
```
