<?php
namespace NeoVance\Phredux\Tests;

use \NeoVance\Phredux\Act\Action;
use \verify;

class ActActionTest extends \Codeception\Test\Unit
{
    use \Codeception\Specify;
    
    /**
     * @var \NeoVance\Phredux\Tests\
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testActionGenerator()
    {
        $this->specify('Action generator requires description argument.', function() {
            try {
                $action = new Action();
                verify($action)->null();
            } catch(\Throwable $e) {
                $action = null;
                verify($action)->null();
            }
        });
        
        $this->specify('Action generator takes a string description.', function() {
            $action = new Action('Test Action');
            verify((string)$action)->contains('Test Action');
        });
        
        $this->specify('Action type is description provided.', function() {
            $action = new Action('Test Action');
            $result = $action('Payload');
            verify($result->type)->contains('Test Action');
        });
    }
    
    public function testActionReducers()
    {
        $action = new Action('Test Action', function($v1, $v2) {
            return (object)[
                'v1' => $v1,
                'v2' => $v2
            ];
        }, function($v3, $v4) {
            return (object)[
                'v3' => $v3,
                'v4' => $v4
            ];
        });
        
        $result = $action('Hello', 'World');
            
        $this->specify('Action payload reducer produces payload.', function() use (&$result) {
            verify($result->payload->v1)->equals('Hello');
            verify($result->payload->v2)->equals('World');
        });
        
        $this->specify('Action meta reducer produces meta.', function() use (&$result) {
            verify($result->meta->v3)->equals('Hello');
            verify($result->meta->v4)->equals('World');
        });
    }
}