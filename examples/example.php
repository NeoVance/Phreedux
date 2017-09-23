<?php
//use Rx\Observable;
use Rx\Scheduler;
use NeoVance\Phredux\Act;
use NeoVance\Phredux\Store;

//You only need to set the default scheduler once
Scheduler::setDefaultFactory(function() {
    return new Scheduler\ImmediateScheduler();
});

session_start();

$a = new Act\Action('Something cool');
$b = new Act\Action('Something cool');

$store = new Store(
    Store::combineReducers([
        'session' => new Act\Reducer([
            "$a" => function($state, $payload) {
                return $payload;
            },
            "$b" => function($state, $payload) {
                return $payload;
            }
        ], (object) $_SESSION),
        'string' => new Act\Reducer(function($on, $off) use ($a, $b) {
            $on($a, function($state, $action) {
                return $action->value;
            });
            $on($b, function($state, $action) {
                return $action->value;
            });
        }, "o.o")
    ]),
    (object) ['session' => (object) $_SESSION]
);

$a = $a->bind($store);
$b = $b->bind($store);

$observable = $store->observable()
    ->map(function($i) {
        return print_r($i, true);
    })
    ->subscribe(function($i) {
        echo "<p>Observe: {$i}</p>";
    }, function($e) {
        echo "<p>Error: {$e->getMessage()}</p>";
    }, function() {
        echo "<p>DONE</p>";
    });

$sub = $store->subscribe(function($previous, $new, $action) use ($store) {
    echo "<h1>Dispatched!</h1>";
    echo "<pre>" . $new->string . "</pre>";
    foreach($store->getState()->session as $k => $v) {
        $_SESSION[$k] = $v;
    }
});

$a((object)['value' => 'Dispatch!']);
$b((object)['value' => 'Woot!']);

$sub();

session_write_close();
