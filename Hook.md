# 扩展框架核心：钩子类Hooks.php

CodeIgniter框架提供了钩子的方法来修改框架的内部运作流程，而无需修改核心文件。CodeIgniter 的运行遵循着一个特定的流程具体可参见前文的框架执行流程分析。通过Hook特性你可能希望在执行流程中的某些阶段添加一些动作，也可以简单理解为主流程执行过程中预留了交给开发者实现的API。钩子的实现在文件`Hooks.php`文件中，类名为CI_Hooks。

## 用户配置
用户可以在配置文件中进行钩子特性的配置，相关的配置项描述如下。

**启用钩子**

钩子特性需要在配置文件中进行设置，启用钩子特性需要在`application/config/config.php`文件中设置参数为：
`$config['enable_hooks'] = TRUE`

**挂钩点列表**

在上一篇文章[启动器CodeIgniter.php](CodeIgniter.md)中，我们介绍了CodeIgniter框架的执行过程，应该已经了解了挂钩点的意思。下面我们进一步解释每个挂钩点的作用。
* pre_system 在系统执行的早期调用，这个时候只有基准测试类和钩子类被加载了，还没有执行到路由或其他的流程。
* pre_controller 在你的控制器调用之前执行，所有的基础类都已加载，路由和安全检查也已经完成。
* post_controller_constructor 在你的控制器实例化之后立即执行，控制器的任何方法都还尚未调用。
* post_controller 在你的控制器完全运行结束时执行。
* display_override 覆盖 _display() 方法，该方法用于在系统执行结束时向浏览器发送最终的页面结果。 这可以让你有自己的显示页面的方法。注意你可能需要使用`$this->CI =& get_instance()`方法来获取CI超级对象，以及使用`$this->CI->output->get_output()`方法来 获取最终的显示数据。
* cache_override 使用你自己的方法来替代 输出类 中的`_display_cache()`方法，这让你有自己的缓存显示机制。
* post_system 在最终的页面发送到浏览器之后、在系统的最后期被调用。

**定义钩子特性**

定义钩子特性需要在`application/config/hooks.php`文件中定义，定义的内容保存在$hook数组中，设置的格式如：
```php
$hook['pre_controller'] = array(
    'class' => 'ClassName',
    'function' => 'FunctionName',
    'filename' => 'FileName',
    'filepath' => 'FilePath',
    'params' => 'Array('argv1','argv2')'
);
```
如果需要在同一个挂钩点添加多个脚本，则需要将钩子数组定义为二维数组即可。

至此，我们已经清楚了钩子的开启和配置方法，了解了CodeIgniter框架支持的钩子的配置方式，下面我们开始分析CI_hooks类的代码，进一步理解CodeIgniter是如何完成这些内容的。
## 属性概览

|属性名称|注释|
|:----------:|:-----:|
|public $enabled = FALSE|钩子特性是否开启的标志|
|public $hooks = array()|获取并保存定义的钩子特性|
|protected $_objects = array()|保存执行过的钩子类的实例|
|protected $_in_progess = FALSE|确定钩子程序的正常执行防止死循环|

## 方法概览
钩子类的方法不错，钩子类在构造函数中完成了配置文件的读取和属性$hooks的初始化，call_hook()是提供给用户调用钩子类的接口，_run_hook()是钩子类的实际执行方法

|方法名称|注释|
|:----------:|:-----:|
|__construct()|钩子类的构造函数|
|call_hook($which='')|调用制定名称的钩子类|
|_run_hook($data)|钩子的实际执行方法，被call_hook方法调用|

**构造函数__construct**

构造函数主要是根据用户配置确定是否开启钩子特性，并在配置文件中寻找用户配置的钩子特性，如果没有定义任何钩子则返回，否则将更新属性$hooks的值和将钩子启动标志位$enabled置为True。
```php
public function __construct()
{
   $CFG =& load_class('Config', 'core');
   log_message('info', 'Hooks Class Initialized');
   //如果用户配置中没有开启钩子特性，直接返回
   if ($CFG->item('enable_hooks') === FALSE)
   {
      return;
   }
   //寻找并获取用户配置文件中定义的钩子特性$hook
   if (file_exists(APPPATH.'config/hooks.php'))
   {
      include(APPPATH.'config/hooks.php');
   }
   if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/hooks.php'))
   {
      include(APPPATH.'config/'.ENVIRONMENT.'/hooks.php');
   }
   //如果用户没有定义任何钩子特性，直接返回
   if ( ! isset($hook) OR ! is_array($hook))
   {
      return;
   }
   //设置类属性$hooks和$enabled的值
   $this->hooks =& $hook;
   $this->enabled = TRUE;
}
```

