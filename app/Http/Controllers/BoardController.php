<?php

namespace App\Http\Controllers;

use App\Models\Board;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index() {
        $user = auth()->user();
        if (!$user) {
            return new JsonResponse("vous n'êtes pas connecté !", JsonResponse::HTTP_FORBIDDEN);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {

    }

    /**
     * Display the specified resource.
     */
    public function show(Board $board) {
        //$board->load("");
        //return view("boards.show", compact("board"));
        return $board;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id) {

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {

    }

    /**
     *
     * @param  int  $boardId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($boardId) {
        // Retrieve the authenticated user
        $user = auth()->user();

        // Retrieve the board
        $board = Board::findOrFail($boardId);

        // Retrieve the user from the board
        $foundUser = $board->users()->where('user_id', $user->id)->first();

        // If user doesn't hare "master" role we return an error
        if ($foundUser->pivot->role !== "master") {
            return response()->json([
                'response' => [
                    'status_title' => 'No permission',
                    'status_message' => 'The user with role Player cannot delete a board.',
                    'status_code' => 403,
                ]
            ], 403);
        }

    }
}
