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
use OpenTelemetry\Contrib\Grpc\GrpcTransportFactory;
use OpenTelemetry\Contrib\Otlp\OtlpUtil;
use OpenTelemetry\API\Common\Signal\Signals;

// OpenTelemetry 初始化配置（需要在PHP应用初始化时就进行OpenTelemetry初始化配置）
function initOpenTelemetry()
{ 
    // 1. 设置 OpenTelemetry 资源信息
    $resource = ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
        ResourceAttributes::SERVICE_NAME => '<your-service-name>', # 应用名，必填
        ResourceAttributes::HOST_NAME => '<your-host-name>' # 主机名，选填
    ])));


    // 2. 创建将 Span 输出到控制台的 SpanExplorer
    // $spanExporter = new SpanExporter(
    //     (new StreamTransportFactory())->create('php://stdout', 'application/json')
    // );

    // 2. 创建通过 gRPC 上报 Span 的 SpanExplorer
    $headers = [
        'Authentication' => "<your-token>",
    ];
    $transport = (new GrpcTransportFactory())->create('<grpc-endpoint>' . OtlpUtil::method(Signals::TRACE), 'application/x-protobuf', $headers);
    $spanExporter = new SpanExporter($transport);


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