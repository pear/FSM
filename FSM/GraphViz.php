<?php
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
/**
 * Copyright (c) 2007-2008 Philippe Jausions / 11abacus
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package   FSM
 * @author    Philippe Jausions <jausions@php.net>
 * @copyright 2007 Philippe Jausions / 11abacus
 * @license   http://www.11abacus.com/license/NewBSD.php  New BSD License
 * @version   CVS: $Id$
 */

/**
 * Requires PEAR packages
 */
require_once 'FSM.php';
require_once 'Image/GraphViz.php';

/**
 * FSM to Image_GraphViz converter
 *
 * This class extends the FSM class to have access to private properties.
 * It is not intended to be used as a FSM instance.
 *
 * PHP 5 or later is recommended to be able to handle action that return a new
 * state.
 *
 * @package   FSM
 * @author    Philippe Jausions <jausions@php.net>
 * @copyright (c) 2007 by Philippe Jausions / 11abacus
 * @since     1.3.0
 */
class FSM_GraphViz extends FSM
{
    /**
     * Machine instance
     *
     * @var FSM
     * @access protected
     */
    var $_fsm;

    /**
     * Action name callback
     *
     * @var string
     * @access protected
     */
    var $_actionNameCallback;

    /**
     * Constructor
     *
     * @param FSM &$machine instance to convert
     *
     * @access public
     */
    function FSM_GraphViz(&$machine)
    {
        $this->_fsm =& $machine;
        $this->_actionNameCallback = array(&$this, '_getActionName');
    }

    /**
     * Sets the callback for the action name
     *
     * @param mixed $callback
     *
     * @return boolean TRUE on success, PEAR_Error on error
     * @access public
     */
    function setActionNameCallback($callback)
    {
        if (!is_callable($callback)) {
            return PEAR::raiseError('Not a valid callback');
        }
        $this->_actionNameCallback = $callback;
        return true;
    }

    /**
     * Converts an FSM to an instance of Image_GraphViz
     *
     * @param string $name Name for the graph
     * @param boolean $strict Whether to collapse multiple edges between
     *                        same nodes.
     *
     * @return Image_GraphViz instance or PEAR_Error on failure
     * @access public
     */
    function &export($name = 'FSM', $strict = true)
    {
        if (!is_a($this->_fsm, 'FSM')) {
            $error = PEAR::raiseError('Not a FSM instance');
            return $error;
        }

        $g = new Image_GraphViz(true, null, $name, $strict);

        // Initial state
        $attr = array('shape' => 'invhouse');
        $g->addNode($this->_fsm->_initialState, $attr);
        $nodes = array($this->_fsm->_initialState => $this->_fsm->_initialState);

        $_t = '_transitions';
        do {
            foreach ($this->_fsm->$_t as $input => $t) {
                if ($_t == '_transitions') {
                    list($symbol, $state) = explode(',', $input, 2);
                } else {
                    $state = $input;
                    $symbol = '';
                }
                list($nextState, $action) = $t;

                if (!array_key_exists($nextState, $nodes)) {
                    $g->addNode($nextState);
                    $nodes[$nextState] = $nextState;
                }
                if (!array_key_exists($state, $nodes)) {
                    $g->addNode($state);
                    $nodes[$state] = $state;
                }

                if (strlen($symbol)) {
                    $g->addEdge(array($state => $nextState),
                                array('label' => $symbol));
                } else {
                    $g->addEdge(array($state => $nextState));
                }

                $this->_addAction($g, $nodes, $action, $nextState);
            }
            if ($_t == '_transitions') {
                $_t = '_transitionsAny';
            } else {
                $_t = false;
            }
        } while ($_t);

        // Add default transition
        if ($this->_defaultTransition) {
            list($nextState, $action) = $this->_defaultTransition;

            if (!array_key_exists($nextState, $nodes)) {
                $g->addNode($nextState, array('style' => 'dotted'));
                $nodes[$nextState] = $nextState;
            }

            $this->_addAction($g, $nodes, $action, $nextState, true);
        }

        return $g;
    }

