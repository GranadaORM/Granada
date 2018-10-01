<?php


include __DIR__ . '/../src/Granada/ORM.php';
include __DIR__ . '/../src/Granada/Orm/Wrapper.php';
include __DIR__ . '/../src/Granada/Orm/Str.php';
include __DIR__ . '/../src/Granada/Granada.php';
include __DIR__ . '/../src/Granada/Eager.php';
include __DIR__ . '/../src/Granada/Model.php';
include __DIR__ . '/../src/Granada/ResultSet.php';
include __DIR__ . '/MockPDO.php';
include __DIR__ . '/models.php';

// Handle both PHP5 and PHP7 tests
if (!class_exists('\PHPUnit_Framework_TestCase') && class_exists('\PHPUnit\Framework\TestCase'))
    class_alias('\PHPUnit\Framework\TestCase', '\PHPUnit_Framework_TestCase');
