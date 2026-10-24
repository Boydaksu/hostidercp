<?php
declare(strict_types=1);
// Explicit, isolated test workload; no live provider or production configuration.
putenv('F3_BENCHMARK=1');require dirname(__DIR__).'/tests/services-http.php';
