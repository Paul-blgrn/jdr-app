<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlayerBoardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Récupère l'utilisateur connecté
        $user = auth()->user();

        $boards = $user->boards()->withCount('users')->get();
        //dd($boards->toJson());
        return $boards->toJson();
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
        $validator = \Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        // Si la validation échoue, on renvoie une erreur
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation Error.'], 422);
        }

        // Retrouve la première board avec pour code la valeur indiqué.
        $board = Board::where('code', $request->code)->first();

        // Si la board n'existe pas, on renvoie une erreur
        if (!$board) {
            return response()->json(['message' => 'Board doesn\'t exist.'], 422);
        }

        // Obtenir l'utilisateur authentifié
        $user = auth()->user();

        // Vérification si l'utilisateur est déjà associé à un board avec le même code
        // en comptant le nombre de résultats correspondants.
        $countBoard = $user->boards()->where('code', $request->code)->count();

        if ($countBoard > 0) {
            // L'utilisateur a déjà accès à ce board
            return response()->json(['message'=> 'You are already in this board.'], 422);
        }

        // Compter les utilisateurs attachés à la Board
        $countUsers = $board->users()->count();
        // Tester la capacité de la Board
        if ($countUsers >= $board->capacity) {
            // La Board est pleine, on envoie une erreur
            return response()->json(['message'=> 'The board is full.'], 403);
        }

        // Tout les tests passent, on procède à l'ajout de l'user à la Board.
        $board->users()->attach($user, ["role" => "player"]);

        // L'api retourne le Code 201 (Created), l'user à rejoint la Board.
        return response()->json(['message' => 'Board joined.'], 201);
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
            return response()->json(['message' => 'You are not a member of this board.'], 403);
        }

        if ($foundUser->pivot->role == "master") {
            return response()->json(['message' => 'The master cannot leave the board.'], 403);
        }

        if ($board->users()->count() <= 1) {
            // Retourner une réponse indiquant que l'utilisateur
            // ne peut pas quitter un board vide
            return response()->json(['message' => 'Cannot leave an empty board.'], 403);
        }

        // Détacher l'utilisateur du board
        $board->users()->detach($user->id);
        return response()->json(['message' => 'You have successfully left the board.'], 200);
    }
}
