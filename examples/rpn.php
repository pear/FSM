<?php

require_once 'FSM.php';

function BeginBuildNumber($symbol, $payload)
{
    array_push($payload, $symbol);
}

function BuildNumber($symbol, $payload)
{
    $n = array_pop($payload);
    $n = $n . $symbol;
    array_push($payload, $n);
}

function EndBuildNumber($symbol, $payload)
{
    $n = array_pop($payload);
    array_push($payload, (int)$n);
}

function DoOperator($symbol, $payload)
{
    $ar = array_pop($payload);
    $al = array_pop($payload);

    if ($symbol == '+') {
        array_push($payload, $al + $ar);
    } elseif ($symbol == '-') {
        array_push($payload, $al - $ar);
    } elseif ($symbol == '*') {
        array_push($payload, $al * $ar);
    } elseif ($symbol == '/') {
        array_push($payload, $al / $ar);
    }
}

function DoEqual($symbol, $payload)
{
    echo array_pop($payload) . "\n";
}

function Error($symbol, $payload)
{
    echo "This does not compute: $symbol\n";
}

$stack = array();

$fsm = new FSM('INIT', $stack);
$fsm->setDefaultTransition('INIT', 'Error');

$fsm->addTransitionAny('INIT', 'INIT');
$fsm->addTransition('=', 'INIT', 'INIT', 'DoEqual');
$fsm->addTransitions(range(0,9), 'INIT', 'BUILDING_NUMBER', 'BeginBuildNumber');
$fsm->addTransitions(range(0,9), 'BUILDING_NUMBER', 'BUILDING_NUMBER', 'BuildNumber');
$fsm->addTransition(' ', 'BUILDING_NUMBER', 'INIT', 'EndBuildNumber');
$fsm->addTransitions(array('+','-','*','/'), 'INIT', 'INIT', 'DoOperator');

echo "Expression:\n";
$stdin = fopen('php://stdin', 'r');
$expression = rtrim(fgets($stdin));
$symbols = preg_split('//', $expression, -1, PREG_SPLIT_NO_EMPTY);

$fsm->processList($symbols);