**调用钩子函数call_hook()**

call_hook方法就是用户在CodeIgniter框架中中调用钩子的方法，根据上文的介绍调用钩子是通过挂钩点来进行的，call_hook()主要判断是否在一个挂钩点定义了多个钩子，如果是需要循环分别处理，钩子类文件的调用执行是通过_run_hook()方法完成的。
```php
public function call_hook($which = '')
{
    //没有开启钩子特性或钩子未定义直接返回FALSE
   if ( ! $this->enabled OR ! isset($this->hooks[$which]))
   {
      return FALSE;
   }
   //判断是否在这个挂钩点定义了多个钩子，并分别进行处理
   if (is_array($this->hooks[$which]) && ! isset($this->hooks[$which]['function']))
   {
      foreach ($this->hooks[$which] as $val)
      {
         $this->_run_hook($val);
      }
   }
   else
   {
      $this->_run_hook($this->hooks[$which]);
   }
   return TRUE;
}
```

**运行钩子函数_run_hook()**

实际运行钩子函数里比较关键的是利用in_progess属性保证钩子的执行遇到循环调用或错误调用时能够跳出函数，并且对定义类和方法以及只定义方法的情况都尝试进行调用，定义类和方法时会将类保存在属性_objects[]数组中。
```php
protected function _run_hook($data)
{
   //判断钩子是否可调用，如无误根据参数位置直接执行Object->Function
   if (is_callable($data))
   {
      is_array($data)
         ? $data[0]->{$data[1]}()
         : $data();
      return TRUE;
   }
   elseif ( ! is_array($data))
   {
      return FALSE;
   }
   // -----------------------------------
   // 安全处理：防止钩子循环调用或错误调用
   // -----------------------------------

   // 如果钩子内部调用的自己那就形成循环调用，其他错误调用也需要跳出
   if ($this->_in_progress === TRUE)
   {
      return;
   }

   // -----------------------------------
   // 设置文件的路径
   // -----------------------------------
   if ( ! isset($data['filepath'], $data['filename']))
   {
      return FALSE;
   }
   $filepath = APPPATH.$data['filepath'].'/'.$data['filename'];
   //文件不存在的话直接返回False
   if ( ! file_exists($filepath))
   {
      return FALSE;
   }
   // 取得类和方法的名称和参数列表
   $class    = empty($data['class']) ? FALSE : $data['class'];
   $function  = empty($data['function']) ? FALSE : $data['function'];
   $params       = isset($data['params']) ? $data['params'] : '';

   if (empty($function))
   {
      return FALSE;
   }
   $this->_in_progress = TRUE;
   // 调用请求的类和方法
   if ($class !== FALSE)
   {
      // 如果$class在属性_objects[]中保存直接调用方法
      if (isset($this->_objects[$class]))
      {
         if (method_exists($this->_objects[$class], $function))
         {
            $this->_objects[$class]->$function($params);
         }
         else
         {
            return $this->_in_progress = FALSE;
         }
      }
      else
      {
         //如果加载类失败直接返回置in_progress为False
         class_exists($class, FALSE) OR require_once($filepath);
         if ( ! class_exists($class, FALSE) OR ! method_exists($class, $function))
         {
            return $this->_in_progress = FALSE;
         }
         // 将类的实例保存在_objects[]中然后执行方法
         $this->_objects[$class] = new $class();
         $this->_objects[$class]->$function($params);
      }
   }
   else
   {
      //如果只在文件中定义了方法也同样尝试调用
      function_exists($function) OR require_once($filepath);
      if ( ! function_exists($function))
      {
         return $this->_in_progress = FALSE;
      }
      $function($params);
   }
   //执行到此处表示未成环，调用成功置in_progess为False
   $this->_in_progress = FALSE;
   return TRUE;
}
```
