<?php

namespace Core\Events;

use Illuminate\Queue\SerializesModels;

class ConnectorAdded
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
