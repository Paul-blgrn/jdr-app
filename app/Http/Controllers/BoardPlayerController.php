<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardPlayerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        // actual authenticated user
        $user = auth()->user();
        // return new JsonResponse($user, JsonResponse::HTTP_FORBIDDEN);

        $request->validate([
            'code' => 'bail | required | string',
        ]);

        // select * from boards where 'code' => $request->code;
        $board = Board::where('code', $request->code)->first();
        //$player = User::where('id', $user->id)->first();

        $board->users()->attach($user->id, ["role" => "player"]);
        return new JsonResponse($board, JsonResponse::HTTP_CREATED);


    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
