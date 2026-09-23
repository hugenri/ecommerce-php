<?php

declare(strict_types=1);

namespace App\Modules\Subcategories\Presentation\Controllers;

use App\Core\Controller;
use App\Modules\Subcategories\Application\UseCases\CreateSubcategoryUseCase;
use App\Modules\Subcategories\Application\UseCases\UpdateSubcategoryUseCase;
use App\Modules\Subcategories\Application\UseCases\DeleteSubcategoryUseCase;
use App\Modules\Subcategories\Application\UseCases\ListSubcategoriesUseCase;
use App\Modules\Subcategories\Application\UseCases\ListSubcategoriesByCategoryUseCase;
use App\Modules\Subcategories\Application\UseCases\ActivateSubcategoryUseCase;
use App\Modules\Subcategories\Application\UseCases\DeactivateSubcategoryUseCase;
use App\Modules\Subcategories\Application\UseCases\GetSubcategoryUseCase;
use App\Modules\Subcategories\Presentation\SubcategorySerializer;
use App\Http\Request;
use App\Core\Validation\Validator;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class SubcategoryController extends Controller
{

    public function __construct(
        private ListSubcategoriesUseCase $listSubcategories,
        private ListSubcategoriesByCategoryUseCase $listByCategory,
        private CreateSubcategoryUseCase $createSubcategory,
        private GetSubcategoryUseCase $getSubcategory,
        private UpdateSubcategoryUseCase $updateSubcategory,
        private DeleteSubcategoryUseCase $deleteSubcategory,
        private ActivateSubcategoryUseCase $activateSubcategory,
        private DeactivateSubcategoryUseCase $deactivateSubcategory,
        private SubcategorySerializer $serializer,
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
            'category_id' => $this->request->get('category_id') !== null ? (int) $this->request->get('category_id') : null,
            'sort_by' => $this->request->get('sort_by') ?? 'subcategories.name',
            'sort_order' => strtoupper($this->request->get('sort_order') ?? 'ASC'),
        ], fn($v) => $v !== null && $v !== '');

        $result = $this->listSubcategories->execute(
            $page,
            $perPage,
            $filters['search'] ?? '',
            $filters['sort_by'] ?? 'subcategories.name',
            $filters['sort_order'] ?? 'ASC',
            $filters
        );

        return $this->success(
            array_map(fn($sub) => $this->serializer->toArray($sub), $result['data']),
            'Lista de subcategorías',
            200,
            $result['meta']
        );
    }

    public function byCategory(int $categoryId)
    {
        $subcategories = $this->listByCategory->execute($categoryId);

        return $this->success(
            array_map(fn($sub) => $this->serializer->toArray($sub), $subcategories),
            'Subcategorías de la categoría'
        );
    }

    public function show(int $id)
    {
        $subcategory = $this->getSubcategory->execute($id);

        if (!$subcategory) {
            return $this->notFound('Subcategoría no encontrada.');
        }

        return $this->success($this->serializer->toArray($subcategory));
    }

    public function store()
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'category_id' => 'required|numeric',
            'name' => 'required|only_letters|min:4|max:25',
            'description' => 'nullable|min:10|max:100',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $subcategory = $this->createSubcategory->execute($data);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        return $this->success(
            ['subcategory' => $this->serializer->toArray($subcategory)],
            'Subcategoría creada exitosamente.',
            201
        );
    }

    public function update(int $id)
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'category_id' => 'sometimes|numeric',
            'name' => 'sometimes|only_letters|min:4|max:25',
            'description' => 'nullable|min:10|max:100',
        ]);

        if ($this->validator->hasErrors($errors)) {
            return $this->validationError($errors);
        }

        try {
            $subcategory = $this->updateSubcategory->execute($id, $data);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        if (!$subcategory) {
            return $this->notFound('Subcategoría no encontrada.');
        }

        return $this->success(
            ['subcategory' => $this->serializer->toArray($subcategory)],
            'Subcategoría actualizada exitosamente.'
        );
    }

    public function destroy(int $id)
    {
        try {
            $deleted = $this->deleteSubcategory->execute($id);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        if (!$deleted) {
            return $this->notFound('Subcategoría no encontrada.');
        }

        return $this->success(null, 'Subcategoría eliminada exitosamente.');
    }

    public function activate(int $id)
    {
        $subcategory = $this->activateSubcategory->execute($id);

        if (!$subcategory) {
            return $this->notFound('Subcategoría no encontrada.');
        }

        return $this->success(
            ['subcategory' => $this->serializer->toArray($subcategory)],
            'Subcategoría activada exitosamente.'
        );
    }

    public function deactivate(int $id)
    {
        $subcategory = $this->deactivateSubcategory->execute($id);

        if (!$subcategory) {
            return $this->notFound('Subcategoría no encontrada.');
        }

        return $this->success(
            ['subcategory' => $this->serializer->toArray($subcategory)],
            'Subcategoría desactivada exitosamente.'
        );
    }
}