    /**
     * Adds an action into the graph
     *
     * @param Image_GraphViz &$graph instance to add the action to
     * @param array &$nodes list of nodes
     * @param mixed $action callback
     * @param string $state start state
     * @param boolean $default whether this is the action tied to the default
     *                         transition
     *
     * @return void
     * @access protected
     */
    function _addAction(&$graph, &$nodes, $action, $state, $default = false)
    {
        $actionName = call_user_func($this->_actionNameCallback, $action);
        if (strlen($actionName)) {
            $attr = array();
            if ($default) {
                $attr['style'] = 'dotted';
            }
            if (!array_key_exists($actionName, $nodes)) {
                $graph->addNode($actionName,
                                array_merge($attr, array('shape' => 'box')));
                $nodes[$actionName] = $actionName;
            }

            $graph->addEdge(array($state => $actionName), $attr);

            // Any new states out of action?
            $states = $this->_getStatesReturnedByAction($action);
            foreach ($states as $state) {
                if (!array_key_exists($state, $nodes)) {
                    $graph->addNode($state, $attr);
                    $nodes[$state] = $state;
                }
                $graph->addEdge(array($actionName => $state), $attr);
            }
        }
    }

    /**
     * Returns an symbol-node name
     *
     * @param string $symbol
     * @param string $state
     *
     * @return string
     * @access protected
     */
    function _getSymbolName($symbol, $state)
    {
        return $symbol.', '.$state;
    }

    /**
     * Returns an action as string
     *
     * @param mixed $callback action
     *
     * @return string
     * @access protected
     */
    function _getActionName($callback)
    {
        if (!is_callable($callback)) {
            return null;
        }
        if (!is_array($callback)) {
            return $callback.'()';
        }
        if (is_object($callback[0])) {
            return get_class($callback[0]).'::'.$callback[1].'()';
        }
        return $callback[0].'::'.$callback[1].'()';
    }

    /**
     * Analyzes callback for possible new state(s) returned
     *
     * PHP version 5
     *
     * This methods requires the use of the Reflection API to parse the
     * doc block and looks for the @return declaration.
     *
     * If the callback shall return new states, @return should specify a string
     * followed by a <ul></ul> containing a list of new states inside <li></li>
     * tags.
     *
     * Example of doc block for action callback:
     * <code>
     * /**
     *  * This method does something then returns a new status
     *  *
     *  * \@param string $symbol
     *  * \@param mixed $payload
     *  *
     *  * \@return string One of
     *  * <ul>
     *  *  <li>RIPE</li>
     *  *  <li>NOT_RIPE</li>
     *  * <ul>
     *  * \@access public
     *  {@*}
     * function checkRipeness($symbol, $payload)
     * {
     *    return ($symbol == 'Orange') ? 'RIPE' : 'NOT_RIPE';
     * }
     * </code>
     *
     * @param mixed $callback callback to analyze
     *
     * @return array a list of possible new states returned by the callback
     * @access protected
     */
    function _getStatesReturnedByAction($callback)
    {
        if (version_compare(PHP_VERSION, '5.1.0') < 0
            || !is_callable($callback)) {
            return array();
        }

        if (!is_array($callback)) {
            strstr($callback, '::')
                ? $reflector = new ReflectionMethod($callback)
                : $reflector = new ReflectionFunction($callback);
        } else {
            $reflector = new ReflectionMethod($callback[0], $callback[1]);
        }

        $doc = $reflector->getDocComment();

        // We're only interested in the docBlock from @return and on
        $returnPos = strpos($doc, '* @return');
        if ($returnPos === false) {
            return array();
        }
        $returnDoc = trim(substr($doc, $returnPos + 9));

        // Returning a string (i.e. new state name)?
        if (strncasecmp($returnDoc, 'string', 6) != 0) {
            return array();
        }

        // Get the list of possible new states
        $length = strpos($returnDoc, '* @');
        if (!$length) {
            $length = strlen($returnDoc);
        }
        $listDoc = substr($returnDoc, 0, $length);

        if (!preg_match_all('~<li>([^\s<]+?).*</li>~Uis', $listDoc, $list)) {
            return array();
        }

        return $list[1];
    }
}
