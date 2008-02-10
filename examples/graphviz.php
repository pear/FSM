<?php

require_once 'FSM.php';
require_once 'FSM/GraphViz.php';

$payload = array();
$fsm = new FSM('START', $payload);

$graphviz = new FSM_GraphViz($fsm);
$graph = $graphviz->export();

$graph->image('png');
