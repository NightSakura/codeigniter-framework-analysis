# 加载器类Loader.php
加载器是CodeIgniter用来加载library、package、helper、model、config等文件的方法，也可谓是CodeIgniter的核心文件，下面我们来分析下CI框架的装载器Loader.php文件的源码
## 属性概览
源码中额外注释了Loader类的属性都是自动进行赋值的，不要手动赋值搞乱了他们，具体的属性列表如下所示，主要是包含框架当前加载的各类文件的目录和加载情况，为了加载器进行正常工作提供基础。

|属性名称|注释|
|:-----:|:----:|
|protected $_ci_ob_level|缓冲机制的嵌套级别|
|protected $_ci_view_paths = array(VIEWPATH => TRUE)|view文件的地址目录|
|protected $_ci_library_paths =  array(APPPATH, BASEPATH)|library文件的地址目录|
|protected $_ci_model_paths =    array(APPPATH)|model文件的地址目录|
|protected $_ci_helper_paths =   array(APPPATH, BASEPATH)|helper文件的地址目录|
|protected $_ci_cached_vars =    array()|已缓存的变量|
|protected $_ci_classes =    array();|已加载的class|
|protected $_ci_models = array();|已加载的model|
|protected $_ci_helpers =    array()|已加载的helper|
|protected $_ci_varmap =    array('unit_test' => 'unit','user_agent' => 'agent');|类名的映射集合|

## 方法概览
|方法名称|注释|
|:-----:|:----:|
|__construct()|构造函数|
|initialize()|初始化函数|
|_ci_autoloader()|自动加载函数|
|driver()|加载driver文件|
|library()|加载library文件|
|_ci_load_library()|内部加载library的方法|
|||
|||

**构造函数__construct()**

构造函数主要完成的是属性的值的初始化，具体为初始化加载器类的_ci_ob_level和_ci_classes变量。
```php
public function __construct()
{
   $this->_ci_ob_level = ob_get_level();
   $this->_ci_classes =& is_loaded();

   log_message('info', 'Loader Class Initialized');
}
```

**初始化函数initialize()**

这个函数在控制器Controller的构造函数中被调用，因此可知控制器实例化完成之后就可以访问加载器加载的任何类了，所以是一个超级对象。
```php
public function initialize()
{
   $this->_ci_autoloader();
}
```

**自动加载函数_ci_autoloader()**

加载器类内部的自动加载函数，分别按顺序完成各类文件的加载。
```php
protected function _ci_autoloader()
{
    //获取autoload.php文件，若为找到文件直接返回
   if (file_exists(APPPATH.'config/autoload.php'))
   {
      include(APPPATH.'config/autoload.php');
   }

   if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/autoload.php'))
   {
      include(APPPATH.'config/'.ENVIRONMENT.'/autoload.php');
   }

   if ( ! isset($autoload))
   {
      return;
   }
   //添加packages所在的目录
   if (isset($autoload['packages']))
   {
      foreach ($autoload['packages'] as $package_path)
      {
         $this->add_package_path($package_path);
      }
   }

   //加载custom config文件-写入到$config变量中
   if (count($autoload['config']) > 0)
   {
      foreach ($autoload['config'] as $val)
      {
         $this->config($val);
      }
   }

   //自动加载helpers和languages
   foreach (array('helper', 'language') as $type)
   {
      if (isset($autoload[$type]) && count($autoload[$type]) > 0)
      {
         $this->$type($autoload[$type]);
      }
   }

   //自动加载drivers
   if (isset($autoload['drivers']))
   {
      $this->driver($autoload['drivers']);
   }

   //加载libraries
   if (isset($autoload['libraries']) && count($autoload['libraries']) > 0)
   {
      //先加载数据库类，在加载其他libraries
      if (in_array('database', $autoload['libraries']))
      {
         $this->database();
         $autoload['libraries'] = array_diff($autoload['libraries'], array('database'));
      }
      $this->library($autoload['libraries']);
   }

   //自动加载models
   if (isset($autoload['model']))
   {
      $this->model($autoload['model']);
   }
}
```

