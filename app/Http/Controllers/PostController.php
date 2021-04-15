<?php

namespace App\Http\Controllers;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostComment;

use Illuminate\Http\Request;

class PostController extends Controller
{
    //
    function __construct(){
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }
    
    public function like(Request $request, $id){

        $array = ['error' => ''];

        $post = Post::find($id);
        if($post){
            
          //check if the post is alreary liked
            $like = PostLike::where('post_id', $post['id'])
                        ->where('user_id', $this->loggedUser['id'])
                        ->first();
            if($like){
                $like->delete();
                $array['isLiked'] = false;
            }else{
                $newLike = new PostLike();
                $newLike->user_id = $this->loggedUser['id'];
                $newLike->post_id = $post['id'];
                $newLike->created_at = date('Y-m-d H:i:s');
                $newLike->save();

                $array['isLiked'] = true;
            } 

            $likes = PostLike::where('post_id', $post['id'])->count();
            $array['likes'] = $likes;
        
        }else{
            $array['error'] = "Post doesn't exist";
        }
        
        return $array;

    }

    public function comment(Request $request, $id){
        $array = ['error' => ''];

        //Check if the post exists
        if($id){
            $post = Post::find($id);
            if(!$post){
                $array['error'] = "The post doesn't exist";
                return $array;
            }
        }

        $body = $request->input('body');

        if($body){

            $comment = new PostComment();
            $comment->user_id = $this->loggedUser['id'];
            $comment->post_id = $id;
            $comment->created_at = date('Y-m-d H:i:s');
            $comment->body = $body;
            $comment->save();
 
            $array['data'] = ['comment' => $comment]; 
        }else{
            $array['error'] = "The comment body can not be empty";
        }
        
        return $array;
    }
}
