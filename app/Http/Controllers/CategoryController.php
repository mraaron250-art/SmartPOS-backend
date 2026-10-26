<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditService;
use App\Support\Validate;
use Illuminate\Database\Capsule\Manager as DB;

final class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Category::query()->orderBy('name');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('search')) {
            $query->where('name', 'ILIKE', Validate::like($search));
        }

        return $this->ok($query->get()->toArray());
    }

    public function store(Request $request): Response
    {
        $name = Validate::text($request->input('name', ''), 120);
        if ($name === '') {
            throw HttpException::badRequest('Category name is required.');
        }
        if (Category::findByName($name)) {
            throw HttpException::conflict('A category with this name already exists.');
        }

        $category = Category::query()->create([
            'name' => $name,
            'description' => Validate::text($request->input('description', ''), 1000),
            'status' => 'ACTIVE',
        ]);

        AuditService::log($this->user($request), [
            'action' => 'CATEGORY_CREATE',
            'module' => 'CATEGORIES',
            'target_type' => 'CATEGORY',
            'target_id' => $category->id,
            'details' => "Created category \"{$category->name}\"",
        ]);

        return $this->created($category->toArray(), 'Category created successfully.');
    }

    public function show(Request $request): Response
    {
        return $this->ok($this->find($request)->toArray());
    }

    public function update(Request $request): Response
    {
        $category = $this->find($request);

        if ($request->has('name')) {
            $name = Validate::text($request->input('name'), 120);
            if ($name === '') {
                throw HttpException::badRequest('Category name is required.');
            }
            $existing = Category::findByName($name);
            if ($existing && $existing->id !== $category->id) {
                throw HttpException::conflict('A category with this name already exists.');
            }
            $category->name = $name;
        }
        if ($request->has('description')) {
            $category->description = Validate::text($request->input('description'), 1000);
        }
        if ($request->has('status')) {
            $category->status = Validate::status($request->input('status'));
        }

        DB::connection()->transaction(function () use ($category) {
            $renamed = $category->isDirty('name');
            $category->save();
            if ($renamed) {
                // Keep the denormalized name on products in sync
                Product::query()->where('category_id', $category->id)->update(['category_name' => $category->name]);
            }
        });

        AuditService::log($this->user($request), [
            'action' => 'CATEGORY_UPDATE',
            'module' => 'CATEGORIES',
            'target_type' => 'CATEGORY',
            'target_id' => $category->id,
            'details' => "Updated category \"{$category->name}\"",
        ]);

        return $this->ok($category->toArray(), 'Category updated successfully.');
    }

    public function destroy(Request $request): Response
    {
        $category = $this->find($request);
        $category->update(['status' => 'INACTIVE']);

        AuditService::log($this->user($request), [
            'action' => 'CATEGORY_DEACTIVATE',
            'module' => 'CATEGORIES',
            'target_type' => 'CATEGORY',
            'target_id' => $category->id,
            'details' => "Deactivated category \"{$category->name}\"",
        ]);

        return $this->ok(null, 'Category deactivated successfully.');
    }

    private function find(Request $request): Category
    {
        return Category::query()->find($this->idParam($request, 'Category not found.'))
            ?? throw HttpException::notFound('Category not found.');
    }
}
