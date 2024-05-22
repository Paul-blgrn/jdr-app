<?php

namespace App\Http\Controllers;

use App\Models\Board;
use Illuminate\Http\Request;

class BoardController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index() {

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
     * Display the specified resource.
     */
    public function join(Request $request, Board $board) {

        $rules = [
            
        ];
        // $code = Board::findOrFail( $board->id );

        // $code->invitation_code = $request->input('code');

        // return $code_board;
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
