<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|unique:users,name',
            'email' => 'required|unique:users,email',
            'password' => 'required|confirmed|min:5',
            'role' => 'required',
        ]);

        $user = new User;
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->role = $request->role;
        $user->save();

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {

        $rules = [
            'name' => 'required',
            'email' => 'required|unique:users,email,'.$id,
            'role' => 'required',
        ];

        if($request->password)
            $rules['password'] = 'required|confirmed|min:5';

        $validatedData = $request->validate($rules);

        $user = User::findOrFail($id);

//        $lastAdmin = User::where('role' , 'admin')->where('id' , '<>' , $id)->count();
//        if ($lastAdmin == 0 && $request->role =='user' ) {
//            return redirect()->back()->with('success', 'Cannot update ROLE of last user.');
//        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;

        if ($request->password) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Check if this is the last user in the system
        $userCount = User::where('role' , 'admin')->where('id' , '<>' , $id)->count();

        if ($userCount == 0) {
            return redirect()->back()->with('success', 'Cannot delete the last user from the system.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
