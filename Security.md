# 安全类Security.php
安全类包含了一些方法，用于安全的处理输入数据，帮助你创建一个安全的应用，主要是用来处理XSS过滤和CSRF防护。
根据用户的配置项，在输入类中获取用户输入数据的流程中完成安全处理，建议了解相关的安全问题之后会更加容易理解CodeIgniter的安全防护策略。

## 属性概览

## 方法概览

****
```php
public function __construct()
{
   //判断是否开启配置CSRF
   if (config_item('csrf_protection'))
   {
      // CSRF config
      foreach (array('csrf_expire', 'csrf_token_name', 'csrf_cookie_name') as $key)
      {
         if (NULL !== ($val = config_item($key)))
         {
            $this->{'_'.$key} = $val;
         }
      }

      //加载配置项中自定义的cookie的csrf前缀
      if ($cookie_prefix = config_item('cookie_prefix'))
      {
         $this->_csrf_cookie_name = $cookie_prefix.$this->_csrf_cookie_name;
      }

      //设置CSRF哈希
      $this->_csrf_set_hash();
   }

   $this->charset = strtoupper(config_item('charset'));

   log_message('info', 'Security Class Initialized');
}
```

****
```php
public function csrf_verify()
{
   //如果不是POST请求，会设置CSRF的cookie
   if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST')
   {
      return $this->csrf_set_cookie();
   }

   //检查URI是否被列入CSRF检查的白名单，如果匹配直接跳出检查
   if ($exclude_uris = config_item('csrf_exclude_uris'))
   {
      $uri = load_class('URI', 'core');
      foreach ($exclude_uris as $excluded)
      {
         if (preg_match('#^'.$excluded.'$#i'.(UTF8_ENABLED ? 'u' : ''), $uri->uri_string()))
         {
            return $this;
         }
      }
   }

   // Check CSRF token validity, but don't error on mismatch just yet - we'll want to regenerate
   $valid = isset($_POST[$this->_csrf_token_name], $_COOKIE[$this->_csrf_cookie_name])
      && hash_equals($_POST[$this->_csrf_token_name], $_COOKIE[$this->_csrf_cookie_name]);

   // We kill this since we're done and we don't want to pollute the _POST array
   unset($_POST[$this->_csrf_token_name]);

   // Regenerate on every submission?
   if (config_item('csrf_regenerate'))
   {
      // Nothing should last forever
      unset($_COOKIE[$this->_csrf_cookie_name]);
      $this->_csrf_hash = NULL;
   }

   $this->_csrf_set_hash();
   $this->csrf_set_cookie();

   if ($valid !== TRUE)
   {
      $this->csrf_show_error();
   }

   log_message('info', 'CSRF token verified');
   return $this;
}
```
