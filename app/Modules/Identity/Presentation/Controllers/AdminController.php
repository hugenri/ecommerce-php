<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Controllers;

use App\Core\Controller;
use App\Framework\Session\SessionManagerInterface;
use App\Core\Http\Response;

class AdminController extends Controller
{

    public function __construct(
        SessionManagerInterface $sessionManager,
        Response $response
    ) {
        parent::__construct($sessionManager, $response);
    }

    public function dashboard()
    {
        $user = $this->sessionManager->get('user');

        $data = [
            'userName'  => $user['name'] ?? 'Admin',
            'userEmail' => $user['email'] ?? '',
            'userRole'  => $user['role'] ?? 'admin',
        ];

        $this->view('dashboard', $data, 'Dashboard');
    }
}
