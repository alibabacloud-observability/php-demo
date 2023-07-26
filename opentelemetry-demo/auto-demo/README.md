## 基于 OpenTelemery PHP Extension 为应用自动埋点并上报链路数据

auto-demo 是基于 PHP Slim Web 框架实现一个模拟扔骰子游戏的应用，并使用 OpenTelemetry 为应用自动埋点（即自动创建Trace/Span等链路数据），实现无侵入的PHP应用链路追踪。


OpenTelemetry 除了支持 Slim 框架的自动埋点，还支持多种框架，完整列表请参考 https://opentelemetry.io/ecosystem/registry/?component=instrumentation&language=php

### 1. 前置条件

已安装 php、composer、pecl，且 php版本>=8.0


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

### 3. 构建 OpenTelemetry PHP 扩展

1. 下载构建 OpenTelemetry PHP extension 所需要的工具

```shell
# macOS
brew install gcc make autoconf

# Linux(apt)
sudo apt-get install gcc make autoconf
```

2. 使用 pecl 构建 OpenTelemetry PHP 扩展

```shell
pecl install opentelemetry-beta
```

* 注意: 构建成功时输出内容的最后几行为(路径可能不完全一致): 

```shell
Build process completed successfully
Installing '/opt/homebrew/Cellar/php/8.2.8/pecl/20220829/opentelemetry.so'
install ok: channel://pecl.php.net/opentelemetry-1.0.0beta6
Extension opentelemetry enabled in php.ini
```

3. 启用 OpenTelemetry PHP 扩展
* 在 php.ini 文件中添加如下内容（注意：如果上一步输出了
"Extension opentelemetry enabled in php.ini"，表明已经启用，这一步请跳过）

```txt
[opentelemetry]
extension=opentelemetry.so
```

4. 再次验证是否构建&启用成功

* 方法一

```
php -m | grep opentelemetry


# 预期输出
opentelemetry
```

* 方法二
```
php --ri opentelemetry

# 预期输出
opentelemetry
opentelemetry support => enabled
extension version => 1.0.0beta6
```

5. 为投骰子应用添加 OpenTelemetry PHP 自动埋点需要的额外依赖

* open-telemetry/sdk: OpenTelemetry PHP SDK

* open-telemetry/opentelemetry-auto-slim: OpenTelemetry PHP针对slim框架实现的自动埋点插件

* open-telemetry/exporter-otlp: OpenTelemetry PHP OTLP协议数据上报所需的依赖


``` shell
# 这一步构建时间较长，会在控制台打印很多内容
pecl install grpc


composer config allow-plugins.php-http/discovery false
composer require \
  open-telemetry/sdk \
  open-telemetry/opentelemetry-auto-slim \
  open-telemetry/exporter-otlp \
  php-http/guzzle7-adapter \
  open-telemetry/transport-grpc
```

### 4. 运行应用

1. 执行以下命令

* 需要替换如下内容
  * \<your-service-name>: 应用名，如 php-demo
  * \<endpoint> : gRPC 接入点，如 http://tracing-analysis-dc-hz.aliyuncs.com:8090
  * \<token> : 接入鉴权信息

```shell
env OTEL_PHP_AUTOLOAD_ENABLED=true \
    OTEL_SERVICE_NAME=<your-service-name> \
    OTEL_TRACES_EXPORTER=otlp \
    OTEL_METRICS_EXPORTER=none \
    OTEL_LOGS_EXPORTER=none \
    OTEL_EXPORTER_OTLP_PROTOCOL=grpc \
    OTEL_EXPORTER_OTLP_ENDPOINT=<endpoint> \
          OTEL_EXPORTER_OTLP_HEADERS=Authentication=<token> \
    OTEL_PROPAGATORS=baggage,tracecontext \
    php -S localhost:8080
```

2. 访问应用： http://localhost:8081/rolldice

* 每次进入该页面，OpenTelemetry 都会自动创建Trace，并将链路数据上报至阿里云可观测链路OpenTelemetry版


3. 查看链路数据

* 登陆阿里云可观测链路OpenTelemetry版控制台，找到应用名为\<your-service-name>的应用（例如php-demo），点击进入应用详情中查看调用链。



