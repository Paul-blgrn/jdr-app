<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TemplateController extends Controller
{
    public function index() {

    }
    
    public function store(Request $request) {
        // Validate the requested data
        $rules = [
            'name' => 'bail|required|string',
            'content' => 'bail|required|json',
            'default' => 'bail|required|boolean',
            'type' => 'bail|required|in:personnage,inventaire,competences,note,autres',
        ];

        $validator = Validator::make($request->all(), $rules);

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

        $validatedData = $validator->validated();

        $template = Template::create($validatedData);

        // Return a JSON response with the created template details
        return response()->json([
            'response' => [
                'status_code' => 201,
                'status_title' => 'Success',
                'status_message' => 'Template created successfully.',
                'template' => $template,
            ]
        ], 201);
    }

    public function update() {

    }

    public function destroy() {

    }
}
