# 安全类Security.php
安全类包含了一些方法，用于安全的处理输入数据，帮助你创建一个安全的应用，主要是用来处理XSS过滤和CSRF防护。
根据用户的配置项，在输入类中获取用户输入数据的流程中完成安全处理，建议了解相关的安全问题之后会更加容易理解CodeIgniter的安全防护策略。
* XSS过滤

使用 XSS 过滤器过滤数据可以使用 xss_clean() 方法`$data = $this->security->xss_clean($data);`

它还有一个可选的第二个参数 is_image ，允许此函数对图片进行检测以发现那些潜在的 XSS 攻击, 这对于保证文件上传的安全非常有用。当此参数被设置为 TRUE 时， 函数的返回值将是一个布尔值，而不是一个修改过的字符串。如果图片是安全的则返回 TRUE ， 相反, 如果图片中包含有潜在的、可能会被浏览器尝试运行的恶意信息，函数将返回 FALSE.
* 跨站请求伪造CSRF
通过配置项`$config['csrf_protection'] = TRUE;`来开启跨站请求伪造的防护。

主要是通过教验客户端的Token来实现csrf_verrify的。

令牌（tokens）默认会在每一次提交时重新生成，或者你也可以设置成在 CSRF cookie 的生命周期内一直有效。默认情况下令牌重新生成提供了更严格的安全机制，但可能会对 可用性带来一定的影响，因为令牌很可能会变得失效（例如使用浏览器的返回前进按钮、 使用多窗口或多标签页浏览、异步调用等等）。你可以修改下面这个参数来改变这一点。

另外还可以添加一个 URI 的白名单，跳过 CSRF 保护（例如某个 API 接口希望接受 原始的 POST 数据），将这些 URI 添加到 'csrf_exclude_uris' 配置参数中:`$config['csrf_exclude_uris'] = array('api/person/add');`

## 属性概览

## 方法概览

