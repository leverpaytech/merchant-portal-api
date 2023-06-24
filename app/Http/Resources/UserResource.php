<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return 
        array_merge(parent::toArray($request), 
            ['merchant'=>$this->role_id == 1 ? $this->merchant:null],
            ['merchantKeys'=>$this->role_id == 1 ? $this->merchantKeys:null]
        );
        
        /*return [
            ...parent::toArray($request),
            'merchant'=>$this->role_id == 1 ? $this->merchant:null
        ];*/
        //'passport' => env('APP_URL').$this->passport,
    }
}
