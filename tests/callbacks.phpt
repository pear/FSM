--TEST--
FSM: Callbacks
--FILE--
<?php

require_once 'FSM.php';

function legacyCallback($symbol, &$payload)
{
    echo "Legacy: $symbol\n";
}

function modernCallback($symbol, &$payload, $currentState, $nextState)
{
    echo "Modern: $symbol, $currentState, $nextState\n";
}

$stack = array();
$fsm = new FSM('STATE1', $stack);

$fsm->addTransition('LEGACY', 'STATE1', 'STATE2', 'legacyCallback');
$fsm->addTransition('MODERN', 'STATE2', 'STATE3', 'modernCallback');

$fsm->process('LEGACY');
$fsm->process('MODERN');
--EXPECT--
Legacy: LEGACY
Modern: MODERN, STATE2, STATE3
