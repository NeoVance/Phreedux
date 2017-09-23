<?php namespace NeoVance\Phredux\Act;

class Reducer {
    
    protected $_default;
    protected $_handlers;
    protected $_has;
    protected $_on;
    protected $_off;
    protected $_setOpts;
    protected $_options;
    
    public function __construct($handlers = [], $default) {
        $this->_default = $default;
        $this->_handlers = [];
        
        $this->_setOpts = function(array $newOpts) {
            $this->_options = (object) $newOpts;
        };
        
        $this->options(['payload' => true]);
        
        $this->_has = function($type) {
            return isset($this->_handlers[(string) $type]);
        };
        
        $this->_on = function($type, $handler) {
            $this->_handlers[(string) $type] = $handler;
        };
        
        $this->_off = function($type) {
            unset($this->_handlers[(string) $type]);
        };
        
        if(is_callable($handlers)) {
            call_user_func($handlers, $this->_on, $this->_off);
        } elseif(is_array($handlers)) {
            $this->_handlers = $handlers;
        }
    }
    
    public function __invoke($state, $action) {
        $state = $state ?? $this->_default;
        $action = is_array($action) ? (object) $action : $action;
        
        if(isset($action) && is_object($action)) {
            if(isset($this->_handlers[$action->type])) {
                $pl = $this->_options->payload;
                return call_user_func(
                    $this->_handlers[$action->type],
                    $state,
                    $pl ? $action->payload : $action,
                    $pl ? $action->meta : null
                );
            }
        }
        
        return $state;
    }
    
    public function __call(string $func, $args) {
        if(isset($this->{"_{$func}"})) {
            if(is_callable($this->{"_{$func}"})) {
                return call_user_func_array($this->{"_{$func}"}, $args);
            }
        }
    }
    
    public function options(array $newOpts) {
        return call_user_func($this->_setOpts, $newOpts);
    }
    
}