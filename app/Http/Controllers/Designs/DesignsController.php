<?php

namespace App\Http\Controllers\Designs;

use App\Models\Design;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\DesignResource;
use App\Repositories\Contracts\IDesign;
use Illuminate\Support\Facades\Storage;
use App\Repositories\Eloquent\Criteria\{
    LatestFirst,
    isLive,
    ForUser,
    EagerLoad
};

class DesignsController extends Controller
{
    protected $designs;
    public function __construct(IDesign $designs)
    {
        $this->designs = $designs;
    }
    public function index(Request $request)
    {
        $designs = $this->designs->withCriteria([
            new LatestFirst(),
            new isLive(),
            // new ForUser(1),
            new EagerLoad(['user','comments'])

        ])->all();
        return response()->json(
            [
                'message'=>trans('messages.success'),
                'errors'=>null,
                'items'=>  DesignResource::collection($designs),
            ]
        );

    }

    public function findDesign($id)
    {
        $design = $this->designs->find($id);
        return new DesignResource($design);
    }
    public function update(Request $request , $id)
    {
        $design = $this->designs->find($id);
        $this->authorize('update',$design);
        $this->validate($request,[
            'title'=>['required','unique:designs,title,'.$id],
            'description'=>['required','min:20','max:100'],
            'tags'=>['required']
        ]);

     $design =  $this->designs->update($id,[
           'title'=>$request->title,
           'description'=>$request->description,
           'slug'=>Str::slug($request->title),
           'is_live'=> ! $design->upload_successfull? false:$request->is_live,
       ]);

       // apply tagg
       $this->designs->applyTags($id ,$request->tags);

       return response()->json(
        [
            'message'=>trans('messages.success'),
            'errors'=>null,
            'item'=> new DesignResource($design),
        ]
    );
    }

    public function destroy(Request $request,$id)
    {
        $design = $this->designs->find($id);
        $this->authorize('delete',$design);


        // delete the files associated to the record
        foreach (['large','thumbnail','original'] as $size) {
         // check if the files existes in the database 
         if(Storage::disk($design->disk)->exists("uploads/designs/{$size}/".$design->image))
         {
            Storage::disk($design->disk)->delete("uploads/designs/{$size}/".$design->image);
         }
        }

        $this->designs->delete($id);

        return response()->json(
            [
                'message'=>trans('messages.success_deleted'),
                'errors'=>null,
                'item'=> $design,
            ]
            ,200
        );
      
    }

    public function like($id)
    {
        $design = $this->designs->like($id);

    }

}
