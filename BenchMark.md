# 基准类BenchMark.php

## 使用方式
CI框架提供了一个叫Benchmark的基准测试类，用于计算两个标记点之间的时间差和当时的内存占用,一般用于开发过程中计算页面加载时间，方法执行时间等中会使用到，便于做性能测试分析代码的执行效率。

使用流程如下：1、标记一个起始点；2、标记一个结束点；3、使用 elapsed_time 函数计算时间差。
```php
$this->benchmark->mark('code_start');  
//......  
$this->benchmark->mark('code_end');  
echo $this->benchmark->elapsed_time('code_start', 'code_end');  
```
基准类的使用方式比较容易理解，实现方式也比较简单，代码分析如下。

## 代码分析
CI_Benchmark没有构造函数，主要的属性和方法如下所示，$marker保存了各个时间点的Unix时间戳数据。

mark函数用于在应用中标记一个点，记录其Unix时间戳。
```php
public function mark($name)
{
   $this->marker[$name] = microtime(TRUE);
}
}
```

elapsed_time()也比较容易理解，计算两个标记点的时间差，其中如果$point2不存在则默认为当前时间，$point1不存在返回空字符。
```php
public function elapsed_time($point1 = '', $point2 = '', $decimals = 4)
{
   if ($point1 === '')
   {
      return '{elapsed_time}';
   }

   if ( ! isset($this->marker[$point1]))
   {
      return '';
   }

   if ( ! isset($this->marker[$point2]))
   {
      $this->marker[$point2] = microtime(TRUE);
   }

   return number_format($this->marker[$point2] - $this->marker[$point1], $decimals);
}
```

另外这个函数可以计算并返回当前使用的内存
```php
public function memory_usage()
{
   return '{memory_usage}';
}
```
