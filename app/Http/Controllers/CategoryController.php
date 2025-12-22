<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    /**
     * Display a paginated listing of categories.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');

        $query = Category::where('user_id', auth()->id());

        // Apply search filter if provided
        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $categories = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request): CategoryResource
    {
        $category = Category::create([
            'user_id' => auth()->id(),
            ...$request->validated(),
        ]);

        return new CategoryResource($category);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): CategoryResource
    {
        // Ensure the category belongs to the authenticated user
        if ($category->user_id !== auth()->id()) {
            abort(403, 'This category does not belong to you.');
        }

        return new CategoryResource($category);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        // Ensure the category belongs to the authenticated user
        if ($category->user_id !== auth()->id()) {
            abort(403, 'This category does not belong to you.');
        }

        $category->update($request->validated());

        return new CategoryResource($category);
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category): Response
    {
        // Ensure the category belongs to the authenticated user
        if ($category->user_id !== auth()->id()) {
            abort(403, 'This category does not belong to you.');
        }

        $category->delete();

        return response()->noContent();
    }
}
