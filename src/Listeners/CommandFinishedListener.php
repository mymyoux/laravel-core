<?php

namespace Core\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Artisan;
use Logger;
class CommandFinishedListener
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
     * Handle the event.
     *
     * @param  Event  $event
     * @return void
     */
    public function handle(CommandFinished $event)
    {
        $this->handleMigrations($event);
        
    }
    protected function handleMigrations(CommandFinished $event)
    {
        switch($event->command)
        {
            case 'migrate:fresh':
            case 'migrate:install':
            case 'migrate:refresh':
            case 'migrate:reset':
                Artisan::call('table:cache');
                Artisan::call('social:sync');
            break;
            case 'migrate':
            case 'migrate:rollback':
                Artisan::call('table:cache');
                break;
        }
    }
}
