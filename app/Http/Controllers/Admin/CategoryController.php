<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\GeneratesUniqueSlugs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\KitchenStation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use GeneratesUniqueSlugs;

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Categories/Index', [
            'categories' => CategoryResource::collection(Category::query()->withCount('products')->ordered()->get())->resolve($request),
            'stations' => KitchenStation::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::query()->create([
            ...$request->validated(),
            'slug' => $this->uniqueSlug(Category::class, $request->validated('name')),
        ]);

        return back()->with('success', "Category {$category->name} was created.");
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update([
            ...$request->validated(),
            'slug' => $this->uniqueSlug(Category::class, $request->validated('name'), $category->id),
        ]);

        return back()->with('success', "Category {$category->name} was updated.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Soft-deleted products still point at their category, so they count too.
        if ($category->products()->withTrashed()->exists()) {
            return back()->with('error', "{$category->name} still has products. Move or remove them first, or hide the category instead.");
        }

        $category->delete();

        return back()->with('success', "Category {$category->name} was deleted.");
    }
}