**加载driver文件 driver()**
```php
public function driver($library, $params = NULL, $object_name = NULL)
{
   if (is_array($library))
   {
      foreach ($library as $key => $value)
      {
         if (is_int($key))
         {
            $this->driver($value, $params);
         }
         else
         {
            $this->driver($key, $params, $value);
         }
      }

      return $this;
   }
   elseif (empty($library))
   {
      return FALSE;
   }

   if ( ! class_exists('CI_Driver_Library', FALSE))
   {
      //基础类尚未加载，保证CI_Driver_Library类可用
      require BASEPATH.'libraries/Driver.php';
   }

   //由于Drivers归属于libraries的次级目录，目录和文件名相同以此区分其他libraries
   if ( ! strpos($library, '/'))
   {
      $library = ucfirst($library).'/'.$library;
   }

   return $this->library($library, $params, $object_name);
}
```

**加载library文件 library()**
```php
public function library($library, $params = NULL, $object_name = NULL)
{
   if (empty($library))
   {
      return $this;
   }
   elseif (is_array($library))
   {
      foreach ($library as $key => $value)
      {
         if (is_int($key))
         {
            $this->library($value, $params);
         }
         else
         {
            $this->library($key, $params, $value);
         }
      }

      return $this;
   }

   if ($params !== NULL && ! is_array($params))
   {
      $params = NULL;
   }
   $this->_ci_load_library($library, $params, $object_name);
   return $this;
}
```

**内部加载library的方法 _ci_load_library()**
```php
protected function _ci_load_library($class, $params = NULL, $object_name = NULL)
{
   //获取trim之后的类名称：去掉斜线之后的名称可能包含路径
    $class = str_replace('.php', '', trim($class, '/'));

   //类名称中包含路径名称吗
   if (($last_slash = strrpos($class, '/')) !== FALSE)
   {
      //提取路径dir
      $subdir = substr($class, 0, ++$last_slash);

      //获取类名称
      $class = substr($class, $last_slash);
   }
   else
   {
      $subdir = '';
   }

   $class = ucfirst($class);

   //默认寻找BASEPATH路径，使用_ci_load_stock_library方法加载CI_开头的library 
   if (file_exists(BASEPATH.'libraries/'.$subdir.$class.'.php'))
   {
      return $this->_ci_load_stock_library($class, $subdir, $params, $object_name);
   }

   // Safety:判断上述方法是否已经加载了该类
   if (class_exists($class, FALSE))
   {
       //配置类和映射的关系
      $property = $object_name;
      if (empty($property))
      {
         $property = strtolower($class);
         isset($this->_ci_varmap[$property]) && $property = $this->_ci_varmap[$property];
      }

      //取$CI检查是否已经加载了该类，如果加载了也不会再重复加载
      $CI =& get_instance();
      if (isset($CI->$property))
      {
         log_message('debug', $class.' class already loaded. Second attempt ignored.');
         return;
      }
      //加载类library
      return $this->_ci_init_library($class, '', $params, $object_name);
   }

   //遍历其他的library路径尝试寻找和加载请求的类
   foreach ($this->_ci_library_paths as $path)
   {
      // BASEPATH 已经寻找过了
      if ($path === BASEPATH)
      {
         continue;
      }

      $filepath = $path.'libraries/'.$subdir.$class.'.php';
      //其他路径下文件不存在的话也继续寻找文件
      if ( ! file_exists($filepath))
      {
         continue;
      }

      //找到文件后加载文件
      include_once($filepath);
      return $this->_ci_init_library($class, '', $params, $object_name);
   }

   //最后的舱室：也许library在子目录中，但是没有明确提出
   if ($subdir === '')
   {
      return $this->_ci_load_library($class.'/'.$class, $params, $object_name);
   }

   //走到这一步表示我们没有找到请求的类，返回错误信息
   log_message('error', 'Unable to load the requested class: '.$class);
   show_error('Unable to load the requested class: '.$class);
}
```

****
```php
```

****
```php
```
