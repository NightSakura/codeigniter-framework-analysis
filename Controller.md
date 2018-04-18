# 控制器类Controller.php
控制器是你整个应用的核心，因为它们决定了 HTTP 请求将被如何处理。 一个控制器就是一个类文件，控制器和方法是以一种能够和 URI 关联在一起的方式来命名的，CodeIgniter提供了`Controller.php`作为用户编写的控制器的基类，使用时需要继承这个类并完成各种方法的书写。下面先看看CI_Controller这个类的内容。

## 代码分析
CI_Controller只有一个私有的属性$instance,保存的是对CI实例的引用。

**构造函数**
在CodeIgniter里，CI通常被称作是超级对象。原因是在构造函数中，通过魔术方法获取了所有通过load_class()方法加载的类，包括的就是我们之前介绍的系统核心类。并且还通过load引入了加载类，并完成初始化。加载类是CodeIgniter中实现使用辅助函数和其他类库的关键组件并且还定义了有关显示和输出等内容，关于加载类的作用我们之后介绍。
```php
public function __construct()
{
   self::$instance =& $this;

   // Assign all the class objects that were instantiated by the
   // bootstrap file (CodeIgniter.php) to local class variables
   // so that CI can run as one big super object.
   foreach (is_loaded() as $var => $class)
   {
      $this->$var =& load_class($class);
   }
   //引入加载类并初始化
   $this->load =& load_class('Loader', 'core');
   $this->load->initialize();
   log_message('info', 'Controller Class Initialized');
}
```

**获取引用函数**
静态方法获取CI对象的引用。
```php
public static function &get_instance()
{
   return self::$instance;
}
```

## 其他使用说明
这部分内容可以通过阅读其他部分组件的代码发现，或者说是CodeIgniter中的约定，使用控制器类也是需要了解的。
**重映射方法**
URI 的第二段通常决定控制器的哪个方法被调用。CodeIgniter 允许你使用_remap()来重写该规则。
这部分内容可以通过阅读路由和地址解析的内容了解，如果控制器中存在名为_remap()的方法，那么直接将该方法作为URI对应的控制方法。这样设计的目的是为了让用户自己来实现该控制器内的路由规则，比如下面的方法定义。
```php
public function _remap($method)
{
    if ($method === 'some_method')
    {
        $this->$method();
    }
    else
    {
        $this->default_method();
    }
}
```
如果请求的URI中带有参数，也可以完成如下所示的函数定义。
```php
public function _remap($method, $params = array())
{
    $method = 'process_'.$method;
    if (method_exists($this, $method))
    {
        return call_user_func_array(array($this, $method), $params);
    }
    show_404();
}
```

**重输出方法**
CodeIgniter 有一个输出类，它可以自动的将最终数据发送到你的浏览器。 更多信息可以阅读 视图 和 输出类 页面。但是，有时候， 你可能希望对最终的数据进行某种方式的后处理，然后你自己手工发送到浏览器。CodeIgniter 允许你向你的控制器中添加一个_output()方法，该方法可以接受最终的输出数据。
通过阅读CodeIgniter输出时的逻辑可以发现这个约定，用户可以基于自己的逻辑实现输出，最简单的逻辑如下,参数$output是最终输出的内容。
```php
public function _output($output)
{
    echo $output;
}
```

