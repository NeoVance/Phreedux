<?php namespace NeoVance\Phredux\Act;

use \NeoVance\Phredux\Store;
use \Equip\Structure\Dictionary;
use \Equip\Structure\UnorderedList;
use \Equip\Structure\ValidationException;
use \Qaribou\Collection\ImmArray;

class Action {
    
    protected $_desc;
    protected $_payload;
    protected $_meta;
    protected $_store;
    protected $_bound;
    protected $_serializable;
    
    protected static $_types;
    
    public function __construct(
        string $desc,
        Callable $payload = null,
        Callable $meta = null,
        ...$stores
    ) {
        $this->_bound = (
            preg_match('/^\[(?P<id>[0-9]+)\]\s(?P<desc>.*)$/', $desc, $bm) === 1
        );
        
        if(!empty($bm)) {
            try {
                $this->_bound = self::$_types[((int) $bm['id'])-1] === $bm['desc'];
            } catch(\Throwable $e) {
                $this->_bound = false;
            }
        }

        if(!empty($bm) && $this->_bound) {
            $desc = $bm['desc'];
            $bid = $bm['id'];
        }
        
        $this->_desc = $desc;
        $this->_serializable = (preg_match('/^[0-9A-Z_]+$/', $desc) === 1);
        $this->_payload = $payload;
        $this->_meta = $meta;
        $this->_store = $this->__normalizeAll($stores);
        
        if($this->_serializable) {
            $this->_id = 0;
        } else {
            $this->_id = isset(self::$_types) ? count(self::$_types) + 1 : 1;
        }
        
        if($this->_bound) {
            $this->_id = $bid;
        } else {
            self::__addType($this->_desc);
        }
    }
    
    public function __invoke(...$args) {
        $action = $this->__makeAction(...$args);
        
        foreach($this->_store as $dispatch) {
            call_user_func($dispatch, $action);
        }
        
        return $action;
    }
    
    public function __toString() {
        $id = $this->_id;
        return $this->_serializable ? $this->_desc : "[{$id}] {$this->_desc}";
    }
    
    public function raw(...$args) {
        return $this->__makeAction(...$args);
    }
    
    public function bind($stores) {
        $opStore = $stores;
        
        if(!is_array($stores)) {
            $opStore = [$stores];
        }
        
        $bound = new self((string) $this, $this->_payload, $this->_meta, ...$opStore);
        
        return $bound;
    }
    
    public function assign($stores) {
        $opStore = $stores;
        
        if(!is_array($stores)) {
            $opStore = [$stores];
        }
        
        $this->_store = $this->__normalizeAll($opStore);
    }
    
    protected function __makeAction(...$args) {
        $payload = call_user_func_array(
            $this->_payload ?? function($arg) {
                return $arg;
            },
            $args
        );
        
        $meta = call_user_func_array(
            $this->_meta ?? function() {
                return null;
            },
            $args
        );
        
        return $this->__createDictionary([
            'type' => (string) $this,
            'payload' => $payload,
            'meta' => $meta,
        ]);
    }
    
    protected function __createDictionary(array $value) {
        return new class($value) extends Dictionary {
            public function __get($key) {
                if(isset($this[$key])) {
                    return $this[$key];
                }
            }
        };
    }
    
    protected function __normalizeStore($store) {
        if(is_callable($store)) {
            return $store;
        } else {
            if($store instanceof Store) {
                return $store->dispatch;
            }
        }
    }
    
    protected function __normalizeAll($stores) {
        $newList = ImmArray::fromArray($stores)->map(function($store) {
            return $this->__normalizeStore($store);
        });
        
        if(isset($this->_store)) {
            $newList = $this->_store->concat($newList);
        }
        
        return $newList;
    }
    
    protected static function __addType(string $type) {
        if(!isset(self::$_types)) {
            self::$_types = ImmArray::fromArray([]);
        }
        
        $new = ImmArray::fromArray([$type]);
        
        self::$_types = self::$_types->concat($new);
    }
    
}
