<?php

declare(strict_types=1);

namespace tests\classes;

use flight\Engine;

class ContainerDefault
{
    protected Engine $app;

    public function __construct(Engine $engine)
    {
        $this->app = $engine;
    }

    public function before(array $params): void
    {
        echo 'I returned before the route was called with the following parameters: ' . json_encode($params);
    }

    /** @return mixed */
    public function testTheContainer()
    {
        return $this->app->get('test_me_out');
    }

    public function echoTheContainer(): void
    {
        echo $this->app->get('test_me_out');
    }

    public function testUi(): void
    {
        echo '<span id="infotext">Route text:</span> The container successfully injected a value into the engine! Engine class: <b>' . get_class($this->app) . '</b>  test_me_out Value: <b>' . $this->app->get('test_me_out') . '</b>';
    }
}
