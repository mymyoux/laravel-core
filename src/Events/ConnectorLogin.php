<?php

namespace Core\Events;

use Illuminate\Queue\SerializesModels;

class ConnectorLogin
{
    use SerializesModels;

    /**
     * The connector user.
     *
     */
    public $connector;
    public $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($connector, $user)
    {
        $this->connector = $connector;
        $this->user = $user;
    }
}
