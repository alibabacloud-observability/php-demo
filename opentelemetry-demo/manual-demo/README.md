## 基于 OpenTelemery PHP SDK 为应用手动埋点并上报链路数据

manual-demo 是基于 PHP Slim Web 框架实现一个模拟扔骰子游戏的应用，并使用 OpenTelemetry PHP SDK为应用手动埋点（即在代码显式创建Span，并为Span设置属性、事件、状态等），实现自定义的PHP应用链路追踪。

当OpenTelemetry PHP Extension自动埋点不满足您的场景，或者需要增加一些自定义业务埋点时，可以使用本篇文档介绍的手动埋点方式上报链路数据。

### 1. 前置条件

已安装 php、composer、pecl，且 php版本>=7.4


### 2. 创建投骰子应用

1. 初始化
```shell
mkdir <project-name> && cd <project-name>


composer init \
  --no-interaction \
  --stability beta \
  --require slim/slim:"^4" \
  --require slim/psr7:"^1"
composer update
```

2. 编写应用代码

* 在 \<project-name\>  目录下创建一个index.php文件，添加如下内容，这段代码模拟扔骰子游戏，返回1-6之间的一个随机数。


```php
<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

$app->get('/rolldice', function (Request $request, Response $response) {
    $result = random_int(1,6);
    $response->getBody()->write(strval($result));
    return $response;
});

$app->run();

```

* 此时应用已经编写完成，执行 `php -S localhost:8080` 命令即可运行应用，访问地址为 http://localhost:8080/rolldice




### 3. 导入 OpenTelemetry 相关依赖

1. 下载 PHP HTTP 客户端库，用于链路数据上报
```bash
composer require guzzlehttp/guzzle
```

2. 下载 OpenTelemetry PHP SDK
```bash
composer require \
  open-telemetry/sdk \
  open-telemetry/exporter-otlp
```

3. 下载使用 gRPC 上报数据时所需依赖（可选）
> 注意：如果使用 gRPC 上报，需要下载以下内容。通过 HTTP 上报不需要下载。

```bash
pecl install grpc # 如果之前已经下载过grpc，可以跳过这一步
composer require open-telemetry/transport-grpc
```


### 4. 编写OpenTelemetry初始化工具类

* 在 index.php 所在目录中创建 opentelemetry_util.php 文件
* 在文件中添加如下代码，并替换以下变量的值
  * \<your-service-name>: 应用名
  * \<your-host-name>: 主机名
  * \<http-endpoint>: 通过 HTTP 上报数据的接入点
  * \<your-token>: 通过 gRPC 上报数据的鉴权TOKEN
  * \<grpc-endpoint>: 通过 gRPC 上报数据的接入点

* 以下代码会设置应用名、Trace导出方式、Trace上报接入点，并创建全局TraceProvide

```php
<?php

use OpenTelemetry\API\Common\Instrumentation\Globals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\Stream\StreamTransportFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;
// 通过 HTTP 上报
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
// 通过 gRPC 上报
// use OpenTelemetry\Contrib\Grpc\GrpcTransportFactory;
// use OpenTelemetry\Contrib\Otlp\OtlpUtil;
// use OpenTelemetry\API\Common\Signal\Signals;


// OpenTelemetry 初始化配置（需要在PHP应用初始化时就进行OpenTelemetry初始化配置）
function initOpenTelemetry()
{ 
    // 1. 设置 OpenTelemetry 资源信息
    $resource = ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
        ResourceAttributes::SERVICE_NAME => '<your-service-name>', # 应用名，必填
        ResourceAttributes::HOST_NAME => '<your-host-name>' # 主机名，选填
    ])));


    // 2.1 创建将 Span 输出到控制台的 SpanExplorer
    // $spanExporter = new SpanExporter(
    //     (new StreamTransportFactory())->create('php://stdout', 'application/json')
    // );

    // 2.2 创建通过 HTTP 上报 Span 的 SpanExporter
    $transport = (new OtlpHttpTransportFactory())->create('<http-endpoint>', 'application/x-protobuf');
    $spanExporter = new SpanExporter($transport);

    // 2.3 创建通过 gRPC 上报 Span 的 SpanExplorer
    // $headers = [
    //     'Authentication' => "<your-token>",
    // ];
    // $transport = (new GrpcTransportFactory())->create('<grpc-endpoint>' . OtlpUtil::method(Signals::TRACE), 'application/x-protobuf', $headers);
    // $spanExporter = new SpanExporter($transport);

    // 3. 创建全局的 TraceProvider，用于创建 tracer
    $tracerProvider = TracerProvider::builder()
        ->addSpanProcessor(
            (new BatchSpanProcessorBuilder($spanExporter))->build()
        )
        ->setResource($resource)
        ->setSampler(new ParentBased(new AlwaysOnSampler()))
        ->build();

    Sdk::builder()
        ->setTracerProvider($tracerProvider)
        ->setPropagator(TraceContextPropagator::getInstance())
        ->setAutoShutdown(true)  // PHP 程序退出后自动关闭 tracerProvider，保证链路数据都被上报
        ->buildAndRegisterGlobal(); // 将 tracerProvider 添加到全局

}
?>
```

