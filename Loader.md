# 加载器类Loader.php
加载器是CodeIgniter用来加载library、package、helper、model、config等文件的方法，也可谓是CodeIgniter的核心文件，下面我们来分析下CI框架的装载器Loader.php文件的源码
## 属性概览
源码中额外注释了Loader类的属性都是自动进行赋值的，不要手动赋值搞乱了他们，具体的属性列表如下所示，主要是包含框架当前加载的各类文件的目录和加载情况，为了加载器进行正常工作提供基础。

|属性名称|注释|
|:-----:|:----:|
|protected $_ci_ob_level||
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
|||
|||
|||
|||
|||
|||
