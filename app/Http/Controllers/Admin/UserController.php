<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/** Správa administrátorských účtov – prístupná len super administrátorovi. */
class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::orderBy('name')->get([
                'id', 'name', 'email', 'is_super_admin', 'created_at',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'       => ['required', 'confirmed', Password::min(8)],
            'is_super_admin' => ['boolean'],
        ]);

        $user = User::create([
            'name'           => $validated['name'],
            'email'          => $validated['email'],
            'password'       => $validated['password'],
            'is_super_admin' => $validated['is_super_admin'] ?? false,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        ActivityLog::record(
            'user.created',
            "Vytvoril používateľa {$user->name} ({$user->email})",
            $user,
            ['super_admin' => $user->is_super_admin],
        );

        return back()->with('success', "Používateľ {$user->name} bol vytvorený.");
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password'       => ['nullable', 'confirmed', Password::min(8)],
            'is_super_admin' => ['boolean'],
        ]);

        $isSuperAdmin = $validated['is_super_admin'] ?? false;

        // Poistka proti zamknutiu sa mimo: posledný super admin si rolu nesmie vziať.
        if ($user->is_super_admin && ! $isSuperAdmin && User::where('is_super_admin', true)->count() <= 1) {
            return back()->with('error', 'Nemôžete odobrať rolu poslednému super administrátorovi.');
        }

        $before = $user->only(['name', 'email', 'is_super_admin']);

        $user->fill([
            'name'           => $validated['name'],
            'email'          => $validated['email'],
            'is_super_admin' => $isSuperAdmin,
        ]);

        $passwordChanged = filled($validated['password'] ?? null);

        if ($passwordChanged) {
            $user->password = $validated['password'];
        }

        $user->save();

        $changes = ActivityLog::diff($before, $user->only(['name', 'email', 'is_super_admin']));

        if ($passwordChanged) {
            $changes['heslo'] = ['pred' => '—', 'po' => 'zmenené'];
        }

        ActivityLog::record(
            'user.updated',
            "Upravil používateľa {$user->name} ({$user->email})",
            $user,
            $changes,
        );

        return back()->with('success', "Používateľ {$user->name} bol upravený.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()?->id) {
            return back()->with('error', 'Vlastný účet nie je možné odstrániť.');
        }

        if ($user->is_super_admin && User::where('is_super_admin', true)->count() <= 1) {
            return back()->with('error', 'Nemôžete odstrániť posledného super administrátora.');
        }

        $name = $user->name;
        $email = $user->email;

        // Záznamy v logu zostávajú (user_id sa vynuluje, meno je uložené v kópii).
        $user->delete();

        ActivityLog::record(
            'user.deleted',
            "Odstránil používateľa {$name} ({$email})",
        );

        return back()->with('success', "Používateľ {$name} bol odstránený.");
    }
}