**构造函数__construct()**
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
**XSS清理xss_clean()**
```php
public function xss_clean($str, $is_image = FALSE)
{
   //数组类型循环调用本方法
   if (is_array($str))
   {
      foreach ($str as $key => &$value)
      {
         $str[$key] = $this->xss_clean($value);
      }

      return $str;
   }

   //去除控制字符
   $str = remove_invisible_characters($str);

   /* 匹配到%就进行URL Decode
    * 防止类似<a href="http://%77%77%77%2E%67%6F%6F%67%6C%65%2E%63%6F%6D">Google</a>的提交
    * Note:使用rawurldecode()函数，不会移除加号
    */
   if (stripos($str, '%') !== false)
   {
      do
      {
         $oldstr = $str;
         $str = rawurldecode($str);
         $str = preg_replace_callback('#%(?:\s*[0-9a-f]){2,}#i', array($this, '_urldecodespaces'), $str);
      }
      while ($oldstr !== $str);
      unset($oldstr);
   }


   //将character转换城ASCII
   $str = preg_replace_callback("/[^a-z0-9>]+[a-z0-9]+=([\'\"]).*?\\1/si", array($this, '_convert_attribute'), $str);
   $str = preg_replace_callback('/<\w+.*/si', array($this, '_decode_entity'), $str);

   //再次去除控制字符!
   $str = remove_invisible_characters($str);

   /*
    * 转换tabs为空字符' '
    * 应对ja vascript这种情况，之后处理空字符
    */
   $str = str_replace("\t", ' ', $str);

   $converted_string = $str;

   //删除不允许的字符
   $str = $this->_do_never_allowed($str);


       //Makes PHP tags safe
   if ($is_image === TRUE)
   {
      //替换图片中的PHP开始标签-只处理长标签
      $str = preg_replace('/<\?(php)/i', '&lt;?\\1', $str);
   }
   else
   {
      $str = str_replace(array('<?', '?'.'>'), array('&lt;?', '?&gt;'), $str);
   }

   /* 处理被拆分的关键词
    * This corrects words like:  j a v a s c r i p t
    * 处理之后让这些词返回正确的状态
    */
   $words = array(
      'javascript', 'expression', 'vbscript', 'jscript', 'wscript',
      'vbs', 'script', 'base64', 'applet', 'alert', 'document',
      'write', 'cookie', 'window', 'confirm', 'prompt', 'eval'
   );

   foreach ($words as $word)
   {
      $word = implode('\s*', str_split($word)).'\s*';

      // 只把空格后边不是完整单词的情况进行合并
      // 这样避免了像"dealer to"被合并城"dealerto"的情况
      $str = preg_replace_callback('#('.substr($word, 0, -3).')(\W)#is', array($this, '_compact_exploded_words'), $str);
   }

   /*
    * 删除在link或者img标签中不允许的Javascript
    * We used to do some version comparisons and use of stripos(),
    * but it is dog slow compared to these simplified non-capturing
    * preg_match(), especially if the pattern exists in the string
    *
    * Note: It was reported that not only space characters, but all in
    * the following pattern can be parsed as separators between a tag name
    * and its attributes: [\d\s"\'`;,\/\=\(\x00\x0B\x09\x0C]
    * ... however, remove_invisible_characters() above already strips the
    * hex-encoded ones, so we'll skip them below.
    */
   do
   {
      $original = $str;

      if (preg_match('/<a/i', $str))
      {
         $str = preg_replace_callback('#<a(?:rea)?[^a-z0-9>]+([^>]*?)(?:>|$)#si', array($this, '_js_link_removal'), $str);
      }

      if (preg_match('/<img/i', $str))
      {
         $str = preg_replace_callback('#<img[^a-z0-9]+([^>]*?)(?:\s?/?>|$)#si', array($this, '_js_img_removal'), $str);
      }

      if (preg_match('/script|xss/i', $str))
      {
         $str = preg_replace('#</*(?:script|xss).*?>#si', '[removed]', $str);
      }
   }
   while ($original !== $str);
   unset($original);

   /*
    * 净化HTML元素
    * So this: <blink>
    * Becomes: &lt;blink&gt;
    */
   $pattern = '#'
      .'<((?<slash>/*\s*)((?<tagName>[a-z0-9]+)(?=[^a-z0-9]|$)|.+)' // tag start and name, followed by a non-tag character
      .'[^\s\042\047a-z0-9>/=]*' // a valid attribute character immediately after the tag would count as a separator
      // optional attributes
      .'(?<attributes>(?:[\s\042\047/=]*' // non-attribute characters, excluding > (tag close) for obvious reasons
      .'[^\s\042\047>/=]+' // attribute characters
      // optional attribute-value
         .'(?:\s*=' // attribute-value separator
            .'(?:[^\s\042\047=><`]+|\s*\042[^\042]*\042|\s*\047[^\047]*\047|\s*(?U:[^\s\042\047=><`]*))' // single, double or non-quoted value
         .')?' // end optional attribute-value group
      .')*)' // end optional attributes group
      .'[^>]*)(?<closeTag>\>)?#isS';

   do
   {
      $old_str = $str;
      $str = preg_replace_callback($pattern, array($this, '_sanitize_naughty_html'), $str);
   }
   while ($old_str !== $str);
   unset($old_str);

   /*
    * Sanitize naughty scripting elements
    *
    * Similar to above, only instead of looking for
    * tags it looks for PHP and JavaScript commands
    * that are disallowed. Rather than removing the
    * code, it simply converts the parenthesis to entities
    * rendering the code un-executable.
    *
    * For example:    eval('some code')
    * Becomes:    eval&#40;'some code'&#41;
    */
   $str = preg_replace(
      '#(alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)#si',
      '\\1\\2&#40;\\3&#41;',
      $str
   );

   // Same thing, but for "tag functions" (e.g. eval`some code`)
   // See https://github.com/bcit-ci/CodeIgniter/issues/5420
   $str = preg_replace(
      '#(alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)`(.*?)`#si',
      '\\1\\2&#96;\\3&#96;',
      $str
   );

    //最终清理，额外的防护措施，防止以上步骤中漏掉的元素
   $str = $this->_do_never_allowed($str);

   /*
    * Images are Handled in a Special Way
    * - Essentially, we want to know that after all of the character
    * conversion is done whether any unwanted, likely XSS, code was found.
    * If not, we return TRUE, as the image is clean.
    * However, if the string post-conversion does not matched the
    * string post-removal of XSS, then it fails, as there was unwanted XSS
    * code found and removed/changed during processing.
    */
   if ($is_image === TRUE)
   {
      return ($str === $converted_string);
   }

   return $str;
}
```

**CSRF验证csrf_verify()**
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
