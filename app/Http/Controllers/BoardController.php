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
     * Remove the specified resource from storage.
     */
    public function destroy($id) {

    }
}
