<?php

// Update CategoryController.php to add missing methods

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Start category query builder
            $categoriesQuery = Category::query();

            // Apply search filter if provided
            if ($request->has('search')) {
                $searchTerm = $request->input('search');
                $categoriesQuery->where('name', 'like', "%{$searchTerm}%");
            }

            // Filter by parent category if provided
            if ($request->has('parent_id')) {
                $categoriesQuery->where('parent_id', $request->input('parent_id'));
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $categoriesQuery->where('is_active', $request->boolean('is_active'));
            }

            // Show only root categories (no parent)
            if ($request->has('root_only') && $request->boolean('root_only')) {
                $categoriesQuery->whereNull('parent_id');
            }

            // Sorting
            $sortField = $request->input('sort_by', 'name');
            $sortDirection = $request->input('sort_direction', 'asc');
            $validSortFields = ['name', 'created_at', 'updated_at'];

            if (in_array($sortField, $validSortFields)) {
                $categoriesQuery->orderBy($sortField, $sortDirection);
            }

            // Eager loading for query optimization
            if ($request->has('with_children') && $request->boolean('with_children')) {
                $categoriesQuery->with('children');
            }

            if ($request->has('with_products_count') && $request->boolean('with_products_count')) {
                $categoriesQuery->withCount('products');
            }

            // Paginate results
            $categories = $categoriesQuery->paginate($request->input('per_page', 15));

            return ApiResponse::success($categories, 'Categories retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created category.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'parent_id' => 'nullable|exists:categories,id',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', 422, $validator->errors());
            }

            // Prevent circular reference
            if ($request->has('parent_id') && $request->parent_id) {
                $parentCategory = Category::find($request->parent_id);
                if (!$parentCategory) {
                    return ApiResponse::error('Parent category not found', 404);
                }

                // Check that we're not creating a circular reference
                $ancestorIds = $parentCategory->ancestors()->pluck('id')->toArray();
                if (in_array($request->parent_id, $ancestorIds)) {
                    return ApiResponse::error('Circular reference detected. Cannot set parent to one of its descendants', 422);
                }
            }

            // Create category
            $category = Category::create([
                'name' => $request->name,
                'parent_id' => $request->parent_id,
                'is_active' => $request->input('is_active', true),
            ]);

            // Load relationships for response
            if ($category->parent_id) {
                $category->load('parent');
            }

            return ApiResponse::success($category, 'Category created successfully', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified category.
     *
     * @param  Category  $category
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Category $category, Request $request)
    {
        try {
            // Load relationships based on request parameters
            $relations = [];

            if ($request->has('with_children') && $request->boolean('with_children')) {
                $relations[] = 'children';
            }

            if ($request->has('with_parent') && $request->boolean('with_parent')) {
                $relations[] = 'parent';
            }

            if ($request->has('with_products') && $request->boolean('with_products')) {
                $relations[] = 'products';
            } elseif ($request->has('with_products_count') && $request->boolean('with_products_count')) {
                $category->loadCount('products');
            }

            // Load selected relationships
            if (!empty($relations)) {
                $category->load($relations);
            }

            return ApiResponse::success($category, 'Category retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified category.
     *
     * @param  Request  $request
     * @param  Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Category $category)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'string|max:255',
                'parent_id' => 'nullable|exists:categories,id',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', 422, $validator->errors());
            }

            // Prevent setting own ID as parent
            if ($request->has('parent_id') && $request->parent_id == $category->id) {
                return ApiResponse::error('A category cannot be its own parent', 422);
            }

            // Prevent circular reference
            if ($request->has('parent_id') && $request->parent_id) {
                // Check if the new parent is not one of the descendants
                $descendantIds = $category->descendants()->pluck('id')->toArray();
                if (in_array($request->parent_id, $descendantIds)) {
                    return ApiResponse::error('Circular reference detected. Cannot set parent to one of its descendants', 422);
                }
            }

            // Update category
            $category->update($request->only(['name', 'parent_id', 'is_active']));

            // Load relationships for response
            if ($category->parent_id) {
                $category->load('parent');
            }

            return ApiResponse::success($category, 'Category updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified category.
     *
     * @param  Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Category $category)
    {
        try {
            // Check if category has children
            if ($category->children()->count() > 0) {
                return ApiResponse::error('Cannot delete category with child categories. Please move or delete children first.', 422);
            }

            // Check if category has products
            if ($category->products()->count() > 0) {
                return ApiResponse::error('Cannot delete category with products. Please move or delete products first.', 422);
            }

            // Delete the category
            $category->delete();

            return ApiResponse::success(null, 'Category deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete category: ' . $e->getMessage(), 500);
        }
    }
}