### 6. 修改应用代码，使用OpenTelemetry API创建Span

1. 在index.php中导入所需包

```php
<?php

use OpenTelemetry\API\Common\Instrumentation\Globals;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Trace\TracerProvider;

require __DIR__ . '/opentelemetry_util.php';
```

2. 调用 initOpenTelemetry 方法完成初始化，需要在PHP应用初始化时就进行OpenTelemetry初始化配置

```php
// OpenTelemetry 初始化，包含设置应用名、Trace导出方式、Trace上报接入点，并创建全局TraceProvider
initOpenTelemetry();
```


3. 为rolldice接口中创建Span
* 接口功能：模拟扔骰子，返回一个1-6之间的随机正整数
* 体验如何创建Span、设置属性、事件、带有属性的事件


```php
/**
 * 1. 接口功能：模拟扔骰子，返回一个1-6之间的随机正整数
 * 并演示如何创建Span、设置属性、事件、带有属性的事件
 */
$app->get('/rolldice', function (Request $request, Response $response) {
    // 获取 tracer
    $tracer = \OpenTelemetry\API\Globals::tracerProvider()->getTracer('my-tracer');
    // 创建 Span
    $span = $tracer->spanBuilder("/rolldice")->startSpan();
    // 为 Span 设置属性
    $span->setAttribute("http.method", "GET");
    // 为 Span 设置事件
    $span->addEvent("Init");
    // 设置带有属性的事件
    $eventAttributes = Attributes::create([
        "key1" => "value",
        "key2" => 3.14159,
    ]);

    // 业务代码
    $result = random_int(1,6);
    $response->getBody()->write(strval($result));

    $span->addEvent("End");
    // 销毁 Span
    $span->end();

    return $response;
});
```


4. 创建嵌套Span

* 新建一个rolltwodices接口
* 接口功能：模拟扔两个骰子，返回两个1-6之间的随机正整数
* 体验如何创建创建嵌套的Span
  
```php
$app->get('/rolltwodices', function (Request $request, Response $response) {
    // 获取 tracer
    $tracer = \OpenTelemetry\API\Globals::tracerProvider()->getTracer('my-tracer');
    // 创建 Span
    $parentSpan = $tracer->spanBuilder("/rolltwodices/parent")->startSpan();
    $scope = $parentSpan->activate();

    $value1 = random_int(1,6);

    $childSpan = $tracer->spanBuilder("/rolltwodices/parent/child")->startSpan();
      
    // 业务代码
    $value2 = random_int(1,6);
    $result = "dice1: " . $value1 . ", dice2: " . $value2; 

    // 销毁 Span
    $childSpan->end();
    $parentSpan->end();
    $scope->detach();

    $response->getBody()->write(strval($result));
    return $response;
});

```


5. 使用Span记录代码中发生的异常

* 新建error接口
* 接口功能：模拟接口发生异常
* 体验如何在代码发生异常使用Span记录状态

```php
$app->get('/error', function (Request $request, Response $response) {
    // 获取 tracer
    $tracer = \OpenTelemetry\API\Globals::tracerProvider()->getTracer('my-tracer');
    // 创建 Span
    $span3 = $tracer->spanBuilder("/error")->startSpan();
    try {
        // 模拟代码发生异常
        throw new \Exception('exception!');
    } catch (\Throwable $t) {
        // 设置Span状态为error
        $span3->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, "expcetion in span3!");
        // 记录异常栈轨迹
        $span3->recordException($t, ['exception.escaped' => true]);
    } finally {
        $span3->end();
        $response->getBody()->write("error");
        return $response;
    }
});
```

### 6. 运行应用

1. 执行以下命令

```shell
php -S localhost:8080
```

2. 访问链接： 
   * http://localhost:8080/rolldice
   * http://localhost:8080/rolltwodices
   * http://localhost:8080/error

* 每次访问页面，OpenTelemetry会创建链路数据，并上报至阿里云可观测链路OpenTelemetry版


3. 查看链路数据

* 登陆阿里云可观测链路OpenTelemetry版控制台，找到应用名为\<your-service-name>的应用（例如php-manual-demo），点击进入应用详情中查看调用链。



