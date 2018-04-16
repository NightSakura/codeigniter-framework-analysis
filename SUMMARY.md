# 代码结构和框架运行流程

CodeIgniter 是一套给 PHP 网站开发者使用的应用程序开发框架和工具包。 它的目标是让你能够更快速的开发，它提供了日常任务中所需的大量类库， 以及简单的接口和逻辑结构。这是一个性能小巧且性能十分出色的框架，本文作为CodeIgniter源码分析的开篇，将介绍框架代码的组织结构，应用程序的流程图和从入口文件开始源码的执行过程，以便于后续的分析工作。
## 代码结构
目前CodeInniter的release版本是3.1.x，本人阅读的代码的具体版本是3.1.8，框架源码可以从官网或者git下载，代码的目录结构如下所示。

![](https://upload-images.jianshu.io/upload_images/8371576-e7f6c8b9f5aafa8c.png?imageMogr2/auto-orient/strip%7CimageView2/2/w/300)

CodeIgniter框架的主要文件目录是application和system，user_guide目录。

application目录顾名思义是应用的主目录，文件夹中包含应用相关配置或扩展类的模板，开发者使用框架进行应用开发时主要在本目录编写相应的配置以及逻辑处理方法。

system目录是框架的核心库、系统库和辅助函数库，其中核心库是框架运行流程的支撑代码，包括公共函数，基础配置项，路由解析，输入输出，安全处理等。系统库诸如数据库辅助类，session和cookie辅助类等。辅助函数包括邮箱验证、日期类等常用的应用组件。这些文件开发者最好不要擅自修改，它是整个框架的根基，也是我们进行源码分析需要最先关注的地方。

## 应用流程

在CodeIngiter的用户手册中有如下图所示的应用程序流程图，实际上已经比较准确的描述了CodeIgniter框架的执行过程，在下一篇分析文章框架启动器CodeIngiter.php的分析中我们也会从代码的角度分析框架从请求到页面显示在浏览器上的执行过程，现在暂且以用户视角对框架进行初步的认识。

![](https://upload-images.jianshu.io/upload_images/8371576-36cc23f9036139da.png?imageMogr2/auto-orient/strip%7CimageView2/2/w/1240)

1. `index.php` 文件作为前端控制器，初始化运行 CodeIgniter所需的基本资源；
2. Router 检查 HTTP 请求，以确定如何处理该请求；
3. 如果存在缓存文件，将直接输出到浏览器，不用走下面正常的系统流程；
4. 在加载应用程序控制器之前，对 HTTP 请求以及任何用户提交的数据进行安全检查；
5. 控制器加载模型、核心类库、辅助函数以及其他所有处理请求所需的资源；
6. 最后一步，渲染视图并发送至浏览器，如果开启了缓存，视图被会先缓存起来用于后续的请求。

## 代码执行过程

和很多MVC框架类似，CodeIgniter框架的入口脚本是`index.php`。

`index.php`完成的工作包括设置框架的执行环境，具体为`development`，`testing`，`production`3种，并根据执行环境设置对应的PHP的报警级别。

然后分别设置系统，应用和视图的实际文件夹的名称，根据文件夹的名称定位这些文件夹在服务器的绝对路径，用取到的值来定义`BASEPATH`，`VIEWPATH`和`APPPATH`3个常量，完成系统路径、视图路径和应用路径的初始化工作。

最后，引入了真正贯穿CI执行过程的启动器文件`CodeIgniter.php`。

**tips：** 在CodeIgniter框架的其他系统文件的首部我们都会发现如下所示的代码段，这意味着`index.php`是框架的唯一入口文件，如果用户试图绕过入口文件`index.php`直接访问框架的其他文件时会以错误的方式跳出框架。
```
defined('BASEPATH') OR exit('No direct script access allowed');
```
到此为止，我们对CodeIgniter框架已经有了大体上的认识，之后我们会根据系统文件在框架中的作用分别进行分析。从代码的角度来看整个框架的文件调用过程如下图所示，代码的命名已经比较易懂，也可以对照代码调用和应用执行流程进一步理解代码完成的工作和在框架中的作用。

![](https://upload-images.jianshu.io/upload_images/8371576-8914246d5cc914f9.png?imageMogr2/auto-orient/strip%7CimageView2/2/w/1240)

通过梳理CodeIgniter框架的代码结构，应用的执行过程和代码的执行与调用过程，我们基本了解了CodeIgniter框架代码的设计，那么我们接下来就从启动器`CodeIgniter.php`开始入手分析框架运行过程中核心库的代码。
