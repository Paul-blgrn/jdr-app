<?php

namespace App\Http\Controllers;

use App\Models\Board;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
     * Store a newly created board in storage.
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        // Validate the requested data
        $rules = [
            'name' => 'bail|required|string|max:50',
            'description' => 'bail|required|string|max:255',
            'capacity' => 'bail|required|integer|min:2',
        ];

        $validator = Validator::make($request->all(), $rules);

        // Si la validation échoue, on renvoie une erreur
        if ($validator->fails()) {
            return response()->json([
                'response' => [
                    'status_title' => 'Validation Error',
                    'status_message' => $validator->errors()->toArray(),
                    'status_code' => 422,
                ]
            ], 422);
        }

        $validatedData = $validator->validated();
        $validatedData['code'] = $this->generateRandomCode(10);

        // Retrieve the authenticated user
        $user = auth()->user();

        // Create a new board with the validated data
        $board = Board::create($validatedData);

        // Attach the authenticated user to the board with the role 'master'
        $board->users()->attach($user->id, ['role' => 'master']);

        // Return a JSON response with the created board details
        return response()->json([
            'response' => [
                'status_code' => 201,
                'status_title' => 'Success',
                'status_message' => 'Board created successfully.',
                'board' => $board->withCount('users')->get()->toJson()
            ]
        ], 201);
    }

    /**
     * Generate random code
     */
    protected function generateRandomCode($length = 10) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * Display the specified resource.
     */
    public function show(Board $board) {

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

        // Retrieve the board by its ID
        $board = Board::findOrFail($boardId);

        // Retrieve the user from the board
        $foundUser = $board->users()->where('user_id', $user->id)->first();

        // Check if the authenticated user has the "master" role for the board
        if ($foundUser->pivot->role !== "master") {
            // Return a JSON response indicating insufficient permissions (403 Forbidden)
            return response()->json([
                'response' => [
                    'status_title' => 'No permission',
                    'status_message' => 'The user with role Player cannot delete a board.',
                    'status_code' => 403,
                ]
            ], 403);
        }

        // Delete the board from the database
        $board->delete();

        // Return a JSON response indicating successful deletion (200 OK)
        return response()->json([
            'response' => [
                'status_title' => 'Success',
                'status_message' => 'The board ha been deleted successfully.',
                'status_code' => 200,
            ]
        ], 200);

    }
}
