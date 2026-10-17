<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{

    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }

    public function getUsers()
    {
        $users = User::get();
        return $this->apiController->respondData($users);
    }
    public function getTechnicalExperts()
    {

        $users = User::where('status' , User::ACTIVE)->whereIn("role" , [User::KARSHENAS_FANI ,User::NASSAB , User::ADMIN])->get();
        return $this->apiController->respondData($users);
    }
    public function getInstallers()
    {
        $users = User::where('status' , User::ACTIVE)->whereIn("role" , [User::NASSAB])->get();
        return $this->apiController->respondData($users);
    }

    public function getUsersInfo()
    {
        if (auth()->user()->role !== User::ADMIN)
            return $this->apiController->respondForbidden();

        $users = User::get();
        return $this->apiController->respondData($users);
    }

    public function getUserInfo()
    {
        $data = [
            'name' => auth()->user()->name,
            'username' => auth()->user()->username,
            'profile' => auth()->user()->profile,
            'emza' => auth()->user()->emza
        ];

        return $this->apiController->respondData($data);

    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'username' => 'required',
            'role' => 'required',
            'password' => 'required|min:5|required_with:rePassword|same:rePassword',
            'rePassword' => 'required'
        ], [
            'name.required' => "نام را وارد نمایید",
            'username.required' => "نام کاربری را وارد نمایید",
            'role.required' => "سمت را وارد نمایید",
            'password.required' => "گذرواژه جدید را وارد نمایید",
            'password.min' => "گذرواژه کاربر باید حداقل از 5 حرف تشکیل شده باشد",
            'password.required_with' => "گذرواژه کاربر با تکرار آن مطابقت ندارد",
            'password.same' => "گذرواژه کاربر با تکرار آن مطابقت ندارد",
            'rePassword.required' => "تکرار گذرواژه کاربر را وارد نمایید",
        ]);

        $check = User::where('username', $this->apiHelper->getRealText($request, 'username'))
            ->first();

        if ($check) {
            return $this->apiController->respondValidationError('نام کاربری تکراری است');
        }


        $user = User::create([
            'name' => $request->input('name'),
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'status' => User::ACTIVE,
            'role' => $request->input('role'),

        ]);


        if ($user) {

            $token = $user->createToken('api')->plainTextToken;

            return response()->json($token, 201);
        } else {
            return response()->json(null, 500);
        }
    }


    public function edit(Request $request)
    {

        if (auth()->user()->role !== User::ADMIN) {
            return $this->apiController->respondForbidden();
        }

        $this->validate($request, [
            'id' => 'required',
            'name' => 'required',
            'username' => 'required',
            'role' => 'required',
        ], [
            'id.required' => "کاربر را انتخاب نمایید",
            'name.required' => "نام را وارد نمایید",
            'username.required' => "نام کاربری را وارد نمایید",
            'role.required' => "سمت را وارد نمایید",
        ]);

        if ($request->input('password') !== $request->input('rePassword')) {
            return $this->apiController->respondError('تکرار رمز مطابقت ندارد');
        }

        $user = User::find($request->input('id'));

        $create = $user->update([
            'name' => $request->input('name'),
            'username' => $request->input('username'),
            'role' => $request->input('role'),
        ]);
        if ($request->input('password') !== null && strlen($request->input('password')) > 0)
            $user->update(['password' => $request->input('password')]);
        if ($create) {
            return $this->apiController->respondUpdated('کاربر با موفقیت ویرایش شد', $user);
        } else {
            return $this->apiController->respondInternalError();
        }

    }


    public function changePassword(Request $request)
    {


        $this->validate($request, [
            'currentPassword' => 'required',
            'newPassword' => 'required|min:5|required_with:reNewPassword|same:reNewPassword',
            'reNewPassword' => 'required',
        ], [
            'currentPassword.required' => "گذرواژه فعلی را وارد نمایید",
            'newPassword.required' => "گذرواژه جدید را وارد نمایید",
            'newPassword.min' => "گذرواژه جدید باید حداقل از 5 حرف تشکیل شده باشد",
            'newPassword.required_with' => "گذرواژه جدید با تکرار آن مطابقت ندارد",
            'newPassword.same' => "گذرواژه جدید با تکرار آن مطابقت ندارد",
            'reNewPassword.required' => "تکرار گذرواژه جدید را وارد نمایید",
        ]);

        $currentPw = $request->input('currentPassword');
        $newPw = $request->input('newPassword');

        if (Hash::check($currentPw, auth()->user()->password)) {

            $update = auth()->user()->update([
                'password' => $newPw,
            ]);


            if ($update) {
                return $this->apiController->respondUpdated('با موفقیت ویرایش شد', auth()->user()->profile);


            } else {
                return $this->apiController->respondInternalError();
            }
        } else {
            return $this->apiController->respondValidationError('گذرواژه فعلی را صحیح وارد نمایید');
        }


    }

    public function changeInfo(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'username' => 'required'

        ], [
            'name.required' => "نام را وارد نمایید",
            'username.required' => "نام کاربری را وارد نمایید",
        ]);

        $username = $request->input('username');
        $name = $request->input('name');
        $inputs = [];
        if ($request->has('emza') && $request->file('emza') != null) {
            $file = $request->file('emza');
            $fileExtension = $file->getClientOriginalExtension();
            $emzaName = Carbon::now()->format('y-m-d') . '-' . rand(100, 9999) . '.' . $fileExtension;
            $file->move(public_path('emza'), $emzaName);

            $inputs['emza'] = $emzaName;

        }

        if ($request->has('profile') && $request->file('profile') != null) {
            $file = $request->file('profile');
            $fileExtension = $file->getClientOriginalExtension();
            $profileName = Carbon::now()->format('y-m-d') . '-' . rand(100, 9999) . '.' . $fileExtension;
            $file->move(public_path('profile'), $profileName);

            $inputs['profile'] = $profileName;
        }

        $update = auth()->user()->update([
            'username' => $username,
            'name' => $name,
            auth()->user()->update($inputs)
        ]);


        if ($update) {
            return $this->apiController->respondUpdated('با موفقیت ویرایش شد', auth()->user()->profile);


        } else {
            return $this->apiController->respondInternalError();
        }

    }

    public function quickAdd(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'username' => 'required',
        ], [
            'name.required' => "نام را وارد نمایید",
            'username.required' => "نام کاربری را وارد نمایید",
        ]);

        $check = User::where('username', $this->apiHelper->getRealText($request, 'username'))
            ->first();

        if ($check)
            return $this->apiController->respondValidationError('نام کاربری تکراری است');

        $user = User::create([
            'name' => $request->input('name'),
            'username' => $request->input('username'),
            'password' => "123456",
            'status' => User::ACTIVE,
            'role' => User::NASSAB,
        ]);

        if ($user) {
            return $this->apiController->respondCreated();
        } else {
            return response()->json(null, 500);
        }
    }


    public function login(Request $request)
    {

        $this->validate($request, [
            'username' => 'required',
            'password' => 'required'
        ], [
            'username.required' => 'نام کاربری را وارد نمایید',
            'password.required' => 'گذرواژه را وارد نمایید'
        ]);

        $user = Auth::attempt([
            'username' => $request->input('username'),
            'password' => $request->input('password')
        ]);
        if ($user) {

            $user = $request->user();
            $token = $user->createToken('api')->plainTextToken;
            $tokenRecord = $user->tokens()->latest('created_at')->first();

            $lastLoggedInDate = null;
            if ($tokenRecord) {
                $lastLoggedInDate = verta($tokenRecord->created_at)->format('Y/m/d ساعت H:i:s');
            }

            $data = [
                'token' => $token,
                'id' => auth()->user()->id,
                'role' => auth()->user()->role,
                'name' => auth()->user()->name,
                'username' => auth()->user()->username,
                'lastLoggedInDate' => $lastLoggedInDate,
            ];
            return $this->apiController->respondData($data, 'ورود با موفقیت انجام شد');
        }
        return $this->apiController->respondValidationError('نام کاربری یا گذرواژه اشتباه می باشد');
    }

    public function toggleStatus(Request $request)
    {
        if (auth()->user()->role !== User::ADMIN) {
            return $this->apiController->respondForbidden();
        }
        $data = User::find($request->input('id'));
        $status = User::INACTIVE;
        if ($data->status === $status)
            $status = User::ACTIVE;

        $update = $data->update([
            'status' => $status
        ]);

        if ($update) {
            return $this->apiController->respondUpdated('با موفقیت بروزرسانی شد');
        } else {
            return $this->apiController->respondInternalError();
        }
    }


    public function logout()
    {
        auth()->user()->tokens()->delete();
        return $this->apiController->respondMessage('خروج با موفقیت انجام شد');
    }


}
