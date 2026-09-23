<?php

declare(strict_types=1);

namespace App\Modules\Settings\Presentation\Controllers;

use App\Core\Controller;
use App\Core\Validation\Validator;
use App\Core\Http\Response;
use App\Framework\Session\SessionManagerInterface;
use App\Http\Request;
use App\Modules\Settings\Application\UseCases\CreateHomeBannerUseCase;
use App\Modules\Settings\Application\UseCases\DeleteHomeBannerUseCase;
use App\Modules\Settings\Application\UseCases\GetSiteSettingsUseCase;
use App\Modules\Settings\Application\UseCases\ListHomeBannersUseCase;
use App\Modules\Settings\Application\UseCases\UpdateHomeBannerUseCase;
use App\Modules\Settings\Application\UseCases\UpdateSiteSettingsUseCase;
use App\Modules\Settings\Domain\HomeBanner;

class SettingsController extends Controller
{
    public function __construct(
        private GetSiteSettingsUseCase $getSiteSettings,
        private UpdateSiteSettingsUseCase $updateSiteSettings,
        private ListHomeBannersUseCase $listHomeBanners,
        private CreateHomeBannerUseCase $createHomeBanner,
        private UpdateHomeBannerUseCase $updateHomeBanner,
        private DeleteHomeBannerUseCase $deleteHomeBanner,
        private Validator $validator,
        private Request $request,
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function general()
    {
        $settings = $this->getSiteSettings->execute();
        $user = $this->sessionManager->get('user');

        $this->view('general', [
            'settings' => $settings,
            'userName' => $user['name'] ?? '',
            'userEmail' => $user['email'] ?? '',
            'flash' => $this->sessionManager->pull('settings_flash'),
        ]);
    }

    public function saveGeneral()
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'store_name' => 'required|string|max:150',
            'logo' => 'nullable|string|max:255',
            'favicon' => 'nullable|string|max:255',
            'slogan' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'business_hours' => 'nullable|string|max:255',
            'facebook_url' => 'nullable|string|max:255',
            'instagram_url' => 'nullable|string|max:255',
            'tiktok_url' => 'nullable|string|max:255',
        ]);

        if ($this->validator->hasErrors($errors)) {
            $this->validationError($errors);
            return;
        }

        $this->updateSiteSettings->execute($data);

        $this->success(null, 'Configuración general actualizada exitosamente.');
    }

    public function home()
    {
        $user = $this->sessionManager->get('user');

        $banners = $this->listHomeBanners->execute();
        $editId = (int) $this->request->get('edit', 0);

        $editing = null;
        if ($editId > 0) {
            foreach ($banners as $banner) {
                if ($banner->getBannerId() === $editId) {
                    $editing = $banner;
                    break;
                }
            }
        }

        $this->view('home', [
            'banners' => $banners,
            'bannersJson' => $this->serializeBanners(),
            'editing' => $editing,
            'userName' => $user['name'] ?? '',
            'userEmail' => $user['email'] ?? '',
            'flash' => $this->sessionManager->pull('settings_flash'),
        ]);
    }

    public function saveBanner()
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'image' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string',
            'alt_text' => 'nullable|string|max:255',
            'sort_order' => 'nullable|numeric',
            'is_active' => 'nullable|in:1',
        ]);

        if ($this->validator->hasErrors($errors)) {
            $this->validationError($errors);
            return;
        }

        try {
            $this->createHomeBanner->execute($data);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
            return;
        }

        $this->success(['banners' => $this->serializeBanners()], 'Banner creado exitosamente.', 201);
    }

    public function updateBanner(int $id)
    {
        $data = $this->request->post();

        $errors = $this->validator->validate($data, [
            'image' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string',
            'alt_text' => 'nullable|string|max:255',
            'sort_order' => 'nullable|numeric',
            'is_active' => 'nullable|in:1',
        ]);

        if ($this->validator->hasErrors($errors)) {
            $this->validationError($errors);
            return;
        }

        try {
            $banner = $this->updateHomeBanner->execute($id, $data);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
            return;
        }

        if (!$banner) {
            $this->notFound('Banner no encontrado.');
            return;
        }

        $this->success(['banners' => $this->serializeBanners()], 'Banner actualizado exitosamente.');
    }

    public function deleteBanner(int $id)
    {
        try {
            $deleted = $this->deleteHomeBanner->execute($id);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
            return;
        }

        if (!$deleted) {
            $this->notFound('Banner no encontrado.');
            return;
        }

        $this->success(['banners' => $this->serializeBanners()], 'Banner eliminado exitosamente.');
    }

    private function serializeBanners(): array
    {
        $banners = $this->listHomeBanners->execute();

        return array_map(static function (HomeBanner $banner): array {
            return [
                'banner_id' => $banner->getBannerId(),
                'title' => $banner->getTitle(),
                'subtitle' => $banner->getSubtitle(),
                'image' => $banner->getImage(),
                'alt_text' => $banner->getAltText(),
                'sort_order' => $banner->getSortOrder(),
                'is_active' => $banner->isActive(),
            ];
        }, $banners);
    }
}