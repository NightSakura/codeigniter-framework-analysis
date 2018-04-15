# CodeIgniter3.1.8源码分析

CodeIgniter 是一套给 PHP 网站开发者使用的应用程序开发框架和工具包。 它的目标是让你能够更快速的开发，它提供了日常任务中所需的大量类库， 以及简单的接口和逻辑结构。这是一个性能小巧且性能十分出色的框架。

目前CodeIgniter的release版本是3.1.x，CodeIgniter4还没有稳定版本，本人阅读的代码的具体版本是3.1.8。现已将注释的代码上传到版本库src文件夹下，该版本的代码除了添加中文注释，和原版本相比有部分串行没有修改任何代码内容。框架源码也可以从官网或者git下载代码，主要代码目录是system和application。
阅读和分析的内容如下所示。

## 核心类库
* [代码结构和执行流程概览](SUMMARY.md)
* [框架启动器CodeIgniter.php](CodeIgniter.md)
* [扩展框架核心：钩子类Hooks.php](Hook.md)
* [地址解析类URI.php](Uri.md)
* [路由类Router.php](Router.md)
* [输出类Output.php](Output.md)
* [安全类URI.php](URI.md)
* [输入类Input.php](Input.md)
* [控制器类Controller.php](Controller.md)
* [加载器类Loader.php](Loader.md)
* [模型类Model.php](Model.md)

## 类库

## 辅助函数
