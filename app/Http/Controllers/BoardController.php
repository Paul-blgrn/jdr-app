<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Role;
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
     * Generate a unique random code.
     *
     * @return string
     */
    private function generateUniqueCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $codeLength = 10;
        $code = '';

        do {
            $code = '';
            for ($i = 0; $i < $codeLength; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }
        } while (Board::where('code', $code)->exists());

        return $code;
    }

    /**
     * Store a newly created board in storage.
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        // Validate the requested data
        $rules = [
            'name' => 'bail|required|string|unique:boards,name|min:10|max:40',
            'description' => 'bail|required|string|min:20|max:70',
            'capacity' => 'bail|required|integer|min:2|max:20',
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
        $validatedData['code'] = $this->generateUniqueCode();

        // Retrieve the authenticated user
        $user = auth()->user();

        // Create a new board with the validated data
        $board = Board::create($validatedData);

        // Retreive the role by name "master"
        $masterRole = Role::where('name', 'master')->first();
        // Check if the master role exists
        if (!$masterRole) {
            return response()->json([
                'response' => [
                    'status_code' => 500,
                    'status_title' => 'Not Found',
                    'status_message' => 'The master role does not exist.',
                ],
            ], 500);
        }

        // Attach the authenticated user to the board with the role 'master'
        $board->users()->attach($user->id, ['role_id' => $masterRole->id]);

        // Return a JSON response with the created board details
        return response()->json([
            'response' => [
                'status_code' => 201,
                'status_title' => 'Success',
                'status_message' => 'Board created successfully.',
                'board' => $board->withCount('users')->get()->toJson(),
            ]
        ], 201);
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
     * Update the specified board in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        // Validate the requested data
        $rules = [
            'name' => 'bail|required|string|unique:boards,name|min:10|max:40',
            'description' => 'bail|required|string|min:20|max:70',
            'capacity' => 'bail|required|integer|min:2|max:20',
        ];

        $validator = Validator::make($request->all(), $rules);

        // If validation fails, return an error
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

        // Retreive the board
        $board = Board::findOrFail($id);

        // Update the board with validated data
        $board->update($validatedData);

        // Return a JSON response with the updated board details
        return response()->json([
            'response' => [
                'status_title' => 'Success',
                'status_message' => 'Board updated successfully.',
                'status_code' => 200,
            ]
        ], 200);

    }

    /**
     *
     * @param  int  $boardId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($boardId) {
        // Retrieve the board by its ID
        $board = Board::findOrFail($boardId);

        // Delete the board from the database
        $board->delete();

        // Return a JSON response indicating successful deletion (200 OK)
        return response()->json([
            'response' => [
                'status_title' => 'Success',
                'status_message' => 'The board has been deleted successfully.',
                'status_code' => 200,
            ]
        ], 200);

    }
}
