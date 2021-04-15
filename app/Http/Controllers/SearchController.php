<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class SearchController extends Controller
{
    //
    function __construct(){
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    public function search(Request $request){

        $array = ['error' => '', 'users' => []]; 

        $search = $request->input('q');
        
        if($search){
            $users = User::where('name', 'like', '%'.$search.'%')->get();

            foreach($users as $user){
                $array['users'][] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'avatar' => url('media/avatars/'.$user['avatar'])
                ];
            }
        }

        return $array;
    }
}
