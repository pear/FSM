<?php
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
/**
 * Copyright (c) 2002-2011 Jon Parise <jon@php.net>
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
 * $Id: FSM.php 308377 2011-02-16 04:51:20Z jon $
 *
 * @package FSM
 */

/**
 * This class implements a Finite State Machine (FSM).
 *
 * In addition to maintaining state, this FSM also maintains a user-defined
 * payload, therefore effectively making the machine a Push-Down Automata
 * (a finite state machine with memory).
 *
 * This code is based on Noah Spurrier's Finite State Machine (FSM) submission
 * to the Python Cookbook:
 *
 *      http://aspn.activestate.com/ASPN/Cookbook/Python/Recipe/146262
 *
 * @author  Jon Parise <jon@php.net>
 * @author  Christopher Valles <info@christophervalles.com>
 * @version $Revision: 308377 $
 * @package FSM
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 *
 * @example rpn.php     A Reverse Polish Notation (RPN) calculator.
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
    private $_initialState = '';
    
    /**
     * Contains the current state of the machine.
     *
     * @var string
     * @see $_initialState
     * @access private
     */
    private $_currentState = '';
    
    /**
     * Contains the payload that will be passed to each action function.
     *
     * @var mixed
     * @access private
     */
    private $_payload = null;
    
    /**
     * Store the states of the machine.
     * This will be used for validation
     *
     * @var array
     * @access private
     * @since 1.4
     **/
    private $_states = array();
    
    /**
     * Maps (inputSymbol, currentState) --> (action, nextState).
     *
     * @var array
     * @see $_initialState, $_currentState
     * @access private
     */
    private $_transitions = array();
    
    /**
     * Maps (currentState) --> (action, nextState).
     *
     * @var array
     * @see $_inputState, $_currentState
     * @access private
     */
    private $_transitionsAny = array();
    
    /**
     * Contains the default transition that is used if no more appropriate
     * transition has been defined.
     *
     * @var array
     * @access private
     */
    private $_defaultTransition = null;
    
    /**
     * This method constructs a new Finite State Machine (FSM) object.
     *
     * In addition to defining the machine's initial state, a "payload" may
     * also be specified.  The payload represents a variable that will be
     * passed along to each of the action functions.  If the FSM is being used
     * for parsing, the payload is often a array that is used as a stack.
     *
     * @param   string  $initialState   The initial state of the FSM.
     * @param   mixed   $payload        A payload that will be passed to each
     *                                  action function.
     */
    public function __construct($initialState, &$payload = NULL)
    {
        $this->_initialState = $initialState;
        $this->_currentState = $initialState;
        $this->_payload = &$payload;
    }
    
    /**
     * This method adds a new state to the machine
     *
     * @param string $state The name of the state
     * @return void
     * @since 1.4
     **/
    public function addState($state){
        if (in_array($state, $this->_states)) {
            throw new Exception(sprintf('The machine already contains the state %s', $state));
        }
        
        if (empty($state)) {
            throw new Exception('State name cannot be empty');
        }
        
        $this->_states[] = $state;
    }
    
    /**
     * This method adds multiple new states to the machine
     *
     * @param array $states The states to be added to the machine
     * @return void
     * @since 1.4
     */
    public function addStates(array $states){
        foreach ($states as $state) {
            $this->addState($state);
        }
    }
    
    /**
     * Checks if the given state is defined in the state machine
     *
     * @param string $state 
     * @return mixed TRUE|Exception
     * @throws Exception if the state is not defined
     * @since 1.4
     */
    public function checkIfStateIsDefined($state){
        if (empty($state)) {
            throw new Exception('State name cannot be empty');
        }
        
        if (!in_array($state, $this->_states)) {
            throw new Exception(sprintf(
                'State not found. Please add %s as state using addState() or addStates()', 
                $state
            ));
        }
        
        return TRUE;
    }
    
    /**
     * This method returns the machine's current state.
     *
     * @return  string  The machine's current state.
     *
     * @since 1.3.1
     */
    public function getCurrentState()
    {
        return $this->_currentState;
    }
    
    /**
     * This method sets the machine's current state.
     *
     * @return  string  The machine's current state.
     * @since 1.4
     */
    public function setCurrentState($state)
    {
        if ($this->checkIfStateIsDefined($state)) {
            $this->_currentState = $state;
        }
    }
    
    /**
     * Method used to get the available states on the machone
     *
     * @return array
     * @since 1.4
     */
    public function getStates(){
        return $this->_states;
    }
    
    /**
     * This method resets the FSM by setting the current state back to the
     * initial state (set by the constructor).
     */
    public function reset()
    {
        $this->_currentState = $this->_initialState;
    }
    
    /**
     * This method adds a new transition that associates:
     *
     *      (symbol, currentState) --> (nextState, action)
     *
     * The action may be set to NULL, in which case the processing routine
     * will ignore the action and just set the next state.
     * 
     * This method also will check if the source and target state exists inside the 
     * machine using the $_states array
     *
     * @param   string  $transition     The input transition.
     * @param   string  $state          This transition's starting state.
     * @param   string  $nextState      This transition's ending state.
     * @param   string  $action         The name of the function to invoke
     *                                  when this transition occurs.
     *
     * @see     addTransitions()
     */
    public function addTransition($transition, $state, $nextState, $action = null)
    {
        if ($this->checkIfStateIsDefined($state) && $this->checkIfStateIsDefined($nextState)) {
            $this->_transitions["$transition,$state"] = array($nextState, $action);
        }
    }
    
    /*
        RADAR Makes no sense! Multiple transitions starting at the ex. state 1 and ending on state 2
    */
    /**
     * This method adds the same transition for multiple different transitions.
     *
     * @param   array   $transitions    A list of input transitions.
     * @param   string  $state          This transition's starting state.
     * @param   string  $nextState      This transition's ending state.
     * @param   string  $action         The name of the function to invoke
     *                                  when this transition occurs.
     *
     * @see     addTransition()
     */
    public function addTransitions(array $transitions, $state, $nextState, $action = null)
    {
        foreach ($transitions as $transition) {
            $this->addTransition($transition, $state, $nextState, $action);
        }
    }
    
    /**
     * This method adds an array of transitions.  Each transition is itself
     * defined as an array of values which will be passed to addTransition()
     * as parameters.
     *
     * @param   array   $transitions    An array of transitions.
     *
     * @see     addTransition
     * @see     addTransitions
     *
     * @since 1.2.4
     */
    public function addTransitionsArray(array $transitions)
    {
        foreach ($transitions as $transition) {
            call_user_func_array(array($this, 'addTransition'), $transition);
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
    public function addTransitionAny($state, $nextState, $action = null)
    {
        if ($this->checkIfStateIsDefined($state) && $this->checkIfStateIsDefined($nextState)) {
            $this->_transitionsAny[$state] = array($nextState, $action);
        }
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
    public function setDefaultTransition($nextState, $action)
    {
        if (is_null($nextState)) {
            $this->_defaultTransition = null;
        }else{
            if ($this->checkIfStateIsDefined($nextState)) {
                $this->_defaultTransition = array($nextState, $action);
            }
        }
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
     * @param   string  $transition         The input symbol.
     *
     * @return  mixed   Array representing (nextState, action), or NULL if the
     *                  transition could not be found and not default
     *                  transition has been defined.
     */
    public function getTransition($transition = NULL)
    {
        $state = $this->_currentState;
        
        if (!empty($this->_transitions["$transition,$state"])) {
            return $this->_transitions["$transition,$state"];
        } elseif (!empty($this->_transitionsAny[$state])) {
            return $this->_transitionsAny[$state];
        } else {
            return $this->_defaultTransition;
        }
    }
    
    /**
     * Method to get the transitions of the machine
     *
     * @return array
     * @since 1.4
     */
    public function getTransitions(){
        return $this->_transitions;
    }
    
    /**
     * Method to get the anonymous transitions of the machine
     *
     * @return array
     * @since 1.4
     */
    public function getTransitionsAny(){
        return $this->_transitionsAny;
    }
    
    /**
     * This method is the main processing routine.  It causes the FSM to
     * change states and execute actions.
     *
     * The transition is determined by calling getTransition() with the
     * provided transition and the current state.  If no valid transition is found,
     * process() returns immediately.
     *
     * The action callback may return the name of a new state.  If one is
     * returned, the current state will be updated to the new value.
     *
     * If no action is defined for the transition, only the state will be
     * changed.
     *
     * @param   string  $transition         The input transition.
     *
     * @see     processList()
     */
    public function process($transition = NULL)
    {
        $trans = $this->getTransition($transition);
        
        // If a valid array wasn't returned, return immediately.
        // if (!is_array($trans) || (count($trans) != 2)) {
        //     return;
        // }
        
        // Update the current state to this transition's exit state.
        $previousState = $this->_currentState;
        $this->_currentState = $trans[0];
        
        // If an action for this transition has been specified, execute it.
        if (!empty($trans[1])) {
            $state = call_user_func_array(
                $trans[1], 
                array(
                    $previousState, 
                    $this->getCurrentState(), 
                    &$this->_payload
                )
            );
            
            // If a new state was returned, update the current state.
            if (!empty($state) && is_string($state)) {
                $this->_currentState = $state;
            }
        }
    }
    
    /**
     * This method processes a list of transitions.  Each transition in the list is
     * sent to process().
     *
     * @param   array   $transitions        List of input transitions to process.
     */
    public function processList(array $transitions)
    {
        foreach ($transitions as $transition) {
            $this->process($transition);
        }
    }
    
    /**
     * Load a state machine from a file
     *
     * @param string $filename 
     * @return void
     * @since 1.4
     */
    public function load($filename){
        if (!file_exists($filename)) {
            throw new Exception(sprintf('Unable to load state machine from %s. File not found', $filename));
        }
        
        $data = file($filename);
        $data = array_map('unserialize', $data);
        
        list(
            $this->_initialState,
            $this->_currentState,
            $this->_payload,
            $this->_states,
            $this->_transitions,
            $this->_transitionsAny,
            $this->_defaultTransition
        ) = $data;
    }
    
    /**
     * Export the state machine to a file
     *
     * @param string $filename 
     * @return int
     * @since 1.4
     */
    public function export($filename){
        return file_put_contents($filename, sprintf(
            "%s\n%s\n%s\n%s\n%s\n%s\n%s",
            serialize($this->_initialState),
            serialize($this->_currentState),
            serialize($this->_payload),
            serialize($this->_states),
            serialize($this->_transitions),
            serialize($this->_transitionsAny),
            serialize($this->_defaultTransition)
        ));
    }
    
    /**
     * This code will export the machine to a dot file
     *
     * @param string $filename 
     * @return void
     * @since 1.4
     */
    public function export2Dot($filename){
        require_once 'FSM/GraphViz.php';
        
        $converter = new FSM_GraphViz($this);
        $graph = $converter->export();
        $graph->saveParsedGraph($filename);
    }
    
    /**
     * Export the state machone to a png representation.
     * Optionally you can add a watermark to the image
     *
     * @param string $filename Output filename for the png file
     * @param string $watermark Input filename for the watermark image
     * @return void
     * @since 1.4
     */
    public function export2Png($filename, $watermark = NULL){
        require_once 'FSM/GraphViz.php';
        
        $converter = new FSM_GraphViz($this);
        $graph = $converter->export();
        
        if ($watermark === NULL) {
            file_put_contents($filename, $graph->fetch('png'));
        } else {
            //Process the watermark
            $watermarkImg = imagecreatefrompng($watermark);
            $watermarkWidth = imagesx($watermarkImg);
            $watermarkHeight = imagesy($watermarkImg);
            
            //Process the plot
            $plotImg = imagecreatefromstring($graph->fetch('png'));
            $plotWidth = imagesx($plotImg);
            $plotHeight = imagesy($plotImg);
            
            //Create the final image
            $finalWidth = $plotWidth + $watermarkWidth + 15;
            $finalHeight = $plotHeight + $watermarkHeight + 15;
            $finalImg = imagecreatetruecolor($finalWidth, $finalHeight);
            $white = imagecolorallocate($finalImg, 255, 255, 255);
            imagefill($finalImg, 0, 0, $white);
            
            //Copy the plot and the watermark
            imagecopy($finalImg, $plotImg, 10, 10, 0, 0, $plotWidth, $plotHeight);
            imagecopy(
                $finalImg, $watermarkImg, 
                $finalWidth - $watermarkWidth - 5, 
                $finalHeight - $watermarkHeight - 5, 
                0, 0, 
                $watermarkWidth, 
                $watermarkHeight
            );
            
            //Output the image
            imagepng($finalImg, $filename);
            
            //Free memory
            imagedestroy($plotImg);
            imagedestroy($watermarkImg);
            imagedestroy($finalImg);
        }
    }
}