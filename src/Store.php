<?php namespace NeoVance\Phredux;

use \Qaribou\Collection\ImmArray;
use \Rx\Subject;

class Store {
    
    protected $_state;
    protected $_reduce;
    protected $_subscribers;
    protected $_subject;
    protected $_observable;
    protected $_dispatcher;
    
    public function __construct(Callable $reducer, $initial, Callable $enhancer = null) {
        $this->_state = $initial;
        $this->_subscribers = ImmArray::fromArray([]);
        $this->_subject = new Subject\Subject();
        $this->_observable = $this->subscribe(function($previous, $next, $action) {
            $this->_subject->onNext($next);
        });
        
        $dispatcher = $this->__createDispatcher();
        
        if(isset($enhancer)) {
            throw new Exception('Enhancers are not yet implemented.');
        }
        
        $middlewareAPI = new class($this, $dispatcher) {
            private $store;
            private $dispatch;
            
            public function __construct($s, $d) {
                $this->store = $s;
                $this->dispatch = $d;
            }
            
            public function getState() {
                return $this->store->getState();
            }
            
            public function __get($prop) {
                if ($prop === 'dispatch') {
                    return $this->dispatch;
                }
            }
            
            public function __call($prop, $args) {
                if ($prop === 'dispatch') {
                    return call_user_func_array($this->dispatch, $args);
                }
            }
        };
        
        $this->_dispatcher = isset($enhancer) ? $enhancer($middlewareAPI) : $dispatcher;
        $this->replaceReducer($reducer);
    }
    
    public function __destruct() {
        $this->_subject->onCompleted();
        call_user_func($this->_observable);
    }
    
    public function __call(string $func, array $args) {
        if ($func === 'dispatch') {
            return call_user_func_array($this->_dispatcher, $args);
        }
    }
    
    public function __get(string $prop) {
        if ($prop === 'dispatch') {
            return $this->_dispatcher;
        }
    }
    
    public function getState() {
        return $this->_state;
    }
    
    public function subscribe(Callable $subscriber) {
        $list = ImmArray::fromArray([$subscriber]);
        $this->_subscribers = $this->_subscribers->concat($list);
        return function() use ($subscriber) {
            $this->_subscribers = $this->_subscribers->filter(function($i) {
                return $i !== $subscriber;
            });
        };
    }
    
    public function observable() {
        return $this->_subject->asObservable();
    }
    
    public function replaceReducer(Callable $reducer) {
        $this->_reduce = $reducer;
        $this->dispatch((object) ['type' => 'phredux.initialize']);
    }
    
    public static function combineReducers($reducers) {
        if(is_array($reducers))
            $reducers = (object) $reducers;
        
        if(!is_object($reducers)) {
            throw new \Exception('Invalid type');
        }
        
        return function($state, $action) use ($reducers) {
            $next = is_array($state) ? [] : (object)[];
            foreach($reducers as $key => $reduce) {
                if(is_array($state)) {
                    $patch =& $state[$key];
                    $place =& $next[$key];
                } else {
                    $patch =& $state->$key;
                    $place =& $next->$key;
                }
                
                if(is_callable($reduce)) {
                    $place = call_user_func($reduce, $patch, $action);
                }
            }
            return $next;
        };
    }
    
    protected function __createDispatcher() {
        return function($action) {
            if(is_array($action)) {
                $opAction = (object) $action;
            } else {
                if (!is_object($action)) {
                    throw new \Exception('Dispatch expects an object or an array.');
                }
                $opAction =& $action;
            }
            
            if(!isset($opAction->type) && !$opAction->type) {
                throw new \Exception('Actions must have property "type".');
            }
            
            $previous = $this->_state;
            $this->_state = call_user_func($this->_reduce, $this->_state, $action);
            foreach($this->_subscribers as $subscriber) {
                call_user_func($subscriber, $previous, $this->_state, $action);
            }
            return $action;
        };
    }
    
}
