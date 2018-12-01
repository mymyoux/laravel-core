<?php
namespace Core\Http\Controllers\Auth\Connectors;
use Socialite;
class Google extends Connector
{
    public function user()
    {
        return $this->rawuser();
    }
}