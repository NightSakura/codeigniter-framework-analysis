# 输入类input.php


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
**_sanitize_globals()**
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
