--TEST--
FSM: Transitions
--FILE--
<?php

require_once 'FSM.php';

function defaultTransition($symbol, &$payload)
{
    array_push($payload, $symbol);
    echo "Default\n";
}

function transition1($symbol, &$payload)
{
    array_push($payload, $symbol);
    echo "Transition 1\n";
}

function transition2($symbol, &$payload)
{
    array_push($payload, $symbol);
    echo "Transition 2\n";
}

$stack = array();

$fsm = new FSM('START', $stack);
echo $fsm->getCurrentState() . "\n";

$fsm->setDefaultTransition('START', 'defaultTransition');
$fsm->addTransition('TRANS1', 'START', 'FINISH', 'transition1');
$fsm->addTransition('TRANS2', 'FINISH', 'START', 'transition2');

$fsm->process('TRANS2');
echo $fsm->getCurrentState() . "\n";

$fsm->process('TRANS1');
echo $fsm->getCurrentState() . "\n";

var_dump($stack);
--EXPECT--
START
Default
START
Transition 1
FINISH
array(2) {
  [0]=>
  string(6) "TRANS2"
  [1]=>
  string(6) "TRANS1"
}
