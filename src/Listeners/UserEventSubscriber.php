<?php

namespace Core\Listeners;

use App\Events\Event;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use Storage;

class UserEventSubscriber
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
      /**
     * Handle user login events.
     */
    public function onUserLogin($event) {

    }
    
    /**
     * Handle user logout events.
     */
    public function onUserLogout($event) {


    }
    protected function setAvatar($user, $avatar)
    {
        //remote url only for now
        if(!starts_with($avatar, 'http')) 
            return;
        $name = 'avatars/'.$user->getKey().'-'.generate_token();
        $data = @file_get_contents($avatar);
        if($data !== False)
        {
            Storage::disk('public')->put($name,$data);
            $user->avatar = $name;
            $user->save();
        }
    }
    public function onUserSignup($event) {
        $user = $event->user;
        //retrieve avatar
        if(isset($user->avatar))
        {
            $this->setAvatar($user, $user->avatar);
        }

    }
    public function onConnectorAdded($event) {
        $connector = $event->connector;
        $user = $event->user;
        
        $apiuser = $connector->getConnectorData();

        //retrieve avatar
        if(!isset($user->avatar) && isset($apiuser->avatar))
        {
            $this->setAvatar($user, $apiuser->avatar);
        }
        if(!isset($user->name) && isset($apiuser->name))
        {
            $user->name = $apiuser->name;
            $user->save();
        }
    }
    public function onConnectorLogin($event) {
        $connector = $event->connector;
        $user = $event->user;
        
        $apiuser = $connector->getConnectorData();

        //retrieve avatar
        if(!isset($user->avatar) && isset($apiuser->avatar))
        {
            $this->setAvatar($user, $apiuser->avatar);
        }
        if(!isset($user->name) && isset($apiuser->name))
        {
            $user->name = $apiuser->name;
            $user->save();
        }
    }

    public function subscribe($events)
    {
        $cls = get_called_class();
        $events->listen(
            'Illuminate\Auth\Events\Login',
            $cls.'@onUserLogin'
        );
        $events->listen(
            'Illuminate\Auth\Events\Logout',
            $cls.'@onUserLogout'
        );
        $events->listen(
            'Illuminate\Auth\Events\Registered',
            $cls.'@onUserSignup'
        );
        $events->listen(
            'Core\Events\ConnectorAdded',
            $cls.'@onConnectorAdded'
        );
        $events->listen(
            'Core\Events\ConnectorLogin',
            $cls.'@onConnectorLogin'
        );
    }
}
