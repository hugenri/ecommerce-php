<?php

declare(strict_types=1);

namespace App\Modules\Categories\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Categories\Application\UseCases\CreateCategoryUseCase;
use App\Modules\Categories\Application\UseCases\UpdateCategoryUseCase;
use App\Modules\Categories\Application\UseCases\DeleteCategoryUseCase;
use App\Modules\Categories\Application\UseCases\ListCategoriesUseCase;
use App\Modules\Categories\Application\UseCases\GetCategoryUseCase;
use App\Modules\Categories\Application\UseCases\ActivateCategoryUseCase;
use App\Modules\Categories\Application\UseCases\DeactivateCategoryUseCase;
use App\Modules\Categories\Presentation\CategorySerializer;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class CategoryController extends Controller
{

    public function __construct(
        private ListCategoriesUseCase $listCategories,
        private CreateCategoryUseCase $createCategory,
        private GetCategoryUseCase $getCategory,
        private UpdateCategoryUseCase $updateCategory,
        private DeleteCategoryUseCase $deleteCategory,
        private ActivateCategoryUseCase $activateCategory,
        private DeactivateCategoryUseCase $deactivateCategory,
        private CategorySerializer $serializer,
        private Validator $validator,
        private Request $request,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function index()
    {
        $user = $this->sessionManager->get('user');
        return $this->view('index', [
            'userName' => $user['name'] ?? '',
            'userEmail' => $user['email'] ?? '',
        ]);
    }

    public function data()
    {
        $page = max(1, (int) ($this->request->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->get('per_page') ?? 10)));
        $filters = array_filter([
            'search' => $this->request->get('search'),
            'status' => $this->request->get('status'),
            'sort_by' => $this->request->get('sort_by') ?? 'name',
            'sort_order' => strtoupper($this->request->get('sort_order') ?? 'ASC'),
        ], fn($v) => $v !== null && $v !== '');

        $result = $this->listCategories->execute(
            $page,
            $perPage,
            $filters['search'] ?? '',
            $filters['sort_by'] ?? 'name',
            $filters['sort_order'] ?? 'ASC',
            $filters
        );

        return $this->success(
            array_map(fn($cat) => $this->serializer->toArray($cat), $result['data']),
            'Lista de categorías',
            200,
            $result['meta']
        );
    }

    public function show(int $id)
    {
        $category = $this->getCategory->execute($id);

        if (!$category) {
            return $this->notFound('Categoría no encontrada.');
        }

        return $this->success($this->serializer->toArray($category));
    }

    public function store()
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'name' => 'required|string|min:10|max:50',
            'description' => 'nullable|string|min:10|max:100',
            'image' => 'nullable|string|max:100|url',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $category = $this->createCategory->execute($data);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        return $this->success(
            ['category' => $this->serializer->toArray($category)],
            'Categoría creada exitosamente.',
            201
        );
    }

    public function update(int $id)
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'name' => 'sometimes|string|min:10|max:50',
            'description' => 'nullable|string|min:10|max:100',
            'image' => 'nullable|string|max:100|url',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $category = $this->updateCategory->execute($id, $data);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        if (!$category) {
            return $this->notFound('Categoría no encontrada.');
        }

        return $this->success(
            ['category' => $this->serializer->toArray($category)],
            'Categoría actualizada exitosamente.'
        );
    }

    public function destroy(int $id)
    {
        try {
            $deleted = $this->deleteCategory->execute($id);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        if (!$deleted) {
            return $this->notFound('Categoría no encontrada.');
        }

        return $this->success(null, 'Categoría eliminada exitosamente.');
    }

    public function activate(int $id)
    {
        $category = $this->activateCategory->execute($id);

        if (!$category) {
            return $this->notFound('Categoría no encontrada.');
        }

        return $this->success(
            ['category' => $this->serializer->toArray($category)],
            'Categoría activada exitosamente.'
        );
    }

    public function deactivate(int $id)
    {
        $category = $this->deactivateCategory->execute($id);

        if (!$category) {
            return $this->notFound('Categoría no encontrada.');
        }

        return $this->success(
            ['category' => $this->serializer->toArray($category)],
            'Categoría desactivada exitosamente.'
        );
    }
}
