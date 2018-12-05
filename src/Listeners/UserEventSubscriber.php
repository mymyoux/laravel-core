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
    public function onUserSignup($event) {
        $user = $event->user;
        //retrieve avatar
        if(isset($user->avatar) && starts_with($user->avatar, 'http'))
        {
            $name = 'avatars/'.$user->getKey().'-'.generate_token();
            $data = @file_get_contents($user->avatar);
            if($data !== False)
            {
                Storage::disk('public')->put($name,$data);
                $user->avatar = $name;
                $user->save();
            }
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
    }
}
