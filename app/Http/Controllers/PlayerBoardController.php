<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Role;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlayerBoardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Retreive authenticated user
        $user = auth()->user();
        $perPage = 5;

        // check the role "master" and init $boardsCreated
        $masterRole = Role::where('name', 'master')->first();
        $boardsCreated = [];
        $metaCreatedBoards = [
            'total' => 0,
            'per_page' => $perPage,
            'current_page' => 1,
            'last_page' => 1,
        ];

        // check if role "master" exists
        if ($masterRole) {
            $createdBoardsQuery = $user->boards()->wherePivot('role_id', $masterRole->id)->withCount('users');
            $boardsCreatedPaginator = $createdBoardsQuery->paginate($perPage, ['*'], 'created_page');
            $boardsCreated = $boardsCreatedPaginator->items();
            $metaCreatedBoards = [
                'total' => $boardsCreatedPaginator->total(),
                'per_page' => $boardsCreatedPaginator->perPage(),
                'current_page' => $boardsCreatedPaginator->currentPage(),
                'last_page' => $boardsCreatedPaginator->lastPage(),
            ];
        }

        // check the role "player" and init $boardsJoined
        $playerRole = Role::where('name', 'player')->first();
        $boardsJoined = [];
        $metaJoinedBoards = [
            'total' => 0,
            'per_page' => $perPage,
            'current_page' => 1,
            'last_page' => 1,
        ];
        // check if role "player" exists
        if ($playerRole) {
            $joinedBoardsQuery = $user->boards()->wherePivot('role_id', $playerRole->id)->withCount('users');
            $boardsJoinedPaginator = $joinedBoardsQuery->paginate($perPage, ['*'], 'joined_page');
            $boardsJoined = $boardsJoinedPaginator->items();
            $metaJoinedBoards = [
                'total' => $boardsJoinedPaginator->total(),
                'per_page' => $boardsJoinedPaginator->perPage(),
                'current_page' => $boardsJoinedPaginator->currentPage(),
                'last_page' => $boardsJoinedPaginator->lastPage(),
            ];
        }

        // Response
        return response()->json([
            'meta' => [
                'created_boards' => $metaCreatedBoards,
                'joined_boards' => $metaJoinedBoards,
            ],
            'data' => [
                'created_boards' => $boardsCreated,
                'joined_boards' => $boardsJoined,
            ],
        ]);

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
        // Data Validation
        $validator = Validator::make($request->all(), [
            'code' => 'required|string'
        ]);
        // If validation fails, an error is returned.
        if ($validator->fails()) {
            return response()->json([
                'response' => [
                    'status_title' => 'Validation Error',
                    'status_message' => $validator->errors()->toArray(),
                    'status_code' => 422,
                ]
            ], 422);
        }
        // Find the first board with the code value indicated.
        $board = Board::where('code', $request->code)->first();
        // If the board doesn't exist, we return an error
        if (!$board) {
            return response()->json([
                'response' => [
                    'status_title' => 'Validation Error',
                    'status_message' => 'The code is invalid or does not exist.',
                    'status_code' => 422,
                ]
            ], 422);
        }
        // Get authenticated user
        $user = auth()->user();
        // Checking if the user is already associated with a board with the same code
        // by counting the number of matching results.
        $countBoard = $user->boards()->where('code', $request->code)->count();
        if ($countBoard > 0) {
            // The user already have access on this board
            return response()->json([
                'response' => [
                    'status_title' => 'Validation Error',
                    'status_message' => 'User is already in this board.',
                    'status_code' => 422,
                ]
            ], 422);
        }
        // Count users attached to the Board
        $countUsers = $board->users()->count();
        // Test the capacity of the Board
        if ($countUsers >= $board->capacity) {
            // The Board is full, we send an error
            return response()->json([
                'response' => [
                    'status_title' => 'No permission',
                    'status_message' => 'User cannot join a full board.',
                    'status_code' => 403,
                ]
            ], 403);
        }
        // Retreive the role by name "player"
        $playerRole = Role::where('name', 'player')->first();
        // All the tests pass, we proceed to add the user to the Board.
        $board->users()->attach($user->id, ['role_id'=> $playerRole->id]);
        // The API returns Code 201 (Created), the user has joined the Board.
        return response()->json([
            'response' => [
                'status_title' => 'Success',
                'status_message' => 'User joined the board successfully.',
                'status_code' => 201,
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Board $board)
    {
        // Obtenir l'utilisateur authentifié
        $user = auth()->user();
        // Récupérer le board de l'utilisateur avec les informations des utilisateurs associés
        $board = $user->boards()->with('users')->withCount('users')->findOrFail($board->id);
        // Retourner le board au format JSON
        return $board->toJson();
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
     *
     * @param  int  $boardId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($boardId)
    {
        // Récupérer l'utilisateur authentifié
        $user = auth()->user();

        // Récupérer le board en question
        $board = Board::findOrFail($boardId);

        // Récupérer l'usilisateur dans la board
        $foundUser = $board->users()->where('user_id', $user->id)->first();

        // Regarder dans la liste des joueurs présent dans la board
        // si l'utilisateur connecté est présent
        // if (!$foundUser) {
        //     // Retourner une réponse indiquant que l'utilisateur
        //     // n'est pas dans la liste des joueurs inscrits dans la board
        //     return response()->json([
        //         'response' => [
        //             'status_title' => 'No permission',
        //             'status_message' => 'The user cannot leave a board if they are not a member.',
        //             'status_code' => 403,
        //         ]
        //     ], 403);
        // }

        $UserRoleID = $foundUser->pivot->role_id;
        $role = Role::where('id', $UserRoleID)->first();

        if ($role->name == "master") {
            return response()->json([
                'response' => [
                    'status_title' => 'No permission',
                    'status_message' => 'The user with role Master cannot leave a board.',
                    'status_code' => 403,
                ]
            ], 403);
        }

        if ($board->users()->count() <= 1) {
            // Retourner une réponse indiquant que l'utilisateur
            // ne peut pas quitter un board qui deviendrait vide s'il le quitte
            return response()->json([
                'response' => [
                    'status_title' => 'No permission',
                    'status_message' => 'The user cannot leave a board if it becomes empty after leaving.',
                    'status_code' => 403,
                ]
            ], 403);
        }

        // Détacher l'utilisateur du board
        $board->users()->detach($user->id);
        return response()->json([
            'response' => [
                'status_title' => 'Success',
                'status_message' => 'The user have successfully left the board.',
                'status_code' => 200,
            ]
        ], 200);
    }
}
