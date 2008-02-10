<?php

require_once 'FSM.php';
require_once 'FSM/GraphViz.php';

$payload = array();
$fsm = new FSM('START', $payload);

$converter = new FSM_GraphViz($fsm);
$graph = $converter->export();

$graph->image('png');
