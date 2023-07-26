### 1. auto-demo: 基于 OpenTelemery PHP Extension 为应用自动埋点并上报链路数据

* **auto-demo** 是基于 PHP Slim Web 框架实现一个模拟扔骰子游戏的应用，并使用 OpenTelemetry 为应用自动埋点（即自动创建Trace/Span等链路数据），实现无侵入的PHP应用链路追踪。

* OpenTelemetry 除了支持 Slim 框架的自动埋点，还支持多种框架，完整列表请参考 https://opentelemetry.io/ecosystem/registry/?component=instrumentation&language=php

* 版本限制：php版本>=8.0

### 2. manual-demo: 基于 OpenTelemery PHP SDK 为应用手动埋点并上报链路数据

* **manual-demo** 是基于 PHP Slim Web 框架实现一个模拟扔骰子游戏的应用，并使用 OpenTelemetry PHP SDK为应用手动埋点（即在代码显式创建Span，并为Span设置属性、事件、状态等），实现自定义的PHP应用链路追踪。

* 当OpenTelemetry PHP Extension自动埋点不满足您的场景，或者需要增加一些自定义业务埋点时，可以使用手动埋点方式上报链路数据。

* 版本限制：php版本>=7.4