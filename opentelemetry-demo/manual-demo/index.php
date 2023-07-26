<?php

use OpenTelemetry\API\Common\Instrumentation\Globals;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Trace\TracerProvider;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/opentelemetry_util.php';


// OpenTelemetry 初始化（包含设置应用名、Trace导出方式、Trace上报接入点，并创建全局TraceProvider）
initOpenTelemetry();

$app = AppFactory::create();

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


/**
 * 2. 接口功能：模拟扔两个骰子，返回两个1-6之间的随机正整数
 * 并演示如何创建嵌套的Span
 */
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

/**
 * 3. 接口功能：模拟接口发生异常
 * 并演示在代码发生异常使用Span记录状态
 */
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

$app->run();