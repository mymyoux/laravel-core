<?php
namespace Core\Http\Controllers\Auth\Connectors;
use Socialite;
class Google extends Connector
{
    public function user()
    {
        return $this->rawuser();
    }
    public function getConnectorData()
    {
        $data = (array)$this->rawuser();

        return std(array_merge($data, [
            "name"=> $data["name"],
            "api_id"=> $data["id"],
            "email"=> $data["email"],
            "access_token"=> $data["token"],
            "refresh_token"=> $data["refresh_token"]??NULL,
            "avatar"=>$data["avatar_original"],
        ]));
    }
    public function getAdditionalColumns()
    {
        return [];
    }
}