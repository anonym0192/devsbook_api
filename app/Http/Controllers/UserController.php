<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Post;
use App\Models\UserRelation;
use Intervention\Image\ImageManagerStatic as Image;


class UserController extends Controller
{
    //
    
    function __construct(){
        $this->middleware('auth:api', ['except' => ['create']]);
        $this->loggedUser = auth()->user();
    }

    public function read(Request $request, $id = false){
        $array = ['error' => ''];

        if($id != false){
            $user = User::find($id);
            if($user){
               $info = $user;
            }else{
                $array['error'] = "User does not exist";
                return $array;
            }   
        }else{
            $info = $this->loggedUser;
        }

        //Image url
        $info['avatar'] = url('/media/avatars/'.$info['avatar']);
        $info['cover'] = url('/media/covers/'.$info['cover']);

        //Age
        $birthdate = new \DateTime($info['birthdate']);
        $today = new \DateTime('today');

        $info['age'] = $birthdate->diff($today)->y;
        
        //Me
        $info['me'] = ($info['id'] === $this->loggedUser['id']) ? true : false;

        //Followers
        $followers = UserRelation::where('user_to', $info['id'])->count();
        $info['followers'] = $followers;

        //Following
        $following = UserRelation::where('user_from', $info['id'])->count();
        $info['following'] = $following;

        //isFollowed
        $isFollowed = UserRelation::where('user_from', $this->loggedUser['id'])
                        ->where('user_to', $info['id'])
                        ->count();
        $info['isFollowed'] = ($isFollowed > 0) ? true : false;

        //Photos
        $photosQt = Post::where('user_id', $info['id'])
                    ->where('type', 'photo')
                    ->count();

        $info['photos'] = $photosQt;

        $array['data'] = $info;

        return response()->json($array);
    }

    public function create(Request $request){
        
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'name' => "required|regex:/^[a-zA-Z-' ]*$/|max:80",
            'email' => 'required|email|min:5|max:30|unique:users,email',
            'password' => 'required|min:3|max:13',
            'birthdate' => 'required|date'
        ]);

        if($validator->fails()){
            return response()->json(['error' => $validator->errors()], 402);
        }

        $hash = password_hash($request->input('password'), PASSWORD_DEFAULT);

        $u = new User();
        $u->name = $request->input('name');
        $u->email = $request->input('email');
        $u->password = $hash;
        $u->birthdate = $request->input('birthdate');
        $u->save();

        $token = auth()->attempt(['email' => $u->email, 'password' => $request->input('password')]);

        if(!$token){
            $array['error'] = 'An error ocorred in user register, please try again later'; 
            return $array;

        }else{

            $array['data'] = [
                'name' => $u->name,
                'email' => $u->email,
                'birthdate' => $u->birthdate
            ];
            return $array;
        }      
    }

    public function update(Request $request){

        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'name' => "nullable|regex:/^[a-zA-Z-' ]*$/|max:80",
            'email' => 'nullable|email|min:5|max:30',
            'password' => 'nullable|min:3|max:13',
            'birthdate' => 'nullable|date',
            'work' => "nullable|regex:/^[a-zA-Z-0-9' ]*$/|max:80",
            'city' => "nullable|regex:/^[a-zA-Z-' ]*$/|max:80"
        ]);

        if($validator->fails()){
            return response()->json(['error' => $validator->errors()], 402);
        }

        $name = $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');
        $birthdate = $request->input('birthdate');
        $work = $request->input('work');
        $city = $request->input('city');

    
        $u = User::find($this->loggedUser['id']);
        
        if($name){
            $u->name = $name;
        }
        if($email && $email != $this->loggedUser['email'] ){

            $emailExists = User::where('email', $email)->count();

            if($emailExists > 0){
                $array['error'] = 'The email has already been taken.';
                return $array;
            }else{
                $u->email = $email;
            }
            
        }
        if($password){
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $u->password = $hash;
        }
        if($birthdate){
            $u->birthdate = $birthdate;
        }
        if($work){
            $u->work = $work;
        }
        if($city){
            $u->city = $city;
        }
        $u->save();

        $array['data'] = 'Your user data was successfully changed';
        return $array;
    }

    public function updateAvatar(Request $request){

        $array = ['error' => ''];

        $allowedTypes =  ['image/jpg', 'image/jpeg', 'image/png'];

        $image = $request->file('avatar');

        if($image){
            if(in_array($image->getClientMimeType(), $allowedTypes ) ){

                $filename = md5(time().rand(0,9999)).'.jpg';
                $destPath = public_path('/media/avatars');

                Image::make($image)->fit(200,200)->save($destPath.'/'.$filename);

                $u = User::find($this->loggedUser['id']);
                $u->avatar = $filename;
                $u->save();

                $array['url'] = url($destPath.'/'.$filename);
            }else{
                $array['error'] = "File extension not supported";
                return $array;
            }
        }else{
            $array['error'] = "No image was sent";
            return $array;
        }

        return $array;



    }

    public function updateCover(Request $request){

        $array = ['error' => ''];
        
        $allowedTypes =  ['image/jpg', 'image/jpeg', 'image/png'];

        $image = $request->file('cover');

        if($image){
            if(in_array($image->getClientMimeType(), $allowedTypes ) ){

                $filename = md5(time().rand(0,9999)).'.jpg';
                $destPath = public_path('/media/covers');

                Image::make($image)->fit(850,310)->save($destPath.'/'.$filename);

                $u = User::find($this->loggedUser['id']);
                $u->cover = $filename;
                $u->save();

                $array['url'] = url($destPath.'/'.$filename);
            }else{
                $array['error'] = "File extension not supported";
                return $array;
            }
        }else{
            $array['error'] = "No image was sent";
            return $array;
        }

        return $array;

    }

    public function follow(Request $request, $id){

        $array = ['error' => ''];

        if($id == $this->loggedUser['id']){
            $array['error'] = "You can't follow yourself";
            return $array;
        }

        $userExists = User::find($id);
        if($userExists){

            $relation = UserRelation::where('user_from', $this->loggedUser['id'])
                        ->where('user_to', $id)->first();
            if($relation){
                $relation->delete();
                $array['data'] = ['following' => false]; 
            }else{

                $newRelation = new UserRelation();
                $newRelation->user_from = $this->loggedUser['id'];
                $newRelation->user_to = $id;
                $newRelation->save();

                $array['data'] = ['following' => true]; 
            }

        }else{
            $array['error'] = "The user you are trying to follow does not exist";
        }
        return $array;
    }

    public function followers(Request $request, $id){

        $array = ['error' => ''];

        $userExists = User::find($id);

        if($userExists){

            $followers = UserRelation::where('user_to', $id)->get();
            $following = UserRelation::where('user_from', $id)->get();

            $followersList = [];
            foreach($followers as $item){
                $user = User::find($item['user_from']);
                if($user){
                    $followersList[] = [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'avatar' => url('/media/avatars/'.$user['avatar'])
                    ];
                }
            }

            $followingList = [];
            foreach($following as $item){
                $user = User::find($item['user_to']);
                
                if($user){
                    $followingList[] = [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'avatar' => url('/media/avatars/'.$user['avatar'])
                    ];
                }
                
            }

            $array['data'] = ['followers' => $followersList, 'following' => $followingList];
        }else{
            $array['error'] = "The user does not exist";
        }

        return $array;
    }

    public function photos(Request $request, $id){

    }
}
