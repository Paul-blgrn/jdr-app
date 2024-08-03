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

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'response' => [
                    'status_title' => 'Unauthenticated',
                    'status_message' => 'You are not authenticated !',
                    'status_code' => 401,
                ]
            ], 401);
        }

        $perPage = 5;
        try {
            // Fetch user's boards with pagination
            $boards = $user->boards()->withCount('users')->paginate($perPage);

            return response()->json([
                'meta' => [
                    'total' => $boards->total(),
                    'per_page' => $boards->perPage(),
                    'current_page' => $boards->currentPage(),
                    'last_page' => $boards->lastPage(),
                ],
                'data' => $boards->items(),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'response' => [
                    'status_title' => 'Error',
                    'status_message' => 'An error occurred while fetching boards',
                    'status_code' => 500,
                ]
            ], 500);
        }
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
        // Validation des données
        $validator = Validator::make($request->all(), [
            'code' => 'required|string'
        ]);

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

        // Retrouve la première board avec pour code la valeur indiqué.
        $board = Board::where('code', $request->code)->first();

        // Si la board n'existe pas, on renvoie une erreur
        if (!$board) {
            return response()->json([
                'response' => [
                    'status_title' => 'Validation Error',
                    'status_message' => 'The code is invalid or does not exist.',
                    'status_code' => 422,
                ]
            ], 422);
        }

        // Obtenir l'utilisateur authentifié
        $user = auth()->user();

        // Vérification si l'utilisateur est déjà associé à un board avec le même code
        // en comptant le nombre de résultats correspondants.
        $countBoard = $user->boards()->where('code', $request->code)->count();

        if ($countBoard > 0) {
            // L'utilisateur a déjà accès à ce board
            return response()->json([
                'response' => [
                    'status_title' => 'Validation Error',
                    'status_message' => 'User is already in this board.',
                    'status_code' => 422,
                ]
            ], 422);
        }

        // Compter les utilisateurs attachés à la Board
        $countUsers = $board->users()->count();
        // Tester la capacité de la Board
        if ($countUsers >= $board->capacity) {
            // La Board est pleine, on envoie une erreur
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
        // Tout les tests passent, on procède à l'ajout de l'user à la Board.
        $board->users()->attach($user->id, ['role_id'=> $playerRole->id]);

        // L'api retourne le Code 201 (Created), l'user à rejoint la Board.
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
        $board = $user->boards()->with('users')->findOrFail($board->id);
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
        if (!$foundUser) {
            // Retourner une réponse indiquant que l'utilisateur
            // n'est pas dans la liste des joueurs inscrits dans la board
            return response()->json([
                'response' => [
                    'status_title' => 'No permission',
                    'status_message' => 'The user cannot leave a board if they are not a member.',
                    'status_code' => 403,
                ]
            ], 403);
        }

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
