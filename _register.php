<?php

declare(strict_types=1);

use OpenTelemetry\Contrib\Instrumentation\Drupal\DatabaseInstrumentation;
use OpenTelemetry\Contrib\Instrumentation\Drupal\DrupalKernelInstrumentation;
use OpenTelemetry\Contrib\Instrumentation\Drupal\HttpClientCallInstrumentation;
use OpenTelemetry\Contrib\Instrumentation\Drupal\HttpClientRequestInstrumentation;
use OpenTelemetry\SDK\Sdk;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(DrupalKernelInstrumentation::NAME) === TRUE) {
  return;
}

if (extension_loaded('opentelemetry') === FALSE) {
  trigger_error('The opentelemetry extension must be loaded in order to autoload the OpenTelemetry Drupal auto-instrumentation', E_USER_WARNING);

  return;
}

try {
  DrupalKernelInstrumentation::register();
  DatabaseInstrumentation::register();
  HttpClientRequestInstrumentation::register();
  HttpClientCallInstrumentation::register();
}
catch (Throwable $exception) {
  \Drupal::logger("drupalInstrumentation")->error($exception->getMessage());

  return;
}
