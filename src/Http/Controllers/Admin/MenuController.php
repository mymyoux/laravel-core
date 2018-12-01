<?php

namespace Core\Http\Controllers\Admin;

use Core\Api\Annotations as myno;
use Auth;
use App\User;
use Storage;
use Core\Admin\Resource;
/**
 */
class MenuController extends \Core\Http\Controllers\Controller
{
    /**
     * @myno\Api
     */
    public function get()
    {

        return Resource::getResources();
    }
}
