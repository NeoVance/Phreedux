<?php namespace NeoVance\Phredux\Enhance;

class Middleware {
    
    public function __construct(...$middlewares) {
        $this->chain = array_map(function($middleware) {
            return function(...$args) use ($middleware) {
                return call_user_func_array($middleware, $args);
            };
        }, $middlewares);
    }
    
    public function __invoke($middlewareAPI) {
        $enhancer = call_user_func_array($this->compose, $this->chain);
        $dispatch = $enhancer($middlewareAPI);
        return $dispatch;
    }
    
    public function compose(...$funcs) {
        if(empty($funcs)) {
            return function($arg) {
                return $arg;
            };
        }
        
        if(count($funcs) === 1) {
            return $funcs[0];
        }
        
        return array_reduce($funcs, function($c, $i) {
            return function(...$args) use ($c, $i) {
                return call_user_func($c, call_user_func_array($i, $args));
            };
        });
    }
    
}