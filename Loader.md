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
|||
|||
|||

**构造函数__construct()**
```php
public function __construct()
{
   $this->_ci_ob_level = ob_get_level();
   $this->_ci_classes =& is_loaded();

   log_message('info', 'Loader Class Initialized');
}
```

**初始化函数initialize()**
```php
public function initialize()
{
   $this->_ci_autoloader();
}
```

**自动加载函数_ci_autoloader()**
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

   //加载custom config文件-如果用户定义了
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

**加载driver文件driver()**
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
      //基础类尚未实力话，保证CI_Driver_Library类可用
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

**加载library文件library()**
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

****
```php
```

****
```php
```

****
```php
```
