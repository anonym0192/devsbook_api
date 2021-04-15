<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\UserRelation;
use Intervention\Image\ImageManagerStatic as Image;

class FeedController extends Controller
{
    function __construct(){
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }
    
    //
    public function create(Request $request){

        $array = ['error' => ''];

        $allowedTypes =  ['image/jpg', 'image/jpeg', 'image/png'];

        $body = $request->input('body');
        $type = $request->input('type');
        $photo = $request->file('photo');

    
        switch($type){
            case 'text':
                if(!$body){
                    $array['error'] = "The post body has no content";
                    return $array;
                }
            break;

            case 'photo':
                if($photo){

                    if(!in_array($photo->getClientMimeType(), $allowedTypes)){
                        $array['error'] = "The image format is not accepted";
                        return $array;  
                    }

                    $destPath = public_path('/media/uploads');
                    $filename = md5(time().rand(0,9999)).'.jpg';

                    Image::make($photo)->resize(800, null, function($constraint){
                        $constraint->aspectRatio();
                    })->save($destPath.'/'.$filename);

                    $body = $filename;

                }else{
                    $array['error'] = "The photo was not sent";
                    return $array;    
                }
            break;

            default:
                $array['error'] = "The post type is not valid";
                return $array;
            }

            $p = new Post();
            $p->body = $body;
            $p->type = $type;
            $p->user_id = $this->loggedUser['id'];
            $p->created_at = date('Y-m-d H:i:s');
            $p->save();

            $array['data'] = 'Post created successfully';
            return $array;

    }

    public function read(Request $request){

        $array = ['error' => ''];

        $page = intval($request->input('page'));
        $postsPerPage = 5;

        // get all posts from followed users 
        $userList = [];
        $users = UserRelation::where('user_from', $this->loggedUser['id'] )->get();
        
        foreach($users as $user){
            $userList[] = $user['user_to'];
        }
        $userList[] = $this->loggedUser['id'];

        // Get posts ordered by date
        $posts =  Post::whereIn('user_id', $userList)
                ->orderBy('created_at', 'desc')
                ->limit($postsPerPage)
                ->offset( ($page - 1) * $postsPerPage)
                ->get();

        // additional info
        $total = Post::whereIn('user_id', $userList)->count();
        $pages = ceil($total / $postsPerPage);

        $posts = $this->_postListToObject( $posts, $this->loggedUser['id']);

        $array['data'] = [
            'posts' => $posts,
            'total' => $total,
            'pages' => $pages,
            'currentPage' => $page
        ];

        return $array;
    }

    public function userFeed(Request $request, $id = false){

        $array = ['error' => ''];

        $page = intval($request->input('page', 1));
        if($page < 1){
            $page = 1;
        } 

        $postsPerPage = 5;

        if($id === false){
            $id = $this->loggedUser['id'];
        }

        $posts = Post::where('user_id', $id)
        ->orderBy('created_at', 'desc')
        ->offset(($page - 1) * $postsPerPage)
        ->limit($postsPerPage)
        ->get();

        $total = Post::where('user_id' ,$id)->count();

        $pages = ceil($total / $postsPerPage);
        $posts = $this->_postListToObject($posts, $this->loggedUser['id']);

        $array['data'] = [
            'posts' => $posts,
            'total' => $total,
            'pages' => $pages,
            'currentPage' => $page
        ];

        return $array;
    }

    public function userPhotos(Request $request, $id = false){

        $array = ['error' => ''];
       
        $page = intval($request->input('page', 1));
        if($page < 1){
            $page = 1;
        } 
        $postsPerPage = 15;

        if($id === false){
            $id = $this->loggedUser['id'];
        }

        $posts = Post::where('user_id', $id)
        ->where('type', 'photo')
        ->orderBy('created_at', 'desc')
        ->offset(($page - 1) * $postsPerPage)
        ->limit($postsPerPage)
        ->get();

        $total = Post::where('user_id' ,$id)->where('type','photo')->count();

        $pages = ceil($total / $postsPerPage);
        $posts = $this->_postListToObject($posts, $this->loggedUser['id']);

        $array['data'] = [
            'posts' => $posts,
            'total' => $total,
            'pages' => $pages,
            'currentPage' => $page
        ];

        return $array;

    }

    private function _postListToObject($posts, $loggedUser){

        foreach($posts as $post){
            
            //Get to know if the post is from the logged user or not
            if($post['user_id'] == $loggedUser){
                $post['mine'] = true;
            }else{
                $post['mine'] = false;
            }

            //Get the complete image URL
            if($post['type'] == 'photo'){
                $post['body'] = url('media/uploads/'.$post['body']);
            }

            //Get the like count
            $likes = PostLike::where('post_id', $post['id'])->count();
            $post['likes'] = $likes;
            $isLiked = PostLike::where('post_id', $post['id'])
                            ->where('user_id', $this->loggedUser['id'])
                            ->count();
            $post['isLiked'] = ($isLiked > 0) ? true : false;
            

        //Get the post comments

            $comments = PostComment::where('post_id', $post['id'])->get();
            
            if($comments){
                foreach($comments as $comment){
                    $user = User::find($comment['user_id']);
                    if($user){
                        unset($comment['user_id']);
                        unset($comment['post_id']);
                        $user['avatar'] = url('/media/avatars/'.$user['avatar']);
                        $user['cover'] = url('/media/covers/'.$user['cover']);
                        $comment['user'] = $user;
                    }        
                }
            }
            
            $post['comments'] = $comments;
        }

        return $posts;
    }

    
}
