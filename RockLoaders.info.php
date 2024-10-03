<?php

namespace ProcessWire;

$info = [
  'title' => 'RockLoaders',
  'version' => json_decode(file_get_contents(__DIR__ . "/package.json"))->version,
  'summary' => 'Easily add animated loading animations/spinners to your website.',
  'autoload' => true,
  'singular' => true,
  'icon' => 'spinner',
  // php 8 for named arguments
  // Less for compiling the css
  'requires' => [
    'PHP>=8.0',
    'Less',
  ],
];
