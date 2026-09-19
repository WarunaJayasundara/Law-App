<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Env;
use App\Core\Request;
use App\Core\Session;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PasswordPolicy;
use App\Validators\Validator;

final class UserController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('users.manage');
        $this->view('users/index', [
            'users' => User::all(),
            'roles' => User::roles(),
            'success' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('users.manage');
        $input = Request::all();

        $v = new Validator($input);
        $v->required('name', 'Name')->maxLength('name', 120, 'Name')
          ->required('email', 'Email')->email('email', 'Email')->maxLength('email', 150, 'Email')
          ->required('username', 'Username')->maxLength('username', 60, 'Username')
          ->required('password', 'Password')
          ->required('role_id', 'Role');

        if ($v->fails()) {
            $this->flashAndRedirect('error', implode(' ', $v->errors()), '/users');
        }

        $passwordProblem = PasswordPolicy::check((string) $input['password'], [trim($input['username']), trim($input['email'])], Env::get('SEED_ADMIN_PASSWORD'));
        if ($passwordProblem !== null) {
            $this->flashAndRedirect('error', $passwordProblem, '/users');
        }
        if (!User::roleExists((int) $input['role_id'])) {
            $this->flashAndRedirect('error', 'Choose a valid role.', '/users');
        }

        if (User::findByLogin(trim($input['username'])) || User::findByLogin(trim($input['email']))) {
            $this->flashAndRedirect('error', 'A user with that username or email already exists.', '/users');
        }

        $id = User::create([
            'name' => trim($input['name']),
            'email' => trim($input['email']),
            'username' => trim($input['username']),
            'password' => $input['password'],
            'role_id' => (int) $input['role_id'],
        ]);

        AuditLogger::log('user.create', 'user', $id, null, ['username' => $input['username'], 'role_id' => $input['role_id']]);

        $this->flashAndRedirect('success', 'User account created.', '/users');
    }

    public function update(array $params): void
    {
        $this->requirePermission('users.manage');
        $id = (int) $params['id'];

        if ($id === Auth::id()) {
            $this->flashAndRedirect('error', "You can't change your own role or status here.", '/users');
        }

        $roleId = (int) Request::input('role_id');
        if (!User::roleExists($roleId) || !User::findWithRole($id)) {
            $this->flashAndRedirect('error', 'That user or role does not exist.', '/users');
        }
        $status = in_array(Request::input('status'), ['active', 'disabled'], true) ? Request::input('status') : 'active';

        User::updateRoleAndStatus($id, $roleId, $status);
        AuditLogger::log('user.update', 'user', $id, null, ['role_id' => $roleId, 'status' => $status]);

        $this->flashAndRedirect('success', 'User updated.', '/users');
    }
}
