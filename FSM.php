<?php
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
/* +----------------------------------------------------------------------+
 * | PHP Version 4                                                        |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 1997-2003 The PHP Group                                |
 * +----------------------------------------------------------------------+
 * | This source file is subject to version 2.02 of the PHP license,      |
 * | that is bundled with this package in the file LICENSE, and is        |
 * | available at through the world-wide-web at                           |
 * | http://www.php.net/license/2_02.txt.                                 |
 * | If you did not receive a copy of the PHP license and are unable to   |
 * | obtain it through the world-wide-web, please send a note to          |
 * | license@php.net so we can mail you a copy immediately.               |
 * +----------------------------------------------------------------------+
 * | Authors: Jon Parise <jon@php.net>                                    |
 * +----------------------------------------------------------------------+
 *
 * $Id$
 */

/**
 * This class implements a Finite State Machine (FSM).
 *
 * In addition to maintaining state, this FSM also maintains a user-defined
 * payload, therefore effectively making the machine a Push-Down Automata
 * (a finite state machine with memory).
 *
 * @author  Jon Parise <jon@php.net>
 * @version $Revision$
 * @package FSM
 */
class FSM
{
    /**
     * Represents the initial state of the machine.
     *
     * @var string
     * @see $_currentState
     * @access private
     */
    var $_initialState = '';

    /**
     * Contains the current state of the machine.
     *
     * @var string
     * @see $_initialState
     * @access private
     */
    var $_currentState = '';

    /**
     * Contains the payload that will be passed to each action function.
     *
     * @var mixed
     * @access private
     */
    var $_payload = null;

    /**
     * Maps (inputSymbol, currentState) --> (action, nextState).
     *
     * @var array
     * @see $_inputState, $_currentState
     * @access private
     */
    var $_transitions = array();

    /**
     * Maps (currentState) --> (action, nextState).
     *
     * @var array
     * @see $_inputState, $_currentState
     * @access private
     */
    var $_transitionsAny = array();

    /**
     * Contains the default transition that is used if no more appropriate
     * transition has been defined.
     *
     * @var array
     * @access private
     */
    var $_defaultTransition = null;


    /**
     * This method constructs a new Finite State Machine (FSM) object.  In
     * addition to defining the machine's initial state, a "payload" may also
     * be specified.  The payload represents a variable that will be passed
     * along to each of the action functions.  If the FSM is being used for
     * parsing, the payload is often a array that is used as a stack.
     *
     * @param   string  $initialState   The initial state of the FSM.
     * @param   mixed   $payload        A payload that will be passed to each
     *                                  action function.
     */
    function FSM($initialState, &$payload)
    {
        $this->_initialState = $initialState;
        $this->_currentState = $initialState;
        $this->_payload = &$payload;
    }

    /**
     * This method resets the FSM by setting the current state back to the
     * initial state (set by the constructor).  The current input symbol is
     * also reset to NULL.
     */
    function reset()
    {
        $this->_currentState = $this->_initialState;
        $this->_inputSymbol = null;
    }

    /**
     * This method adds a new transition that associates:
     *
     *      (symbol, currentState) --> (nextState, action)
     *
     * The action may be set to NULL, in which case the processing routine
     * will ignore the action and just set the next state.
     *
     * @param   string  $symbol         The input symbol.
     * @param   string  $state          This transition's starting state.
     * @param   string  $nextState      This transition's ending state.
     * @param   string  $action         The name of the function to invoke
     *                                  when this transition occurs.
     *
     * @see     addTransitions()
     */
    function addTransition($symbol, $state, $nextState, $action = null)
    {
        $this->_transitions["$symbol,$state"] = array($nextState, $action);
    }

    /**
     * This method adds the same transition for multiple different symbols.
     *
     * @param   array   $symbols        A list of input symbols.
     * @param   string  $state          This transition's starting state.
     * @param   string  $nextState      This transition's ending state.
     * @param   string  $action         The name of the function to invoke
     *                                  when this transition occurs.
     *
     * @see     addTransition()
     */
    function addTransitions($symbols, $state, $nextState, $action = null)
    {
        foreach ($symbols as $symbol) {
            $this->addTransition($symbol, $state, $nextState, $action);
        }
    }

    /**
     * This method adds a new transition that associates:
     *
     *      (currentState) --> (nextState, action)
     *
     * The processing routine checks these associations if it cannot first
     * find a match for (symbol, currentState).
     *
     * @param   string  $state          This transition's starting state.
     * @param   string  $nextState      This transition's ending state.
     * @param   string  $action         The name of the function to invoke
     *                                  when this transition occurs.
     *
     * @see     addTransition()
     */
    function addTransitionAny($state, $nextState, $action = null)
    {
        $this->_transitionsAny[$state] = array($nextState, $action);
    }

    /**
     * This method sets the default transition.  This defines an action and
     * next state that will be used if the processing routine cannot find a
     * suitable match in either transition list.  This is useful for catching
     * errors caused by undefined states.
     *
     * The default transition can be removed by setting $nextState to NULL.
     *
     * @param   string  $nextState      The transition's ending state.
     * @param   string  $action         The name of the function to invoke
     *                                  when this transition occurs.
     */
    function setDefaultTransition($nextState, $action)
    {
        if (empty($nextState)) {
            $this->_defaultTransition = null;
            return;
        }

        $this->_defaultTransition = array($nextState, $action);
    }

    /**
     * This method returns (nextState, action) given an input symbol and
     * state.  The FSM is not modified in any way.  This method is rarely
     * called directly (generally only for informational purposes).
     *
     * If the transition cannot be found in either of the transitions lists,
     * the default transition will be returned.  Note that it is possible for
     * the default transition to be set to NULL.
     *
     * @param   string  $symbol         The input symbol.
     *
     * @return  mixed   Array representing (nextState, action), or NULL if the
     *                  transition could not be found and not default
     *                  transition has been defined.
     */
    function getTransition($symbol)
    {
        $state = $this->_currentState;

        if (array_key_exists("$symbol,$state", $this->_transitions)) {
            return $this->_transitions["$symbol,$state"];
        } elseif (array_key_exists($state, $this->_transitionsAny)) {
            return $this->_transitionsAny[$state];
        } else {
            return $this->_defaultTransition;
        }
    }

    /**
     * This method is the main processing routine.  It causes the FSM to
     * change states and execute actions.
     *
     * The transition is determined by calling getTransition() with the
     * provided symbol and the current state.  If no valid transition is found,
     * process() returns immediately.
     *
     * The action callback may return the name of a new state.  If one is
     * returned, the current state will be updated to the new value.
     *
     * If no action is defined for the transition, only the state will be
     * changed.
     *
     * @param   string  $symbol         The input symbol.
     *
     * @see     processList()
     */
    function process($symbol)
    {
        $transition = $this->getTransition($symbol);

        /* If a valid array wasn't returned, return immediately. */
        if (!is_array($transition) || (count($transition) != 2)) {
            return;
        }

        /* Update the current state to this transition's exit state. */
        $this->_currentState = $transition[0];

        /* If an action for this transition has been specified, execute it. */
        if (!empty($transition[1])) {
            $state = call_user_func_array($transition[1],
                                          array($symbol, &$this->_payload));

            /* If a new state was returned, update the current state. */
            if (!empty($state) && is_string($state)) {
                $this->_currentState = $state;
            }
        }
    }

    /**
     * This method processes a list of symbols.  Each symbol in the list is
     * sent to process().
     *
     * @param   array   $symbols        List of input symbols to process.
     */
    function processList($symbols)
    {
        foreach ($symbols as $symbol) {
            $this->process($symbol);
        }
    }
}

?>
